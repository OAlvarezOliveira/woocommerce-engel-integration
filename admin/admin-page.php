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

    add_submenu_page(
        'engel-sync',
        'Sincronizar Productos',
        'Sincronizar Productos',
        'manage_options',
        'engel-sync-products',
        'engel_sync_products_callback'
    );

    add_submenu_page(
        'engel-sync',
        'Actualizar Stock',
        'Actualizar Stock',
        'manage_options',
        'engel-sync-stock',
        'engel_sync_stock_callback'
    );

    add_submenu_page(
        'engel-sync',
        'Historial de Sincronización',
        'Historial de Sincronización',
        'manage_options',
        'engel-sync-log',
        'engel_sync_log_callback'
    );
}

function engel_sync_dashboard() {
    echo '<div class="wrap"><h1>Engel Sync</h1><p>Usa las opciones del menú para sincronizar productos o actualizar stock.</p></div>';
}

function engel_sync_products_callback() {
    if (isset($_POST['engel_sync_all_products'])) {
        $api = new Engel_API_Client();
        $products = $api->fetch_all_products();
        Engel_Product_Sync::sync_all_products($products);
        echo '<div class="updated"><p>Productos sincronizados correctamente.</p></div>';
    }
    echo '<div class="wrap"><h1>Sincronizar Productos</h1><form method="post"><input type="submit" name="engel_sync_all_products" class="button button-primary" value="Sincronizar Ahora"></form></div>';
}

function engel_sync_stock_callback() {
    if (isset($_POST['engel_update_stock'])) {
        $api = new Engel_API_Client();
        $stock = $api->fetch_stock_updates();
        Engel_Product_Sync::update_stock_only($stock);
        echo '<div class="updated"><p>Stock actualizado correctamente.</p></div>';
    }
    echo '<div class="wrap"><h1>Actualizar Stock</h1><form method="post"><input type="submit" name="engel_update_stock" class="button button-secondary" value="Actualizar Stock Ahora"></form></div>';
}

function engel_sync_log_callback() {
    global $wpdb;
    $log_table = $wpdb->prefix . 'engel_sync_log';

    // Borrar historial si se solicitó
    if (isset($_POST['engel_clear_log']) && check_admin_referer('engel_clear_log_action')) {
        $wpdb->query("DELETE FROM $log_table");
        echo '<div class="updated"><p>Historial de sincronización borrado.</p></div>';
    }

    $logs = $wpdb->get_results("SELECT * FROM $log_table ORDER BY synced_at DESC LIMIT 50");

    echo '<div class="wrap"><h1>Historial de Sincronización</h1>';
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
