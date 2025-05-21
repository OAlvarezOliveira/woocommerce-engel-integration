<?php

class Engel_API_Client {

	private $base_url;
	private $api_key;

	public function __construct($base_url, $api_key) {
		$this->base_url = rtrim($base_url, '/');
		$this->api_key = $api_key;
	}

	private function request($endpoint) {
		$url = $this->base_url . '/' . ltrim($endpoint, '/');

		$response = wp_remote_get($url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json'
			],
			'timeout' => 15,
		]);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if ($code !== 200) {
			return new WP_Error('api_error', "Error de API: $code");
		}

		$data = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_Error('json_error', 'Error al decodificar JSON.');
		}

		return $data;
	}

	public function fetch_all_products() {
		return $this->request('/products');
	}

	public function login($username, $password) {
		$url = $this->base_url . '/login';

		$response = wp_remote_post($url, [
			'body' => json_encode([
				'username' => $username,
				'password' => $password,
			]),
			'headers' => [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json'
			],
			'timeout' => 15,
		]);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if ($code !== 200) {
			return new WP_Error('login_error', "Login fallido: $code");
		}

		$data = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_Error('json_error', 'Error al decodificar JSON.');
		}

		return isset($data['token']) ? $data['token'] : new WP_Error('token_error', 'Token no encontrado.');
	}
}
