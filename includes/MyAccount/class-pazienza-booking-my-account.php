<?php
defined('ABSPATH') || exit;

/**
 * Sezione "Le mie prenotazioni" nell'area clienti di WooCommerce.
 * Richiede pazienza-core attivo (pazienza_booking_client()).
 */
class Pazienza_Booking_My_Account
{
    private const ENDPOINT = 'pazienza-bookings';

    public function register(): void
    {
        add_action('init',                                                 [$this, 'add_endpoint'], 0);
        add_filter('woocommerce_account_menu_items',                      [$this, 'add_menu_item']);
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', [$this, 'render']);
        add_action('wp_enqueue_scripts',                                   [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(): void
    {
        if (!is_account_page()) {
            return;
        }
        $base_url = plugin_dir_url(dirname(__DIR__));
        wp_enqueue_style(
            'pazienza-booking-my-account',
            $base_url . 'assets/css/pazienza-my-account.css',
            [],
            PAZIENZA_BOOKING_VERSION
        );

        $cancel_enabled = (bool) get_option('pazienza_booking_cancellation_enabled', false);
        if (!$cancel_enabled) {
            return;
        }
        wp_enqueue_script(
            'pazienza-booking-my-account',
            $base_url . 'assets/js/pazienza-my-account.js',
            [],
            PAZIENZA_BOOKING_VERSION,
            true
        );
        wp_localize_script('pazienza-booking-my-account', 'pazienzaMyAccountData', [
            'nonce'        => wp_create_nonce('wp_rest'),
            'cancelUrl'    => rest_url('pazienza-booking/v1/appointments/cancel'),
            'confirmMsg'   => __('Confermi la cancellazione di questa prenotazione?', 'pazienza-booking'),
            'cancellingMsg'=> __('Annullamento…', 'pazienza-booking'),
            'cancelledMsg' => __('Annullata', 'pazienza-booking'),
            'cancelLabel'  => __('Annulla', 'pazienza-booking'),
            'errorMsg'     => __('Errore nella cancellazione.', 'pazienza-booking'),
        ]);
    }

    public function add_endpoint(): void
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function add_menu_item(array $items): array
    {
        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);
        $items[self::ENDPOINT] = __('Le mie prenotazioni', 'pazienza-booking');
        if ($logout !== null) {
            $items['customer-logout'] = $logout;
        }
        return $items;
    }

    public function render(): void
    {
        if (!class_exists('Pazienza_Token_Store')) {
            return;
        }

        $store = new Pazienza_Token_Store();
        if (!$store->has_credentials()) {
            echo '<p>' . esc_html__('Il servizio di prenotazione non è ancora configurato.', 'pazienza-booking') . '</p>';
            return;
        }

        $user_email = wp_get_current_user()->user_email;
        if (!$user_email) {
            return;
        }

        try {
            $client   = pazienza_booking_client();
            $customer = null;
            try {
                $customer = $client->find_customer_by_email($user_email);
            } catch (RuntimeException) {
                // Prosegui con la ricerca per email.
            }

            // Raccoglie appuntamenti da entrambe le sorgenti e deduplicare per id.
            $by_id = [];
            if ($customer !== null) {
                foreach ($client->get_appointments_for_customer($customer['id']) as $a) {
                    if (!empty($a['id'])) $by_id[$a['id']] = $a;
                }
            }
            foreach ($client->get_appointments_by_subject_email($user_email) as $a) {
                if (!empty($a['id'])) $by_id[$a['id']] = $a;
            }
            $appointments = array_values($by_id);
        } catch (RuntimeException $e) {
            echo '<p class="woocommerce-error">' . esc_html($e->getMessage()) . '</p>';
            return;
        }

        if (empty($appointments)) {
            echo '<p>' . esc_html__('Non hai ancora effettuato prenotazioni.', 'pazienza-booking') . '</p>';
            return;
        }

        $now       = time();
        $upcoming  = [];
        $past      = [];

        foreach ($appointments as $a) {
            $start_ts = strtotime($a['start'] ?? '');
            if ($start_ts === false) continue;
            if ($start_ts >= $now) {
                $upcoming[] = $a;
            } else {
                $past[] = $a;
            }
        }

        // Upcoming: crescente; Past: decrescente (le più recenti prima).
        usort($upcoming, fn($x, $y) => strtotime($x['start']) <=> strtotime($y['start']));
        usort($past,     fn($x, $y) => strtotime($y['start']) <=> strtotime($x['start']));
        $past = array_slice($past, 0, 10);

        $cancellation_enabled = (bool) get_option('pazienza_booking_cancellation_enabled', false);
        $cancel_hours         = (int)  get_option('pazienza_booking_cancel_hours_before', 24);
        $local_tz             = wp_timezone();
        ?>
        <?php if ($upcoming): ?>
        <p class="pazienza-appt-section-title"><?php esc_html_e('Prossime prenotazioni', 'pazienza-booking'); ?></p>
        <table class="pazienza-appt-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Data e ora', 'pazienza-booking'); ?></th>
                    <th><?php esc_html_e('Servizio', 'pazienza-booking'); ?></th>
                    <th><?php esc_html_e('Con', 'pazienza-booking'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcoming as $appt): ?>
                    <?php
                    $start_dt   = !empty($appt['start']) ? (new DateTimeImmutable($appt['start']))->setTimezone($local_tz) : null;
                    $end_dt     = !empty($appt['end'])   ? (new DateTimeImmutable($appt['end']))->setTimezone($local_tz)   : null;
                    $start_ts   = $start_dt?->getTimestamp();
                    $date_str   = $start_dt ? $start_dt->format('d/m/Y') : '—';
                    $time_str   = $start_dt ? $start_dt->format('H:i')   : '';
                    $end_str    = $end_dt   ? $end_dt->format('H:i')     : '';
                    $can_cancel = $cancellation_enabled
                        && $start_ts
                        && $start_ts - ($cancel_hours * HOUR_IN_SECONDS) > $now;
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html($date_str); ?>
                            <?php if ($time_str): ?>
                                <br><span style="font-size:.875em;color:#555"><?php echo esc_html($time_str . ($end_str ? ' – ' . $end_str : '')); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="pazienza-appt-service"><?php echo esc_html($appt['productName'] ?? '—'); ?></span>
                        </td>
                        <td>
                            <span class="pazienza-appt-resource"><?php echo esc_html(is_array($appt['resourceNames'] ?? null) ? implode(', ', $appt['resourceNames']) : ($appt['resourceNames'] ?? '—')); ?></span>
                        </td>
                        <td>
                            <?php if ($can_cancel && !empty($appt['id'])): ?>
                                <button class="pazienza-appt-cancel"
                                        data-id="<?php echo esc_attr($appt['id']); ?>"
                                        data-start="<?php echo esc_attr($appt['start'] ?? ''); ?>">
                                    <?php esc_html_e('Annulla', 'pazienza-booking'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p><?php esc_html_e('Nessuna prenotazione futura.', 'pazienza-booking'); ?></p>
        <?php endif; ?>

        <?php if ($past): ?>
        <p class="pazienza-appt-section-title"><?php esc_html_e('Prenotazioni passate', 'pazienza-booking'); ?></p>
        <table class="pazienza-appt-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Data e ora', 'pazienza-booking'); ?></th>
                    <th><?php esc_html_e('Servizio', 'pazienza-booking'); ?></th>
                    <th><?php esc_html_e('Con', 'pazienza-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($past as $appt): ?>
                    <?php
                    $start_dt = !empty($appt['start']) ? (new DateTimeImmutable($appt['start']))->setTimezone($local_tz) : null;
                    $date_str = $start_dt ? $start_dt->format('d/m/Y') : '—';
                    $time_str = $start_dt ? $start_dt->format('H:i')   : '';
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html($date_str); ?>
                            <?php if ($time_str): ?>
                                <br><span style="font-size:.875em;color:#555"><?php echo esc_html($time_str); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($appt['productName'] ?? '—'); ?></td>
                        <td><?php echo esc_html(is_array($appt['resourceNames'] ?? null) ? implode(', ', $appt['resourceNames']) : ($appt['resourceNames'] ?? '—')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php
    }
}
