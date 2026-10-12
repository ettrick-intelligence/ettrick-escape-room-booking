<?php
/**
 * Plugin Name: Ettrick Escape Room Booking
 * Plugin URI:  https://ettrickintelligence.com/escape-room-booking
 * Description: A complete booking system for escape room venues. Manage games, take bookings and collect payments via Stripe — all from your own WordPress website. Upgrade to Pro for unlimited games, promo codes, reports and more.
 * Version:     1.3.2
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:      Ettrick Intelligence
 * Author URI:  https://ettrickintelligence.com
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ettrick-escape-room-booking
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Constants ────────────────────────────────────────────────────────────────

define( 'EERB_VERSION',     '1.3.2' );
define( 'EERB_LITE',        true );  // Lite version flag

define( 'EERB_PLUGIN_FILE', __FILE__ );
define( 'EERB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'EERB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'EERB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ─── Activation / Deactivation ────────────────────────────────────────────────

function eerb_activate() {
    require_once EERB_PLUGIN_DIR . 'includes/class-erb-activator.php';
    EERB_Activator::activate();
}
register_activation_hook( __FILE__, 'eerb_activate' );

function eerb_deactivate() {
    require_once EERB_PLUGIN_DIR . 'includes/class-erb-deactivator.php';
    EERB_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'eerb_deactivate' );

// ─── Bootstrap ────────────────────────────────────────────────────────────────

require_once EERB_PLUGIN_DIR . 'includes/class-erb-loader.php';

/**
 * Runs lightweight upgrade checks on every load when version changes.
 * Handles cases where activation hook doesn't fire on plugin update.
 */
function eerb_maybe_upgrade() {
    $installed = get_option( 'eerb_version', '0' );
    if ( version_compare( $installed, EERB_VERSION, '<' ) ) {
        require_once EERB_PLUGIN_DIR . 'includes/class-erb-activator.php';
        EERB_Activator::upgrade();
        update_option( 'eerb_version', EERB_VERSION );
    }
}
add_action( 'plugins_loaded', 'eerb_maybe_upgrade' );

function eerb_run() {
    $plugin = new EERB_Loader();
    $plugin->run();
}
eerb_run();
