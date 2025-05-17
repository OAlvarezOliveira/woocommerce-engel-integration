<?php
trait Engel_Profitable_Filter_Trait {

    private function is_product_profitable($engel_product) {
        $amazon_price = $this->get_amazon_price($engel_product['ean']);
        if (!$amazon_price) return false;

        $cost = $engel_product['price'];
        $fees = $amazon_price * 0.15; // comisión Amazon
        $profit_margin = $amazon_price - $fees - $cost;

        return $profit_margin > 5; // ganancia mínima deseada
    }

    private function get_amazon_price($ean) {
        return rand(10, 100); // simulación para demo
    }
}
