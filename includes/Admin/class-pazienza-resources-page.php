<?php
defined('ABSPATH') || exit;

class Pazienza_Booking_Resources_Page
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        try {
            $client    = pazienza_booking_client();
            $resources = $client->get_web_bookable_resources();
            $all_resources_raw = $client->get('/api/cloud/appointment-resources', ['includeArchived' => 'false']);
            $products  = $client->get('/api/cloud/products', ['page' => '1', 'pageSize' => '200', 'q' => '']);

            // Indicizza le risorse web-bookable per confronto rapido.
            $bookable_ids = array_flip(array_column($resources, 'id'));
            // Indicizza i prodotti web-visible.
            $visible_products_raw = $client->get_web_visible_products();
            $visible_ids = array_flip(array_column($visible_products_raw, 'id'));

            // Filtra solo le risorse di tipo ServiceProvider.
            // Il server serializza ResourceType come intero: Space=0, Equipment=1, ServiceProvider=2.
            $service_providers = array_filter(
                $all_resources_raw['items'] ?? $all_resources_raw,
                fn(array $r): bool => ($r['type'] ?? -1) === 2
            );
        } catch (RuntimeException $e) {
            echo '<div class="wrap"><div class="notice notice-error"><p>'
                . esc_html__('Errore API Pazienza: ', 'pazienza-booking') . esc_html($e->getMessage())
                . '</p></div></div>';
            return;
        }
        $web_url  = (string) get_option('pazienza_booking_web_url', '');
        $day_names = [
            0 => __('Dom', 'pazienza-booking'), 1 => __('Lun', 'pazienza-booking'),
            2 => __('Mar', 'pazienza-booking'), 3 => __('Mer', 'pazienza-booking'),
            4 => __('Gio', 'pazienza-booking'), 5 => __('Ven', 'pazienza-booking'),
            6 => __('Sab', 'pazienza-booking'),
        ];
        ?>
        <div class="wrap">
            <h1 style="display:flex;align-items:center;gap:12px">
                <?php echo esc_html__('Risorse e Servizi — Prenotazione online', 'pazienza-booking'); ?>
                <?php if ($web_url): ?>
                    <a href="<?php echo esc_url(rtrim($web_url, '/') . '/risorse'); ?>" target="_blank" rel="noopener"
                       class="button button-secondary" style="font-size:13px;text-decoration:none">
                        <?php echo esc_html__('Gestisci in Pazienza ↗', 'pazienza-booking'); ?>
                    </a>
                <?php endif; ?>
            </h1>
            <p><?php echo esc_html__('Abilita le risorse e i servizi da mostrare nel form di prenotazione online.', 'pazienza-booking'); ?></p>

            <h2><?php echo esc_html__('Risorse (operatori / stanze)', 'pazienza-booking'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Nome', 'pazienza-booking'); ?></th>
                        <th><?php echo esc_html__('Disponibilità', 'pazienza-booking'); ?></th>
                        <th><?php echo esc_html__('Prenotabile online', 'pazienza-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($service_providers as $resource) :
                        $is_bookable = isset($bookable_ids[$resource['id']]);
                        $slots       = $resource['availability'] ?? [];
                        // Raggruppa slot per giorno → "Lun 08:00–20:00, Mar 08:00–20:00, …"
                        $slot_labels = array_map(
                            fn(array $s): string => ($day_names[$s['day'] ?? -1] ?? '?') . ' ' . ($s['from'] ?? '') . '–' . ($s['to'] ?? ''),
                            $slots
                        );
                    ?>
                    <tr>
                        <td><?php echo esc_html($resource['name'] ?? ''); ?></td>
                        <td style="font-size:12px;color:#646970">
                            <?php if ($slot_labels) {
                                echo esc_html(implode(', ', $slot_labels));
                            } else {
                                echo '<em style="color:#d63638">' . esc_html__('Nessuna fascia — slot non generati', 'pazienza-booking') . '</em>';
                            } ?>
                        </td>
                        <td>
                            <label class="pbf-toggle">
                                <input type="checkbox"
                                    class="pbf-toggle-resource"
                                    data-id="<?php echo esc_attr($resource['id']); ?>"
                                    <?php echo checked($is_bookable, true, false); ?>>
                                <span><?php echo $is_bookable
                                    ? esc_html__('Sì', 'pazienza-booking')
                                    : esc_html__('No', 'pazienza-booking'); ?></span>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top:32px"><?php echo esc_html__('Servizi', 'pazienza-booking'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Nome', 'pazienza-booking'); ?></th>
                        <th><?php echo esc_html__('Visibile online', 'pazienza-booking'); ?></th>
                        <th><?php echo esc_html__('Durata', 'pazienza-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($products['items'] ?? $products) as $product) :
                        $is_visible = isset($visible_ids[$product['id']]);
                        $duration   = $product['defaultDurationMinutes'] ?? null;
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html($product['name'] ?? ''); ?>
                            <?php if ($is_visible && $duration === null) : ?>
                            <span style="color:#c0392b;font-size:.85em">
                                ⚠ <?php echo esc_html__('Durata non impostata — gli slot non verranno generati.', 'pazienza-booking'); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <label class="pbf-toggle">
                                <input type="checkbox"
                                    class="pbf-toggle-product"
                                    data-id="<?php echo esc_attr($product['id']); ?>"
                                    <?php echo checked($is_visible, true, false); ?>>
                                <span><?php echo $is_visible
                                    ? esc_html__('Sì', 'pazienza-booking')
                                    : esc_html__('No', 'pazienza-booking'); ?></span>
                            </label>
                        </td>
                        <td><?php echo $duration !== null ? esc_html($duration . ' min') : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
    }

    // ── AJAX handlers ─────────────────────────────────────────────────────────

    public static function ajax_toggle_resource(): void
    {
        check_ajax_referer('pazienza_booking_toggle');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permesso negato.', 403);
        }

        $id    = sanitize_text_field(wp_unslash($_POST['id']    ?? ''));
        $value = sanitize_text_field(wp_unslash($_POST['value'] ?? '0')) === '1';

        try {
            pazienza_booking_client()->toggle_resource_web_bookable($id, $value);
            wp_send_json_success();
        } catch (RuntimeException $e) {
            wp_send_json_error($e->getMessage(), 502);
        }
    }

    public static function ajax_toggle_product(): void
    {
        check_ajax_referer('pazienza_booking_toggle');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permesso negato.', 403);
        }

        $id    = sanitize_text_field(wp_unslash($_POST['id']    ?? ''));
        $value = sanitize_text_field(wp_unslash($_POST['value'] ?? '0')) === '1';

        try {
            pazienza_booking_client()->toggle_product_web_visible($id, $value);
            wp_send_json_success();
        } catch (RuntimeException $e) {
            wp_send_json_error($e->getMessage(), 502);
        }
    }
}
