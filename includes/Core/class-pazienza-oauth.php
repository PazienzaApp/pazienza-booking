<?php
defined('ABSPATH') || exit;

if (!class_exists('Pazienza_OAuth')) {
    class Pazienza_OAuth
    {
        private const OPT_OAUTH_STATE = 'pazienza_wc_oauth_state';

        public function __construct(private readonly Pazienza_Token_Store $store) {}

        /**
         * Restituisce l'URL della pagina di connessione su Pazienza.
         * Il browser viene reindirizzato qui per login + consenso.
         */
        public function get_plugin_install_url(array $scopes, string $callback_uri = ''): string
        {
            $state = bin2hex(random_bytes(16));
            update_option(self::OPT_OAUTH_STATE, $state, false);

            return add_query_arg(
                [
                    'app_id'             => PAZIENZA_APP_ID,
                    'installation_token' => PAZIENZA_INSTALLATION_TOKEN,
                    'callback_uri'       => !empty($callback_uri) ? $callback_uri : admin_url('admin.php'),
                    'state'              => $state,
                    'scopes'             => implode(' ', $scopes),
                ],
                PAZIENZA_AUTH_BASE_URL . '/connect/plugin-install'
            );
        }

        /**
         * Gestisce il callback da /connect/plugin-install.
         *
         * @throws RuntimeException se la validazione o lo scambio fallisce.
         */
        public function handle_install_callback(
            string $installation_code,
            string $client_id,
            string $state): void
        {
            $saved_state = (string) get_option(self::OPT_OAUTH_STATE, '');
            if (!hash_equals($saved_state, $state)) {
                throw new RuntimeException('State OAuth non valido. Riprova la connessione.');
            }
            delete_option(self::OPT_OAUTH_STATE);

            $body = $this->token_request([
                'grant_type'         => 'urn:pazienza:plugin-connect',
                'client_id'          => $client_id,
                'installation_token' => PAZIENZA_INSTALLATION_TOKEN,
                'installation_code'  => $installation_code,
            ]);

            $this->store->save_credentials($client_id, '');
            $this->store->save_tokens(
                $body['access_token'],
                $body['refresh_token'] ?? '',
                (int) ($body['expires_in'] ?? 3600)
            );
        }

        /**
         * Rinnova l'access token usando il refresh token.
         *
         * @throws RuntimeException se il rinnovo fallisce.
         */
        public function refresh_token(string $refresh_token): void
        {
            try {
                $body = $this->token_request([
                    'grant_type'    => 'refresh_token',
                    'client_id'     => $this->store->get_client_id(),
                    'refresh_token' => $refresh_token,
                ]);
            } catch (RuntimeException $e) {
                $this->store->clear();
                throw new RuntimeException('Rinnovo token fallito. Riconnetti il plugin a Pazienza. Dettaglio: ' . $e->getMessage()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            $this->store->save_tokens(
                $body['access_token'],
                $body['refresh_token'] ?? $refresh_token,
                (int) ($body['expires_in'] ?? 3600)
            );
        }

        /**
         * Restituisce un access token valido, rinnovandolo automaticamente se necessario.
         *
         * @throws RuntimeException se non ci sono token o il rinnovo fallisce.
         */
        public function get_valid_access_token(): string
        {
            if ($this->store->has_valid_token()) {
                return $this->store->get_access_token();
            }

            $rt = $this->store->get_refresh_token();
            if (empty($rt)) {
                throw new RuntimeException('Nessun token valido. Riconnetti il plugin a Pazienza.');
            }

            $this->refresh_token($rt);
            return $this->store->get_access_token();
        }

        private function token_request(array $params): array
        {
            $ssl_verify = !(defined('PAZIENZA_DISABLE_SSL_VERIFY') && PAZIENZA_DISABLE_SSL_VERIFY === 'true');

            $response = wp_remote_post(
                PAZIENZA_SERVER_URL . '/connect/token',
                [
                    'body'      => $params,
                    'timeout'   => 15,
                    'sslverify' => $ssl_verify,
                ]
            );

            if (is_wp_error($response)) {
                throw new RuntimeException('Errore di rete: ' . $response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $data      = json_decode(wp_remote_retrieve_body($response), true);

            if ($http_code !== 200 || empty($data['access_token'])) {
                $err = $data['error_description'] ?? ($data['error'] ?? 'errore sconosciuto');
                throw new RuntimeException("Token request fallita (HTTP {$http_code}): {$err}"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            return $data;
        }
    }
}
