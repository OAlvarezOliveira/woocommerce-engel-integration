<?php
// trait-engel-connection.php

trait Engel_Connection_Trait {

    private $login_url = const LOGIN_URL = 'https://b2b.novaengel.com/api/login';
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
            'headers' => [
                'Content-Type' => 'application/json'
            ],
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

        if (empty($data['Token'])) {
            throw new Exception('No se recibió token en login');
        }

        $this->token = $data['Token'];
        update_option('engel_api_token', $this->token);
        $this->engel_log("Login exitoso. Token guardado.");
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

