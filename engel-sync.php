<?php
/*
Plugin Name: Engel Sync
Description: Sincroniza productos, stock y pedidos con Nova Engel.
Version: 1.0
Author: O.Alvarez
*/

// Carga funciones del plugin
require_once plugin_dir_path(__FILE__) . 'includes/api-connection.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

// Hook para ejecutar la función en el cron
add_action('engel_import_products_cron_hook', 'engel_import_products_cron_callback');

// Programar cron al activar plugin
register_activation_hook(__FILE__, 'engel_schedule_import_cron');
function engel_schedule_import_cron() {
    if (!wp_next_scheduled('engel_import_products_cron_hook')) {
        wp_schedule_event(time(), 'hourly', 'engel_import_products_cron_hook');
    }
}

// Eliminar cron al desactivar plugin
register_deactivation_hook(__FILE__, 'engel_clear_import_cron');
function engel_clear_import_cron() {
    $timestamp = wp_next_scheduled('engel_import_products_cron_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'engel_import_products_cron_hook');
    }
}
