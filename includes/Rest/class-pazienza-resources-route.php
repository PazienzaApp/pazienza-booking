<?php
defined('ABSPATH') || exit;

class Pazienza_Resources_Route
{
    public function register(): void
    {
        register_rest_route('pazienza-booking/v1', '/resources', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
            'args'                => [
                'service_id' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Il filtro per service_id è intenzionalmente non applicato lato server:
            // tutte le risorse IsBookableFromWeb=true possono erogare qualsiasi servizio web-visible.
            $resources = pazienza_booking_client()->get_web_bookable_resources();
            return new WP_REST_Response($resources, 200);
        } catch (RuntimeException $e) {
            return new WP_Error('pazienza_api_error', $e->getMessage(), ['status' => 502]);
        }
    }
}
