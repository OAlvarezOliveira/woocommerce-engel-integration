<?php
/*
Plugin Name: Engel WooCommerce Sync
Description: Sincroniza productos de Nova Engel con WooCommerce y valida rentabilidad contra Amazon.
*/

require_once plugin_dir_path(__FILE__) . 'includes/trait-engel-connection.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-engel-profitable-filter.php';
require_once plugin_dir_path(__FILE__) . 'includes/trait-engel-wc-sync.php';

class Engel_Product_Sync {
    use Engel_Connection_Trait;
    use Engel_Profitable_Filter_Trait;
    use Engel_WC_Sync_Trait;
}

add_action('admin_menu', function() {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', function () {
        echo '<h1>Sincronización con Engel</h1>';
        echo '<form method="post">';
        submit_button('Sincronizar ahora', 'primary', 'sync_now');
        submit_button('Actualizar stock', 'secondary', 'sync_stock');
        echo '</form>';
    });
});

add_action('admin_init', function () {
    $sync = new Engel_Product_Sync();

    if (isset($_POST['sync_now'])) {
        $sync->sync_all_products();
    }

    if (isset($_POST['sync_stock'])) {
        $sync->sync_stock_only();
    }
});
