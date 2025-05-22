<?php

class Engel_API_Client {
    private $api_url = 'https://drop.novaengel.com/api/';
    private $username;
    private $password;
    private $token;

    public function __construct($username, $password) {
        $this->username = $username;
        $this->password = $password;
        $this->token = $this->get_token();
    }

    private function get_token() {
        $response = wp_remote_post($this->api_url . 'login', [
            'body' => [
                'username' => $this->username,
                'password' => $this->password,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['token'])) {
            return $body['token'];
        }

        return new WP_Error('no_token', 'No se pudo obtener el token de autenticación');
    }

    private function request($endpoint) {
        if (is_wp_error($this->token)) {
            return $this->token;
        }

        $response = wp_remote_get($this->api_url . $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body;
    }

    public function fetch_all_products() {
        return $this->request('products');
    }

    public function fetch_stock_updates() {
        return $this->request('stock');
    }
}
