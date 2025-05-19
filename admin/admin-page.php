<?php
if (!defined('ABSPATH')) exit;

// Encola JS solo en la página del plugin
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'toplevel_page_engel-sync') {
        wp_enqueue_script('engel-sync-admin', plugin_dir_url(__FILE__) . 'engel-sync-admin.js', ['jquery'], null, true);
        wp_localize_script('engel-sync-admin', 'engelSync', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('engel_stock_sync_nonce'),
        ]);
    }
});

// Página de administración del plugin
function engel_sync_admin_page() {
    ?>
    <div class="wrap">
        <h1>Engel WooCommerce Sync</h1>
        <p>Este plugin permite sincronizar productos y stock desde Nova Engel.</p>

        <button id="sync-stock-btn" class="button button-primary">Sincronizar Stock</button>
        <div id="sync-status" style="margin-top: 1em;"></div>
    </div>
    <?php
}
