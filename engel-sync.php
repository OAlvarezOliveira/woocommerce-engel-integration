<?php
/*
Plugin Name: Engel Sync
Description: Sincroniza productos de Nova Engel y los guarda en una tabla propia.
Version: 1.6
Author: OAlvarez
*/

if (!defined('ABSPATH')) exit;

// Definir constantes
define('ENGEL_SYNC_PATH', plugin_dir_path(__FILE__));

// Activación y creación de tabla
register_activation_hook(__FILE__, function() {
    global $wpdb;
    $table = $wpdb->prefix . 'engel_products';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(50),
        nombre VARCHAR(255),
        descripcion TEXT,
        pvp DECIMAL(10,2),
        stock INT,
        updated_at DATETIME
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

// Incluir archivos
require_once ENGEL_SYNC_PATH . 'admin/admin-page.php';
require_once ENGEL_SYNC_PATH . 'includes/class-api-client.php';
require_once ENGEL_SYNC_PATH . 'includes/class-product-sync.php';
