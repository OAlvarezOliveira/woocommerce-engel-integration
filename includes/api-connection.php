<?php
// Función login
function engel_api_login($user, $password) {
    $url = 'https://drop.novaengel.com/api/login';
    $body = json_encode([
        'user' => $user,
        'password' => $password
    ]);
    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => $body,
        'timeout' => 30,
    ]);
    if (is_wp_error($response)) return false;
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code !== 200) return false;
    $data = json_decode($body, true);
    if (isset($data['Token'])) {
        update_option('engel_api_token', sanitize_text_field($data['Token']));
        return $data['Token'];
    }
    return false;
}

// Función logout
function engel_api_logout() {
    $token = get_option('engel_api_token');
    if (!$token) return false;
    $url = 'https://drop.novaengel.com/api/logout/' . $token;
    $response = wp_remote_post($url, ['timeout' => 30]);
    if (is_wp_error($response)) return false;
    $code = wp_remote_retrieve_response_code($response);
    if ($code === 200) {
        delete_option('engel_api_token');
        return true;
    }
    return false;
}

// Obtener productos paginados
function engel_api_get_products_paginated($page = 1, $elements = 100, $language = 'es') {
    $token = get_option('engel_api_token');
    if (!$token) return false;
    $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements}/{$language}";
    $response = wp_remote_get($url, ['timeout' => 60]);
    if (is_wp_error($response)) return false;
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code !== 200) return false;
    $data = json_decode($body, true);
    return is_array($data) ? $data : false;
}

// Función para importar un producto a WooCommerce
function engel_sync_import_single_product($product_data) {
    if (empty($product_data['Codigo'])) return;
    $product_id = wc_get_product_id_by_sku($product_data['Codigo']);
    if ($product_id) {
        $product = wc_get_product($product_id);
    } else {
        $product = new WC_Product_Simple();
        $product->set_sku($product_data['Codigo']);
    }
    $product->set_name($product_data['Descripcion'] ?? '');
    $price = floatval($product_data['PrecioVenta'] ?? 0);
    $product->set_price($price);
    $product->set_regular_price($price);
    $product->set_description($product_data['DescripcionCorta'] ?? '');
    $product->set_manage_stock(true);
    $product->set_stock_quantity(intval($product_data['Stock'] ?? 0));
    $product->set_status('publish');
    $product->save();
}

// Callback para WP-Cron que importa productos paginados
function engel_sync_import_products_callback() {
    $page = (int) get_option('engel_sync_last_page', 1);
    $elements = 100;
    $language = 'es';

    $products = engel_api_get_products_paginated($page, $elements, $language);
    if ($products === false || empty($products)) {
        update_option('engel_sync_last_page', 1); // reiniciar al final
        return;
    }

    foreach ($products as $product) {
        engel_sync_import_single_product($product);
    }

    update_option('engel_sync_last_page', $page + 1);
}
