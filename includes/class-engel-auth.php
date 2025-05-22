<?php

class Engel_Auth {
    const TOKEN_OPTION_NAME = 'engel_sync_token';

    public static function login($username, $password) {
        $response = wp_remote_post('https://drop.novaengel.com/api/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['Usuario' => $username, 'Password' => $password])
        ]);

        if (is_wp_error($response)) return false;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['Token'])) {
            update_option(self::TOKEN_OPTION_NAME, $body['Token']);
            return true;
        }

        return false;
    }

    public static function logout() {
        delete_option(self::TOKEN_OPTION_NAME);
    }

    public static function get_token() {
        return get_option(self::TOKEN_OPTION_NAME);
    }
}
