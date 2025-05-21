<?php

function engel_sync_dashboard() {
    $api_url = get_option('engel_api_url', '');
    $api_token = get_option('engel_api_token', '');

    if (isset($_POST['engel_sync_form'])) {
        $api_url = sanitize_text_field($_POST['engel_api_url']);
        $api_token = sanitize_text_field($_POST['engel_api_token']);

        update_option('engel_api_url', $api_url);
        update_option('engel_api_token', $api_token);

        echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>';
    }

    $api = new Engel_API_Client($api_url, $api_token);

    if (isset($_POST['sync_all_products'])) {
        $products = $api->fetch_all_products();
        if (!is_wp_error($products)) {
            Engel_Product_Sync::sync_all_products($products);
            echo '<div class="notice notice-success is-dismissible"><p>Productos sincronizados correctamente.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error al sincronizar productos: ' . esc_html($products->get_error_message()) . '</p></div>';
        }
    }

    if (isset($_POST['update_stock'])) {
        $stock_data = $api->fetch_stock_updates();
        if (!is_wp_error($stock_data)) {
            Engel_Product_Sync::update_stock_only($stock_data);
            echo '<div class="notice notice-success is-dismissible"><p>Stock actualizado correctamente.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error al actualizar stock: ' . esc_html($stock_data->get_error_message()) . '</p></div>';
        }
    }

    ?>
    <div class="wrap">
        <h1>Engel Sync</h1>
        <form method="post">
            <input type="hidden" name="engel_sync_form" value="1">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="engel_api_url">API URL</label></th>
                    <td><input name="engel_api_url" type="text" id="engel_api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="engel_api_token">Token</label></th>
                    <td><input name="engel_api_token" type="text" id="engel_api_token" value="<?php echo esc_attr($api_token); ?>" class="regular-text" required></td>
                </tr>
            </table>
            <?php submit_button('Guardar Configuración'); ?>
        </form>

        <form method="post" style="margin-top: 20px;">
            <input type="hidden" name="sync_all_products" value="1">
            <?php submit_button('Sincronizar Todos los Productos', 'primary'); ?>
        </form>

        <form method="post" style="margin-top: 20px;">
            <input type="hidden" name="update_stock" value="1">
            <?php submit_button('Actualizar Solo Stock', 'secondary'); ?>
        </form>
    </div>
    <?php
}

add_action('admin_menu', function () {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_dashboard');
});
