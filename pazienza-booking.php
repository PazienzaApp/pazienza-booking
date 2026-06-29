<?php
/**
 * Plugin Name: Pazienza Booking
 * Plugin URI:  https://www.pazienza.app/integrazioni/wordpress/pazienza-booking
 * Description: Online booking form for studios and professionals using Pazienza. Gutenberg block, real-time availability, confirmation emails.
 * Version:     0.1.0
 * Author:      Pazienza
 * Author URI:  https://www.pazienza.app
 * License:     GPL-2.0+
 * Text Domain: pazienza-booking
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP:      8.1
 */

defined('ABSPATH') || exit;

define('PAZIENZA_BOOKING_VERSION',    '0.1.0');
define('PAZIENZA_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAZIENZA_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!defined('PAZIENZA_APP_ID')) {
    define('PAZIENZA_APP_ID', 'd47b6f3a-1c2e-4f8a-9b0c-2d3e4f5a6b7c');
}

if (!defined('PAZIENZA_SERVER_URL')) {
    define('PAZIENZA_SERVER_URL',
        getenv('PAZIENZA_SERVER_URL') ?: get_option('pazienza_wc_server_url', 'https://server.pazienza.app')
    );
}

if (!defined('PAZIENZA_AUTH_BASE_URL')) {
    define('PAZIENZA_AUTH_BASE_URL',
        getenv('PAZIENZA_AUTH_BASE_URL') ?: PAZIENZA_SERVER_URL
    );
}

if (!defined('PAZIENZA_INSTALLATION_TOKEN')) {
    define('PAZIENZA_INSTALLATION_TOKEN',
        getenv('PAZIENZA_INSTALLATION_TOKEN') ?: 'REPLACE_WITH_PRODUCTION_TOKEN'
    );
}

require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Core/class-pazienza-token-store.php';
require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Core/class-pazienza-oauth.php';
require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Core/class-pazienza-client.php';

// Genera il segreto per i token di cancellazione all'attivazione.
// Deve stare a top-level (non dentro un hook) per funzionare correttamente.
register_activation_hook(__FILE__, function (): void {
    if (!get_option('pazienza_booking_cancel_secret')) {
        update_option('pazienza_booking_cancel_secret', wp_generate_password(64, true, true));
    }
    flush_rewrite_rules();
});

add_action('plugins_loaded', function (): void {

    require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Admin/class-pazienza-booking-settings.php';
    require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Admin/class-pazienza-resources-page.php';
    require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Rest/class-pazienza-services-route.php';
    require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Rest/class-pazienza-resources-route.php';
    require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Rest/class-pazienza-slots-route.php';
    require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Rest/class-pazienza-appointments-route.php';
    require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/Email/class-pazienza-booking-mailer.php';
    require_once PAZIENZA_BOOKING_PLUGIN_DIR . 'includes/MyAccount/class-pazienza-booking-my-account.php';

    // ── Admin ─────────────────────────────────────────────────────────────────

    $settings = new Pazienza_Booking_Settings();

    add_action('admin_init', [$settings, 'handle_oauth_callback']);

    add_action('admin_menu', function () use ($settings): void {
        add_menu_page(
            __('Pazienza Booking', 'pazienza-booking'),
            __('Prenotazioni', 'pazienza-booking'),
            'manage_options',
            'pazienza-booking',
            [$settings, 'render_page'],
            'dashicons-calendar-alt',
            56
        );
        add_submenu_page(
            'pazienza-booking',
            __('Impostazioni', 'pazienza-booking'),
            __('Impostazioni', 'pazienza-booking'),
            'manage_options',
            'pazienza-booking',
            [$settings, 'render_page']
        );
        add_submenu_page(
            'pazienza-booking',
            __('Risorse e Servizi', 'pazienza-booking'),
            __('Risorse e Servizi', 'pazienza-booking'),
            'manage_options',
            'pazienza-booking-resources',
            [Pazienza_Booking_Resources_Page::class, 'render']
        );
    });

    if (class_exists('WooCommerce')) {
        (new Pazienza_Booking_My_Account())->register();
    }

    add_action('wp_ajax_pazienza_booking_toggle_resource', [Pazienza_Booking_Resources_Page::class, 'ajax_toggle_resource']);
    add_action('wp_ajax_pazienza_booking_toggle_product',  [Pazienza_Booking_Resources_Page::class, 'ajax_toggle_product']);

    // ── REST API ──────────────────────────────────────────────────────────────

    add_action('rest_api_init', function (): void {
        (new Pazienza_Services_Route())->register();
        (new Pazienza_Resources_Route())->register();
        (new Pazienza_Slots_Route())->register();
        (new Pazienza_Appointments_Route())->register();
    });

    // ── Block ─────────────────────────────────────────────────────────────────

    add_action('init', function (): void {
        $block_dir = PAZIENZA_BOOKING_PLUGIN_DIR . 'blocks/booking-form';
        if (!file_exists($block_dir . '/block.json')) {
            return;
        }
        register_block_type($block_dir, [
            'render_callback' => 'pazienza_booking_render_block',
        ]);
    });

}, 5);

// ── Funzioni globali ──────────────────────────────────────────────────────────
// Definite a top-level perché la render_callback del blocco viene risolta
// per nome e può essere invocata anche prima di plugins_loaded.

function pazienza_booking_render_block(array $attributes): string
{
    static $config_injected = false;

    $id   = esc_attr(wp_unique_id('pbf-'));
    $data = esc_attr(wp_json_encode($attributes));

    $out = '';

    if (!$config_injected) {
        $logged_in = is_user_logged_in();
        $user      = $logged_in ? wp_get_current_user() : null;
        $user_name = '';
        if ($user) {
            $user_name = $user->display_name ?: trim($user->first_name . ' ' . $user->last_name);
        }
        $config = wp_json_encode([
            'nonce'      => wp_create_nonce('wp_rest'),
            'restUrl'    => rest_url('pazienza-booking/v1'),
            'isLoggedIn' => $logged_in,
            'userName'   => $user_name,
            'userEmail'  => $user ? $user->user_email : '',
        ]);
        $out .= "<script>window.pazienzaBookingConfig={$config};</script>";
        $config_injected = true;
    }

    $out .= sprintf(
        '<div class="pazienza-booking" id="%s" data-attributes="%s"></div>',
        $id,
        $data
    );

    return $out;
}

function pazienza_booking_client(): Pazienza_Client
{
    static $client = null;
    if ($client === null) {
        $store  = new Pazienza_Token_Store();
        $oauth  = new Pazienza_OAuth($store);
        $client = new Pazienza_Client($store, $oauth);
    }
    return $client;
}
