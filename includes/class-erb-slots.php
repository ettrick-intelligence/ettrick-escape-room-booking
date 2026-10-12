<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Calculates slot availability for a game across a date range.
 * Handles: booked slots, held slots, blocked slots, shared-room blocking,
 *          min notice, booking horizon, and operating hours.
 */
class EERB_Slots {

    /**
     * Returns a structured array of days → slots with availability status.
     *
     * @param int    $game_id
     * @param string $date_from  Y-m-d
     * @param string $date_to    Y-m-d
     * @return array
     */
    public static function get_availability( $game_id, $date_from, $date_to ) {
        $game = EERB_DB::get_game( $game_id );
        if ( ! $game || $game->status !== 'active' ) return array();

        // Load all data we need for the date range in bulk
        $dt_from = $date_from . ' 00:00:00';
        $dt_to   = $date_to   . ' 23:59:59';

        $hours_by_day   = self::index_hours( EERB_DB::get_game_hours( $game_id ) );
        $booked         = self::index_datetimes( EERB_DB::get_booked_slots( $game_id, $dt_from, $dt_to ) );
        $held           = self::index_datetimes( EERB_DB::get_held_slots(   $game_id, $dt_from, $dt_to ) );
        $blocked        = self::index_datetimes( EERB_DB::get_blocked_slots( $game_id, $dt_from, $dt_to ) );

        // If this game shares a room, load the sibling's bookings/holds too
        // Use range-based overlap detection so differing game durations are handled correctly.
        $sibling_booked_ranges = array();
        $sibling_held_ranges   = array();
        $sibling = EERB_DB::get_room_sibling( $game_id );
        if ( $sibling ) {
            $sibling_booked_ranges = self::index_ranges( EERB_DB::get_booked_slots( $sibling->id, $dt_from, $dt_to ) );
            $sibling_held_ranges   = self::index_ranges( EERB_DB::get_held_slots(   $sibling->id, $dt_from, $dt_to ) );
        }

        $now             = time();
        $min_notice_secs = (int) $game->min_notice_hours * 3600;
        $horizon         = $game->booking_horizon_date ?: null;

        $result = array();
        $current = new DateTime( $date_from );
        $end     = new DateTime( $date_to );

        while ( $current <= $end ) {
            $date_str  = $current->format( 'Y-m-d' );
            $day_of_wk = (int) $current->format( 'w' ); // 0=Sun

            $day_data = array(
                'date'      => $date_str,
                'day_label' => date_i18n( 'D', $current->getTimestamp() ),
                'date_label'=> date_i18n( 'j M', $current->getTimestamp() ),
                'is_today'  => $date_str === gmdate( 'Y-m-d' ),
                'slots'     => array(),
                'is_closed' => true,
            );

            // Check horizon — entire day unavailable if beyond it
            if ( $horizon && $date_str > $horizon ) {
                $result[] = $day_data;
                $current->modify( '+1 day' );
                continue;
            }

            $hours = $hours_by_day[ $day_of_wk ] ?? null;
            if ( ! $hours || $hours->is_closed || empty( $hours->open_time ) ) {
                $result[] = $day_data;
                $current->modify( '+1 day' );
                continue;
            }

            $day_data['is_closed'] = false;

            // Generate slots for this day
            $slot_secs = ( (int) $game->duration_minutes + (int) $game->setup_minutes ) * 60;
            $open_ts   = strtotime( $date_str . ' ' . $hours->open_time );
            $close_ts  = strtotime( $date_str . ' ' . $hours->close_time );

            for ( $ts = $open_ts; $ts + $slot_secs <= $close_ts; $ts += $slot_secs ) {
                $slot_start_dt = gmdate( 'Y-m-d H:i:s', $ts );
                $slot_end_dt   = gmdate( 'Y-m-d H:i:s', $ts + (int) $game->duration_minutes * 60 );
                $slot_key      = gmdate( 'Y-m-d H:i', $ts );

                // Determine status
                $status = 'available';

                if ( $ts <= $now ) {
                    $status = 'past';
                } elseif ( ( $ts - $now ) < $min_notice_secs ) {
                    $status = 'notice'; // within min-notice window
                } elseif ( isset( $booked[ $slot_key ] ) || self::overlaps_ranges( $sibling_booked_ranges, $ts, $ts + (int) $game->duration_minutes * 60 ) ) {
                    $status = 'booked';
                } elseif ( isset( $held[ $slot_key ] ) || self::overlaps_ranges( $sibling_held_ranges, $ts, $ts + (int) $game->duration_minutes * 60 ) ) {
                    $status = 'held';
                } elseif ( isset( $blocked[ $slot_key ] ) ) {
                    $status = 'blocked';
                }

                $day_data['slots'][] = array(
                    'start'  => gmdate( 'H:i', $ts ),
                    'end'    => gmdate( 'H:i', $ts + (int) $game->duration_minutes * 60 ),
                    'start_dt' => $slot_start_dt,
                    'end_dt'   => $slot_end_dt,
                    'status' => $status,
                );
            }

            $result[] = $day_data;
            $current->modify( '+1 day' );
        }

        return $result;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Index game_hours rows by day_of_week for O(1) lookup */
    private static function index_hours( $rows ) {
        $idx = array();
        foreach ( $rows as $r ) {
            $idx[ (int) $r->day_of_week ] = $r;
        }
        return $idx;
    }

    /** Index slot rows by 'Y-m-d H:i' for O(1) lookup */
    private static function index_datetimes( $rows ) {
        $idx = array();
        foreach ( $rows as $r ) {
            $key = gmdate( 'Y-m-d H:i', strtotime( $r->slot_start ) );
            $idx[ $key ] = true;
        }
        return $idx;
    }

    /**
     * Index slot rows as [start_ts, end_ts] pairs for overlap detection.
     * Used for sibling-room bookings where game durations may differ.
     */
    private static function index_ranges( $rows ) {
        $ranges = array();
        foreach ( $rows as $r ) {
            $ranges[] = array(
                strtotime( $r->slot_start ),
                strtotime( $r->slot_end ),
            );
        }
        return $ranges;
    }

    /**
     * Returns true if [check_start, check_end) overlaps any range in $ranges.
     * Two slots overlap when one starts before the other ends (exclusive boundary).
     */
    private static function overlaps_ranges( $ranges, $check_start, $check_end ) {
        foreach ( $ranges as $range ) {
            if ( $check_start < $range[1] && $check_end > $range[0] ) {
                return true;
            }
        }
        return false;
    }
}
