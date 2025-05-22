
<?php

class Engel_API_Client {

    private $base_url = 'https://drop.novaengel.com/api';

    public function login($username, $password) {
        $response = wp_remote_post($this->base_url . '/login', [
            'body' => json_encode([
                'username' => $username,
                'password' => $password
            ]),
            'headers' => ['Content-Type' => 'application/json']
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['Token'] ?? new WP_Error('no_token', 'No token received');
    }
}
