<?php
/*
Plugin Name: Engel WooCommerce Sync Version: 1.5
Description: Sincroniza productos de Nova Engel con WooCommerce, login/logout y descarga CSV.
Version: 1.5
Author: OAlvarezOliveira
*/

if (!defined('ABSPATH')) exit;

$base = plugin_dir_path(__FILE__);

// Carga traits
require_once $base . 'includes/trait-engel-connection.php';
require_once $base . 'includes/trait-engel-wc-sync.php';
require_once $base . 'includes/class-engel-export-background.php';

// Carga página admin
require_once $base . 'admin/admin-page.php';

class Engel_Product_Sync {
    use Engel_Connection_Trait;
    use Engel_WC_Sync_Trait;

    public function __construct() {
        set_time_limit(300);
        ini_set('memory_limit', '512M');
    }

    public function run_full_sync() {
        $products = $this->get_all_products();
        $this->sync_all_products_to_wc($products);
        $this->log('Sincronización completa finalizada.', 'info', count($products));
    }

    public function run_stock_sync() {
        $products = $this->get_all_products();
        $this->sync_stock_only_to_wc($products);
        $this->log('Sincronización de stock finalizada.', 'info', count($products));
    }

    public function export_products_to_csv($filename = 'engel_products.csv', $language = 'es') {
        $products = $this->get_all_products();
        $this->log("Exportando " . count($products) . " productos a CSV", 'info', count($products));

        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($file_path, 'w');
        if (!$file) {
            throw new Exception('No se pudo crear archivo CSV');
        }

        $headers = ['ID', 'SKU', 'Nombre', 'Descripción corta', 'Descripción larga', 'Precio', 'Stock', 'Marca', 'EAN', 'Categoría', 'IVA', 'Peso (kg)'];
        fputcsv($file, $headers);

        foreach ($products as $p) {
            $row = [
                $p['Id'] ?? '',
                $p['ItemId'] ?? '',
                $p['Description'] ?? '',
                $p['CompleteDescription'] ?? '',
                $p['Price'] ?? '',
                $p['Stock'] ?? '',
                $p['BrandName'] ?? '',
                isset($p['EANs'][0]) ? $p['EANs'][0] : '',
                isset($p['Families'][0]) ? $p['Families'][0] : '',
                $p['IVA'] ?? '',
                $p['Kgs'] ?? '',
            ];
            fputcsv($file, $row);
        }

        fclose($file);
        $this->log("CSV exportado: $file_path", 'info');

        return $file_path;
    }

    public function get_all_products() {
        $token = $this->get_token();
        if (!$token) {
            throw new Exception('Token de autenticación no disponible');
        }

        $elements_per_page = (int) get_option('engel_elements_per_page', 10);
        $max_pages = (int) get_option('engel_max_pages', 5);

        $all_products = [];
        $page = 0;

        do {
            if ($page >= $max_pages) {
                $this->log("Límite máximo de páginas ($max_pages) alcanzado.", 'info');
                break;
            }

            $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements_per_page}/es";

            $this->log("Solicitando página $page con $elements_per_page productos por página.", 'info');

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                $this->log("Error al obtener productos página $page: " . $response->get_error_message(), 'error');
                break;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!is_array($data)) {
                $this->log("Respuesta inválida en página $page: $body", 'error');
                break;
            }

            $count = count($data);
            $this->log("Página $page: recibidos $count productos", 'info');

            $all_products = array_merge($all_products, $data);
            $page++;

        } while ($count === $elements_per_page);

        $this->log("Total productos obtenidos: " . count($all_products), 'info');
        return $all_products;
    }

    public function sync_products_page(int $page = 0, int $elements_per_page = 50) {
        $token = $this->get_token();
        if (!$token) {
            $this->log('Token no disponible para sincronización por página.', 'error');
            return false;
        }

        $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements_per_page}/es";
        $this->log("Sincronizando página $page con $elements_per_page productos por página.", 'info');

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => "Bearer $token",
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->log('Error en request: ' . $response->get_error_message(), 'error');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data)) {
            $this->log("No hay más productos o respuesta inválida en página $page.", 'info');
            return false;
        }

        $this->sync_all_products_to_wc($data);
        $this->log("Página $page sincronizada correctamente.", 'info');

        return count($data) === $elements_per_page;
    }

    public function log(string $message, string $type = 'info', ?int $count = null) {
        if (function_exists('engel_log')) {
            engel_log($message, $type, $count);
        } else {
            error_log('[Engel Sync] ' . $message);
        }
    }
}

// --- Código fuera de la clase ---

add_action('wp_ajax_engel_get_export_status', function () {
    $in_progress = get_option('engel_export_in_progress', false);
    $page = (int) get_option('engel_export_page', 0);
    $max_pages = (int) get_option('engel_max_pages', 100);
    $filename = get_option('engel_export_filename', '');
    $file_path = wp_upload_dir()['basedir'] . '/' . $filename;
    $file_url = file_exists($file_path) ? wp_upload_dir()['baseurl'] . '/' . $filename : '';

    wp_send_json([
        'in_progress' => $in_progress,
        'page' => $page,
        'max_pages' => $max_pages,
        'file_url' => $file_url,
    ]);
});

if (!function_exists('engel_log')) {
    function engel_log($message, $type = 'info', $count = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[Engel Sync][$type] " . $message);
        }
        engel_add_log_entry($type, $message, $count);
    }
}

function engel_get_sync_instance() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Engel_Product_Sync();
    }
    return $instance;
}

add_action('admin_menu', function () {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_admin_page');
});

add_action('admin_menu', function () {
    add_submenu_page(
        'engel-sync',
        'Configuración de Paginación',
        'Configuración Paginación',
        'manage_options',
        'engel-sync-settings',
        'engel_sync_settings_page'
    );
});

function engel_sync_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para acceder a esta página.');
    }

    if (isset($_POST['submit'])) {
        check_admin_referer('engel_sync_settings_nonce');

        $elements_per_page = max(1, intval($_POST['engel_elements_per_page']));
        $max_pages = max(1, intval($_POST['engel_max_pages']));
        $frequency = $_POST['engel_sync_frequency'] ?? 'hourly';

        update_option('engel_elements_per_page', $elements_per_page);
        update_option('engel_max_pages', $max_pages);
        update_option('engel_sync_frequency', $frequency);

        // Reprogramar cron con la nueva frecuencia
        engel_schedule_cron_event();

        echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada correctamente.</p></div>';
    }

function engel_schedule_cron_event() {
    if (wp_next_scheduled('engel_sync_cron_event')) {
        wp_clear_scheduled_hook('engel_sync_cron_event');
    }

    $frequency = get_option('engel_sync_frequency', 'hourly');
    if (!in_array($frequency, ['hourly', 'twicedaily', 'daily'], true)) {
        $frequency = 'hourly';
    }

    wp_schedule_event(time(), $frequency, 'engel_sync_cron_event');
}

add_action('engel_sync_cron_event', function () {
    $sync = engel_get_sync_instance();
    $sync->run_stock_sync();
});

// Activar/desactivar cron al activar/desactivar plugin
register_activation_hook(__FILE__, 'engel_activate_plugin');
register_deactivation_hook(__FILE__, 'engel_deactivate_plugin');

function engel_activate_plugin() {
    engel_schedule_cron_event();
}

function engel_deactivate_plugin() {
    wp_clear_scheduled_hook('engel_sync_cron_event');
}
