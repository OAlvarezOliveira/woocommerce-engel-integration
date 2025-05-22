<?php
/*
Plugin Name: Engel Sync
Description: Plugin para sincronizar productos desde Nova Engel.
Version: 1.0
Author: Tu Nombre
*/

defined('ABSPATH') or die('No script kiddies please!');

require_once plugin_dir_path(__FILE__) . 'includes/class-engel-auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-engel-product-sync.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';

register_activation_hook(__FILE__, function () {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $products_table = $wpdb->prefix . "engel_products";
    $log_table = $wpdb->prefix . "engel_sync_log";

    $sql = "
        CREATE TABLE IF NOT EXISTS $products_table (
            item_id VARCHAR(255) PRIMARY KEY,
            nombre TEXT,
            descripcion_larga TEXT,
            ean VARCHAR(255),
            marca VARCHAR(255),
            linea VARCHAR(255),
            precio FLOAT,
            pvp FLOAT,
            stock INT,
            genero VARCHAR(255),
            familias TEXT,
            tags TEXT,
            ingredientes TEXT,
            peso FLOAT,
            ancho FLOAT,
            alto FLOAT,
            fondo FLOAT,
            pais_fabricacion VARCHAR(255),
            oferta BOOLEAN,
            fecha_actualizacion DATETIME
        ) $charset_collate;

        CREATE TABLE IF NOT EXISTS $log_table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            synced_at DATETIME,
            total INT,
            errores TEXT
        ) $charset_collate;
    ";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});
