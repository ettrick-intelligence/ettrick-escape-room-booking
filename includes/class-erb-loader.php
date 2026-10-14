<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers all hooks and loads all classes.
 * Acts as the central nervous system of the plugin.
 */
class EERB_Loader {

    protected $actions = array();
    protected $filters = array();

    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
    }

    // ─── Load all required files ──────────────────────────────────────────────

    private function load_dependencies() {
        // Helpers & utilities
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-helpers.php';
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-db.php';
require_once EERB_PLUGIN_DIR . 'includes/class-erb-pagination.php';

        // Core domain classes
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-games.php';
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-slots.php';
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-bookings.php';
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-customers.php';
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-slot-holds.php';
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-promo-codes.php';
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-emails.php';

        // Payment
        require_once EERB_PLUGIN_DIR . 'includes/payments/class-erb-payment-gateway.php';
        require_once EERB_PLUGIN_DIR . 'includes/payments/class-erb-stripe.php';

        // Admin
        if ( is_admin() ) {
            require_once EERB_PLUGIN_DIR . 'admin/class-erb-admin.php';
        }

        // Public-facing
        require_once EERB_PLUGIN_DIR . 'public/class-erb-public.php';
    }

    // ─── Admin hooks ──────────────────────────────────────────────────────────

    private function define_admin_hooks() {
        if ( ! is_admin() ) return;

        $admin = new EERB_Admin();

        $this->add_action( 'admin_menu',            $admin, 'register_admin_menu' );
        $this->add_action( 'admin_init',            $admin, 'register_settings' );
        $this->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );

        // AJAX handlers (admin-side)
        $this->add_action( 'wp_ajax_eerb_save_room',          $admin, 'ajax_save_room' );
        $this->add_action( 'wp_ajax_eerb_delete_room',        $admin, 'ajax_delete_room' );
        $this->add_action( 'wp_ajax_eerb_save_game',          $admin, 'ajax_save_game' );
        $this->add_action( 'wp_ajax_eerb_get_game',           $admin, 'ajax_get_game' );
        $this->add_action( 'wp_ajax_eerb_save_pricing',       $admin, 'ajax_save_pricing' );
        $this->add_action( 'wp_ajax_eerb_delete_game',        $admin, 'ajax_delete_game' );
        $this->add_action( 'wp_ajax_eerb_save_hours',         $admin, 'ajax_save_hours' );
        $this->add_action( 'wp_ajax_eerb_block_slot',         $admin, 'ajax_block_slot' );
        $this->add_action( 'wp_ajax_eerb_unblock_slot',       $admin, 'ajax_unblock_slot' );
        $this->add_action( 'wp_ajax_eerb_save_promo',         $admin, 'ajax_save_promo' );
        $this->add_action( 'wp_ajax_eerb_delete_promo',       $admin, 'ajax_delete_promo' );
        $this->add_action( 'wp_ajax_eerb_save_gamekeeper',    $admin, 'ajax_save_gamekeeper' );
        $this->add_action( 'wp_ajax_eerb_delete_gamekeeper',  $admin, 'ajax_delete_gamekeeper' );
        $this->add_action( 'wp_ajax_eerb_get_bookings',           $admin, 'ajax_get_bookings' );
        $this->add_action( 'wp_ajax_eerb_admin_get_booking',       $admin, 'ajax_admin_get_booking' );
        $this->add_action( 'wp_ajax_eerb_admin_cancel_booking',    $admin, 'ajax_admin_cancel_booking' );
        $this->add_action( 'wp_ajax_eerb_update_booking',     $admin, 'ajax_update_booking' );
    }

    // ─── Public / front-end hooks ─────────────────────────────────────────────

    private function define_public_hooks() {
        $public = new EERB_Public();

        $this->add_action( 'wp_enqueue_scripts',   $public, 'enqueue_assets' );
        $this->add_action( 'init',                 $public, 'register_shortcodes' );
        $this->add_filter( 'query_vars',            $this,   'add_query_vars' );

        // AJAX handlers (front-end — both logged-in and guests)
        $this->add_action( 'wp_ajax_eerb_get_slots',          $public, 'ajax_get_slots' );
        $this->add_action( 'wp_ajax_nopriv_eerb_get_slots',   $public, 'ajax_get_slots' );

        $this->add_action( 'wp_ajax_eerb_hold_slot',          $public, 'ajax_hold_slot' );
        $this->add_action( 'wp_ajax_nopriv_eerb_hold_slot',   $public, 'ajax_hold_slot' );

        $this->add_action( 'wp_ajax_eerb_release_hold',       $public, 'ajax_release_hold' );
        $this->add_action( 'wp_ajax_nopriv_eerb_release_hold',$public, 'ajax_release_hold' );

        $this->add_action( 'wp_ajax_eerb_create_booking',          $public, 'ajax_create_booking' );
        $this->add_action( 'wp_ajax_nopriv_eerb_create_booking',   $public, 'ajax_create_booking' );

        $this->add_action( 'wp_ajax_eerb_confirm_payment',          $public, 'ajax_confirm_payment' );
        $this->add_action( 'wp_ajax_nopriv_eerb_confirm_payment',   $public, 'ajax_confirm_payment' );

        $this->add_action( 'wp_ajax_eerb_validate_promo',          $public, 'ajax_validate_promo' );
        $this->add_action( 'wp_ajax_nopriv_eerb_validate_promo',   $public, 'ajax_validate_promo' );

        $this->add_action( 'wp_ajax_eerb_check_email',              $public, 'ajax_check_email' );
        $this->add_action( 'wp_ajax_nopriv_eerb_check_email',      $public, 'ajax_check_email' );
        $this->add_action( 'wp_ajax_eerb_customer_login',          $public, 'ajax_customer_login' );
        $this->add_action( 'wp_ajax_nopriv_eerb_customer_login',   $public, 'ajax_customer_login' );

        // Stripe webhook (no WP auth — verified by Stripe signature)
        $this->add_action( 'wp_ajax_nopriv_eerb_stripe_webhook',   $public, 'handle_stripe_webhook' );
        $this->add_action( 'wp_ajax_eerb_stripe_webhook',          $public, 'handle_stripe_webhook' );

        // Manage-booking token endpoint
        $this->add_action( 'init', $public, 'handle_manage_booking_request' );
    }

    // ─── Cron hooks ───────────────────────────────────────────────────────────

    private function define_cron_hooks() {
        // Schedule cleanup of expired slot holds (runs every 5 minutes)
        if ( ! wp_next_scheduled( 'eerb_cleanup_expired_holds' ) ) {
            wp_schedule_event( time(), 'eerb_every_five_minutes', 'eerb_cleanup_expired_holds' );
        }

        $slot_holds = new EERB_Slot_Holds();
        $this->add_action( 'eerb_cleanup_expired_holds', $slot_holds, 'cleanup_expired' );

        // Register the custom cron interval
        $this->add_filter( 'cron_schedules', $this, 'add_cron_intervals' );
    }

    public function add_cron_intervals( $schedules ) {
        $schedules['eerb_every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes', 'ettrick-escape-room-booking' ),
        );
        return $schedules;
    }

    // ─── Hook registration helpers ────────────────────────────────────────────

    public function add_action( $hook, $component, $callback, $priority = 10, $args = 1 ) {
        $this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
    }

    public function add_filter( $hook, $component, $callback, $priority = 10, $args = 1 ) {
        $this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'eerb_action';
        $vars[] = 'token';
        return $vars;
    }

    public function run() {
        foreach ( $this->filters as $f ) {
            add_filter( $f['hook'], array( $f['component'], $f['callback'] ), $f['priority'], $f['args'] );
        }
        foreach ( $this->actions as $a ) {
            add_action( $a['hook'], array( $a['component'], $a['callback'] ), $a['priority'], $a['args'] );
        }
    }
}
