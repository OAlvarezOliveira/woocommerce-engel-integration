<?php
add_action('admin_menu', function() {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_settings_page');
});

function engel_sync_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configuración Engel Sync</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('engel_sync_settings');
            do_settings_sections('engel-sync');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function() {
    register_setting('engel_sync_settings', 'engel_api_user');
    register_setting('engel_sync_settings', 'engel_api_pass');

    add_settings_section('engel_api_section', 'Credenciales API Engel', null, 'engel-sync');

    add_settings_field('engel_api_user', 'Usuario API', function() {
        $value = get_option('engel_api_user');
        echo "<input type='text' name='engel_api_user' value='" . esc_attr($value) . "' />";
    }, 'engel-sync', 'engel_api_section');

    add_settings_field('engel_api_pass', 'Clave API', function() {
        $value = get_option('engel_api_pass');
        echo "<input type='password' name='engel_api_pass' value='" . esc_attr($value) . "' />";
    }, 'engel-sync', 'engel_api_section');
});
