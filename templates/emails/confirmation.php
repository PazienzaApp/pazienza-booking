<?php
/**
 * Template email di conferma prenotazione.
 *
 * Variabili disponibili:
 *   $customer_name    string  Nome del cliente
 *   $service_name     string  Nome del servizio prenotato
 *   $resource_name    string  Nome della risorsa/operatore
 *   $start            string  Data/ora inizio (ISO 8601)
 *   $end              string  Data/ora fine (ISO 8601)
 *   $cancellation_link string|null  Link per cancellare la prenotazione (null se non abilitata)
 *
 * Per personalizzare questo template copia il file in:
 *   wp-content/themes/YOUR_THEME/pazienza-booking/emails/confirmation.php
 */
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$site_name = get_bloginfo('name');
$date_fmt  = get_option('date_format', 'd/m/Y');
$time_fmt  = get_option('time_format', 'H:i');

$start_ts  = strtotime($start ?? '');
$end_ts    = strtotime($end ?? '');
$date_str  = $start_ts ? date_i18n($date_fmt, $start_ts) : '';
$start_str = $start_ts ? date_i18n($time_fmt, $start_ts) : '';
$end_str   = $end_ts   ? date_i18n($time_fmt, $end_ts)   : '';
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title><?php echo esc_html__('Conferma prenotazione', 'pazienza-booking'); ?></title>
</head>
<body style="font-family:sans-serif;color:#333;max-width:600px;margin:0 auto;padding:24px">
    <h2 style="color:#1a1a1a"><?php echo esc_html($site_name); ?></h2>
    <?php /* translators: %s: customer name */ ?>
    <p><?php echo sprintf(esc_html__('Ciao %s,', 'pazienza-booking'), esc_html($customer_name ?? '')); ?></p>
    <p><?php echo esc_html__('La tua prenotazione è confermata.', 'pazienza-booking'); ?></p>

    <table style="border-collapse:collapse;width:100%;margin:16px 0">
        <tr>
            <td style="padding:8px 12px;background:#f5f5f5;font-weight:bold;width:40%"><?php echo esc_html__('Servizio', 'pazienza-booking'); ?></td>
            <td style="padding:8px 12px"><?php echo esc_html($service_name ?? ''); ?></td>
        </tr>
        <?php if (!empty($resource_name)) : ?>
        <tr>
            <td style="padding:8px 12px;background:#f5f5f5;font-weight:bold"><?php echo esc_html__('Con', 'pazienza-booking'); ?></td>
            <td style="padding:8px 12px"><?php echo esc_html($resource_name); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td style="padding:8px 12px;background:#f5f5f5;font-weight:bold"><?php echo esc_html__('Data', 'pazienza-booking'); ?></td>
            <td style="padding:8px 12px"><?php echo esc_html($date_str); ?></td>
        </tr>
        <tr>
            <td style="padding:8px 12px;background:#f5f5f5;font-weight:bold"><?php echo esc_html__('Orario', 'pazienza-booking'); ?></td>
            <td style="padding:8px 12px"><?php echo esc_html($start_str . ' – ' . $end_str); ?></td>
        </tr>
    </table>

    <?php if (!empty($cancellation_link)) : ?>
    <p style="margin-top:24px;font-size:.9em;color:#666">
        <?php echo esc_html__('Se non puoi presentarti, puoi cancellare la prenotazione:', 'pazienza-booking'); ?><br>
        <a href="<?php echo esc_url($cancellation_link); ?>" style="color:#c0392b">
            <?php echo esc_html__('Cancella la prenotazione', 'pazienza-booking'); ?>
        </a>
    </p>
    <?php endif; ?>

    <hr style="margin:32px 0;border:none;border-top:1px solid #e0e0e0">
    <p style="font-size:.8em;color:#999"><?php echo esc_html($site_name); ?></p>
</body>
</html>
