<?php
defined('ABSPATH') || exit;

if (!class_exists('Pazienza_Client')) {
    class Pazienza_Client
    {
        public function __construct(
            private readonly Pazienza_Token_Store $store,
            private readonly Pazienza_OAuth       $oauth
        ) {}

        // ── Customers ─────────────────────────────────────────────────────────────

        public function find_customer_by_email(string $email): ?array
        {
            $results = $this->get('/api/cloud/customers', ['email' => $email]);
            return !empty($results) ? $results[0] : null;
        }

        public function create_person_customer(array $data): array
        {
            return $this->post('/api/cloud/customers/person', $data);
        }

        public function create_company_customer(array $data): array
        {
            return $this->post('/api/cloud/customers/company', $data);
        }

        public function add_customer_contact(string $customer_id, string $type, string $value, bool $is_primary = false): void
        {
            $this->post("/api/cloud/customers/{$customer_id}/contacts", [
                'contactType' => self::contact_type_int($type),
                'value'       => $value,
                'isPrimary'   => $is_primary,
            ]);
        }

        private static function contact_type_int(string $type): int
        {
            return match (strtolower($type)) {
                'email'   => 0,
                'phone'   => 1,
                'mobile'  => 2,
                'fax'     => 3,
                'pec'     => 4,
                'website' => 5,
                default   => throw new \InvalidArgumentException("ContactType sconosciuto: {$type}"), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            };
        }

        public function add_customer_address(string $customer_id, array $data): void
        {
            if (isset($data['addressType']) && is_string($data['addressType'])) {
                $data['addressType'] = self::address_type_int($data['addressType']);
            }
            $this->post("/api/cloud/customers/{$customer_id}/addresses", $data);
        }

        private static function address_type_int(string $type): int
        {
            return match (strtolower($type)) {
                'residence'        => 0,
                'domicile'         => 1,
                'registeredoffice' => 2,
                'operationalsite'  => 3,
                'shipping'         => 4,
                'billing'          => 5,
                default            => throw new \InvalidArgumentException("AddressType sconosciuto: {$type}"), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            };
        }

        // ── Invoices ──────────────────────────────────────────────────────────────

        public function create_invoice(string $customer_id, string $issue_date, string $type = 'Invoice'): array
        {
            return $this->post('/api/cloud/invoices', [
                'companyId'  => '',
                'customerId' => $customer_id ?: null,
                'type'       => $type,
                'number'     => null,
                'issueDate'  => $issue_date,
                'currency'   => 'EUR',
            ]);
        }

        public function add_invoice_line(
            string $invoice_id,
            int    $line_number,
            string $description,
                   $quantity,
                   $unit_price,
                   $vat_rate,
            ?string $vat_nature = null
        ): void {
            $body = [
                'invoiceId'   => $invoice_id,
                'lineNumber'  => $line_number,
                'description' => $description,
                'quantity'    => (float) $quantity,
                'unitPrice'   => (float) $unit_price,
                'vatRate'     => (float) $vat_rate,
            ];
            if ($vat_nature !== null) {
                $body['vatNature'] = $vat_nature;
            }
            $this->post("/api/cloud/invoices/{$invoice_id}/lines", $body);
        }

        public function validate_invoice_xml(string $invoice_id): void
        {
            $this->post("/api/cloud/invoices/{$invoice_id}/xml/validate", []);
        }

        public function get_invoice_pdf_bytes(string $invoice_id): string
        {
            return $this->get_binary("/api/cloud/invoices/{$invoice_id}/pdf");
        }

        public function mark_invoice_ready(string $invoice_id): void
        {
            $this->post("/api/cloud/invoices/{$invoice_id}/ready", []);
        }

        public function send_invoice_to_sdi(string $invoice_id): void
        {
            $this->post("/api/cloud/invoices/{$invoice_id}/send", ['senderKey' => null]);
        }

        public function get_invoice(string $invoice_id): array
        {
            return $this->get("/api/cloud/invoices/{$invoice_id}");
        }

        public function link_invoice_customer(string $invoice_id, string $customer_id): void
        {
            $this->post("/api/cloud/invoices/{$invoice_id}/customer/link", [
                'customerId' => $customer_id,
            ]);
        }

        // ── Settings ──────────────────────────────────────────────────────────────

        public function get_company_settings(): array
        {
            $data = $this->get('/api/cloud/settings/company');
            if (empty($data)) {
                throw new RuntimeException('GET /api/cloud/settings/company ha restituito una risposta vuota.');
            }
            return $data;
        }

        public function get_invoicing_profile(): array
        {
            $data = $this->get('/api/cloud/invoicing-profile');
            if (empty($data)) {
                throw new RuntimeException('GET /api/cloud/invoicing-profile ha restituito una risposta vuota.');
            }
            return $data;
        }

        // ── Booking ───────────────────────────────────────────────────────────────

        public function get_web_bookable_resources(): array
        {
            return $this->get('/api/cloud/appointment-resources/web-bookable');
        }

        public function get_web_visible_products(): array
        {
            return $this->get('/api/cloud/products/web-visible');
        }

        public function get_available_slots(string $service_id, string $from, string $to, ?string $resource_id = null): array
        {
            $params = [
                'serviceId' => $service_id,
                'from'      => $from,
                'to'        => $to,
            ];
            if ($resource_id !== null) {
                $params['resourceId'] = $resource_id;
            }
            return $this->get('/api/cloud/appointments/slots', $params);
        }

        public function toggle_resource_web_bookable(string $resource_id, bool $value): void
        {
            $this->patch("/api/cloud/appointment-resources/{$resource_id}/web-bookable", ['value' => $value]);
        }

        public function toggle_product_web_visible(string $product_id, bool $value): void
        {
            $this->patch("/api/cloud/products/{$product_id}/web-visible", ['value' => $value]);
        }

        // ── Appointments ──────────────────────────────────────────────────────────

        public function create_appointment(array $data): array
        {
            return $this->post('/api/cloud/appointments', $data);
        }

        public function delete_appointment(string $appointment_id): void
        {
            $this->delete("/api/cloud/appointments/{$appointment_id}");
        }

        public function get_appointments_for_customer(string $customer_id): array
        {
            return $this->get("/api/cloud/appointments/by-customer/{$customer_id}");
        }

        public function get_appointments_by_subject_email(string $email): array
        {
            return $this->get('/api/cloud/appointments/by-subject-email/' . rawurlencode($email));
        }

        // ── Primitivi HTTP ─────────────────────────────────────────────────────────

        public function get(string $path, array $params = []): array
        {
            $url = PAZIENZA_SERVER_URL . $path;
            if (!empty($params)) {
                $url = add_query_arg($params, $url);
            }
            return $this->request('GET', $url);
        }

        public function post(string $path, array $body): array
        {
            return $this->request('POST', PAZIENZA_SERVER_URL . $path, $body);
        }

        public function put(string $path, array $body): array
        {
            return $this->request('PUT', PAZIENZA_SERVER_URL . $path, $body);
        }

        public function patch(string $path, array $body): array
        {
            return $this->request('PATCH', PAZIENZA_SERVER_URL . $path, $body);
        }

        public function delete(string $path): void
        {
            $this->request('DELETE', PAZIENZA_SERVER_URL . $path);
        }

        private function get_binary(string $path): string
        {
            $token = $this->get_valid_token();

            $response = wp_remote_get(PAZIENZA_SERVER_URL . $path, [
                'headers'   => ['Authorization' => 'Bearer ' . $token],
                'timeout'   => 30,
                'sslverify' => !(defined('PAZIENZA_DISABLE_SSL_VERIFY') && PAZIENZA_DISABLE_SSL_VERIFY === 'true'),
            ]);

            if (is_wp_error($response)) {
                throw new RuntimeException('Richiesta Pazienza fallita: ' . $response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            $status = wp_remote_retrieve_response_code($response);
            if ($status >= 400) {
                $data  = json_decode(wp_remote_retrieve_body($response), true);
                $error = is_array($data) ? ($data['error'] ?? $data['title'] ?? ('HTTP ' . $status)) : ('HTTP ' . $status);
                throw new RuntimeException('Errore API Pazienza: ' . $error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            return wp_remote_retrieve_body($response);
        }

        private function request(string $method, string $url, ?array $body = null): array
        {
            $token = $this->get_valid_token();

            $args = [
                'method'    => $method,
                'headers'   => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'timeout'   => 20,
                'sslverify' => !(defined('PAZIENZA_DISABLE_SSL_VERIFY') && PAZIENZA_DISABLE_SSL_VERIFY === 'true'),
            ];

            if ($body !== null) {
                $args['body'] = wp_json_encode($body);
            }

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                throw new RuntimeException('Richiesta Pazienza fallita: ' . $response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            $status = wp_remote_retrieve_response_code($response);

            if ($status === 204) {
                return [];
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);

            if ($status >= 400) {
                $error = $data['error'] ?? $data['title'] ?? ('HTTP ' . $status);
                throw new RuntimeException('Errore API Pazienza (' . $method . ' ' . $url . '): ' . $error); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }

            return $data ?? [];
        }

        private function get_valid_token(): string
        {
            if ($this->store->has_valid_token()) {
                return $this->store->get_access_token();
            }

            $refresh = $this->store->get_refresh_token();
            if (!empty($refresh)) {
                $this->oauth->refresh_token($refresh);
                if ($this->store->has_valid_token()) {
                    return $this->store->get_access_token();
                }
            }

            throw new RuntimeException(
                'Token Pazienza non disponibile. Riconfigura la connessione nelle impostazioni del plugin.'
            );
        }
    }
}
