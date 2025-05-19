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

// Carga página admin
require_once $base . 'admin/admin-page.php';

// Añadir página de configuración
add_action('admin_menu', function () {
    add_options_page('Engel Sync Config', 'Engel Sync Config', 'manage_options', 'engel-sync-settings', 'engel_sync_settings_page');
});

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

class Engel_Product_Sync {
    use Engel_Connection_Trait;
    use Engel_WC_Sync_Trait;

    public function __construct() {}

    public function run_full_sync() {
        $products = $this->get_all_products();
        $this->sync_all_products_to_wc($products);
        $this->log('Sincronización completa finalizada.');
    }

    public function run_stock_sync() {
        $products = $this->get_all_products();
        $this->sync_stock_only_to_wc($products);
        $this->log('Sincronización de stock finalizada.');
    }

    public function export_products_to_csv($filename = 'engel_products.csv', $language = 'es') {
        $elements_per_page = intval(get_option('engel_elements_per_page', 100));
        $max_pages = intval(get_option('engel_max_pages', 200));

        $products = $this->get_all_products($elements_per_page, $language, $max_pages);
        $this->log("Exportando " . count($products) . " productos a CSV");

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
        $this->log("CSV exportado: $file_path");

        return $file_path;
    }

    public function get_all_products($elements_per_page = 100, $language = 'es', $max_pages = 200) {
        $token = $this->get_token();
        if (!$token) {
            throw new Exception('Token de autenticación no disponible');
        }

        $all_products = [];
        $page = 0;

        do {
            $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements_per_page}/{$language}";

            $this->log("Requesting page $page, $elements_per_page productos");

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Accept' => 'application/json',
                ],
                'timeout' => 60,
            ]);

            if (is_wp_error($response)) {
                $this->log("Error al obtener productos página $page: " . $response->get_error_message());
                break;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!is_array($data)) {
                $this->log("Respuesta inválida en página $page: $body");
                break;
            }

            $count = count($data);
            $this->log("Página $page: recibidos $count productos");

            $all_products = array_merge($all_products, $data);
            $page++;

        } while ($count === $elements_per_page && $page < $max_pages);

        $this->log("Total productos obtenidos: " . count($all_products));

        return $all_products;
    }

    public function run_stock_sync_page(int $page, int $elements_per_page = 100): bool {
        $token = $this->get_token();
        if (!$token) {
            throw new Exception('Token de autenticación no disponible');
        }

        $language = 'es';
        $max_pages = intval(get_option('engel_max_pages', 200));

        $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements_per_page}/{$language}";

        $this->log("Sincronizando stock página $page ($elements_per_page productos)");

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => "Bearer $token",
                'Accept' => 'application/json',
            ],
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            $this->log("Error al obtener productos página $page: " . $response->get_error_message());
            throw new Exception("Error al obtener productos: " . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $products = json_decode($body, true);

        if (!is_array($products)) {
            $this->log("Respuesta inválida en página $page: $body");
            throw new Exception("Respuesta inválida de API");
        }

        $count = count($products);

        if ($count === 0) {
            return false;
        }

        $this->sync_stock_only_to_wc($products);

        $has_more = ($count === $elements_per_page) && ($page + 1 < $max_pages);

        $this->log("Sincronización stock página $page finalizada. Hay más páginas: " . ($has_more ? 'sí' : 'no'));

        return $has_more;
    }

    private function log(string $message) {
        if (function_exists('engel_log')) {
            engel_log($message);
        } else {
            error_log('[Engel Sync] ' . $message);
        }
    }
}

function engel_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Engel Sync] ' . $message);
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

add_action('wp_ajax_engel_stock_sync_async', function () {
    check_ajax_referer('engel_stock_sync_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permisos insuficientes');
    }

    $page = isset($_POST['page']) ? intval($_POST['page']) : 0;
    $elements_per_page = intval(get_option('engel_elements_per_page', 100));
    $max_pages = intval(get_option('engel_max_pages', 200));

    try {
        $sync = engel_get_sync_instance();
        $has_more = $sync->run_stock_sync_page($page, $elements_per_page);

        $progress = min(100, (($page + 1) / $max_pages) * 100);
        $finished = !$has_more;

        wp_send_json_success([
            'message' => "Página $page procesada correctamente.",
            'progress' => $progress,
            'finished' => $finished,
            'next_page' => $has_more ? $page + 1 : false,
        ]);
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Error en sincronización de stock: ' . $e->getMessage(),
        ]);
    }
});
