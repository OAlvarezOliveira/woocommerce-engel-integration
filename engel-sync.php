<?php
/*
Plugin Name: Engel Sync
Description: Sincroniza productos desde la API de Nova Engel.
Version: 1.0
Author: Tu Nombre
*/

require_once plugin_dir_path(__FILE__) . 'includes/class-engel-api-client.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-engel-product-sync.php';
require_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';

register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $products_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}engel_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id VARCHAR(50),
        nombre VARCHAR(255),
        descripcion_larga TEXT,
        ean VARCHAR(50),
        marca VARCHAR(100),
        linea VARCHAR(100),
        precio DECIMAL(10,2),
        pvp DECIMAL(10,2),
        stock INT,
        genero VARCHAR(50),
        familias TEXT,
        tags TEXT,
        ingredientes TEXT,
        peso DECIMAL(10,2),
        ancho DECIMAL(10,2),
        alto DECIMAL(10,2),
        fondo DECIMAL(10,2),
        pais_fabricacion VARCHAR(100),
        oferta TINYINT(1),
        fecha_actualizacion DATETIME
    ) $charset_collate;";

    $log_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}engel_sync_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        synced_at DATETIME,
        total INT,
        errores TEXT
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($products_table);
    dbDelta($log_table);
});
