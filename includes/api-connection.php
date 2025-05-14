<?php
// includes/api-connection.php
defined('ABSPATH') || exit;

/**
 * Login en API Engel y guardar token.
 */
function engel_api_login($user, $password) {
    $url = 'https://drop.novaengel.com/api/login';
    $body = json_encode([
        'user' => $user,
        'password' => $password,
    ]);
    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $body,
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code !== 200) {
        return false;
    }

    $data = json_decode($body, true);
    if (!empty($data['Token'])) {
        update_option('engel_api_token', $data['Token']);
        return $data['Token'];
    }

    return false;
}

/**
 * Logout en API Engel.
 */
function engel_api_logout() {
    $token = get_option('engel_api_token');
    if (!$token) {
        return false;
    }

    $url = "https://drop.novaengel.com/api/logout/{$token}";
    $response = wp_remote_post($url, ['timeout' => 15]);

    if (is_wp_error($response)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200 || $code === 204) {
        delete_option('engel_api_token');
        return true;
    }

    return false;
}

/**
 * Obtener productos disponibles.
 */
function engel_api_get_products($language = 'es') {
    $token = get_option('engel_api_token');
    if (!$token) {
        return false;
    }

    $url = "https://drop.novaengel.com/api/products/availables/{$token}/{$language}";
    $response = wp_remote_get($url, ['timeout' => 30]);

    if (is_wp_error($response)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code !== 200) {
        return false;
    }

    $data = json_decode($body, true);
    return is_array($data) ? $data : false;
}
