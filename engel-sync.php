<?php
/*
Plugin Name: Engel Sync
Description: Sincroniza productos de Nova Engel y los guarda en una tabla propia.
Version: 1.6
Author: OAlvarez
*/

if (!defined('ABSPATH')) exit;

// Constantes
define('ENGEL_SYNC_PATH', plugin_dir_path(__FILE__));

// Incluir archivos necesarios
require_once ENGEL_SYNC_PATH . 'admin/admin-page.php';
require_once ENGEL_SYNC_PATH . 'includes/class-api-client.php';
require_once ENGEL_SYNC_PATH . 'includes/class-product-sync.php';

// Crear tablas y programar cron al activar
register_activation_hook(__FILE__, function () {
    global $wpdb;

    $products_table = $wpdb->prefix . 'engel_products';
    $log_table = $wpdb->prefix . 'engel_sync_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql1 = "CREATE TABLE $products_table (
        item_id VARCHAR(100) PRIMARY KEY,
        nombre TEXT,
        descripcion_larga LONGTEXT,
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
        peso DECIMAL(10,3),
        ancho DECIMAL(10,2),
        alto DECIMAL(10,2),
        fondo DECIMAL(10,2),
        pais_fabricacion VARCHAR(100),
        oferta TINYINT(1),
        fecha_actualizacion DATETIME
    ) $charset_collate;";

    $sql2 = "CREATE TABLE $log_table (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        synced_at DATETIME,
        total INT,
        errores TEXT
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);

    // Programar cron si no está
    if (!wp_next_scheduled('engel_daily_stock_sync')) {
        wp_schedule_event(time(), 'daily', 'engel_daily_stock_sync');
    }
});

// Limpiar cron al desactivar
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('engel_daily_stock_sync');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'engel_daily_stock_sync');
    }
});
