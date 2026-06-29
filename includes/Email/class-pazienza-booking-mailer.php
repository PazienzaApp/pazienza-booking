<?php
defined('ABSPATH') || exit;

class Pazienza_Booking_Mailer
{
    /**
     * Invia l'email di conferma prenotazione al cliente.
     *
     * @param string $to      Indirizzo email del cliente.
     * @param string $name    Nome del cliente.
     * @param array  $vars    Variabili per il template: service_name, resource_name, start, end, cancellation_link.
     */
    public static function send_confirmation(string $to, string $name, array $vars): void
    {
        /* translators: %s: site name */
        $subject  = sprintf(__('[%s] Conferma prenotazione', 'pazienza-booking'), get_bloginfo('name'));
        $body     = self::load_template('confirmation', $vars + ['customer_name' => $name]);
        $headers  = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Invia l'email di conferma cancellazione.
     *
     * @param string $to   Indirizzo email del cliente.
     * @param string $name Nome del cliente.
     * @param array  $vars Variabili per il template: service_name, start.
     */
    public static function send_cancellation_confirmation(string $to, string $name, array $vars): void
    {
        /* translators: %s: site name */
        $subject = sprintf(__('[%s] Prenotazione cancellata', 'pazienza-booking'), get_bloginfo('name'));
        $body    = self::load_template('cancellation', $vars + ['customer_name' => $name]);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail($to, $subject, $body, $headers);
    }

    // ── Template loader ───────────────────────────────────────────────────────

    private static function load_template(string $name, array $vars): string
    {
        // I webdesigner possono sovrascrivere i template nella cartella del tema.
        $theme_path  = get_stylesheet_directory() . "/pazienza-booking/emails/{$name}.php";
        $plugin_path = PAZIENZA_BOOKING_PLUGIN_DIR . "templates/emails/{$name}.php";

        $template = file_exists($theme_path) ? $theme_path : $plugin_path;

        if (!file_exists($template)) {
            return '';
        }

        ob_start();
        // Espone le variabili nel template tramite extract().
        extract($vars, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract
        include $template;
        return (string) ob_get_clean();
    }
}
