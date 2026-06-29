<?php
/**
 * Template email di conferma cancellazione prenotazione.
 *
 * Variabili disponibili:
 *   $customer_name string  Nome del cliente
 *   $service_name  string  Nome del servizio
 *   $start         string  Data/ora inizio originale (ISO 8601)
 *
 * Per personalizzare: copia in YOUR_THEME/pazienza-booking/emails/cancellation.php
 */
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$site_name = get_bloginfo('name');
$date_fmt  = get_option('date_format', 'd/m/Y');
$time_fmt  = get_option('time_format', 'H:i');
$start_ts  = strtotime($start ?? '');
$date_str  = $start_ts ? date_i18n($date_fmt . ' ' . $time_fmt, $start_ts) : '';
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html__('Prenotazione cancellata', 'pazienza-booking'); ?></title>
</head>
<body style="font-family:sans-serif;color:#333;max-width:600px;margin:0 auto;padding:24px">
    <h2 style="color:#1a1a1a"><?php echo esc_html($site_name); ?></h2>
    <?php /* translators: %s: customer name */ ?>
    <p><?php echo sprintf(esc_html__('Ciao %s,', 'pazienza-booking'), esc_html($customer_name ?? '')); ?></p>
    <p><?php echo esc_html__('La tua prenotazione è stata cancellata.', 'pazienza-booking'); ?></p>

    <table style="border-collapse:collapse;width:100%;margin:16px 0">
        <tr>
            <td style="padding:8px 12px;background:#f5f5f5;font-weight:bold;width:40%"><?php echo esc_html__('Servizio', 'pazienza-booking'); ?></td>
            <td style="padding:8px 12px"><?php echo esc_html($service_name ?? ''); ?></td>
        </tr>
        <tr>
            <td style="padding:8px 12px;background:#f5f5f5;font-weight:bold"><?php echo esc_html__('Data', 'pazienza-booking'); ?></td>
            <td style="padding:8px 12px"><?php echo esc_html($date_str); ?></td>
        </tr>
    </table>

    <p><?php echo esc_html__('Se si è trattato di un errore, puoi effettuare una nuova prenotazione sul nostro sito.', 'pazienza-booking'); ?></p>

    <hr style="margin:32px 0;border:none;border-top:1px solid #e0e0e0">
    <p style="font-size:.8em;color:#999"><?php echo esc_html($site_name); ?></p>
</body>
</html>
