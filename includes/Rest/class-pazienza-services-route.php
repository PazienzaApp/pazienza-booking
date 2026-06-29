<?php
defined('ABSPATH') || exit;

class Pazienza_Services_Route
{
    public function register(): void
    {
        register_rest_route('pazienza-booking/v1', '/services', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(): WP_REST_Response|WP_Error
    {
        try {
            $services = pazienza_booking_client()->get_web_visible_products();
            return new WP_REST_Response($services, 200);
        } catch (RuntimeException $e) {
            return new WP_Error('pazienza_api_error', $e->getMessage(), ['status' => 502]);
        }
    }
}
