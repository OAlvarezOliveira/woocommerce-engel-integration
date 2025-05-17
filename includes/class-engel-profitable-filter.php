<?php
class Engel_Product_Sync {

    private function is_product_profitable($engel_product) {
        $amazon_price = $this->get_amazon_price($engel_product['ean']);
        if (!$amazon_price) return false;

        $cost = $engel_product['price'];
        $fees = $amazon_price * 0.15; // comisión Amazon
        $profit_margin = $amazon_price - $fees - $cost;

        return $profit_margin > 5; // ganancia mínima deseada
    }

    private function get_amazon_price($ean) {
        // Simulación de consulta a Amazon
        // Idealmente, usar API oficial de Amazon SP-API
        return rand(10, 100); // precio aleatorio para demo
    }
}
