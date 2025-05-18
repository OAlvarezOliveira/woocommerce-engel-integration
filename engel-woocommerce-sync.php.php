<?php
/*
Plugin Name: Engel WooCommerce Sync
Description: Sincroniza productos de Nova Engel con WooCommerce, login/logout y descarga CSV.
Version: 1.1
Author: OAlvarezOliveira
*/

if (!defined('ABSPATH')) exit;

$base = plugin_dir_path(__FILE__);

// Carga traits
require_once $base . 'includes/trait-engel-connection.php';
require_once $base . 'includes/trait-engel-wc-sync.php';

// Carga página admin
require_once $base . 'admin/admin-page.php';

// Clase principal que usa traits
class Engel_Product_Sync {
    use Engel_Connection_Trait;
    use Engel_WC_Sync_Trait;

    public function __construct() {
        // No cargamos token en constructor para evitar errores
        // Se carga cuando se llama a get_token()
    }

    public function run_full_sync() {
        $products = $this->get_all_products();
        $this->sync_all_products_to_wc($products);
        $this->engel_log('Sincronización completa finalizada.');
    }

    public function run_stock_sync() {
        $products = $this->get_all_products();
        $this->sync_stock_only_to_wc($products);
        $this->engel_log('Sincronización de stock finalizada.');
    }

    public function export_products_to_csv($filename = 'engel_products.csv', $language = 'es') {
        $products = $this->get_all_products(100, $language);
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($file_path, 'w');
        if (!$file) {
            throw new Exception('No se pudo crear archivo CSV');
        }

        $headers = ['ID', 'SKU', 'Nombre', 'Descripción', 'Precio', 'Stock'];
        fputcsv($file, $headers);

        foreach ($products as $p) {
            $row = [
                $p['id'] ?? '',
                $p['sku'] ?? ($p['id'] ?? ''),
                $p['name'] ?? '',
                $p['description'] ?? '',
                $p['price'] ?? '',
                $p['stock'] ?? '',
            ];
            fputcsv($file, $row);
        }

        fclose($file);
        $this->engel_log("CSV exportado: $file_path");

        return $file_path;
    }

    /**
     * Obtiene todos los productos paginados desde la API Engel.
     *
     * @param int $elements Número de elementos por página (default 100)
     * @param string $language Idioma para la API (default 'es')
     * @return array Lista de productos
     */
    public function get_all_products($elements = 100, $language = 'es') {
        $token = $this->get_token();
        if (!$token) {
            throw new Exception('No autenticado. Por favor, haga login primero.');
        }

        $products = [];
        $page = 0;

        do {
            $url = "https://b2b.novaengel.com/api/products/paging/$token/$page/$elements/$language";
            $response = wp_remote_get($url, ['timeout' => 20]);

            if (is_wp_error($response)) {
                throw new Exception('Error al obtener productos: ' . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code !== 200) {
                throw new Exception("Error HTTP $code al obtener productos");
            }

            $data = json_decode($body, true);
            if (!is_array($data)) {
                throw new Exception('Respuesta no válida al obtener productos.');
            }

            $count = count($data);
            $products = array_merge($products, $data);
            $page++;
        } while ($count === $elements);

        return $products;
    }
}

// Función global para logs centralizados
function engel_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Engel Sync] ' . $message);
    }
}

// Instancia del plugin para usar en acciones
function engel_get_sync_instance() {
    static $instance = null;
    if ($instance === null) {
        $instance = new Engel_Product_Sync();
    }
    return $instance;
}

// Añade menú en admin
add_action('admin_menu', function () {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_admin_page');
});
