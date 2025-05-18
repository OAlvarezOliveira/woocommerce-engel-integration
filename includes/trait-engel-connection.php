<?php
if (!defined('ABSPATH')) exit;

trait Engel_Connection_Trait {

    private $login_url = 'https://b2b.novaengel.com/api/login';
    private $logout_url_pattern = 'https://b2b.novaengel.com/api/logout/%s';
    private $products_url_pattern = 'https://b2b.novaengel.com/api/products/paging/%s/%d/%d/%s';

    private $token;

    public function load_token() {
        $this->token = get_option('engel_api_token', '');
    }

    public function engel_login($user, $password) {
        $body = json_encode(['user' => $user, 'password' => $password]);
        $response = wp_remote_post($this->login_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $body,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Error en login: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new Exception('Login fallido con código HTTP ' . $code);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['token'])) {
            throw new Exception('No se recibió token en login');
        }

        $this->token = $data['token'];
        update_option('engel_api_token', $this->token);
        engel_log("Login exitoso. Token guardado.");
    }

    public function engel_logout() {
        if (!$this->token) {
            throw new Exception('No hay token para logout.');
        }
        $url = sprintf($this->logout_url_pattern, $this->token);
        $response = wp_remote_post($url, ['timeout' => 10]);

        if (is_wp_error($response)) {
            throw new Exception('Error en logout: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new Exception('Logout fallido con código HTTP ' . $code);
        }

        $this->token = '';
        update_option('engel_api_token', '');
        engel_log("Logout exitoso. Token eliminado.");
    }

    public function get_all_products($elements_per_page = 50, $language = 'es') {
        if (!$this->token) {
            throw new Exception('No autenticado. Token no disponible.');
        }

        $all_products = [];
        $page = 0;

        do {
            $url = sprintf($this->products_url_pattern, $this->token, $page, $elements_per_page, $language);
            $response = wp_remote_get($url, ['timeout' => 20]);

            if (is_wp_error($response)) {
                throw new Exception('Error descargando productos: ' . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                throw new Exception('Error HTTP descargando productos: ' . $code);
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($data)) {
                throw new Exception('Error al decodificar JSON productos.');
            }

            $count = count($data);
            $all_products = array_merge($all_products, $data);
            $page++;

        } while ($count === $elements_per_page);

        engel_log("Descargados " . count($all_products) . " productos de Engel.");
        return $all_products;
    }
}
