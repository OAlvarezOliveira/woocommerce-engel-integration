<?php

function engel_sync_admin_page() {
    if (isset($_POST['engel_login'])) {
        $response = wp_remote_post('https://drop.novaengel.com/api/login', [
            'body' => [
                'usuario' => sanitize_text_field($_POST['engel_user']),
                'clave'   => sanitize_text_field($_POST['engel_pass'])
            ]
        ]);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['Token'])) {
            update_option('engel_api_token', $body['Token']);
            echo '<div class="notice notice-success">Login correcto.</div>';
        } else {
            echo '<div class="notice notice-error">Error en el login.</div>';
        }
    }

    if (isset($_POST['engel_logout'])) {
        delete_option('engel_api_token');
        echo '<div class="notice notice-success">Logout realizado.</div>';
    }

    if (isset($_POST['engel_sync_products'])) {
        $token = get_option('engel_api_token');
        if ($token) {
            $response = wp_remote_get('https://drop.novaengel.com/api/products', [
                'headers' => ['Authorization' => 'Bearer ' . $token]
            ]);
            $products = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($products)) {
                Engel_Product_Sync::sync_all_products($products);
                echo '<div class="notice notice-success">Productos sincronizados.</div>';
            } else {
                echo '<div class="notice notice-error">Error al sincronizar productos.</div>';
            }
        }
    }

    if (isset($_POST['engel_update_stock'])) {
        $token = get_option('engel_api_token');
        if ($token) {
            $response = wp_remote_get('https://drop.novaengel.com/api/stock', [
                'headers' => ['Authorization' => 'Bearer ' . $token]
            ]);
            $stock_data = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($stock_data)) {
                Engel_Product_Sync::update_stock_only($stock_data);
                echo '<div class="notice notice-success">Stock actualizado.</div>';
            } else {
                echo '<div class="notice notice-error">Error al actualizar stock.</div>';
            }
        }
    }

    $token = get_option('engel_api_token');
    ?>
    <div class="wrap">
        <h1>Engel Sync</h1>
        <form method="post">
            <?php if (!$token): ?>
                <input type="text" name="engel_user" placeholder="Usuario" required>
                <input type="password" name="engel_pass" placeholder="Contraseña" required>
                <button class="button button-primary" name="engel_login">Login</button>
            <?php else: ?>
                <p><strong>Token:</strong> <?= esc_html($token); ?></p>
                <button class="button" name="engel_sync_products">Sincronizar Productos</button>
                <button class="button" name="engel_update_stock">Actualizar Stock</button>
                <button class="button button-secondary" name="engel_logout">Logout</button>
            <?php endif; ?>
        </form>
    </div>
    <?php
}