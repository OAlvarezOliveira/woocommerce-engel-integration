<?php
// Añadir página de menú en el admin
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

// Renderizar la página de administración
function engel_sync_admin_page() {
    // Guardar token si viene en POST
    if (isset($_POST['engel_api_token'])) {
        update_option('engel_api_token', sanitize_text_field($_POST['engel_api_token']));
        echo '<div class="updated"><p>Token guardado correctamente.</p></div>';
    }

    // Lanzar importación si se ha pulsado el botón
    if (isset($_POST['engel_start_import'])) {
        engel_start_import();
        echo '<div class="updated"><p>Importación iniciada, revisa los logs o productos.</p></div>';
    }

    $token = get_option('engel_api_token', '');
    ?>

    <div class="wrap">
        <h1>Engel Sync Settings</h1>

        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="engel_api_token">Token API Engel</label></th>
                    <td><input type="text" id="engel_api_token" name="engel_api_token" value="<?php echo esc_attr($token); ?>" size="50" required></td>
                </tr>
            </table>
            <?php submit_button('Guardar Token'); ?>
        </form>

        <hr>

        <form method="post" action="">
            <input type="hidden" name="engel_start_import" value="1" />
            <?php submit_button('Iniciar Importación Manual', 'primary'); ?>
        </form>
    </div>

    <?php
}
