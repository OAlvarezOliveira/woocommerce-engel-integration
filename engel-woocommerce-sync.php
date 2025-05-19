<?php
/*
Plugin Name: Engel WooCommerce Sync
Description: Sincroniza productos de Nova Engel con WooCommerce, login/logout y descarga CSV.
Version: 1.4
Author: OAlvarezOliveira
*/

if (!defined('ABSPATH')) exit;

$base = plugin_dir_path(__FILE__);

// Carga traits
require_once $base . 'includes/trait-engel-connection.php';
require_once $base . 'includes/trait-engel-wc-sync.php';

// Carga clase para exportación background
require_once $base . 'includes/class-engel-export-background.php';

// ** NO INCLUIR admin-page.php aquí directamente **

// Añadir página de configuración simple
add_action('admin_menu', function () {
    add_options_page('Engel Sync Config', 'Engel Sync Config', 'manage_options', 'engel-sync-settings', 'engel_sync_settings_page');
});

// Función para mostrar página configuración
function engel_sync_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configuración de Engel Sync</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('engel_sync_settings');
            do_settings_sections('engel_sync_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function () {
    register_setting('engel_sync_settings', 'engel_elements_per_page');
    register_setting('engel_sync_settings', 'engel_max_pages');

    add_settings_section('engel_sync_section', 'Paginación para exportación CSV', null, 'engel_sync_settings');

    add_settings_field('engel_elements_per_page', 'Productos por página', function () {
        $value = get_option('engel_elements_per_page', 100);
        echo "<input type='number' name='engel_elements_per_page' value='" . esc_attr($value) . "' min='1' max='500' />";
    }, 'engel_sync_settings', 'engel_sync_section');

    add_settings_field('engel_max_pages', 'Máximo de páginas', function () {
        $value = get_option('engel_max_pages', 200);
        echo "<input type='number' name='engel_max_pages' value='" . esc_attr($value) . "' min='1' max='1000' />";
    }, 'engel_sync_settings', 'engel_sync_section');
});

// Clase principal (igual que antes, la omito aquí para no alargar)
// [Mantén toda la clase Engel_Product_Sync tal cual la tienes]

// Instancia singleton
function engel_get_sync_instance() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Engel_Product_Sync();
    }
    return $instance;
}

// Agregar página principal en el menú admin
add_action('admin_menu', function () {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_admin_page');
});

// Función para mostrar contenido admin (incluye el archivo admin-page.php)
function engel_sync_admin_page() {
    include plugin_dir_path(__FILE__) . 'admin/admin-page.php';
}

// Encolar scripts para la página admin
add_action('admin_enqueue_scripts', function ($hook) {
    // El hook para la página es toplevel_page_engel-sync
    if ($hook !== 'toplevel_page_engel-sync') return;

    wp_enqueue_script(
        'engel-sync-admin',
        plugin_dir_url(__FILE__) . 'admin/engel-sync-admin.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('engel-sync-admin', 'engelSync', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('engel_stock_sync_nonce'),
    ]);
});

// Handler AJAX para sincronización stock por página
add_action('wp_ajax_engel_process_stock_sync_page', function () {
    check_ajax_referer('engel_stock_sync_nonce');

    $page = isset($_POST['page']) ? intval($_POST['page']) : 0;

    $sync = engel_get_sync_instance();

    $elements_per_page = intval(get_option('engel_elements_per_page', 100));

    try {
        $has_more = $sync->run_stock_sync_page($page, $elements_per_page);

        wp_send_json_success([
            'next_page' => $has_more ? $page + 1 : false
        ]);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

// Función global para logs
function engel_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Engel Sync] ' . $message);
    }
}
