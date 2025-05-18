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

// Clase principal que usa traits
class Engel_Product_Sync {
    use Engel_Connection_Trait;
    use Engel_WC_Sync_Trait;

    public function __construct() {
        $this->load_token();
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

    // Exporta productos a CSV y devuelve el path absoluto del archivo creado
    public function export_products_to_csv($filename = 'engel_products.csv', $language = 'es') {
        $products = $this->get_all_products(100, $language);
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/' . $filename;

        $file = fopen($file_path, 'w');
        if (!$file) {
            throw new Exception('No se pudo crear archivo CSV');
        }

        // Cabeceras CSV (ajusta según tus datos)
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

// Página admin con formularios y botones
function engel_sync_admin_page() {
    $sync = engel_get_sync_instance();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('engel_sync_action', 'engel_sync_nonce')) {

        try {
            if (isset($_POST['login'])) {
                $user = sanitize_text_field($_POST['engel_user']);
                $pass = sanitize_text_field($_POST['engel_password']);
                $sync->engel_login($user, $pass);
                echo '<div class="notice notice-success"><p>Login exitoso.</p></div>';
            }
            if (isset($_POST['logout'])) {
                $sync->engel_logout();
                echo '<div class="notice notice-success"><p>Logout exitoso.</p></div>';
            }
            if (isset($_POST['download_csv'])) {
                $file_path = $sync->export_products_to_csv();
                $url = wp_upload_dir()['baseurl'] . '/' . basename($file_path);
                echo '<div class="notice notice-success"><p>CSV generado. <a href="' . esc_url($url) . '" target="_blank">Descargar CSV</a></p></div>';
            }
            if (isset($_POST['sync_now'])) {
                $sync->run_full_sync();
                echo '<div class="notice notice-success"><p>Sincronización completa ejecutada.</p></div>';
            }
            if (isset($_POST['sync_stock'])) {
                $sync->run_stock_sync();
                echo '<div class="notice notice-success"><p>Sincronización de stock ejecutada.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    // Cargar token guardado para mostrar estado
    $token = get_option('engel_api_token', '');
    ?>

    <div class="wrap">
        <h1>Engel WooCommerce Sync</h1>
        <form method="post">
            <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>

            <h2>Login a Nova Engel</h2>
            <label>Usuario: <input type="text" name="engel_user" required></label><br>
            <label>Contraseña: <input type="password" name="engel_password" required></label><br>
            <button type="submit" name="login" class="button button-primary">Login</button>

            <h2>Acciones</h2>
            <p>Token actual: <strong><?php echo $token ? esc_html($token) : 'No autenticado'; ?></strong></p>

            <button type="submit" name="logout" class="button button-secondary">Logout</button><br><br>

            <button type="submit" name="download_csv" class="button">Descargar CSV Productos</button><br><br>

            <button type="submit" name="sync_now" class="button button-primary">Sincronizar todos los productos</button><br><br>

            <button type="submit" name="sync_stock" class="button button-secondary">Actualizar solo stock</button>
        </form>
    </div>

    <?php
}

