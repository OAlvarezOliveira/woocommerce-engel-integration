<?php
/*
Plugin Name: Engel Sync
Description: Sincroniza productos, stock y pedidos con Nova Engel.
Version: 1.0
Author: O.Alvarez
*/

// Cargar funciones del plugin
require_once plugin_dir_path(__FILE__) . 'includes/api-connection.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

// Cron: programar evento al activar plugin
register_activation_hook(__FILE__, 'engel_sync_activation');
function engel_sync_activation() {
    if (! wp_next_scheduled('engel_sync_import_products')) {
        wp_schedule_event(time(), 'hourly', 'engel_sync_import_products');
    }
}

// Cron: eliminar evento al desactivar plugin
register_deactivation_hook(__FILE__, 'engel_sync_deactivation');
function engel_sync_deactivation() {
    wp_clear_scheduled_hook('engel_sync_import_products');
}

// Hook para evento cron
add_action('engel_sync_import_products', 'engel_sync_import_products_callback');
