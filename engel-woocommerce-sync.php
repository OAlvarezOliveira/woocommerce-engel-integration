<?php
/*
Plugin Name: Engel WooCommerce Sync
Description: Importa productos rentables y con alta rotación desde Nova Engel comparando con Amazon.
Version: 1.2
Author: TuNombre
*/

if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'engel_sync_menu');
function engel_sync_menu() {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_admin_page');
    add_options_page('Engel Ajustes', 'Engel Ajustes', 'manage_options', 'engel-sync-settings', 'engel_sync_settings_page');
}

function engel_sync_admin_page() {
    if (isset($_POST['sync_now'])) {
        $sync = new Engel_Product_Sync();
        $sync->sync_all_products();
        echo '<div class="updated"><p>Sincronización completada.</p></div>';
    }
    if (isset($_POST['sync_stock'])) {
        $sync = new Engel_Product_Sync();
        $sync->sync_stock_only(true);
        echo '<div class="updated"><p>Stock actualizado.</p></div>';
    }
    ?>
    <h1>Engel WooCommerce Sync</h1>
    <form method="post">
        <input type="submit" name="sync_now" value="Sincronizar catálogo" class="button button-primary" />
        <input type="submit" name="sync_stock" value="Actualizar stock y precio" class="button" />
    </form>
    <?php
}

function engel_sync_settings_page() {
    if (isset($_POST['save_settings'])) {
        update_option('engel_api_user', sanitize_text_field($_POST['engel_api_user']));
        update_option('engel_api_pass', sanitize_text_field($_POST['engel_api_pass']));
        update_option('engel_profit_threshold', floatval($_POST['engel_profit_threshold']));
        update_option('engel_country', sanitize_text_field($_POST['engel_country']));
        update_option('keepa_api_key', sanitize_text_field($_POST['keepa_api_key']));
        update_option('keepa_cache_hours', intval($_POST['keepa_cache_hours']));
        echo '<div class="updated"><p>Ajustes guardados.</p></div>';
    }
    ?>
    <h2>Ajustes de Engel Sync</h2>
    <form method="post">
        <label>Usuario Engel:
            <input name="engel_api_user" value="<?php echo esc_attr(get_option('engel_api_user')); ?>" />
        </label><br><br>
        <label>Contraseña Engel:
            <input type="password" name="engel_api_pass" value="<?php echo esc_attr(get_option('engel_api_pass')); ?>" />
        </label><br><br>
        <label>Rentabilidad mínima (€):
            <input name="engel_profit_threshold" value="<?php echo esc_attr(get_option('engel_profit_threshold', 5)); ?>" />
        </label><br><br>
        <label>País destino (ISO):
            <input name="engel_country" value="<?php echo esc_attr(get_option('engel_country', 'ES')); ?>" />
        </label><br><br>
        <label>Clave API Keepa:
            <input type="text" name="keepa_api_key" value="<?php echo esc_attr(get_option('keepa_api_key')); ?>" />
        </label><br><br>
        <label>Duración caché precios Keepa (horas):
            <input type="number" min="1" name="keepa_cache_hours" value="<?php echo esc_attr(get_option('keepa_cache_hours', 24)); ?>" />
        </label><br><br>
        <input type="submit" name="save_settings" class="button button-primary" value="Guardar ajustes" />
    </form>
    <?php
}

class Engel_API_Client {
    public function authenticate() {
        $token = get_transient('engel_api_token');
        if ($token) return $token;

        $user = get_option('engel_api_user');
        $pass = get_option('engel_api_pass');

        $response = wp_remote_post('https://drop.novaengel.com/api/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['user' => $user, 'password' => $pass])
        ]);

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['Token'])) {
            set_transient('engel_api_token', $body['Token'], 3600);
            return $body['Token'];
        }

        return false;
    }

    public function fetch_products($page = 0, $per_page = 100, $lang = 'es') {
        $token = $this->authenticate();
        if (!$token) return [];

        $url = "https://drop.novaengel.com/api/products/paging/$token/$page/$per_page/$lang";
        $response = wp_remote_get($url);
        return json_decode(wp_remote_retrieve_body($response), true);
    }
}

class Engel_Product_Sync {
    private $shipping_table;

    public function __construct() {
        $this->shipping_table = [
            'ES' => [0.5 => 4.35, 2 => 4.95, 5 => 6.30, 10 => 8.30, 15 => 10.35, 20 => 10.35],
            'FR' => [0.5 => 7.55, 2 => 7.55, 5 => 9.05, 10 => 13.80, 15 => 14.95, 20 => 21.90],
            'DE' => [0.5 => 7.55, 2 => 7.55, 5 => 9.05, 10 => 13.80, 15 => 14.95, 20 => 21.90],
            'IT' => [0.5 => 8.20, 2 => 8.20, 5 => 13.35, 10 => 15.50, 15 => 16.55, 20 => 18.20],
        ];
    }

    public function sync_all_products() {
        $api = new Engel_API_Client();
        $page = 0;
        $pais = get_option('engel_country', 'ES');

        do {
            $products = $api->fetch_products($page);
            if (!$products) break;

            foreach ($products as $p) {
                if ($this->should_import($p, $pais)) {
                    $this->import_product($p);
                }
            }
            $page++;
        } while (count($products) === 100);
    }

    public function sync_stock_only($update_price = false) {
        $api = new Engel_API_Client();
        $token = $api->authenticate();
        if (!$token) return;

        $url = "https://drop.novaengel.com/api/stock/update/$token";
        $response = wp_remote_get($url);
        $items = json_decode(wp_remote_retrieve_body($response), true);

        foreach ($items as $item) {
            $id = wc_get_product_id_by_sku($item['Id']);
            if (!$id) continue;

            $product = wc_get_product($id);
            $product->set_stock_quantity($item['Stock']);
            $product->set_manage_stock(true);
            if ($update_price) $product->set_regular_price($item['Price']);
            $product->save();
        }
    }

    private function calcular_envio($peso, $pais) {
        $tramos = $this->shipping_table[$pais] ?? [];
        foreach ($tramos as $limite => $precio) {
            if ($peso <= $limite) return $precio;
        }
        return end($tramos);
    }

    private function calcular_coste_total($precio, $peso, $pais) {
        $envio = $this->calcular_envio($peso, $pais);
        $tarifa = $precio <= 10 ? 0.08 : 0.15;
        return $precio + $envio + ($precio * $tarifa);
    }

    private function is_profitable($p, $pais) {
        $precio = $p['Price'];
        $peso = $p['Kgs'];
        $ean = $p['EANs'][0] ?? '';
        $amazon = (new Amazon_Price_Checker())->get_price_by_ean($ean);
        if (!$amazon) return false;

        $coste = $this->calcular_coste_total($precio, $peso, $pais);
        return ($amazon - $coste) >= get_option('engel_profit_threshold', 5);
    }

    private function should_import($product, $pais) {
        if ($product['Stock'] <= 0) return false;
        if (!($product['EsOferta'] || $product['Novedad'])) return false;
        return $this->is_profitable($product, $pais);
    }

    private function import_product($p) {
        $id = wc_get_product_id_by_sku($p['ItemId']);
        $product = $id ? wc_get_product($id) : new WC_Product_Simple();

        $product->set_name($p['Description']);
        $product->set_regular_price($p['Price']);
        $product->set_sku($p['ItemId']);
        $product->set_manage_stock(true);
        $product->set_stock_quantity($p['Stock']);
        $product->set_weight($p['Kgs']);
        $product->set_description($p['CompleteDescription'] ?? $p['Content']);
        $product->save();

        $this->asignar_categorias($p, $product);
        $this->importar_imagen_destacada($p['Id'], $product->get_id());
    }

    private function asignar_categorias($p, $product) {
        if (empty($p['Families'])) return;
        $ids = [];
        foreach ($p['Families'] as $cat) {
            $term = term_exists($cat, 'product_cat') ?: wp_insert_term($cat, 'product_cat');
            if (!is_wp_error($term)) $ids[] = $term['term_id'];
        }
        wp_set_object_terms($product->get_id(), $ids, 'product_cat');
    }

    private function importar_imagen_destacada($pid, $wid) {
        $token = get_transient('engel_api_token');
        $url = "https://drop.novaengel.com/api/products/image/{$token}/{$pid}";
        $tmp = download_url($url);
        if (is_wp_error($tmp)) return;
        $file = ['name' => "engel-{$pid}.jpg", 'tmp_name' => $tmp];
        $id = media_handle_sideload($file, $wid);
        if (!is_wp_error($id)) set_post_thumbnail($wid, $id);
    }
}

class Amazon_Price_Checker {
    private $api_key;
    private $cache_expiration;

    public function __construct() {
        $this->api_key = get_option('keepa_api_key');
        $hours = intval(get_option('keepa_cache_hours', 24));
        $this->cache_expiration = max(3600, $hours * 3600);
    }

    public function get_price_by_ean($ean) {
        if (empty($ean) || empty($this->api_key)) {
            return null;
        }

        $cache_key = 'keepa_price_' . sanitize_key($ean);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url = "https://api.keepa.com/product?key={$this->api_key}&domain=3&ean={$ean}";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['products'][0])) {
            return null;
        }

        $product = $data['products'][0];
        $buy_box_price = $product['buyBoxPrice'] ?? -1;

        if ($buy_box_price <= 0) {
            return null;
        }

        $price = $buy_box_price / 100;

        set_transient($cache_key, $price, $this->cache_expiration);

        return $price;
    }
}

add_filter('cron_schedules', function($schedules) {
    $schedules['every_15_min'] = ['interval' => 900, 'display' => 'Cada 15 minutos'];
    return $schedules;
});

register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('engel_sync_stock_cron')) {
        wp_schedule_event(time(), 'every_15_min', 'engel_sync_stock_cron');
    }
});

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('engel_sync_stock_cron');
});

add_action('engel_sync_stock_cron', function() {
    (new Engel_Product_Sync())->sync_stock_only(true);
});
