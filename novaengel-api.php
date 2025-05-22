<?php
/*
Plugin Name: Engel Sync
Description: Sincroniza productos desde la API de NovaEngel automáticamente mediante cron y guarda logs de sincronización.
Version: 1.3.2
Author: ChatGPT
*/

if (!defined('ABSPATH')) exit;

// Registrar log en la tabla
function log_engel_sync($args = []) {
    global $wpdb;

    $defaults = [
        'type'             => 'full',
        'total_items'      => 0,
        'success_items'    => 0,
        'error_items'      => 0,
        'error_log'        => null,
        'duration_seconds' => 0,
    ];

    $data = wp_parse_args($args, $defaults);

    $wpdb->insert(
        $wpdb->prefix . 'engel_sync_log',
        [
            'synced_at'        => current_time('mysql'),
            'type'             => $data['type'],
            'total_items'      => $data['total_items'],
            'success_items'    => $data['success_items'],
            'error_items'      => $data['error_items'],
            'error_log'        => $data['error_log'],
            'duration_seconds' => $data['duration_seconds'],
        ],
        ['%s', '%s', '%d', '%d', '%d', '%s', '%d']
    );
}

// Activar y desactivar cron
register_activation_hook(__FILE__, 'engel_cron_activate');
register_deactivation_hook(__FILE__, 'engel_cron_deactivate');

function engel_cron_activate() {
    if (!wp_next_scheduled('engel_sync_cron_event')) {
        wp_schedule_event(time(), 'daily', 'engel_sync_cron_event');
    }
}

function engel_cron_deactivate() {
    wp_clear_scheduled_hook('engel_sync_cron_event');
}

// Asociar el evento al callback
add_action('engel_sync_cron_event', 'engel_sync_products_cron');

// Obtener token de la API
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

// Función principal de sincronización
function engel_sync_products_cron() {
    if (!defined('DOING_CRON')) return;

    $start_time = microtime(true);
    $total = $success = $errors = 0;
    $error_log = [];

    $token = engel_get_token();
    if (!$token) {
        log_engel_sync([
            'error_items' => 1,
            'error_log' => 'No se pudo obtener token'
        ]);
        return;
    }

    $page = 0;
    $per_page = 100;
    $language = 'es';
    global $wpdb;

    while (true) {
        $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$per_page}/{$language}";
        $response = wp_remote_get($url, ['timeout' => 120]);

        if (is_wp_error($response)) {
            $error_log[] = "Error página $page";
            break;
        }

        $products = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($products) || count($products) === 0) break;

        foreach ($products as $product) {
            $total++;
            $result = $wpdb->replace("{$wpdb->prefix}engel_products", [
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

            if ($result === false) {
                $errors++;
                $error_log[] = $product['ItemId'] ?? 'ID desconocido';
            } else {
                $success++;
            }
        }

        if (count($products) < $per_page) break;
        $page++;
    }

    log_engel_sync([
        'type'             => 'full',
        'total_items'      => $total,
        'success_items'    => $success,
        'error_items'      => $errors,
        'error_log'        => $errors ? implode(', ', array_slice($error_log, 0, 50)) : null,
        'duration_seconds' => round(microtime(true) - $start_time),
    ]);
}

// Interfaz de admin
add_action('admin_menu', function() {
    add_menu_page('NovaEngel API', 'NovaEngel API', 'manage_options', 'novaengel-api', 'engel_admin_page');
    add_submenu_page('novaengel-api', 'Logs de sincronización', 'Logs', 'manage_options', 'novaengel-logs', 'engel_logs_page');
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

function engel_logs_page() {
    global $wpdb;
    $logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}engel_sync_log ORDER BY synced_at DESC LIMIT 50");
    ?>
    <div class="wrap">
        <h1>Logs de Sincronización</h1>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Total</th>
                    <th>Correctos</th>
                    <th>Errores</th>
                    <th>Duración (s)</th>
                    <th>Error Log</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->synced_at); ?></td>
                    <td><?php echo esc_html($log->type); ?></td>
                    <td><?php echo esc_html($log->total_items); ?></td>
                    <td><?php echo esc_html($log->success_items); ?></td>
                    <td><?php echo esc_html($log->error_items); ?></td>
                    <td><?php echo esc_html($log->duration_seconds); ?></td>
                    <td><?php echo esc_html(wp_trim_words($log->error_log, 20, '...')); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
