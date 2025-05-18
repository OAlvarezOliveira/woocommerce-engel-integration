<?php
if (!defined('ABSPATH')) exit;

trait Engel_WC_Sync_Trait {

    public function sync_all_products_to_wc(array $products) {
        foreach ($products as $product) {
            if ($this->is_product_profitable($product)) {
                $this->sync_product_to_wc($product);
            }
        }
    }

    public function sync_stock_only_to_wc(array $products) {
        foreach ($products as $product) {
            $this->sync_product_to_wc($product, true);
        }
    }

    private function sync_product_to_wc(array $product_data, $only_stock = false) {
        $sku = $product_data['sku'] ?? ($product_data['id'] ?? uniqid('engel_'));

        $product_id = wc_get_product_id_by_sku($sku);
        if ($product_id) {
            $product = wc_get_product($product_id);
        } else {
            $product = new WC_Product_Simple();
            $product->set_sku($sku);
        }

        if (!$only_stock) {
            $product->set_name($this->clean_text($product_data['name'] ?? ''));
            $price = isset($product_data['price']) ? floatval($product_data['price']) : 0;
            $product->set_price($price);
            $product->set_regular_price($price);
            $product->set_description($this->clean_text($product_data['description'] ?? ''));
        }

        $stock = isset($product_data['stock']) ? intval($product_data['stock']) : 0;
        $product->set_stock_quantity($stock);
        $product->set_manage_stock(true);
        $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
        $product->save();

        $this->log("Producto sincronizado SKU: $sku");
    }

    private function clean_text(string $text) {
        return wp_strip_all_tags($text);
    }

    public function is_product_profitable(array $product_data): bool {
        return (isset($product_data['price']) && $product_data['price'] > 50);
    }

    private function log(string $message) {
        if (function_exists('engel_log')) {
            engel_log($message);
        } else {
            error_log('[Engel Sync] ' . $message);
        }
    }
}
