<?php
/*
Plugin Name: Engel Sync
Description: Sincroniza productos con el API de Nova Engel.
Version: 1.0
Author: OpenAI
*/

require_once plugin_dir_path(__FILE__) . 'includes/class-engel-api-client.php';

function engel_sync_init() {
    // Inicializar si es necesario
}
add_action('init', 'engel_sync_init');

require_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';
