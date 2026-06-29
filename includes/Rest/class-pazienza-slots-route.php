<?php
defined('ABSPATH') || exit;

class Pazienza_Slots_Route
{
    public function register(): void
    {
        register_rest_route('pazienza-booking/v1', '/slots', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
            'args'                => [
                'service_id'  => ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'from'        => ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'to'          => ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'resource_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $service_id  = $request->get_param('service_id');
        $from        = $request->get_param('from');
        $to          = $request->get_param('to');
        $resource_id = $request->get_param('resource_id') ?: null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return new WP_Error('invalid_date', __('Formato data non valido. Usa YYYY-MM-DD.', 'pazienza-booking'), ['status' => 400]);
        }

        try {
            $slots = pazienza_booking_client()->get_available_slots($service_id, $from, $to, $resource_id);
            return new WP_REST_Response($slots, 200);
        } catch (RuntimeException $e) {
            return new WP_Error('pazienza_api_error', $e->getMessage(), ['status' => 502]);
        }
    }
}
