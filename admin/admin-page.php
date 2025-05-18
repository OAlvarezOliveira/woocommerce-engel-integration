<?php
if (!defined('ABSPATH')) exit;

function engel_sync_admin_page() {
    $sync = engel_get_sync_instance();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('engel_sync_action', 'engel_sync_nonce')) {
        try {
            if (isset($_POST['login'])) {
                $user = sanitize_text_field($_POST['engel_user']);
                $pass = sanitize_text_field($_POST['engel_password']);
                $sync->engel_login($user, $pass);
                echo '<div class="notice notice-success"><p>Login exitoso.</p></div>';
            }
            if (isset($_POST['logout'])) {
                $sync->clear_token();
                echo '<div class="notice notice-success"><p>Logout exitoso.</p></div>';
            }
            if (isset($_POST['download_csv'])) {
                $file_path = $sync->export_products_to_csv();
                $url = wp_upload_dir()['baseurl'] . '/' . basename($file_path);
                echo '<div class="notice notice-success"><p>CSV generado. <a href="' . esc_url($url) . '" target="_blank">Descargar CSV</a></p></div>';
            }
            if (isset($_POST['sync_now'])) {
                $sync->run_full_sync();
                echo '<div class="notice notice-success"><p>Sincronización completa ejecutada.</p></div>';
            }
            if (isset($_POST['sync_stock'])) {
                $sync->run_stock_sync();
                echo '<div class="notice notice-success"><p>Sincronización de stock ejecutada.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    $token = $sync->get_token();
    ?>

    <div class="wrap">
        <h1>Engel WooCommerce Sync</h1>
        <form method="post">
            <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>

            <h2>Login a Nova Engel</h2>
            <label>Usuario: <input type="text" name="engel_user" required></label><br>
            <label>Contraseña: <input type="password" name="engel_password" required></label><br>
            <button type="submit" name="login" class="button button-primary">Login</button>

            <h2>Acciones</h2>
            <p>Token actual: <strong><?php echo $token ? esc_html($token) : 'No autenticado'; ?></strong></p>

            <button type="submit" name="logout" class="button button-secondary">Logout</button><br><br>

            <button type="submit" name="download_csv" class="button">Descargar CSV Productos</button><br><br>

            <button type="submit" name="sync_now" class="button button-primary">Sincronizar todos los productos</button><br><br>

            <button type="submit" name="sync_stock" class="button button-secondary">Actualizar solo stock</button>
        </form>
    </div>

    <?php
}
