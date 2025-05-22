<?php

function engel_sync_admin_page() {
    ?>
    <div class="wrap">
        <h1>Engel Sync</h1>
        <form method="post">
            <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>

            <h2>API Login</h2>
            <input type="text" name="engel_user" placeholder="Usuario" required />
            <input type="password" name="engel_pass" placeholder="Contraseña" required />
            <button type="submit" name="engel_login">Login</button>

            <hr>
            <h2>Acciones</h2>
            <button type="submit" name="engel_sync">Sincronizar Productos</button>
            <button type="submit" name="engel_stock">Actualizar Stock</button>
            <button type="submit" name="engel_logout">Logout</button>
        </form>

        <?php
        $token = get_option('engel_api_token');

        if ($token instanceof WP_Error) {
            echo '<p style="color:red;">Error de login: ' . esc_html($token->get_error_message()) . '</p>';
        } elseif (!empty($token)) {
            echo '<p style="color:green;">Token actual: ' . esc_html($token) . '</p>';
        } else {
            echo '<p style="color:gray;">No hay token guardado.</p>';
        }
        ?>
    </div>
    <?php
}

add_action('admin_menu', function () {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_admin_page');
});

add_action('admin_init', function () {
    if (!isset($_POST['engel_sync_nonce']) || !wp_verify_nonce($_POST['engel_sync_nonce'], 'engel_sync_action')) return;

    require_once plugin_dir_path(__FILE__) . '../includes/class-engel-api-client.php';

    $client = new Engel_API_Client();

    if (isset($_POST['engel_login'])) {
        $token = $client->login(sanitize_text_field($_POST['engel_user']), sanitize_text_field($_POST['engel_pass']));
        update_option('engel_api_token', $token); // Puede ser string o WP_Error
    }

    if (isset($_POST['engel_logout'])) {
        delete_option('engel_api_token');
    }
});
