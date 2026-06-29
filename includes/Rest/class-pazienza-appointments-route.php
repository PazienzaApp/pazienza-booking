<?php
defined('ABSPATH') || exit;

class Pazienza_Appointments_Route
{
    private const NAMESPACE = 'pazienza-booking/v1';

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/appointments', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_create'],
            'permission_callback' => [$this, 'verify_nonce'],
            'args'                => [
                'service_id'    => ['required' => true,  'type' => 'string'],
                'resource_id'   => ['required' => true,  'type' => 'string'],
                'start'         => ['required' => true,  'type' => 'string'],
                'end'           => ['required' => true,  'type' => 'string'],
                'customer_name' => ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'customer_email'=> ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                'customer_phone'=> ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'notes'            => ['required' => false, 'type' => 'string',  'sanitize_callback' => 'sanitize_textarea_field'],
                'custom_fields'    => ['required' => false, 'type' => 'object'],
                'register_account' => ['required' => false, 'type' => 'boolean', 'default' => false],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/appointments/(?P<token>[A-Za-z0-9+/=]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handle_cancel'],
            'permission_callback' => '__return_true',
            'args'                => [
                'token' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        // Cancellazione autenticata da area My Account (utente loggato).
        register_rest_route(self::NAMESPACE, '/appointments/cancel', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_authenticated_cancel'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'appointment_id' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function verify_nonce(WP_REST_Request $request): bool|WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_forbidden', __('Nonce non valido.', 'pazienza-booking'), ['status' => 403]);
        }
        return true;
    }

    public function handle_create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $name   = $request->get_param('customer_name');
        $email  = $request->get_param('customer_email');
        $phone  = $request->get_param('customer_phone') ?? '';
        $start  = $request->get_param('start');
        $end    = $request->get_param('end');
        $notes  = $request->get_param('notes') ?? '';

        $custom_fields = $request->get_param('custom_fields') ?? [];
        if (!empty($custom_fields)) {
            $extra = [];
            foreach ((array) $custom_fields as $field_id => $value) {
                $extra[] = esc_html($field_id) . ': ' . esc_html((string) $value);
            }
            if ($notes !== '') {
                $notes .= "\n\n";
            }
            $notes .= implode("\n", $extra);
        }

        try {
            $client   = pazienza_booking_client();
            $customer = null;
            try {
                $customer = $client->find_customer_by_email($email);
            } catch (RuntimeException) {
                // Non bloccare la prenotazione se la lookup fallisce.
            }

            $appointment = $client->create_appointment([
                'subjectDisplayName'      => $name,
                'subjectEmail'            => $email,
                'subjectPhone'            => $phone ?: null,
                'subjectLinkedCustomerId' => $customer['id'] ?? null,
                'productId'               => $request->get_param('service_id'),
                'resourceIds'             => [$request->get_param('resource_id')],
                'start'                   => $start,
                'end'                     => $end,
                'notes'                   => $notes ?: null,
                'isWebCreated'            => true,
            ]);
        } catch (RuntimeException $e) {
            return new WP_Error('pazienza_api_error', $e->getMessage(), ['status' => 502]);
        }

        $appointment_id = $appointment['id'] ?? '';

        $cancel_link = null;
        if ((bool) get_option('pazienza_booking_cancellation_enabled', false) && $appointment_id) {
            $cancel_link = $this->build_cancel_link($appointment_id, $start);
        }

        // Invia email di conferma al cliente.
        Pazienza_Booking_Mailer::send_confirmation($email, $name, [
            'service_name'      => $appointment['productName']   ?? '',
            'resource_name'     => $appointment['resourceNames'] ?? '',
            'start'             => $start,
            'end'               => $end,
            'cancellation_link' => $cancel_link,
        ]);

        $registration_result = null;
        if ($request->get_param('register_account') && !is_user_logged_in()) {
            $registration_result = $this->maybe_register_user($email, $name);
        }

        return new WP_REST_Response([
            'appointment_id'      => $appointment_id,
            'cancellation_link'   => $cancel_link,
            'registration_result' => $registration_result,
        ], 201);
    }

    public function handle_cancel(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!(bool) get_option('pazienza_booking_cancellation_enabled', false)) {
            return new WP_Error('not_allowed', __('La cancellazione online non è abilitata.', 'pazienza-booking'), ['status' => 403]);
        }

        $token = $request->get_param('token');
        $parsed = $this->verify_cancel_token($token);

        if (is_wp_error($parsed)) {
            return $parsed;
        }

        ['appointment_id' => $appointment_id] = $parsed;

        try {
            pazienza_booking_client()->delete_appointment($appointment_id);
            return new WP_REST_Response(['cancelled' => true], 200);
        } catch (RuntimeException $e) {
            return new WP_Error('pazienza_api_error', $e->getMessage(), ['status' => 502]);
        }
    }

    public function handle_authenticated_cancel(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!(bool) get_option('pazienza_booking_cancellation_enabled', false)) {
            return new WP_Error('not_allowed', __('La cancellazione online non è abilitata.', 'pazienza-booking'), ['status' => 403]);
        }

        $appointment_id = $request->get_param('appointment_id');
        $user_email     = wp_get_current_user()->user_email;

        try {
            $client   = pazienza_booking_client();
            $customer = $client->find_customer_by_email($user_email);
            if ($customer === null) {
                return new WP_Error('not_found', __('Account non trovato in Pazienza.', 'pazienza-booking'), ['status' => 404]);
            }

            $appointments = $client->get_appointments_for_customer($customer['id']);
            $owned        = array_values(array_filter($appointments, fn(array $a) => ($a['id'] ?? '') === $appointment_id));
            if (empty($owned)) {
                return new WP_Error('forbidden', __('Prenotazione non trovata.', 'pazienza-booking'), ['status' => 403]);
            }

            $cancel_hours = (int) get_option('pazienza_booking_cancel_hours_before', 24);
            $start_ts     = strtotime($owned[0]['start'] ?? '');
            if (!$start_ts || ($start_ts - $cancel_hours * HOUR_IN_SECONDS) <= time()) {
                return new WP_Error('expired', __('Il termine per la cancellazione è scaduto.', 'pazienza-booking'), ['status' => 410]);
            }

            $client->delete_appointment($appointment_id);
            return new WP_REST_Response(['cancelled' => true], 200);
        } catch (RuntimeException $e) {
            return new WP_Error('pazienza_api_error', $e->getMessage(), ['status' => 502]);
        }
    }

    // ── Account registration ──────────────────────────────────────────────────

    private function maybe_register_user(string $email, string $display_name): string
    {
        if (get_user_by('email', $email)) {
            return 'already_exists';
        }

        $base     = sanitize_user(strtolower((string) strstr($email, '@', true)) ?: 'utente', true);
        $username = $base;
        $i        = 1;
        while (username_exists($username)) {
            $username = $base . $i++;
        }

        $user_id = wp_create_user($username, wp_generate_password(24), $email);
        if (is_wp_error($user_id)) {
            return 'failed';
        }

        wp_update_user(['ID' => $user_id, 'display_name' => $display_name]);
        wp_new_user_notification($user_id, null, 'user');

        return 'success';
    }

    // ── Token helpers ─────────────────────────────────────────────────────────

    private function build_cancel_link(string $appointment_id, string $start): ?string
    {
        $cancel_hours = (int) get_option('pazienza_booking_cancel_hours_before', 24);
        $expires_at   = strtotime($start) - ($cancel_hours * HOUR_IN_SECONDS);

        if ($expires_at <= time()) {
            return null;
        }

        $payload = $appointment_id . '|' . $expires_at;
        $hmac    = hash_hmac('sha256', $payload, (string) get_option('pazienza_booking_cancel_secret', ''));
        $token   = base64_encode($payload . '|' . $hmac);

        return rest_url('pazienza-booking/v1/appointments/' . rawurlencode($token));
    }

    private function verify_cancel_token(string $token): array|WP_Error
    {
        $decoded = base64_decode($token, strict: true);
        if ($decoded === false) {
            return new WP_Error('invalid_token', __('Token di cancellazione non valido.', 'pazienza-booking'), ['status' => 400]);
        }

        $parts = explode('|', $decoded, 3);
        if (count($parts) !== 3) {
            return new WP_Error('invalid_token', __('Token di cancellazione non valido.', 'pazienza-booking'), ['status' => 400]);
        }

        [$appointment_id, $expires_at, $hmac] = $parts;

        $expected = hash_hmac('sha256', $appointment_id . '|' . $expires_at, (string) get_option('pazienza_booking_cancel_secret', ''));
        if (!hash_equals($expected, $hmac)) {
            return new WP_Error('invalid_token', __('Token di cancellazione non valido.', 'pazienza-booking'), ['status' => 400]);
        }

        if ((int) $expires_at <= time()) {
            return new WP_Error('token_expired', __('Il termine per la cancellazione è scaduto.', 'pazienza-booking'), ['status' => 410]);
        }

        return ['appointment_id' => $appointment_id];
    }
}
