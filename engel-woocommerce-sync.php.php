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
        // Ya no llamamos a load_token() que no existe, usamos get_token()
        $this->token = $this->get_token();
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
        $this->log("CSV exportado: $file_path");

        return $file_path;
    }

    // Debes definir este método para que la sincronización funcione
    public function get_all_products($limit = 0, $language = 'es') {
        // Aquí debes implementar la lógica para obtener los productos de la API Nova Engel
        // Por ejemplo, llamar a una API con $this->token y devolver un array de productos

        // Por ahora, ejemplo estático:
        return [
            [
                'id' => 1,
                'sku' => 'SKU001',
                'name' => 'Producto de prueba',
                'description' => 'Descripción del producto',
                'price' => 100,
                'stock' => 10
            ],
        ];
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
