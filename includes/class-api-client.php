<?php
// includes/class-api-client.php

class Engel_API_Client {
    private $token_option = 'engel_api_token';
    private $base_url = 'https://drop.novaengel.com/api';

    public function login($user, $password) {
        $response = wp_remote_post("{$this->base_url}/login", [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['user' => $user, 'password' => $password]),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'Error al conectar: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['token'])) {
            update_option($this->token_option, $body['token']);
            return true;
        }

        return new WP_Error('login_failed', 'Login incorrecto o respuesta inválida.');
    }

    public function get_products() {
        $token = get_option($this->token_option);
        if (!$token) return new WP_Error('no_token', 'Token no disponible.');

        $response = wp_remote_get("{$this->base_url}/products", [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body;
    }
}
