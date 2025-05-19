<?php
/*
Plugin Name: Engel WooCommerce Sync
Description: Sincroniza productos de Nova Engel con WooCommerce, login/logout y descarga CSV.
Version: 1.2
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

    /**
     * Obtiene todos los productos paginados, paginación automática.
     *
     * @param int $elements_per_page Número de elementos por página (por defecto 100).
     * @param string $language Idioma, por defecto 'es'.
     * @return array Array con todos los productos.
     * @throws Exception Si no hay token o error en la petición.
     */
    public function get_all_products($elements_per_page = 100, $language = 'es') {
        $token = $this->get_token();
        if (!$token) {
            throw new Exception('Token de autenticación no disponible');
        }

        $all_products = [];
        $page = 0;

        do {
            $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements_per_page}/{$language}";

            $this->log("Requesting page $page, $elements_per_page elementos");

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => "Bearer $token",
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
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

        } while ($count === $elements_per_page);

        $this->log("Total productos obtenidos: " . count($all_products));

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
