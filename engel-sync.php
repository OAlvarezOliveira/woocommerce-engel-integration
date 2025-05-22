<?php
/**
 * Plugin Name: Engel Product Sync
 * Description: Sincroniza productos desde Nova Engel.
 * Version: 1.0
 * Author: Tu Nombre
 */

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-engel-product-sync.php';
require_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';

function engel_sync_menu() {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_admin_page');
}
add_action('admin_menu', 'engel_sync_menu');