<?php
trait Engel_WC_Sync_Trait {

    public function sync_all_products() {
        $products = $this->fetch_engel_products();
        foreach ($products as $product) {
            if ($this->is_product_profitable($product)) {
                $this->sync_product_to_woocommerce($product);
            }
        }
    }

    public function sync_stock_only() {
        $products = $this->fetch_engel_products();
        foreach ($products as $product) {
            $this->sync_product_to_woocommerce($product, true);
        }
    }

    private function sync_product_to_woocommerce($product_data, $only_stock = false) {
        $product_id = wc_get_product_id_by_sku($product_data['sku']);

        if ($product_id) {
            $product = wc_get_product($product_id);
        } else {
            $product = new WC_Product_Simple();
            $product->set_sku($product_data['sku']);
        }

        if (!$only_stock) {
            $product->set_name($this->clean_text($product_data['name']));
            $product->set_price($product_data['price']);
            $product->set_regular_price($product_data['price']);
            $product->set_description($this->clean_text($product_data['description']));
        }

        $product->set_stock_quantity($product_data['stock']);
        $product->set_manage_stock(true);
        $product->set_stock_status('instock');
        $product->save();

        $this->log('Producto sincronizado: ' . $product_data['sku']);
    }

    private function clean_text($text) {
        return wp_strip_all_tags($text);
    }

    private function log($message) {
        error_log('[Engel Sync] ' . $message);
    }
}
