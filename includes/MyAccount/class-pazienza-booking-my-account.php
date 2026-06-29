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
        <style>
        .pazienza-appt-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .pazienza-appt-table th { text-align: left; font-size: .8em; text-transform: uppercase; letter-spacing: .05em; color: #555; border-bottom: 2px solid #eee; padding: 6px 8px; }
        .pazienza-appt-table td { padding: 10px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .pazienza-appt-table tr:last-child td { border-bottom: none; }
        .pazienza-appt-service { font-weight: 600; }
        .pazienza-appt-resource { font-size: .875em; color: #555; }
        .pazienza-appt-cancel { font-size: .8em; color: #c0392b; text-decoration: underline; cursor: pointer; background: none; border: none; padding: 0; }
        .pazienza-appt-cancel:hover { color: #922b21; }
        .pazienza-appt-section-title { font-size: 1em; font-weight: 600; margin: 20px 0 8px; }
        </style>

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

        <?php if ($cancellation_enabled): ?>
        <script>
        document.querySelectorAll('.pazienza-appt-cancel').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('<?php echo esc_js(__('Confermi la cancellazione di questa prenotazione?', 'pazienza-booking')); ?>')) return;
                btn.disabled = true;
                btn.textContent = '<?php echo esc_js(__('Annullamento…', 'pazienza-booking')); ?>';

                var nonce = '<?php echo esc_js(wp_create_nonce('wp_rest')); ?>';
                var id    = btn.dataset.id;
                var start = btn.dataset.start;
                var hours = <?php echo (int) $cancel_hours; ?>;

                // Ricrea il token HMAC lato server non è possibile dal client.
                // Usa l'endpoint REST del plugin se la cancellazione è abilitata.
                // Il token viene richiesto al server tramite l'endpoint dedicato.
                fetch('<?php echo esc_js(rest_url('pazienza-booking/v1/appointments/cancel')); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                    body: JSON.stringify({ appointment_id: id }),
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.cancelled) {
                        btn.closest('tr').style.opacity = '.4';
                        btn.textContent = '<?php echo esc_js(__('Annullata', 'pazienza-booking')); ?>';
                    } else {
                        alert(data.message || '<?php echo esc_js(__('Errore nella cancellazione.', 'pazienza-booking')); ?>');
                        btn.disabled = false;
                        btn.textContent = '<?php echo esc_js(__('Annulla', 'pazienza-booking')); ?>';
                    }
                })
                .catch(function() { btn.disabled = false; });
            });
        });
        </script>
        <?php endif; ?>
        <?php
    }
}
