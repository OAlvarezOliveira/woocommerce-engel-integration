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

    $elements_per_page = get_option('engel_elements_per_page', 10);
    $max_pages = get_option('engel_max_pages', 5);
    $frequency = get_option('engel_sync_frequency', 'hourly');
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
                <tr>
                    <th scope="row"><label for="engel_sync_frequency">Frecuencia de sincronización automática</label></th>
                    <td>
                        <select name="engel_sync_frequency" id="engel_sync_frequency">
                            <option value="hourly" <?php selected($frequency, 'hourly'); ?>>Cada hora</option>
                            <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>>Dos veces al día</option>
                            <option value="daily" <?php selected($frequency, 'daily'); ?>>Una vez al día</option>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="submit" class="button button-primary">Guardar cambios</button>
            </p>
        </form>

        <hr>

        <h2>Sincronización Manual</h2>
        <form method="post">
            <?php wp_nonce_field('engel_sync_settings_nonce'); ?>
            <p>
                <button type="submit" name="start_manual_sync" class="button button-secondary">Iniciar sincronización ahora</button>
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
        $instance->log('Sincronización batch completada.', 'info');
    }
}
add_action('engel_sync_batch_process_hook', 'engel_sync_batch_process');

function engel_sync_start_batch() {
    if (!wp_next_scheduled('engel_sync_batch_process_hook')) {
        update_option('engel_sync_current_page', 0);
        wp_schedule_single_event(time(), 'engel_sync_batch_process_hook');
    }
}

// Función para programar o reprogramar evento cron
function engel_schedule_cron_event() {
    $frequency = get_option('engel_sync_frequency', 'hourly');
    if (!in_array($frequency, ['hourly', 'twicedaily', 'daily'])) {
        $frequency = 'hourly';
    }

    // Eliminar evento programado si existe para evitar duplicados
    $timestamp = wp_next_scheduled('engel_sync_batch_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'engel_sync_batch_event');
    }

    // Programar evento cron con la frecuencia actual
    wp_schedule_event(time(), $frequency, 'engel_sync_batch_event');
}
add_action('init', 'engel_schedule_cron_event');

// Acción programada automáticamente
add_action('engel_sync_batch_event', function () {
    engel_sync_start_batch();
    update_option('engel_last_sync_time', current_time('mysql'));
});

/* ---- HISTÓRICO DE SINCRONIZACIÓN EN DB ---- */

function engel_add_log_entry($type, $message, $count = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'engel_sync_log';

    $wpdb->insert($table, [
        'log_time'   => current_time('mysql'),
        'type'       => sanitize_text_field($type),
        'message'    => sanitize_text_field($message),
        'product_count' => is_null($count) ? null : intval($count),
    ]);
}

register_activation_hook(__FILE__, function () {
    global $wpdb;
    $table = $wpdb->prefix . 'engel_sync_log';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        log_time DATETIME NOT NULL,
        type VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        product_count INT NULL
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // Programar cron al activar el plugin
    engel_schedule_cron_event();
});

/* ---- LOGIN/LOGOUT AUTOMÁTICO (como tenías en tu código) ---- */

add_action('wp_login', function ($user_login, $user) {
    // código para login automático, si lo tienes
}, 10, 2);

add_action('wp_logout', function () {
    // código para logout automático, si lo tienes
});

/* ---- EXPORTAR A CSV MANUALMENTE ---- */

add_action('admin_post_engel_export_csv', function () {
    if (!current_user_can('manage_options')) {
        wp_die('No tienes permisos para realizar esta acción.');
    }
    $instance = engel_get_sync_instance();
    try {
        $file_path = $instance->export_products_to_csv();
        $filename = basename($file_path);

        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } catch (Exception $e) {
        wp_die('Error al exportar CSV: ' . $e->getMessage());
    }
});
