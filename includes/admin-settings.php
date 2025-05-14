<?php
// Añade la página de menú en admin
add_action('admin_menu', function() {
    add_menu_page(
        'Engel Sync',
        'Engel Sync',
        'manage_options',
        'engel-sync',
        'engel_sync_admin_page',
        'dashicons-update',
        60
    );
});

// Página de administración
function engel_sync_admin_page() {
    // Procesar login
    if (isset($_POST['engel_user'], $_POST['engel_password'], $_POST['engel_do_login'])) {
        $user = sanitize_text_field($_POST['engel_user']);
        $pass = sanitize_text_field($_POST['engel_password']);
        $token = engel_api_login($user, $pass);
        if ($token) {
            echo '<div class="updated"><p>Login correcto. Token guardado.</p></div>';
        } else {
            echo '<div class="error"><p>Login fallido. Verifica las credenciales.</p></div>';
        }
    }

    // Procesar logout
    if (isset($_POST['engel_do_logout'])) {
        if (engel_api_logout()) {
            echo '<div class="updated"><p>Logout correcto. Token eliminado.</p></div>';
        } else {
            echo '<div class="error"><p>Error en logout o token no encontrado.</p></div>';
        }
    }

    // Procesar importación manual
    if (isset($_POST['engel_do_import'])) {
        engel_sync_import_products_callback();
        echo '<div class="updated"><p>Importación manual ejecutada. Consulta el log para detalles.</p></div>';
    }

    $token = get_option('engel_api_token', '');
    ?>
    <div class="wrap">
        <h1>Engel Sync Settings</h1>
        <form method="post" action="">
            <h2>Login API Engel</h2>
            <table class="form-table">
                <tr>
                    <th><label for="engel_user">Usuario</label></th>
                    <td><input type="text" id="engel_user" name="engel_user" value="" size="30" required></td>
                </tr>
                <tr>
                    <th><label for="engel_password">Contraseña</label></th>
                    <td><input type="password" id="engel_password" name="engel_password" value="" size="30" required></td>
                </tr>
            </table>
            <input type="submit" name="engel_do_login" value="Login" class="button button-primary" />
            <input type="submit" name="engel_do_logout" value="Logout" class="button button-secondary" />
        </form>

        <hr>

        <form method="post" action="">
            <h2>Importar Productos</h2>
            <p>Ejecuta la importación manualmente sin esperar al cron.</p>
            <input type="submit" name="engel_do_import" value="Importar ahora" class="button button-primary" />
        </form>

        <p><strong>Token actual:</strong> <?php echo esc_html($token ?: 'No hay token'); ?></p>
    </div>
    <?php
}
