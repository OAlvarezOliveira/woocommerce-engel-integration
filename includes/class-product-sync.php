<?php

class Engel_Product_Sync {

    public static function sync_all_products($products) {
        global $wpdb;

        $errores = [];
        $total = 0;

        foreach ($products as $product) {
            $total++;
            $data = [
                'item_id'            => $product['item_id'] ?? '',
                'nombre'             => $product['nombre'] ?? '',
                'descripcion_larga'  => $product['descripcion_larga'] ?? '',
                'ean'                => $product['ean'] ?? '',
                'marca'              => $product['marca'] ?? '',
                'linea'              => $product['linea'] ?? '',
                'precio'             => $product['precio'] ?? 0,
                'pvp'                => $product['pvp'] ?? 0,
                'stock'              => $product['stock'] ?? 0,
                'genero'             => $product['genero'] ?? '',
                'familias'           => $product['familias'] ?? '',
                'tags'               => $product['tags'] ?? '',
                'ingredientes'       => $product['ingredientes'] ?? '',
                'peso'               => $product['peso'] ?? 0,
                'ancho'              => $product['ancho'] ?? 0,
                'alto'               => $product['alto'] ?? 0,
                'fondo'              => $product['fondo'] ?? 0,
                'pais_fabricacion'   => $product['pais_fabricacion'] ?? '',
                'oferta'             => $product['oferta'] ?? 0,
                'fecha_actualizacion'=> current_time('mysql', 1)
            ];

            $result = $wpdb->replace(
                $wpdb->prefix . 'engel_products',
                $data
            );

            if ($result === false) {
                $errores[] = $product['item_id'] ?? 'Unknown';
            }
        }

        $wpdb->insert(
            $wpdb->prefix . 'engel_sync_log',
            [
                'synced_at' => current_time('mysql', 1),
                'total'     => $total,
                'errores'   => json_encode($errores)
            ]
        );
    }

    public static function update_stock_only($stock_data) {
        global $wpdb;

        foreach ($stock_data as $stock) {
            $wpdb->update(
                $wpdb->prefix . 'engel_products',
                [
                    'stock' => $stock['stock'],
                    'fecha_actualizacion' => current_time('mysql', 1)
                ],
                ['item_id' => $stock['item_id']]
            );
        }
    }
}
