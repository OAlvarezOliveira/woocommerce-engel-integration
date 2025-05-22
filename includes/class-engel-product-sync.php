
<?php

class Engel_Product_Sync {

    public static function sync_all_products($products) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'engel_products';
        $synced_at = current_time('mysql');

        $inserted = 0;
        $errors = [];

        foreach ($products as $product) {
            $data = [
                'item_id' => $product['item_id'] ?? '',
                'nombre' => $product['nombre'] ?? '',
                'descripcion_larga' => $product['descripcion_larga'] ?? '',
                'ean' => $product['ean'] ?? '',
                'marca' => $product['marca'] ?? '',
                'linea' => $product['linea'] ?? '',
                'precio' => $product['precio'] ?? 0,
                'pvp' => $product['pvp'] ?? 0,
                'stock' => $product['stock'] ?? 0,
                'genero' => $product['genero'] ?? '',
                'familias' => $product['familias'] ?? '',
                'tags' => $product['tags'] ?? '',
                'ingredientes' => $product['ingredientes'] ?? '',
                'peso' => $product['peso'] ?? 0,
                'ancho' => $product['ancho'] ?? 0,
                'alto' => $product['alto'] ?? 0,
                'fondo' => $product['fondo'] ?? 0,
                'pais_fabricacion' => $product['pais_fabricacion'] ?? '',
                'oferta' => $product['oferta'] ?? 0,
                'fecha_actualizacion' => $synced_at,
            ];

            $format = [
                '%s','%s','%s','%s','%s','%s','%f','%f','%d','%s','%s','%s','%s','%f','%f','%f','%f','%s','%d','%s'
            ];

            $result = $wpdb->replace($table_name, $data, $format);

            if ($result === false) {
                $errors[] = $product['item_id'] ?? 'unknown';
            } else {
                $inserted++;
            }
        }

        $wpdb->insert(
            $wpdb->prefix . 'engel_sync_log',
            [
                'synced_at' => $synced_at,
                'total' => $inserted,
                'errores' => json_encode($errors),
            ],
            ['%s', '%d', '%s']
        );
    }

    public static function update_stock_only($stock_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'engel_products';

        foreach ($stock_data as $item) {
            if (!isset($item['item_id']) || !isset($item['stock'])) {
                continue;
            }

            $wpdb->update(
                $table_name,
                ['stock' => $item['stock']],
                ['item_id' => $item['item_id']],
                ['%d'],
                ['%s']
            );
        }
    }
}
