<?php
// Aseguramos que el archivo api-connection.php esté cargado
require_once plugin_dir_path(__FILE__) . 'api-connection.php';

// Añadir las opciones del menú en el panel de administración
add_action('admin_menu', function() {
    // Menú principal Engel Sync
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_settings_page');
    // Subpestaña Configuración
    add_submenu_page('engel-sync', 'Configuración', 'Configuración', 'manage_options', 'engel-sync', 'engel_sync_settings_page');
    // Subpestaña Ver Stock
    add_submenu_page('engel-sync', 'Ver Stock', 'Ver Stock', 'manage_options', 'engel-sync-stock', 'engel_sync_stock_page');
});

// Página de configuración de Engel Sync
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

// Registrar y mostrar los campos de configuración
add_action('admin_init', function() {
    // Registrar las opciones de la API
    register_setting('engel_sync_settings', 'engel_api_user');
    register_setting('engel_sync_settings', 'engel_api_pass');

    // Añadir sección de configuración para las credenciales de la API
    add_settings_section('engel_api_section', 'Credenciales API Engel', null, 'engel-sync');

    // Campo para el usuario de la API
    add_settings_field('engel_api_user', 'Usuario API', function() {
        $value = get_option('engel_api_user');
        echo "<input type='text' name='engel_api_user' value='" . esc_attr($value) . "' />";
    }, 'engel-sync', 'engel_api_section');

    // Campo para la clave de la API
    add_settings_field('engel_api_pass', 'Clave API', function() {
        $value = get_option('engel_api_pass');
        echo "<input type='password' name='engel_api_pass' value='" . esc_attr($value) . "' />";
    }, 'engel-sync', 'engel_api_section');
});

// Función para mostrar la página de "Ver Stock"
function engel_sync_stock_page() {
    echo '<div class="wrap"><h1>Stock de Engel</h1>';

    // Llamada a la API para obtener los artículos
    $response = engel_api_call('GetArticulos');

    // Verificar si hay un error en la respuesta
    if (isset($response['error'])) {
        echo '<p>Error: ' . esc_html($response['error']) . '</p></div>';
        return;
    }

    // Verificar si la respuesta es válida y tiene datos
    if (!is_array($response) || empty($response)) {
        echo '<p>No se encontraron artículos o la respuesta está vacía.</p></div>';
        return;
    }

    // Mostrar la tabla con los artículos
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Código</th><th>Descripción</th><th>Stock</th><th>Precio</th></tr></thead><tbody>';

    // Mostrar solo los primeros 50 productos
    foreach (array_slice($response, 0, 50) as $articulo) {
        echo '<tr>';
        echo '<td>' . esc_html($articulo['Codigo']) . '</td>';
        echo '<td>' . esc_html($articulo['Descripcion']) . '</td>';
        echo '<td>' . esc_html($articulo['Stock']) . '</td>';
        echo '<td>' . esc_html($articulo['Precio']) . ' €</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}
?>
