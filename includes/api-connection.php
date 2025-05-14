<?php
/**
 * Función para hacer login en la API de Engel.
 * Guarda el token en opciones de WordPress.
 */
function engel_api_login($user, $pass) {
    $url = 'https://drop.novaengel.com/api/login';
    $args = [
        'method'  => 'POST',
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode([
            'user' => $user,
            'password' => $pass,
        ]),
        'timeout' => 20,
    ];

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (isset($data['Token']) && !empty($data['Token'])) {
        update_option('engel_api_token', $data['Token']);
        return $data['Token'];
    }

    return false;
}

/**
 * Función para hacer logout en la API de Engel.
 * Elimina el token guardado.
 */
function engel_api_logout() {
    $token = get_option('engel_api_token');
    if (!$token) {
        return false;
    }

    $url = "https://drop.novaengel.com/api/logout/{$token}";

    $args = [
        'method' => 'POST',
        'timeout' => 15,
    ];

    $response = wp_remote_post($url, $args);

    // Eliminamos el token localmente aunque falle la llamada (puedes ajustarlo)
    delete_option('engel_api_token');

    if (is_wp_error($response)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    return $code === 200 || $code === 204;
}

/**
 * Importar productos desde Engel paginados.
 * Este ejemplo importa solo la primera página (puedes ampliarlo).
 */
function engel_sync_import_products_callback($page = 1, $elements = 100, $language = 'es') {
    $token = get_option('engel_api_token');
    if (!$token) {
        error_log('Engel Sync: No hay token para importar productos.');
        return false;
    }

    $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements}/{$language}";

    $response = wp_remote_get($url, ['timeout' => 60]);

    if (is_wp_error($response)) {
        error_log('Engel Sync: Error en la petición de productos: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        error_log("Engel Sync: Código HTTP inesperado al importar productos: $code");
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $products = json_decode($body, true);

    if (!is_array($products)) {
        error_log('Engel Sync: Respuesta de productos no es un array.');
        return false;
    }

    // Aquí añadirías la lógica para insertar o actualizar productos en WooCommerce.
    // Por simplicidad, vamos a escribir un log con la cantidad recibida.

    $count = count($products);
    error_log("Engel Sync: Importados $count productos de la página $page.");

    // Ejemplo básico: solo para demostrar
    foreach ($products as $product) {
        // Aquí insertas/actualizas producto WooCommerce
        // Por ejemplo:
        // engel_sync_insert_or_update_product($product);
    }

    return true;
}
