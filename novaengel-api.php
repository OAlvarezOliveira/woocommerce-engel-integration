<?php
/*
Plugin Name: Engel Sync
Description: Sincroniza productos desde la API de NovaEngel automáticamente mediante cron.
Version: 1.1
Author: ChatGPT
*/

if (!defined('ABSPATH')) exit;

register_activation_hook(__FILE__, 'engel_cron_activate');
register_deactivation_hook(__FILE__, 'engel_cron_deactivate');

function engel_cron_activate() {
    if (!wp_next_scheduled('engel_daily_sync_event')) {
        wp_schedule_event(time(), 'daily', 'engel_daily_sync_event');
    }
}

function engel_cron_deactivate() {
    wp_clear_scheduled_hook('engel_daily_sync_event');
}

add_action('engel_daily_sync_event', 'engel_sync_products_cron');

function engel_get_token() {
    $user = get_option('engel_api_user');
    $password = get_option('engel_api_password');
    $response = wp_remote_post('https://drop.novaengel.com/api/login', [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode(['user' => $user, 'password' => $password]),
        'timeout' => 60
    ]);

    if (is_wp_error($response)) return false;
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['Token'] ?? false;
}

function engel_sync_products_cron() {
    if (!defined('DOING_CRON')) return;
    $token = engel_get_token();
    if (!$token) {
        error_log("Engel Sync: No se pudo obtener el token.");
        return;
    }

    $page = 0;
    $per_page = 100;
    $language = 'es';

    while (true) {
        $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$per_page}/{$language}";
        $response = wp_remote_get($url, ['timeout' => 120]);

        if (is_wp_error($response)) {
            error_log("Engel Sync: Error obteniendo página $page.");
            break;
        }

        $products = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($products) || count($products) === 0) break;

        global $wpdb;
        foreach ($products as $product) {
            $wpdb->replace("{$wpdb->prefix}engel_products", [
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
                'tags' => json_encode($product['Tags']),
                'ingredientes' => $product['Ingredientes'],
                'peso' => $product['Kgs'],
                'ancho' => $product['Ancho'],
                'alto' => $product['Alto'],
                'fondo' => $product['Fondo'],
                'pais_fabricacion' => $product['PaisFabricacion'],
                'oferta' => $product['EsOferta'] ? 1 : 0,
                'fecha_actualizacion' => current_time('mysql')
            ]);
        }

        if (count($products) < $per_page) break;
        $page++;
    }

    error_log("Engel Sync: Sincronización completa finalizada.");
}

add_action('admin_menu', function() {
    add_menu_page('NovaEngel API', 'NovaEngel API', 'manage_options', 'novaengel-api', 'engel_admin_page');
});

function engel_admin_page() {
    if (isset($_POST['engel_save_settings'])) {
        update_option('engel_api_user', sanitize_text_field($_POST['engel_api_user']));
        update_option('engel_api_password', sanitize_text_field($_POST['engel_api_password']));
        echo '<div class="updated"><p>Credenciales guardadas.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Configuración de NovaEngel</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">Usuario API</th>
                    <td><input type="text" name="engel_api_user" value="<?php echo esc_attr(get_option('engel_api_user')); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row">Contraseña API</th>
                    <td><input type="password" name="engel_api_password" value="<?php echo esc_attr(get_option('engel_api_password')); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="engel_save_settings" class="button-primary" value="Guardar Cambios" /></p>
        </form>
    </div>
    <?php
}
