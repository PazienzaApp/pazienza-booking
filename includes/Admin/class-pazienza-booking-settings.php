<?php
defined('ABSPATH') || exit;

/**
 * Pagina impostazioni Pazienza Booking.
 * Tab: Connessione | Impostazioni | Campi personalizzati
 */
class Pazienza_Booking_Settings
{
    private Pazienza_Token_Store $store;
    private Pazienza_OAuth       $oauth;

    public function __construct()
    {
        $this->store = new Pazienza_Token_Store();
        $this->oauth = new Pazienza_OAuth($this->store);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets(string $hook): void
    {
        if (!str_contains($hook, 'pazienza-booking')) {
            return;
        }

        $base_url = PAZIENZA_BOOKING_PLUGIN_URL;

        wp_enqueue_style(
            'pazienza-booking-admin',
            $base_url . 'assets/css/pazienza-admin.css',
            [],
            PAZIENZA_BOOKING_VERSION
        );

        wp_enqueue_script(
            'pazienza-booking-settings',
            $base_url . 'assets/js/pazienza-settings.js',
            [],
            PAZIENZA_BOOKING_VERSION,
            true
        );
        $custom_fields = json_decode((string) get_option('pazienza_booking_custom_fields', '[]'), true) ?: [];
        wp_localize_script('pazienza-booking-settings', 'pazienzaSettingsData', [
            'fieldCount'  => count($custom_fields),
            'labelRemove' => __('Rimuovi', 'pazienza-booking'),
        ]);

        wp_enqueue_script(
            'pazienza-booking-resources',
            $base_url . 'assets/js/pazienza-resources.js',
            [],
            PAZIENZA_BOOKING_VERSION,
            true
        );
        wp_localize_script('pazienza-booking-resources', 'pazienzaResourcesData', [
            'nonce'      => wp_create_nonce('pazienza_booking_toggle'),
            'labelYes'   => __('Sì', 'pazienza-booking'),
            'labelNo'    => __('No', 'pazienza-booking'),
            'labelError' => __('Errore API', 'pazienza-booking'),
        ]);
    }

    /**
     * Chiamato su admin_init: gestisce il redirect da Pazienza dopo la connessione.
     */
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    public function handle_oauth_callback(): void
    {
        if (!isset($_GET['page'], $_GET['installation_code'])
            || $_GET['page'] !== 'pazienza-booking') {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permesso negato.', 'pazienza-booking'));
        }

        if (isset($_GET['error'])) {
            add_settings_error(
                'pazienza_booking',
                'install_denied',
                esc_html(sanitize_text_field(wp_unslash($_GET['error_description'] ?? $_GET['error']))),
                'error'
            );
            return;
        }

        try {
            $this->oauth->handle_install_callback(
                sanitize_text_field(wp_unslash($_GET['installation_code'] ?? '')),
                sanitize_text_field(wp_unslash($_GET['client_id']         ?? '')),
                sanitize_text_field(wp_unslash($_GET['state']             ?? ''))
            );
            wp_safe_redirect(admin_url('admin.php?page=pazienza-booking&tab=connection&connected=1'));
            exit;
        } catch (RuntimeException $e) {
            add_settings_error('pazienza_booking', 'callback_error', esc_html($e->getMessage()), 'error');
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $connect_error = null;

        // ── POST: avvia connessione ───────────────────────────────────────────
        if (isset($_POST['pazienza_booking_connect']) && check_admin_referer('pazienza_booking_connect')) {
            try {
                $scopes = [
                    'openid', 'offline_access',
                    'pazienza:appointments:read', 'pazienza:appointments:write',
                    'pazienza:customers:read',    'pazienza:customers:write',
                ];
                $callback_uri = admin_url('admin.php?page=pazienza-booking&tab=connection');
                wp_redirect($this->oauth->get_plugin_install_url($scopes, $callback_uri)); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
                exit;
            } catch (RuntimeException $e) {
                $connect_error = $e->getMessage();
            }
        }

        // ── POST: disconnessione ─────────────────────────────────────────────
        if (isset($_POST['pazienza_booking_disconnect']) && check_admin_referer('pazienza_booking_disconnect')) {
            $this->store->disconnect();
            wp_safe_redirect(admin_url('admin.php?page=pazienza-booking&tab=connection'));
            exit;
        }

        // ── POST: URL web app ────────────────────────────────────────────────
        if (isset($_POST['pazienza_booking_save_web_url']) && check_admin_referer('pazienza_booking_save_web_url')) {
            $url = esc_url_raw(wp_unslash($_POST['pazienza_booking_web_url'] ?? ''));
            update_option('pazienza_booking_web_url', $url, false);
            wp_safe_redirect(admin_url('admin.php?page=pazienza-booking&tab=connection&saved=1'));
            exit;
        }

        // ── POST: impostazioni generali ──────────────────────────────────────
        if (isset($_POST['pazienza_booking_save_settings']) && check_admin_referer('pazienza_booking_save_settings')) {
            $this->save_settings();
            wp_safe_redirect(admin_url('admin.php?page=pazienza-booking&tab=settings&saved=1'));
            exit;
        }

        // ── POST: campi personalizzati ───────────────────────────────────────
        if (isset($_POST['pazienza_booking_save_fields']) && check_admin_referer('pazienza_booking_save_fields')) {
            $this->save_fields();
            wp_safe_redirect(admin_url('admin.php?page=pazienza-booking&tab=fields&saved=1'));
            exit;
        }

        $is_connected = $this->store->has_valid_token();

        $tabs = [
            'connection' => __('Connessione', 'pazienza-booking'),
            'settings'   => __('Impostazioni', 'pazienza-booking'),
            'fields'     => __('Campi personalizzati', 'pazienza-booking'),
        ];

        $current_tab = sanitize_key($_GET['tab'] ?? 'connection');
        if (!array_key_exists($current_tab, $tabs)) {
            $current_tab = 'connection';
        }

        ?>
        <div class="wrap pazienza-wrap">
            <h1 class="pazienza-page-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 512 512" aria-hidden="true" style="vertical-align:middle;margin-right:8px;flex-shrink:0">
                    <rect width="512" height="512" rx="112" fill="#1B7B8A"/>
                    <g transform="translate(56, 96) scale(0.625)">
                        <path fill="white" d="M272 191.91c-17.6 0-32 14.4-32 32v80c0 8.84-7.16 16-16 16s-16-7.16-16-16v-76.55c0-17.39 4.72-34.47 13.69-49.39l77.75-129.59c9.09-15.16 4.19-34.81-10.97-43.91-14.45-8.67-32.72-4.3-42.3 9.21-.2.23-.62.21-.79.48l-117.26 175.9C117.56 205.9 112 224.31 112 243.29v80.23l-90.12 30.04A31.974 31.974 0 0 0 0 383.91v96c0 10.82 8.52 32 32 32 2.69 0 5.41-.34 8.06-1.03l179.19-46.62C269.16 449.99 304 403.8 304 351.91v-128c0-17.6-14.4-32-32-32zm346.12 161.73L528 323.6v-80.23c0-18.98-5.56-37.39-16.12-53.23L394.62 14.25c-.18-.27-.59-.24-.79-.48-9.58-13.51-27.85-17.88-42.3-9.21-15.16 9.09-20.06 28.75-10.97 43.91l77.75 129.59c8.97 14.92 13.69 32 13.69 49.39V304c0 8.84-7.16 16-16 16s-16-7.16-16-16v-80c0-17.6-14.4-32-32-32s-32 14.4-32 32v128c0 51.89 34.84 98.08 84.75 112.34l179.19 46.62c2.66.69 5.38 1.03 8.06 1.03 23.48 0 32-21.18 32-32v-96c0-13.77-8.81-25.99-21.88-30.35z"/>
                    </g>
                </svg>
                <?php esc_html_e('Pazienza Booking', 'pazienza-booking'); ?>
                <?php if ($is_connected): ?>
                    <span class="pazienza-badge pazienza-badge-ok"><?php esc_html_e('Connesso', 'pazienza-booking'); ?></span>
                <?php else: ?>
                    <span class="pazienza-badge pazienza-badge-err"><?php esc_html_e('Non connesso', 'pazienza-booking'); ?></span>
                <?php endif; ?>
            </h1>

            <?php if (isset($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php esc_html_e('Impostazioni salvate.', 'pazienza-booking'); ?>
                </p></div>
            <?php endif; ?>

            <?php settings_errors('pazienza_booking'); ?>

            <nav class="nav-tab-wrapper pazienza-tab-nav">
                <?php foreach ($tabs as $slug => $label): ?>
                <a href="<?php echo esc_url(admin_url("admin.php?page=pazienza-booking&tab={$slug}")); ?>"
                   class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <div class="pazienza-panel">
                <?php
                match ($current_tab) {
                    'settings' => $this->render_tab_settings(),
                    'fields'   => $this->render_tab_fields(),
                    default    => $this->render_tab_connection($is_connected, $connect_error),
                };
                ?>
            </div>
        </div>
        <?php
    }

    // ── Tab: Connessione ──────────────────────────────────────────────────────

    private function render_tab_connection(bool $is_connected, ?string $connect_error = null): void
    {
        $just_connected = isset($_GET['connected']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="pazienza-section">
            <?php if ($just_connected): ?>
                <div class="notice notice-success inline" style="margin-bottom:16px"><p>
                    <?php esc_html_e('Connessione a Pazienza completata con successo.', 'pazienza-booking'); ?>
                </p></div>
            <?php endif; ?>
            <?php if ($connect_error): ?>
                <div class="notice notice-error inline" style="margin-bottom:16px"><p>
                    <?php echo esc_html($connect_error); ?>
                </p></div>
            <?php endif; ?>

            <h2><?php esc_html_e('Stato connessione', 'pazienza-booking'); ?></h2>

            <div class="pazienza-status-row">
                <?php if ($is_connected): ?>
                    <span class="pazienza-status-indicator ok">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="#007017" aria-hidden="true"><path d="M10 2a8 8 0 1 0 0 16A8 8 0 0 0 10 2zm3.71 6.29-4 4a1 1 0 0 1-1.42 0l-2-2a1 1 0 1 1 1.42-1.42L9 10.17l3.29-3.3a1 1 0 0 1 1.42 1.42z"/></svg>
                        <?php esc_html_e('Connesso a Pazienza', 'pazienza-booking'); ?>
                    </span>
                <?php else: ?>
                    <span class="pazienza-status-indicator err">
                        <svg width="16" height="16" viewBox="0 0 20 20" fill="#d63638" aria-hidden="true"><path d="M10 2a8 8 0 1 0 0 16A8 8 0 0 0 10 2zm1 11H9v-2h2v2zm0-4H9V6h2v3z"/></svg>
                        <?php esc_html_e('Non connesso', 'pazienza-booking'); ?>
                    </span>
                <?php endif; ?>
                <span class="pazienza-server-label">
                    <?php esc_html_e('Server:', 'pazienza-booking'); ?>
                    <code><?php echo esc_html(defined('PAZIENZA_AUTH_BASE_URL') ? PAZIENZA_AUTH_BASE_URL : '—'); ?></code>
                </span>
            </div>

            <?php if (!$is_connected): ?>
                <form method="post">
                    <?php wp_nonce_field('pazienza_booking_connect'); ?>
                    <button type="submit" name="pazienza_booking_connect" class="button button-primary button-large">
                        <?php esc_html_e('Connetti a Pazienza', 'pazienza-booking'); ?>
                    </button>
                    <p class="description" style="margin-top:8px">
                        <?php esc_html_e('Verrai reindirizzato su Pazienza per autenticarti e autorizzare il plugin.', 'pazienza-booking'); ?>
                    </p>
                </form>
                <?php if ($this->store->has_credentials()): ?>
                <p class="description" style="margin-top:16px;color:#646970">
                    <?php esc_html_e('Il plugin era connesso in precedenza ma la sessione è scaduta. Riconnetti per ripristinare il funzionamento.', 'pazienza-booking'); ?>
                </p>
                <?php endif; ?>
            <?php else: ?>
                <form method="post">
                    <?php wp_nonce_field('pazienza_booking_disconnect'); ?>
                    <button type="submit" name="pazienza_booking_disconnect" class="button button-secondary"
                            onclick="return confirm('<?php esc_attr_e('Disconnettere il plugin da Pazienza? Le impostazioni salvate saranno mantenute ma le prenotazioni online si interromperanno.', 'pazienza-booking'); ?>')">
                        <?php esc_html_e('Disconnetti da Pazienza', 'pazienza-booking'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="pazienza-section">
            <h2><?php esc_html_e('URL applicazione Pazienza', 'pazienza-booking'); ?></h2>
            <p class="description" style="margin-bottom:12px">
                <?php esc_html_e('Imposta l\'URL dell\'applicazione web Pazienza per accedere rapidamente alla gestione delle risorse.', 'pazienza-booking'); ?>
            </p>
            <form method="post">
                <?php wp_nonce_field('pazienza_booking_save_web_url'); ?>
                <table class="form-table" style="margin-top:0">
                    <tr>
                        <th scope="row"><?php esc_html_e('URL web app', 'pazienza-booking'); ?></th>
                        <td>
                            <input type="url" name="pazienza_booking_web_url"
                                   value="<?php echo esc_attr((string) get_option('pazienza_booking_web_url', '')); ?>"
                                   class="regular-text" placeholder="https://app.pazienza.app" />
                        </td>
                    </tr>
                </table>
                <button type="submit" name="pazienza_booking_save_web_url" class="button">
                    <?php esc_html_e('Salva URL', 'pazienza-booking'); ?>
                </button>
            </form>
        </div>

        <div class="pazienza-section">
            <h2><?php esc_html_e('Informazioni', 'pazienza-booking'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Versione plugin', 'pazienza-booking'); ?></th>
                    <td><code><?php echo esc_html(PAZIENZA_BOOKING_VERSION); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Blocco Gutenberg', 'pazienza-booking'); ?></th>
                    <td>
                        <p class="description">
                            <?php esc_html_e('Inserisci il blocco "Pazienza Booking" in qualsiasi pagina per mostrare il form di prenotazione.', 'pazienza-booking'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    // ── Tab: Impostazioni ─────────────────────────────────────────────────────

    private function render_tab_settings(): void
    {
        $cancellation_enabled = (bool) get_option('pazienza_booking_cancellation_enabled', false);
        $cancel_hours         = (int)  get_option('pazienza_booking_cancel_hours_before', 24);
        $success_message      = (string) get_option('pazienza_booking_success_message', '');
        ?>
        <div class="pazienza-section">
            <h2><?php esc_html_e('Conferma prenotazione', 'pazienza-booking'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('pazienza_booking_save_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="pbf_success_message">
                                <?php esc_html_e('Messaggio di conferma', 'pazienza-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="pbf_success_message"
                                name="pazienza_booking_success_message"
                                rows="4" class="large-text"><?php echo esc_textarea($success_message); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Messaggio mostrato al cliente dopo la prenotazione.', 'pazienza-booking'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" name="pazienza_booking_save_settings" class="button button-primary">
                    <?php esc_html_e('Salva', 'pazienza-booking'); ?>
                </button></p>
            </form>
        </div>

        <div class="pazienza-section">
            <h2><?php esc_html_e('Cancellazione online', 'pazienza-booking'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('pazienza_booking_save_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Abilita cancellazione', 'pazienza-booking'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pazienza_booking_cancellation_enabled" value="1"
                                    <?php checked($cancellation_enabled, true); ?>>
                                <?php esc_html_e('Includi il link di cancellazione nelle email di conferma', 'pazienza-booking'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pbf_cancel_hours"><?php esc_html_e('Limite cancellazione', 'pazienza-booking'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="pbf_cancel_hours"
                                name="pazienza_booking_cancel_hours_before"
                                value="<?php echo esc_attr($cancel_hours); ?>"
                                min="1" max="720" style="width:80px">
                            <?php esc_html_e("ore prima dell'appuntamento", 'pazienza-booking'); ?>
                        </td>
                    </tr>
                </table>
                <p><button type="submit" name="pazienza_booking_save_settings" class="button button-primary">
                    <?php esc_html_e('Salva', 'pazienza-booking'); ?>
                </button></p>
            </form>
        </div>
        <?php
    }

    // ── Tab: Campi personalizzati ─────────────────────────────────────────────

    private function render_tab_fields(): void
    {
        $custom_fields = self::get_custom_fields();
        ?>
        <div class="pazienza-section">
            <h2><?php esc_html_e('Campi personalizzati', 'pazienza-booking'); ?></h2>
            <p class="description">
                <?php esc_html_e("Aggiungi campi extra al form di prenotazione. Vengono inclusi nelle note dell'appuntamento.", 'pazienza-booking'); ?>
            </p>
            <form method="post">
                <?php wp_nonce_field('pazienza_booking_save_fields'); ?>
                <table class="wp-list-table widefat fixed striped" id="pazienza-custom-fields-table">
                    <thead>
                        <tr>
                            <th style="width:130px"><?php esc_html_e('ID', 'pazienza-booking'); ?></th>
                            <th style="width:180px"><?php esc_html_e('Etichetta', 'pazienza-booking'); ?></th>
                            <th style="width:110px"><?php esc_html_e('Tipo', 'pazienza-booking'); ?></th>
                            <th><?php esc_html_e('Opzioni (una per riga)', 'pazienza-booking'); ?></th>
                            <th style="width:100px;text-align:center"><?php esc_html_e('Obbligatorio', 'pazienza-booking'); ?></th>
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($custom_fields as $i => $field): ?>
                        <tr>
                            <td><input type="text" name="pbf_fields[<?php echo absint($i); ?>][id]"
                                value="<?php echo esc_attr($field['id'] ?? ''); ?>"
                                style="width:100%" placeholder="es. fonte"></td>
                            <td><input type="text" name="pbf_fields[<?php echo absint($i); ?>][label]"
                                value="<?php echo esc_attr($field['label'] ?? ''); ?>"
                                style="width:100%"></td>
                            <td>
                                <select name="pbf_fields[<?php echo absint($i); ?>][type]" style="width:100%">
                                    <?php foreach (['text', 'textarea', 'select', 'radio', 'checkbox'] as $t): ?>
                                    <option value="<?php echo esc_attr($t); ?>" <?php selected($field['type'] ?? 'text', $t); ?>>
                                        <?php echo esc_html($t); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><textarea name="pbf_fields[<?php echo absint($i); ?>][options]" rows="3"
                                style="width:100%"><?php echo esc_textarea($field['options'] ?? ''); ?></textarea></td>
                            <td style="text-align:center">
                                <input type="checkbox" name="pbf_fields[<?php echo absint($i); ?>][required]" value="1"
                                    <?php checked(!empty($field['required']), true); ?>>
                            </td>
                            <td>
                                <a href="#" class="pbf-remove-row button button-small">
                                    <?php esc_html_e('Rimuovi', 'pazienza-booking'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top:12px">
                    <a href="#" id="pbf-add-field" class="button">
                        + <?php esc_html_e('Aggiungi campo', 'pazienza-booking'); ?>
                    </a>
                </p>
                <p><button type="submit" name="pazienza_booking_save_fields" class="button button-primary">
                    <?php esc_html_e('Salva campi', 'pazienza-booking'); ?>
                </button></p>
            </form>
        </div>
        <?php
    }

    // ── Persistenza ───────────────────────────────────────────────────────────

    private function save_settings(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        update_option('pazienza_booking_cancellation_enabled',
            !empty($_POST['pazienza_booking_cancellation_enabled']));
        update_option('pazienza_booking_cancel_hours_before',
            max(1, absint(wp_unslash($_POST['pazienza_booking_cancel_hours_before'] ?? 24))));
        update_option('pazienza_booking_success_message',
            sanitize_textarea_field(wp_unslash($_POST['pazienza_booking_success_message'] ?? '')));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    private function save_fields(): void
    {
        $fields = [];
        foreach ((array) wp_unslash($_POST['pbf_fields'] ?? []) as $raw) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $id = sanitize_key($raw['id'] ?? '');
            if ($id === '') continue;
            $fields[] = [
                'id'       => $id,
                'label'    => sanitize_text_field($raw['label'] ?? $id),
                'type'     => in_array($raw['type'] ?? '', ['text', 'textarea', 'select', 'radio', 'checkbox'], true)
                                ? $raw['type'] : 'text',
                'options'  => sanitize_textarea_field($raw['options'] ?? ''),
                'required' => !empty($raw['required']),
            ];
        }
        update_option('pazienza_booking_custom_fields', wp_json_encode($fields));
    }

    public static function get_custom_fields(): array
    {
        $raw = get_option('pazienza_booking_custom_fields', '[]');
        return json_decode((string) $raw, true) ?: [];
    }
}
