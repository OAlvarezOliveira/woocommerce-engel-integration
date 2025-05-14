<?php
function engel_api_call($endpoint, $params = []) {
    $user = get_option('engel_api_user');
    $pass = get_option('engel_api_pass');

    $url = 'https://www.novaengel.com/APIREST/V1/' . $endpoint;
    $args = [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode("$user:$pass"),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($params),
        'timeout' => 20,
    ];

    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        return ['error' => $response->get_error_message()];
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}
