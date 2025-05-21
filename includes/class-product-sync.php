<?php
// includes/class-product-sync.php

class Engel_Product_Sync {
    public static function sync_all_products($products) {
        global $wpdb;
        $table = $wpdb->prefix . 'engel_products';
        $log_table = $wpdb->prefix . 'engel_sync_log';

        $inserted = 0;
        $errors = [];

        foreach ($products as $product) {
            try {
                $ean = isset($product['EANs'][0]) ? $product['EANs'][0] : '';
                $familias = isset($product['Families']) ? json_encode($product['Families']) : '';
                $tags = isset($product['Tags']) ? json_encode($product['Tags']) : '';

                $data = [
                    'item_id' => $product['ItemId'] ?? '',
                    'nombre' => $product['Description'] ?? '',
                    'descripcion_larga' => $product['CompleteDescription'] ?? '',
                    'ean' => $ean,
                    'marca' => $product['BrandName'] ?? '',
                    'linea' => $product['LineaName'] ?? '',
                    'precio' => $product['Price'] ?? 0,
                    'pvp' => $product['PVR'] ?? 0,
                    'stock' => $product['Stock'] ?? 0,
                    'genero' => $product['Gender'] ?? '',
                    'familias' => $familias,
                    'tags' => $tags,
                    'ingredientes' => $product['Ingredientes'] ?? '',
                    'peso' => $product['Kgs'] ?? 0,
                    'ancho' => $product['Ancho'] ?? 0,
                    'alto' => $product['Alto'] ?? 0,
                    'fondo' => $product['Fondo'] ?? 0,
                    'pais_fabricacion' => $product['PaisFabricacion'] ?? '',
                    'oferta' => $product['EsOferta'] ? 1 : 0,
                    'fecha_actualizacion' => current_time('mysql'),
                ];

                $wpdb->replace($table, $data);
                $inserted++;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $wpdb->insert($log_table, [
            'synced_at' => current_time('mysql'),
            'total' => $inserted,
            'errores' => json_encode($errors)
        ]);
    }

    public static function update_stock_only($stock_data) {
        global $wpdb;
        $table = $wpdb->prefix . 'engel_products';

        foreach ($stock_data as $item) {
            if (!isset($item['ItemId'])) continue;

            $wpdb->update(
                $table,
                [
                    'stock' => $item['Stock'] ?? 0,
                    'precio' => $item['Price'] ?? 0,
                    'fecha_actualizacion' => current_time('mysql')
                ],
                ['item_id' => $item['ItemId']]
            );
        }
    }

    public static function schedule_cron() {
        if (!wp_next_scheduled('engel_daily_stock_sync')) {
            wp_schedule_event(time(), 'daily', 'engel_daily_stock_sync');
        }
    }

    public static function clear_cron() {
        $timestamp = wp_next_scheduled('engel_daily_stock_sync');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'engel_daily_stock_sync');
        }
    }
}

// Hook para ejecutar la actualización de stock
add_action('engel_daily_stock_sync', function () {
    $api = new Engel_API_Client();
    $stock = $api->fetch_stock_updates();
    Engel_Product_Sync::update_stock_only($stock);
});

// Activar y desactivar cron
register_activation_hook(__FILE__, ['Engel_Product_Sync', 'schedule_cron']);
register_deactivation_hook(__FILE__, ['Engel_Product_Sync', 'clear_cron']);
