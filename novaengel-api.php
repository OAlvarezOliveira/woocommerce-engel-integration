
<?php
/*
Plugin Name: NovaEngel API Sync
Description: Plugin para conectarse a la API de NovaEngel, sincronizar productos y actualizar stock/precio diariamente.
Version: 1.0
Author: ChatGPT
*/

define('NOVAENGEL_API_URL', 'https://drop.novaengel.com/api');

function login_novaengel($user, $password) {
    $response = wp_remote_post(NOVAENGEL_API_URL . '/login', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode(['user' => $user, 'password' => $password])
    ]);

    if (is_wp_error($response)) return false;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['Token'])) {
        update_option('novaengel_token', $body['Token']);
        return true;
    }

    return false;
}

function logout_novaengel() {
    $token = get_option('novaengel_token');
    if (!$token) return;
    wp_remote_post(NOVAENGEL_API_URL . "/logout/$token");
    delete_option('novaengel_token');
}

function novaengel_api_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configuración NovaEngel API</h1>
        <form method="post">
            <input type="text" name="novaengel_user" placeholder="Usuario" value="<?php echo esc_attr(get_option('novaengel_user', '')); ?>" required />
            <input type="password" name="novaengel_password" placeholder="Contraseña" value="<?php echo esc_attr(get_option('novaengel_password', '')); ?>" required />
            <input type="submit" name="login_novaengel" value="Iniciar sesión" class="button button-primary" />
        </form>
        <form method="post" style="margin-top:10px;">
            <input type="submit" name="logout_novaengel" value="Cerrar sesión" class="button button-secondary" />
        </form>
        <form method="post" style="margin-top:10px;">
            <input type="submit" name="sync_novaengel_products" value="Sincronizar Productos" class="button button-primary" />
        </form>
        <form method="post" style="margin-top:10px;">
            <input type="submit" name="manual_stock_update" value="Actualizar Stock y Precio" class="button button-secondary" />
        </form>
    </div>
    <?php

    if (isset($_POST['login_novaengel'])) {
        update_option('novaengel_user', sanitize_text_field($_POST['novaengel_user']));
        update_option('novaengel_password', sanitize_text_field($_POST['novaengel_password']));
        $logged_in = login_novaengel($_POST['novaengel_user'], $_POST['novaengel_password']);
        echo $logged_in ? '<div class="updated"><p>Login correcto.</p></div>' : '<div class="error"><p>Error de login.</p></div>';
    }

    if (isset($_POST['logout_novaengel'])) {
        logout_novaengel();
        echo '<div class="updated"><p>Sesión cerrada.</p></div>';
    }

    if (isset($_POST['sync_novaengel_products'])) {
        novaengel_sync_products();
    }

    if (isset($_POST['manual_stock_update'])) {
        novaengel_update_stock_and_price();
        echo '<div class="updated"><p>Stock y precios actualizados manualmente.</p></div>';
    }
}

add_action('admin_menu', function () {
    add_menu_page('NovaEngel API', 'NovaEngel API', 'manage_options', 'novaengel-api', 'novaengel_api_settings_page');
});

function novaengel_sync_products() {
    global $wpdb;
    $table = $wpdb->prefix . 'engel_products';
    $token = get_option('novaengel_token');
    $page = 0;
    $per_page = 100;
    $language = 'es';

    if (!$token) return;

    do {
        $url = NOVAENGEL_API_URL . "/products/paging/$token/$page/$per_page/$language";
        $response = wp_remote_get($url);
        if (is_wp_error($response)) break;
        $products = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($products)) break;

        foreach ($products as $product) {
            $wpdb->replace($table, [
                'item_id' => $product['ItemId'],
                'nombre' => $product['Description'],
                'descripcion_larga' => $product['CompleteDescription'],
                'ean' => $product['EANs'][0] ?? '',
                'marca' => $product['BrandName'],
                'linea' => $product['LineaName'],
                'precio' => $product['Price'],
                'pvp' => $product['PVR'],
                'stock' => $product['Stock'],
                'genero' => $product['Gender'],
                'familias' => implode(', ', $product['Families']),
                'tags' => implode(', ', array_map(function($t) { return $t['GrupoTag'] . ':' . $t['Tag']; }, $product['Tags'])),
                'ingredientes' => $product['Ingredientes'],
                'peso' => $product['Kgs'],
                'ancho' => $product['Ancho'],
                'alto' => $product['Alto'],
                'fondo' => $product['Fondo'],
                'pais_fabricacion' => $product['PaisFabricacion'],
                'oferta' => $product['EsOferta'] ? 1 : 0,
                'fecha_actualizacion' => current_time('mysql', 1),
            ]);
        }
        $page++;
    } while (count($products) === $per_page);
}

function novaengel_update_stock_and_price() {
    global $wpdb;
    $table = $wpdb->prefix . 'engel_products';
    $token = get_option('novaengel_token');

    if (!$token) return;

    $url = NOVAENGEL_API_URL . "/stock/update/$token";
    $response = wp_remote_get($url);

    if (is_wp_error($response)) return;

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data)) return;

    foreach ($data as $item) {
        $wpdb->update($table, [
            'stock' => $item['Stock'],
            'precio' => $item['Price'],
            'fecha_actualizacion' => current_time('mysql', 1)
        ], ['item_id' => $item['Id']]);
    }
}

register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('novaengel_cron_stock_update')) {
        wp_schedule_event(time(), 'daily', 'novaengel_cron_stock_update');
    }
});
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('novaengel_cron_stock_update');
});

add_action('novaengel_cron_stock_update', 'novaengel_update_stock_and_price');
