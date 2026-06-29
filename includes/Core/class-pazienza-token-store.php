<?php
defined('ABSPATH') || exit;

if (!class_exists('Pazienza_Token_Store')) {
    class Pazienza_Token_Store
    {
        private const OPT_CLIENT_ID     = 'pazienza_wc_client_id';
        private const OPT_CLIENT_SECRET = 'pazienza_wc_client_secret';
        private const OPT_ACCESS_TOKEN  = 'pazienza_wc_access_token';
        private const OPT_REFRESH_TOKEN = 'pazienza_wc_refresh_token';
        private const OPT_EXPIRES_AT    = 'pazienza_wc_token_expires_at';

        public function has_credentials(): bool
        {
            return !empty(get_option(self::OPT_CLIENT_ID));
        }

        public function get_client_id(): string
        {
            return (string) get_option(self::OPT_CLIENT_ID, '');
        }

        public function get_client_secret(): string
        {
            return (string) get_option(self::OPT_CLIENT_SECRET, '');
        }

        public function save_credentials(string $client_id, string $client_secret): void
        {
            update_option(self::OPT_CLIENT_ID,     $client_id,     false);
            update_option(self::OPT_CLIENT_SECRET, $client_secret, false);
        }

        public function has_valid_token(): bool
        {
            $expires_at = (int) get_option(self::OPT_EXPIRES_AT, 0);
            return !empty(get_option(self::OPT_ACCESS_TOKEN)) && time() < $expires_at - 60;
        }

        public function get_access_token(): string
        {
            return (string) get_option(self::OPT_ACCESS_TOKEN, '');
        }

        public function get_refresh_token(): string
        {
            return (string) get_option(self::OPT_REFRESH_TOKEN, '');
        }

        public function save_tokens(string $access_token, string $refresh_token, int $expires_in): void
        {
            update_option(self::OPT_ACCESS_TOKEN,  $access_token,        false);
            update_option(self::OPT_REFRESH_TOKEN, $refresh_token,       false);
            update_option(self::OPT_EXPIRES_AT,    time() + $expires_in, false);
        }

        /** Cancella solo i token (sessione scaduta); le credenziali dell'installazione restano. */
        public function clear(): void
        {
            delete_option(self::OPT_ACCESS_TOKEN);
            delete_option(self::OPT_REFRESH_TOKEN);
            delete_option(self::OPT_EXPIRES_AT);
        }

        /**
         * Decodifica il payload JWT e restituisce la lista dei moduli Pazienza attivi.
         * Non verifica la firma (il token è già stato validato dal server quando emesso).
         *
         * @return string[]
         */
        public function get_modules_from_token(): array
        {
            $token = $this->get_access_token();
            if (empty($token)) return [];

            $parts = explode('.', $token);
            if (count($parts) !== 3) return [];

            $payload_json = base64_decode(
                str_pad(
                    str_replace(['-', '_'], ['+', '/'], $parts[1]),
                    (int) (ceil(strlen($parts[1]) / 4) * 4),
                    '=',
                    STR_PAD_RIGHT
                )
            );

            if ($payload_json === false) return [];

            $payload = json_decode($payload_json, true);
            if (!is_array($payload)) return [];

            $modules_str = $payload['pazienza:modules'] ?? '';
            if (empty($modules_str)) return [];

            return array_values(array_filter(array_map('trim', explode(',', $modules_str))));
        }

        /** Disconnessione completa: cancella credenziali + token. */
        public function disconnect(): void
        {
            delete_option(self::OPT_CLIENT_ID);
            delete_option(self::OPT_CLIENT_SECRET);
            $this->clear();
        }
    }
}
