
<?php

class Engel_API_Client {
    private $base_url = 'https://drop.novaengel.com/api/';

    public function login($user, $pass) {
        $response = wp_remote_post($this->base_url . 'login', [
            'body' => json_encode(['Usuario' => $user, 'Password' => $pass]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['Token'])) {
            return $body['Token'];
        }

        return new WP_Error('login_failed', 'No se pudo obtener el token de la API.');
    }
}
