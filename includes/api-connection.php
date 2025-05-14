<?php
// Función para obtener productos paginados desde Engel API
function engel_api_get_products_paged($page = 1, $elements = 100, $language = 'es') {
    $token = get_option('engel_api_token');
    if (!$token) {
        return false;
    }

    $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements}/{$language}";
    $response = wp_remote_get($url, ['timeout' => 30]);

    if (is_wp_error($response)) {
        error_log('Engel Sync API error: ' . $response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code !== 200) {
        error_log('Engel Sync API HTTP code: ' . $code);
        return false;
    }

    $data = json_decode($body, true);
    return is_array($data) ? $data : false;
}

// Función para importar/actualizar un producto en WooCommerce
function engel_import_product_to_woocommerce($product_data) {
    if (!class_exists('WC_Product')) {
        error_log('Engel Sync: WooCommerce no activo');
        return;
    }

    $sku = $product_data['SKU'] ?? null;
    if (!$sku) {
        return;
    }

    $product_id = wc_get_product_id_by_sku($sku);

    if ($product_id) {
        $product = wc_get_product($product_id);
    } else {
        $product = new WC_Product_Simple();
        $product->set_sku($sku);
    }

    $product->set_name($product_data['Name'] ?? 'Sin nombre');
    $product->set_price($product_data['Price'] ?? 0);
    $product->set_regular_price($product_data['Price'] ?? 0);

    $product->save();
}

// Callback para ejecutar la importación paginada desde cron
function engel_import_products_cron_callback() {
    $language = 'es';
    $elements_per_page = 100;

    $page = (int) get_option('engel_import_page', 1);

    $products = engel_api_get_products_paged($page, $elements_per_page, $language);

    if ($products === false || empty($products)) {
        delete_option('engel_import_page');
        error_log('Engel Sync: Importación finalizada.');
        return;
    }

    foreach ($products as $product_data) {
        engel_import_product_to_woocommerce($product_data);
    }

    if (count($products) < $elements_per_page) {
        delete_option('engel_import_page');
        error_log('Engel Sync: Última página importada, importación finalizada.');
    } else {
        update_option('engel_import_page', $page + 1);
        error_log('Engel Sync: Página ' . $page . ' importada, próxima: ' . ($page + 1));
    }
}

// Función para iniciar importación manualmente
function engel_start_import() {
    update_option('engel_import_page', 1);
    engel_import_products_cron_callback();
}
