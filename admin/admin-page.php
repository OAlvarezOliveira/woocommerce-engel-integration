<?php
// admin/admin-menu.php

add_action('admin_menu', 'engel_sync_admin_menu');

function engel_sync_admin_menu() {
    add_menu_page(
        'Engel Sync',             // Page title
        'Engel Sync',             // Menu title
        'manage_options',         // Capability
        'engel-sync',             // Menu slug
        'engel_sync_dashboard',   // Callback function
        'dashicons-update',       // Icon
        56                        // Position
    );
}

function engel_sync_dashboard() {
    global $wpdb;
    $log_table = $wpdb->prefix . 'engel_sync_log';

    $login_test_result = '';
    $token = '';

    // Procesar formulario de credenciales
    if (isset($_POST['engel_save_credentials'])) {
        check_admin_referer('engel_sync_credentials');
        $user = sanitize_text_field($_POST['engel_api_user']);
        $pass = sanitize_text_field($_POST['engel_api_password']);
        update_option('engel_api_user', $user);
        update_option('engel_api_password', $pass);

        // Intentar login para validación
        $api = new Engel_API_Client($user, $pass);
        $token = $api->login($user, $pass);
        if ($token && !is_wp_error($token)) {
            echo '<div class="updated"><p>Credenciales guardadas y validadas correctamente.</p></div>';
            update_option('engel_api_token', $token);
        } else {
            echo '<div class="notice notice-error"><p>Credenciales guardadas, pero falló la validación contra la API.</p></div>';
        }
    }

    // Mostrar token actual si existe y es válido
    $saved_token = get_option('engel_api_token', '');
    if (!empty($saved_token) && !is_wp_error($saved_token)) {
        echo '<div class="notice notice-success"><p><strong>Token actual:</strong> ' . esc_html($saved_token) . '</p></div>';
    } elseif (is_wp_error($saved_token)) {
        echo '<div class="notice notice-error"><p>Error al obtener el token: ' . esc_html($saved_token->get_error_message()) . '</p></div>';
    }

    // Procesar sincronización de productos
    if (isset($_POST['engel_sync_all_products'])) {
        $api = new Engel_API_Client();
        $products = $api->fetch_all_products();
        Engel_Product_Sync::sync_all_products($products);
        echo '<div class="updated"><p>Productos sincronizados correctamente.</p></div>';
    }

    // Procesar actualización de stock
    if (isset($_POST['engel_update_stock'])) {
        $api = new Engel_API_Client();
        $stock = $api->fetch_stock_updates();
        Engel_Product_Sync::update_stock_only($stock);
        echo '<div class="updated"><p>Stock actualizado correctamente.</p></div>';
    }

    // Procesar logout
    if (isset($_POST['engel_logout'])) {
        $token = get_option('engel_api_token', '');
        if (!empty($token) && !is_wp_error($token)) {
            $api = new Engel_API_Client();
            $api->logout($token);
            delete_option('engel_api_token');
            echo '<div class="updated"><p>Sesión cerrada correctamente.</p></div>';
        }
    }

    // Procesar borrado de historial
    if (isset($_POST['engel_clear_log']) && check_admin_referer('engel_clear_log_action')) {
        $wpdb->query("DELETE FROM $log_table");
        echo '<div class="updated"><p>Historial de sincronización borrado.</p></div>';
    }

    // Mostrar interfaz
    echo '<div class="wrap">';
    echo '<h1>Engel Sync</h1>';

    // Formulario de credenciales
    echo '<h2>Credenciales API</h2>';
    echo '<form method="post">';
    wp_nonce_field('engel_sync_credentials');
    echo '<table class="form-table"><tr><th scope="row">Usuario</th><td><input type="text" name="engel_api_user" value="' . esc_attr(get_option('engel_api_user', '')) . '" class="regular-text"></td></tr>';
    echo '<tr><th scope="row">Contraseña</th><td><input type="password" name="engel_api_password" value="' . esc_attr(get_option('engel_api_password', '')) . '" class="regular-text"></td></tr></table>';
    echo '<p><input type="submit" name="engel_save_credentials" class="button button-primary" value="Guardar Credenciales"></p>';
    echo '</form>';

    // Botones de sincronización
    echo '<h2>Sincronización</h2>';
    echo '<form method="post">';
    echo '<input type="submit" name="engel_sync_all_products" class="button button-primary" value="Sincronizar Productos"> ';
    echo '<input type="submit" name="engel_update_stock" class="button button-secondary" value="Actualizar Stock"> ';
    echo '<input type="submit" name="engel_logout" class="button" value="Cerrar Sesión">';
    echo '</form>';

    // Historial
    $logs = $wpdb->get_results("SELECT * FROM $log_table ORDER BY synced_at DESC LIMIT 50");
    echo '<h2>Historial de Sincronización</h2>';
    echo '<form method="post">';
    wp_nonce_field('engel_clear_log_action');
    echo '<input type="submit" name="engel_clear_log" class="button button-danger" value="Borrar Historial">';
    echo '</form>';

    if ($logs) {
        echo '<table class="widefat fixed striped"><thead><tr><th>Fecha</th><th>Total Productos</th><th>Errores</th></tr></thead><tbody>';
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log->synced_at) . '</td>';
            echo '<td>' . esc_html($log->total) . '</td>';
            echo '<td>' . (!empty($log->errores) ? esc_html($log->errores) : '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No hay registros de sincronización aún.</p>';
    }

    echo '</div>';
}
