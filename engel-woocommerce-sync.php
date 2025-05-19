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
        // Nada aquí, el token se gestiona en el trait
    }

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
        $products = $this->get_all_products(100, $language);
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($file_path, 'w');
        if (!$file) {
            throw new Exception('No se pudo crear archivo CSV');
        }

        // Encabezados
        $headers = [
            'ID', 'SKU', 'Nombre', 'Descripción corta', 'Descripción larga',
            'Precio', 'Stock', 'Marca', 'EAN', 'Categoría', 'IVA', 'Peso (kg)'
        ];
        fputcsv($file, $headers);

        foreach ($products as $p) {
            $row = [
                $p['Id'] ?? '',
                $p['ItemId'] ?? ($p['Id'] ?? ''),
                $p['Description'] ?? '',
                mb_substr(strip_tags($p['CompleteDescription'] ?? ''), 0, 120),
                $p['CompleteDescription'] ?? '',
                $p['Price'] ?? '',
                $p['Stock'] ?? '',
                $p['BrandName'] ?? '',
                isset($p['EANs']) ? implode(', ', $p['EANs']) : '',
                isset($p['Families']) ? implode(', ', $p['Families']) : '',
                $p['IVA'] ?? '',
                $p['Kgs'] ?? ''
            ];
            fputcsv($file, $row);
        }

        fclose($file);
        $this->log("CSV exportado: $file_path");

        return $file_path;
    }

    public function get_all_products($limit = 0, $language = 'es') {
        $token = $this->get_token();
        if (!$token) {
            throw new Exception('Token de autenticación no disponible');
        }

        $page = 0;
        $all_products = [];
        $elements = $limit > 0 ? $limit : 100; // elementos por página
        do {
            $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements}/{$language}";
            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                $this->log("Error al obtener productos: " . $response->get_error_message());
                break;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (!is_array($data) || empty($data)) {
                break;
            }

            $all_products = array_merge($all_products, $data);

            // Si la cantidad de productos obtenidos es menor que los pedidos, se acabaron
            if (count($data) < $elements) {
                break;
            }

            $page++;
        } while ($limit == 0 || count($all_products) < $limit);

        // Si se definió un límite, recortar la lista
        if ($limit > 0) {
            $all_products = array_slice($all_products, 0, $limit);
        }

        return $all_products;
    }

    private function log(string $message) {
        if (function_exists('engel_log')) {
            engel_log($message);
        } else {
            error_log('[Engel Sync] ' . $message);
        }
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
