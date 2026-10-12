<?php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
if ( ! defined( 'ABSPATH' ) ) exit;

class EERB_Public {

    public function enqueue_assets() {
        if ( ! $this->is_erb_page() ) return;

        wp_enqueue_style( 'eerb-public', EERB_PLUGIN_URL . 'public/css/erb-public.css', array(), EERB_VERSION );
        wp_enqueue_script( 'eerb-public',   EERB_PLUGIN_URL . 'public/js/erb-public.js',   array( 'jquery' ), EERB_VERSION, true );
        wp_enqueue_script( 'eerb-calendar', EERB_PLUGIN_URL . 'public/js/erb-calendar.js', array( 'eerb-public' ), EERB_VERSION, true );
        wp_enqueue_script( 'eerb-booking',  EERB_PLUGIN_URL . 'public/js/erb-booking.js',  array( 'eerb-public' ), EERB_VERSION, true );

        // Stripe.js — only on booking page
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'eerb_booking' ) ) {
            wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
        }

        // Build games data for the booking JS
        $all_games  = EERB_DB::get_games();
        $games_data = array();
        foreach ( $all_games as $g ) {
            $prices_raw = EERB_DB::get_prices( $g->id );
            $prices     = array();
            foreach ( $prices_raw as $p ) {
                $prices[ (int) $p->player_count ] = (int) $p->price_pence;
            }
            $games_data[ (int) $g->id ] = array(
                'id'          => (int) $g->id,
                'name'        => $g->name,
                'slug'        => $g->slug,
                'prices'      => empty( $prices ) ? new stdClass() : (object) $prices,
                'max_players' => (int) $g->max_players,
            );
        }

        wp_localize_script( 'eerb-public', 'eerbPublic', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'eerb_public_nonce' ),
            'holdMinutes'    => (int) get_option( 'eerb_slot_hold_minutes', 15 ),
            'stripePublicKey'=> $this->get_stripe_public_key(),
            'currency'       => get_option( 'eerb_currency', 'GBP' ),
            'currencySymbol' => get_option( 'eerb_currency_symbol', '£' ),
            'bookingPageUrl' => get_option( 'eerb_booking_page_url', '' ),
            'dateFormat'     => get_option( 'eerb_date_format', 'j F Y' ),
            'gamesData'      => $games_data,
        ) );
    }

    private function get_stripe_public_key() {
        $mode = get_option( 'eerb_stripe_mode', 'test' );
        return $mode === 'live' ? get_option( 'eerb_stripe_live_pk', '' ) : get_option( 'eerb_stripe_test_pk', '' );
    }

    private function is_erb_page() {
        global $post;
        if ( ! $post ) return false;
        return has_shortcode( $post->post_content, 'eerb_calendar' )
            || has_shortcode( $post->post_content, 'eerb_booking' )
            || has_shortcode( $post->post_content, 'eerb_manage_booking' );
    }

    // ── Shortcodes ────────────────────────────────────────────────────────────

    public function register_shortcodes() {
        add_shortcode( 'eerb_calendar',       array( $this, 'shortcode_calendar' ) );
        add_shortcode( 'eerb_booking',        array( $this, 'shortcode_booking' ) );
        add_shortcode( 'eerb_manage_booking', array( $this, 'shortcode_manage_booking' ) );
    }

    public function shortcode_calendar( $atts ) {
        $atts = shortcode_atts( array( 'game' => '' ), $atts );
        ob_start(); include EERB_PLUGIN_DIR . 'public/views/calendar.php'; return ob_get_clean();
    }

    public function shortcode_booking( $atts ) {
        ob_start(); include EERB_PLUGIN_DIR . 'public/views/booking.php'; return ob_get_clean();
    }

    public function shortcode_manage_booking( $atts ) {
        ob_start(); include EERB_PLUGIN_DIR . 'public/views/manage-booking.php'; return ob_get_clean();
    }

    // ── AJAX: Get slots ───────────────────────────────────────────────────────

    public function ajax_get_slots() {
        check_ajax_referer( 'eerb_public_nonce', 'nonce' );
        $game_id    = (int) ( $_POST['game_id'] ?? 0 );
        $week_start = sanitize_text_field( wp_unslash( $_POST['week_start'] ) );
        if ( ! $game_id || ! $week_start ) EERB_Helpers::json_error( 'Invalid parameters.' );
        $dt = DateTime::createFromFormat( 'Y-m-d', $week_start );
        if ( ! $dt ) EERB_Helpers::json_error( 'Invalid date.' );
        $date_from    = $dt->format( 'Y-m-d' );
        $date_to      = ( clone $dt )->modify( '+6 days' )->format( 'Y-m-d' );
        $availability = EERB_Slots::get_availability( $game_id, $date_from, $date_to );
        EERB_Helpers::json_success( array( 'days' => $availability, 'date_from' => $date_from, 'date_to' => $date_to ) );
    }

    // ── AJAX: Hold slot ───────────────────────────────────────────────────────

    public function ajax_hold_slot() {
        check_ajax_referer( 'eerb_public_nonce', 'nonce' );
        $game_id    = (int) sanitize_text_field( wp_unslash( $_POST['game_id'] ) );
        $slot_start = sanitize_text_field( wp_unslash( $_POST['slot_start'] ) );
        $session_key= sanitize_text_field( wp_unslash( $_POST['session_key'] ) );

        if ( ! $game_id || ! $slot_start || ! $session_key ) EERB_Helpers::json_error( 'Invalid parameters.' );

        // Check slot is still available
        $dt_from = gmdate( 'Y-m-d H:i:s', strtotime( $slot_start ) );
        $dt_to   = $dt_from;
        $booked  = EERB_DB::get_booked_slots( $game_id, $dt_from, $dt_to );
        if ( ! empty( $booked ) ) EERB_Helpers::json_error( 'This slot has just been booked. Please choose another time.' );

        // Check sibling
        $sibling = EERB_DB::get_room_sibling( $game_id );
        if ( $sibling ) {
            $sib_booked = EERB_DB::get_booked_slots( $sibling->id, $dt_from, $dt_to );
            if ( ! empty( $sib_booked ) ) EERB_Helpers::json_error( 'This slot is unavailable (shared room). Please choose another time.' );
        }

        // Check game rules
        $game = EERB_DB::get_game( $game_id );
        if ( EERB_Helpers::is_within_min_notice( $game, $slot_start ) ) EERB_Helpers::json_error( 'This slot is too soon to book.' );
        if ( EERB_Helpers::is_beyond_horizon( $game, $slot_start ) ) EERB_Helpers::json_error( 'This slot is outside the booking window.' );

        $holds      = new EERB_Slot_Holds();
        $expires_at = $holds->hold( $game_id, $slot_start, $session_key );
        EERB_Helpers::json_success( array( 'expires_at' => $expires_at ) );
    }

    // ── AJAX: Release hold ────────────────────────────────────────────────────

    public function ajax_release_hold() {
        check_ajax_referer( 'eerb_public_nonce', 'nonce' );
        $game_id    = (int) sanitize_text_field( wp_unslash( $_POST['game_id'] ) );
        $slot_start = sanitize_text_field( wp_unslash( $_POST['slot_start'] ) );
        EERB_DB::delete_hold( $game_id, $slot_start );
        EERB_Helpers::json_success();
    }

    // ── AJAX: Validate promo ──────────────────────────────────────────────────

    public function ajax_validate_promo() {
        check_ajax_referer( 'eerb_public_nonce', 'nonce' );
        $code   = sanitize_text_field( wp_unslash( $_POST['code'] ) );
        $promos = new EERB_Promo_Codes();
        $result = $promos->validate( $code );
        if ( $result['valid'] ) {
            EERB_Helpers::json_success( array( 'discount_percent' => $result['discount_percent'], 'promo_id' => $result['promo_id'] ) );
        } else {
            EERB_Helpers::json_error( $result['message'] );
        }
    }

    // ── AJAX: Customer login ──────────────────────────────────────────────────

    public function ajax_customer_login() {
        check_ajax_referer( 'eerb_public_nonce', 'nonce' );
        $email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';
        if ( ! $email || ! $password ) EERB_Helpers::json_error( 'Email and password are required.' );

        $customers = new EERB_Customers();
        $customer  = $customers->get_by_email( $email );
        if ( ! $customer || ! $customers->verify_password( $customer, $password ) ) {
            EERB_Helpers::json_error( 'Incorrect email or password.' );
        }
        EERB_DB::update_customer( $customer->id, array( 'last_login' => current_time( 'mysql' ) ) );
        EERB_Helpers::json_success( array(
            'customer_id' => $customer->id,
            'first_name'  => $customer->first_name,
            'last_name'   => $customer->last_name,
            'email'       => $customer->email,
        ) );
    }

    // ── AJAX: Create booking ──────────────────────────────────────────────────

    public function ajax_create_booking() {
        check_ajax_referer( 'eerb_public_nonce', 'nonce' );

        $game_id     = (int) sanitize_text_field( wp_unslash( $_POST['game_id'] ) );
        $slot_start  = sanitize_text_field( wp_unslash( $_POST['slot_start'] ) );
        $slot_end    = sanitize_text_field( wp_unslash( $_POST['slot_end'] ) );
        $session_key = sanitize_text_field( wp_unslash( $_POST['session_key'] ) );
        $players     = (int) sanitize_text_field( wp_unslash( $_POST['player_count'] ) );

        if ( ! $game_id || ! $slot_start || ! $players ) EERB_Helpers::json_error( 'Missing booking data.' );

        // Verify hold still active — check by session key first, fall back to
        // any active hold for this slot (handles page refresh between hold and payment)
        $hold = EERB_DB::get_hold_by_session( $game_id, $slot_start, $session_key );
        if ( ! $hold ) {
            $hold = EERB_DB::get_any_active_hold( $game_id, $slot_start );
        }
        if ( ! $hold ) EERB_Helpers::json_error( 'Your slot reservation has expired. Please select the slot again.' );

        // Get price
        $price_pence = EERB_DB::get_price( $game_id, $players );
        if ( ! $price_pence ) EERB_Helpers::json_error( 'Could not retrieve price. Please try again.' );

        // Promo discount
        $promo_id      = (int) ( $_POST['promo_id'] ?? 0 );
        $discount_pence = 0;
        if ( $promo_id ) {
            $promo = EERB_DB::get_promo_code( '' ); // fetch by id below
            global $wpdb;
            $promo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eerb_promo_codes WHERE id = %d AND is_active = 1", $promo_id ) );
            if ( $promo ) {
                $discount_pence = (int) round( $price_pence * $promo->discount_percent / 100 );
            }
        }
        $total_pence = $price_pence - $discount_pence;

        // Get or create customer
        $customers  = new EERB_Customers();
        $customer_id = (int) ( $_POST['customer_id'] ?? 0 );

        if ( ! $customer_id ) {
            $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
            if ( ! $email ) EERB_Helpers::json_error( 'Email address is required.' );

            $existing = $customers->get_by_email( $email );
            if ( $existing ) {
                $customer_id = $existing->id;
                $customers->update( $customer_id, array(
                    'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ) ),
                    'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ) ),
                    'mobile'     => sanitize_text_field( wp_unslash( $_POST['mobile'] ) ),
                ) );
            } else {
                $pw_hash = '';
                if ( ! empty( $_POST['create_account'] ) && ! empty( $_POST['new_password'] ) ) {
                    $pw_hash = $customers->hash_password( $_POST['new_password'] );
                }
                $customer_id = $customers->create( array(
                    'first_name'    => sanitize_text_field( wp_unslash( $_POST['first_name'] ) ),
                    'last_name'     => sanitize_text_field( wp_unslash( $_POST['last_name'] ) ),
                    'email'         => $email,
                    'mobile'        => sanitize_text_field( wp_unslash( $_POST['mobile'] ) ),
                    'password_hash' => $pw_hash,
                    'is_guest'      => empty( $pw_hash ) ? 1 : 0,
                    'created_at'    => current_time( 'mysql' ),
                ) );
            }
        }

        if ( ! $customer_id ) EERB_Helpers::json_error( 'Could not create customer record.' );

        // Create Stripe Payment Intent
        $stripe_pk = get_option( 'eerb_stripe_mode', 'test' ) === 'live'
            ? get_option( 'eerb_stripe_live_sk' )
            : get_option( 'eerb_stripe_test_sk' );

        if ( ! $stripe_pk ) EERB_Helpers::json_error( 'Payment gateway not configured. Please contact us.' );

        // Stripe minimum charge is 30p (GBP). Catch zero/sub-minimum totals before calling Stripe.
        if ( $total_pence <= 0 ) {
            EERB_Helpers::json_error( 'The total after discount is zero. Please contact us to complete this booking.' );
        }
        if ( $total_pence < 30 ) {
            EERB_Helpers::json_error( 'The total after discount (' . EERB_Helpers::format_price( $total_pence ) . ') is below the minimum charge amount. Please contact us to complete this booking.' );
        }

        $game    = EERB_DB::get_game( $game_id );
        $customer = EERB_DB::get_customer( $customer_id );

        $intent_response = wp_remote_post( 'https://api.stripe.com/v1/payment_intents', array(
            'headers' => array( 'Authorization' => 'Bearer ' . $stripe_pk ),
            'body'    => array(
                'amount'   => $total_pence,
                'currency' => strtolower( get_option( 'eerb_currency', 'GBP' ) ),
                'metadata' => array(
                    'game'       => $game->name,
                    'slot_start' => $slot_start,
                    'players'    => $players,
                    'customer'   => $customer->first_name . ' ' . $customer->last_name,
                    'email'      => $customer->email,
                ),
            ),
        ) );

        if ( is_wp_error( $intent_response ) ) EERB_Helpers::json_error( 'Payment gateway error. Please try again.' );

        $intent_status = wp_remote_retrieve_response_code( $intent_response );
        $intent_body   = json_decode( wp_remote_retrieve_body( $intent_response ), true );
        if ( empty( $intent_body['client_secret'] ) ) {
            $stripe_error = $intent_body['error']['message'] ?? 'Payment intent creation failed (HTTP ' . $intent_status . ').';
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[ERB Stripe] Intent failed: ' . wp_remote_retrieve_body( $intent_response ) ); }
            EERB_Helpers::json_error( $stripe_error );
        }

        // Create booking record (status = pending until payment confirmed)
        $booking_ref   = EERB_Helpers::generate_booking_ref();
        $manage_token  = EERB_Helpers::generate_token();
        $booking_id    = EERB_DB::insert_booking( array(
            'booking_ref'      => $booking_ref,
            'game_id'          => $game_id,
            'customer_id'      => $customer_id,
            'slot_start'       => $slot_start,
            'slot_end'         => $slot_end,
            'player_count'     => $players,
            'price_pence'      => $price_pence,
            'discount_pence'   => $discount_pence,
            'total_pence'      => $total_pence,
            'promo_code_id'    => $promo_id ?: null,
            'status'           => 'pending',
            'stripe_payment_id'=> $intent_body['id'],
            'manage_token'     => $manage_token,
            'created_at'       => current_time( 'mysql' ),
            'updated_at'       => current_time( 'mysql' ),
        ) );

        // Apply promo use count
        if ( $promo_id ) EERB_DB::increment_promo_use( $promo_id );

        EERB_Helpers::json_success( array(
            'booking_id'    => $booking_id,
            'booking_ref'   => $booking_ref,
            'client_secret' => $intent_body['client_secret'],
        ) );
    }

    // ── AJAX: Confirm payment ─────────────────────────────────────────────────

    public function ajax_confirm_payment() {
        // Security fix (v1.3.1): Payment status is set exclusively by the Stripe
        // webhook (handle_stripe_webhook). This endpoint is intentionally a no-op
        // to prevent unauthenticated booking confirmation.
        // Reported by ryan fabella (WPScan request 11770271).
        check_ajax_referer( 'eerb_public_nonce', 'nonce' );
        EERB_Helpers::json_success( array( 'message' => 'ok' ) );
    }

    // ── AJAX: Stripe webhook ──────────────────────────────────────────────────

    public function handle_stripe_webhook() {
        // Read raw body — must happen before any output
        $payload   = file_get_contents( 'php://input' );
        $signature = sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '' ) );

        $stripe = new EERB_Stripe();
        $event  = $stripe->handle_webhook( $payload, $signature );

        if ( is_wp_error( $event ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[ERB Webhook] Verification failed: ' . $event->get_error_message() ); }
            status_header( 400 );
            echo json_encode( array( 'error' => $event->get_error_message() ) );
            exit;
        }

        $type   = $event['type']                          ?? '';
        $pi_id  = $event['data']['object']['id']          ?? '';
        $status = $event['data']['object']['status']      ?? '';
        $meta   = $event['data']['object']['metadata']    ?? array();

        switch ( $type ) {

            case 'payment_intent.succeeded':
                $this->webhook_payment_succeeded( $pi_id );
                break;

            case 'payment_intent.payment_failed':
                $this->webhook_payment_failed( $pi_id );
                break;

            case 'charge.refunded':
                // Handled manually by admin for now — log only
                break;
        }

        status_header( 200 );
        echo json_encode( array( 'received' => true ) );
        exit;
    }

    private function webhook_payment_succeeded( $payment_intent_id ) {
        if ( ! $payment_intent_id ) return;

        global $wpdb;
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eerb_bookings WHERE stripe_payment_id = %s",
            $payment_intent_id
        ) );

        if ( ! $booking ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[ERB Webhook] No booking found for PI: ' . $payment_intent_id ); }
            return;
        }

        // Skip if already confirmed or cancelled (duplicate webhook delivery)
        if ( in_array( $booking->status, array( 'confirmed', 'cancelled' ), true ) ) {
            return;
        }

        // Confirm the booking
        EERB_DB::update_booking( $booking->id, array(
            'status'     => 'confirmed',
            'updated_at' => current_time( 'mysql' ),
        ) );

        // Release the slot hold
        EERB_DB::delete_hold( $booking->game_id, $booking->slot_start );

        // Log history
        EERB_DB::add_booking_history( array(
            'booking_id' => $booking->id,
            'action'     => 'created',
            'changed_by' => 'customer',
            'note'       => 'Confirmed via Stripe webhook.',
            'created_at' => current_time( 'mysql' ),
        ) );

        // Send confirmation emails
        $emails = new EERB_Emails();
        $emails->send_confirmation( EERB_DB::get_booking( $booking->id ) );
    }

    private function webhook_payment_failed( $payment_intent_id ) {
        if ( ! $payment_intent_id ) return;

        global $wpdb;
        $booking = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}eerb_bookings WHERE stripe_payment_id = %s",
            $payment_intent_id
        ) );

        if ( ! $booking || $booking->status !== 'pending' ) return;

        // Mark as failed (we keep the record for admin visibility)
        EERB_DB::update_booking( $booking->id, array(
            'status'     => 'cancelled',
            'updated_at' => current_time( 'mysql' ),
        ) );

        // Release the slot hold so others can book
        EERB_DB::delete_hold( $booking->game_id, $booking->slot_start );

        EERB_DB::add_booking_history( array(
            'booking_id' => $booking->id,
            'action'     => 'cancelled',
            'changed_by' => 'customer',
            'note'       => 'Payment failed — auto-cancelled via webhook.',
            'created_at' => current_time( 'mysql' ),
        ) );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[ERB Webhook] Booking ' . $booking->booking_ref . ' cancelled due to payment failure.' ); }
    }

    public function handle_manage_booking_request() {
        // Nothing needed here — token is read directly from $_GET in the view
        // WordPress does not strip custom query args from page URLs by default
    }
}
