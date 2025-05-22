<?php

class Engel_API_Client {
    public function login($username, $password) {
        $response = wp_remote_post('https://drop.novaengel.com/api/login', [
            'method' => 'POST',
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['Usuario' => $username, 'Password' => $password]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['Token'])) {
            return sanitize_text_field($body['Token']);
        } else {
            return new WP_Error('login_failed', 'Token no recibido');
        }
    }
}
