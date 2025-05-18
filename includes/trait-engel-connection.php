<?php
if (!defined('ABSPATH')) exit;

trait Engel_Connection_Trait {

    private $login_url = 'https://b2b.novaengel.com/api/login';
    private $products_url_paged = 'https://b2b.novaengel.com/api/products/paging';
    private $token = null;

    /**
     * Realiza login en la API de Nova Engel y guarda el token en WP options.
     *
     * @param string $user Usuario de Engel
     * @param string $password Contraseña de Engel
     * @throws Exception Si falla la petición o no se recibe token
     */
    public function engel_login($user, $password) {
        $body = json_encode([
            'user' => $user,
            'password' => $password
        ]);

        $response = wp_remote_post($this->login_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $body,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            $this->engel_log('Error en login: ' . $response->get_error_message());
            throw new Exception('Error en login: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);

        $this->engel_log("Login HTTP code: $code");
        $this->engel_log("Respuesta login: $body_response");

        if ($code !== 200) {
            throw new Exception('Login fallido con código HTTP ' . $code);
        }

        $data = json_decode($body_response, true);

        if (empty($data['token'])) {
            throw new Exception('No se recibió token en login');
        }

        $this->token = $data['token'];
        update_option('engel_api_token', $this->token);
        $this->engel_log("Login exitoso. Token guardado.");
    }

    /**
     * Carga el token guardado en opciones WP.
     */
    public function load_token() {
        $this->token = get_option('engel_api_token', null);
    }

    /**
     * Obtiene el token guardado en opciones WP.
     *
     * @return string|null
     */
    public function get_token() {
        if ($this->token) {
            return $this->token;
        }

        $this->token = get_option('engel_api_token', null);
        return $this->token;
    }

    /**
     * Limpia el token guardado (logout local).
     */
    public function clear_token() {
        $this->token = null;
        delete_option('engel_api_token');
        $this->engel_log("Token eliminado (logout).");
    }

    /**
     * Obtiene todos los productos paginados desde la API.
     *
     * @param int $page Página actual, empieza en 0
     * @param int $elements Número de productos por página
     * @param string $language Código idioma (es, en, fr...)
     * @return array Lista de productos (array)
     * @throws Exception si no está autenticado o falla la petición
     */
    public function get_all_products($elements = 100, $language = 'es') {
        $token = $this->get_token();
        if (!$token) {
            throw new Exception('No autenticado. Por favor, inicia sesión.');
        }

        $all_products = [];
        $page = 0;

        do {
            $url = sprintf(
                '%s/%s/%d/%d/%s',
                $this->products_url_paged,
                $token,
                $page,
                $elements,
                $language
            );

            $response = wp_remote_get($url, ['timeout' => 30]);

            if (is_wp_error($response)) {
                $this->engel_log('Error al obtener productos: ' . $response->get_error_message());
                throw new Exception('Error al obtener productos: ' . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code !== 200) {
                throw new Exception("Error HTTP $code al obtener productos.");
            }

            $data = json_decode($body, true);

            if (!is_array($data)) {
                throw new Exception("Respuesta inválida al obtener productos.");
            }

            $count = count($data);
            $all_products = array_merge($all_products, $data);
            $this->engel_log("Página $page descargada, productos: $count");

            $page++;
        } while ($count === $elements);

        return $all_products;
    }

    /**
     * Función para registrar logs si WP_DEBUG está activo.
     *
     * @param string $message Mensaje a registrar
     */
    private function engel_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Engel Sync] ' . $message);
        }
    }
}
