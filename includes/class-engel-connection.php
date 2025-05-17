<?php
class Engel_Product_Sync {

    public function fetch_engel_products() {
        // Conexión a Nova Engel
        $url = 'https://api.novaengel.com/destacados';
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            $this->log('Error al conectar con Nova Engel');
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            $this->log('Respuesta inválida de Nova Engel');
            return [];
        }

        return $data;
    }
}
