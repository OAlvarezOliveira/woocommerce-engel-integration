<?php
// includes/admin-settings.php
defined('ABSPATH') || exit;

add_action('admin_menu', function() {
    add_menu_page(
        'Engel Sync',
        'Engel Sync',
        'manage_options',
        'engel-sync',
        'engel_sync_admin_page',
        'dashicons-update',
        81
    );
});

function engel_sync_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Guardar datos formulario
    if (isset($_POST['engel_sync_submit'])) {
        check_admin_referer('engel_sync_save_settings');
        update_option('engel_sync_user', sanitize_text_field($_POST['engel_sync_user']));
        update_option('engel_sync_password', sanitize_text_field($_POST['engel_sync_password']));
        echo '<div class="updated"><p>Configuración guardada</p></div>';
    }

    $user = get_option('engel_sync_user', '');
    $password = get_option('engel_sync_password', '');

    // Manejo acciones AJAX simuladas por formulario para pruebas (login, logout, get products)
    if (isset($_POST['engel_action'])) {
        check_admin_referer('engel_sync_actions');
        $output = '';
        switch ($_POST['engel_action']) {
            case 'login':
                $token = engel_api_login($user, $password);
                if ($token) {
                    $output = "Login correcto. Token: $token";
                } else {
                    $output = "Error en login.";
                }
                break;
            case 'logout':
                if (engel_api_logout()) {
                    $output = "Logout correcto.";
                } else {
                    $output = "Error en logout.";
                }
                break;
            case 'get_products':
                $products = engel_api_get_products();
                if ($products) {
                    $output = "Productos recibidos: " . count($products);
                } else {
                    $output = "Error al obtener productos.";
                }
                break;
        }
        echo '<div class="notice notice-info is-dismissible"><p>' . esc_html($output) . '</p></div>';
    }

    ?>

    <div class="wrap">
        <h1>Configuración Engel Sync</h1>

        <form method="post" action="">
            <?php wp_nonce_field('engel_sync_save_settings'); ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="engel_sync_user">Usuario API</label></th>
                        <td><input name="engel_sync_user" type="text" id="engel_sync_user" value="<?php echo esc_attr($user); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="engel_sync_password">Contraseña API</label></th>
                        <td><input name="engel_sync_password" type="password" id="engel_sync_password" value="<?php echo esc_attr($password); ?>" class="regular-text" required></td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button('Guardar configuración', 'primary', 'engel_sync_submit'); ?>
        </form>

        <h2>Pruebas de API</h2>

        <form method="post" action="" style="margin-bottom: 1em;">
            <?php wp_nonce_field('engel_sync_actions'); ?>
            <input type="hidden" name="engel_action" value="login" />
            <?php submit_button('Login', 'secondary'); ?>
        </form>

        <form method="post" action="" style="margin-bottom: 1em;">
            <?php wp_nonce_field('engel_sync_actions'); ?>
            <input type="hidden" name="engel_action" value="logout" />
            <?php submit_button('Logout', 'secondary'); ?>
        </form>

        <form method="post" action="">
            <?php wp_nonce_field('engel_sync_actions'); ?>
            <input type="hidden" name="engel_action" value="get_products" />
            <?php submit_button('Obtener Productos', 'secondary'); ?>
        </form>
    </div>

    <?php
}
