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

// Carga página admin
require_once $base . 'admin/admin-page.php';

class Engel_Product_Sync {
    use Engel_Connection_Trait;
    use Engel_WC_Sync_Trait;

    public function __construct() {
        // Opcional: aumentar tiempo y memoria para evitar timeout
        set_time_limit(300);
        ini_set('memory_limit', '512M');
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
        $products = $this->get_all_products();
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
     * Obtiene todos los productos paginados, con límite configurable.
     *
     * @return array
     * @throws Exception
     */
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
                $this->log("Límite máximo de páginas ($max_pages) alcanzado.");
                break;
            }

            $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements_per_page}/es";

            $this->log("Solicitando página $page con $elements_per_page productos por página.");

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

    // NUEVO método para sincronizar solo una página
    public function sync_products_page(int $page = 0, int $elements_per_page = 50) {
        $token = $this->get_token();
        if (!$token) {
            $this->log('Token no disponible para sincronización por página.');
            return false;
        }

        $url = "https://drop.novaengel.com/api/products/paging/{$token}/{$page}/{$elements_per_page}/es";
        $this->log("Sincronizando página $page con $elements_per_page productos por página.");

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => "Bearer $token",
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->log('Error en request: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data)) {
            $this->log("No hay más productos o respuesta inválida en página $page.");
            return false;
        }

        $this->sync_all_products_to_wc($data);
        $this->log("Página $page sincronizada correctamente.");

        // Retornar si llenó la página (para saber si hay que seguir)
        return count($data) === $elements_per_page;
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

// Añade página de ajustes para configuración de paginación
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

// Página de configuración para elementos por página y páginas máximas
function engel_sync_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para acceder a esta página.');
    }

    if (isset($_POST['submit'])) {
        check_admin_referer('engel_sync_settings_nonce');

        $elements_per_page = max(1, intval($_POST['engel_elements_per_page']));
        $max_pages = max(1, intval($_POST['engel_max_pages']));

        update_option('engel_elements_per_page', $elements_per_page);
        update_option('engel_max_pages', $max_pages);

        echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada correctamente.</p></div>';
    }

    $elements_per_page = get_option('engel_elements_per_page', 10);
    $max_pages = get_option('engel_max_pages', 5);
    ?>
    <div class="wrap">
        <h1>Configuración de Paginación - Engel Sync</h1>
        <form method="post">
            <?php wp_nonce_field('engel_sync_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="engel_elements_per_page">Productos por página</label></th>
                    <td><input name="engel_elements_per_page" type="number" id="engel_elements_per_page" value="<?php echo esc_attr($elements_per_page); ?>" min="1" max="1000" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="engel_max_pages">Número máximo de páginas</label></th>
                    <td><input name="engel_max_pages" type="number" id="engel_max_pages" value="<?php echo esc_attr($max_pages); ?>" min="1" max="100" required></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="submit" class="button button-primary">Guardar cambios</button>
            </p>
        </form>
    </div>
    <?php
}

/* ---- SINCRONIZACIÓN BATCH POR WP-CRON ---- */

function engel_sync_batch_process() {
    $instance = engel_get_sync_instance();

    $page = (int) get_option('engel_sync_current_page', 0);
    $elements_per_page = (int) get_option('engel_elements_per_page', 50);

    $has_more = $instance->sync_products_page($page, $elements_per_page);

    if ($has_more) {
        update_option('engel_sync_current_page', $page + 1);
        wp_schedule_single_event(time() + 60, 'engel_sync_batch_process_hook');
    } else {
        delete_option('engel_sync_current_page');
        $instance->log('Sincronización batch completada.');
    }
}

add_action('engel_sync_batch_process_hook', 'engel_sync_batch_process');

function engel_sync_start_batch() {
    if (!wp_next_scheduled('engel_sync_batch_process_hook')) {
        update_option('engel_sync_current_page', 0);
        wp_schedule_single_event(time(), 'engel_sync_batch_process_hook');
    }
}
