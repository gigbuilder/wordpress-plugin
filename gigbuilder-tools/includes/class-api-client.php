<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gigbuilder_API_Client {

    /**
     * Send a request to the Domino jsontools agent.
     *
     * @param array $payload The JSON payload (must include 'action' key).
     * @return array|WP_Error Decoded JSON response or WP_Error on failure.
     */
    public static function request( $payload ) {
        $url = Gigbuilder_Settings::get_endpoint_url();

        if ( empty( $url ) ) {
            return new WP_Error(
                'gigbuilder_not_configured',
                'Gigbuilder Tools is not configured. Please set the Server URL and Database Path in Settings.'
            );
        }

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'gigbuilder_connection_error',
                'Could not connect to Gigbuilder CRM: ' . $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'gigbuilder_http_error',
                'CRM returned HTTP ' . $code
            );
        }

        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'gigbuilder_json_error',
                'Invalid JSON response from CRM'
            );
        }

        return $data;
    }

    /**
     * Check availability for a given date.
     *
     * @param string $date Date in MM/DD/YYYY format.
     * @return array|WP_Error
     */
    public static function check_availability( $date ) {
        return self::request( array(
            'action' => 'checkAvailability',
            'date'   => $date,
        ) );
    }

    /**
     * Submit a booking form.
     *
     * @param string $date    Date in MM/DD/YYYY format.
     * @param string $ip      Visitor IP address.
     * @param array  $answers Array of {name, value} answer objects.
     * @return array|WP_Error
     */
    public static function submit_booking( $date, $ip, $answers ) {
        return self::request( array(
            'action'  => 'submitBooking',
            'date'    => $date,
            'ip'      => $ip,
            'answers' => $answers,
        ) );
    }
}
