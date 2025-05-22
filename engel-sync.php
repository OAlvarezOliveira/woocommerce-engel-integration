
<?php
/*
Plugin Name: Engel Sync
Description: Plugin para sincronizar productos con API externa de Nova Engel.
Version: 1.0
Author: Tu Nombre
*/

define('ENGEL_SYNC_PATH', plugin_dir_path(__FILE__));

require_once ENGEL_SYNC_PATH . 'includes/class-engel-product-sync.php';
require_once ENGEL_SYNC_PATH . 'includes/class-engel-api-client.php';
require_once ENGEL_SYNC_PATH . 'admin/admin-page.php';
