<?php
if (!defined('ABSPATH')) exit;

function engel_sync_admin_page() {
    $sync = engel_get_sync_instance();
    $message = '';

    if (isset($_POST['action'])) {
        check_admin_referer('engel_sync_action', 'engel_sync_nonce');

        try {
            switch ($_POST['action']) {
                case 'login':
                    $user = sanitize_text_field($_POST['user'] ?? '');
                    $pass = sanitize_text_field($_POST['password'] ?? '');
                    $sync->engel_login($user, $pass);
                    $message = "Login exitoso.";
                    break;

                case 'logout':
                    $sync->clear_token();
                    $message = "Logout realizado.";
                    break;

                case 'full_sync':
                    $sync->run_full_sync();
                    $message = "Sincronización completa iniciada. Se procesará en segundo plano.";
                    break;

                case 'stock_sync':
                    $sync->run_stock_sync();
                    $message = "Sincronización de stock iniciada.";
                    break;

                case 'export_csv':
                    $file_path = $sync->export_products_to_csv();
                    $url = wp_upload_dir()['baseurl'] . '/' . basename($file_path);
                    $message = "CSV exportado correctamente. <a href='$url' target='_blank'>Descargar CSV</a>";
                    break;

                case 'save_pagination_settings':
                    $elements_per_page = intval($_POST['elements_per_page'] ?? 10);
                    $max_pages = intval($_POST['max_pages'] ?? 5);
                    $frequency = sanitize_text_field($_POST['sync_frequency'] ?? 'daily');

                    if ($elements_per_page < 1) $elements_per_page = 10;
                    if ($elements_per_page > 100) $elements_per_page = 100;
                    if ($max_pages < 1) $max_pages = 5;
                    if ($max_pages > 100) $max_pages = 100;

                    update_option('engel_elements_per_page', $elements_per_page);
                    update_option('engel_max_pages', $max_pages);
                    update_option('engel_sync_frequency', $frequency);

                    engel_schedule_cron_event($frequency);

                    $message = "Configuración guardada correctamente.";
                    break;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }

    $token = $sync->get_token();
    $elements_per_page = get_option('engel_elements_per_page', 10);
    $max_pages = get_option('engel_max_pages', 5);
    $frequency = get_option('engel_sync_frequency', 'daily');
    $last_sync = get_option('engel_last_sync_time', '—');
    $logs = get_option('engel_sync_logs', []);
    $frequencies = [
        'hourly' => 'Cada hora',
        'twicedaily' => 'Cada 12 horas',
        'daily' => 'Cada 24 horas'
    ];
    ?>
    <div class="wrap">
        <h1>Engel WooCommerce Sync</h1>

        <?php if ($message): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo wp_kses_post($message); ?></p>
            </div>
        <?php endif; ?>

        <h2>Autenticación</h2>
        <?php if ($token): ?>
            <p><strong>Token actual:</strong> <?php echo esc_html($token); ?></p>
            <form method="post">
                <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>
                <input type="hidden" name="action" value="logout" />
                <button type="submit" class="button button-secondary">Logout</button>
            </form>
        <?php else: ?>
            <form method="post">
                <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>
                <input type="hidden" name="action" value="login" />
                <table class="form-table">
                    <tr>
                        <th><label for="user">Usuario Engel</label></th>
                        <td><input name="user" type="text" id="user" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="password">Contraseña Engel</label></th>
                        <td><input name="password" type="password" id="password" class="regular-text" required></td>
                    </tr>
                </table>
                <button type="submit" class="button button-primary">Login</button>
            </form>
        <?php endif; ?>

        <hr>

        <h2>Sincronización manual</h2>
        <form method="post" style="display:inline-block; margin-right:10px;">
            <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>
            <input type="hidden" name="action" value="full_sync" />
            <button type="submit" class="button button-primary">Sincronizar todos los productos</button>
        </form>

        <form method="post" style="display:inline-block;">
            <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>
            <input type="hidden" name="action" value="stock_sync" />
            <button type="submit" class="button button-secondary">Sincronizar solo stock</button>
        </form>

        <hr>

        <h2>Exportar productos</h2>
        <form method="post">
            <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>
            <input type="hidden" name="action" value="export_csv" />
            <button type="submit" class="button button-secondary">Exportar a CSV</button>
        </form>

        <hr>

        <h2>Configuración</h2>
        <form method="post">
            <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>
            <input type="hidden" name="action" value="save_pagination_settings" />
            <table class="form-table">
                <tr>
                    <th><label for="elements_per_page">Productos por página</label></th>
                    <td><input name="elements_per_page" type="number" id="elements_per_page" value="<?php echo esc_attr($elements_per_page); ?>" min="1" max="100" required></td>
                </tr>
                <tr>
                    <th><label for="max_pages">Número máximo de páginas</label></th>
                    <td><input name="max_pages" type="number" id="max_pages" value="<?php echo esc_attr($max_pages); ?>" min="1" max="100" required></td>
                </tr>
                <tr>
                    <th><label for="sync_frequency">Frecuencia de sincronización automática</label></th>
                    <td>
                        <select name="sync_frequency" id="sync_frequency">
                            <?php foreach ($frequencies as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($frequency, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Última sincronización automática</th>
                    <td><?php echo esc_html($last_sync); ?></td>
                </tr>
            </table>
            <button type="submit" class="button button-primary">Guardar configuración</button>
        </form>

        <hr>

        <h2>Historial de sincronización</h2>
        <?php if (!empty($logs)): ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Mensaje</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($logs) as $entry): ?>
                        <tr>
                            <td><?php echo esc_html($entry['time']); ?></td>
                            <td><?php echo esc_html($entry['type']); ?></td>
                            <td><?php echo esc_html($entry['message']); ?></td>
                            <td><?php echo isset($entry['count']) ? intval($entry['count']) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No hay registros disponibles.</p>
        <?php endif; ?>
    </div>
    <?php
}
