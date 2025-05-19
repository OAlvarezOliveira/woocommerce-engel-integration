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
                    $message = "Sincronización completa realizada.";
                    break;

                case 'save_pagination_settings':
                    $elements_per_page = intval($_POST['elements_per_page'] ?? 10);
                    $max_pages = intval($_POST['max_pages'] ?? 5);

                    if ($elements_per_page < 1) $elements_per_page = 10;
                    if ($elements_per_page > 100) $elements_per_page = 100;
                    if ($max_pages < 1) $max_pages = 5;
                    if ($max_pages > 100) $max_pages = 100;

                    update_option('engel_elements_per_page', $elements_per_page);
                    update_option('engel_max_pages', $max_pages);

                    $message = "Configuración de paginación guardada.";
                    break;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }

    $token = $sync->get_token();
    $export_in_progress = get_option('engel_export_in_progress', false);
    $export_filename = get_option('engel_export_filename');
    $export_url = $export_filename ? wp_upload_dir()['baseurl'] . '/' . $export_filename : '';
    $stock_sync_in_progress = get_option('engel_stock_sync_in_progress', false);
    $stock_sync_log_url = get_option('engel_stock_sync_log_url', '');
    ?>
    <div class="wrap">
        <h1>Engel WooCommerce Sync</h1>

        <?php if ($message): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo wp_kses_post($message); ?></p></div>
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
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th><label for="user">Usuario Engel</label></th>
                            <td><input name="user" type="text" id="user" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="password">Contraseña Engel</label></th>
                            <td><input name="password" type="password" id="password" class="regular-text" required></td>
                        </tr>
                    </tbody>
                </table>
                <button type="submit" class="button button-primary">Login</button>
            </form>
        <?php endif; ?>

        <hr>

        <h2>Sincronización de productos</h2>
        <form method="post" style="display:inline-block; margin-right:10px;">
            <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>
            <input type="hidden" name="action" value="full_sync" />
            <button type="submit" class="button button-primary">Sincronizar todos los productos</button>
        </form>

        <button id="start-stock-sync" class="button button-secondary" <?php echo $stock_sync_in_progress ? 'disabled' : ''; ?>>
            <?php echo $stock_sync_in_progress ? 'Sincronización en curso...' : 'Sincronizar solo stock'; ?>
        </button>
        <div id="stock-sync-progress" style="margin-top:10px;"></div>
        <?php if ($stock_sync_log_url): ?>
            <p>Último log: <a href="<?php echo esc_url($stock_sync_log_url); ?>" target="_blank">Descargar log</a></p>
        <?php endif; ?>

        <hr>

        <h2>Exportar productos</h2>
        <button id="start-export" class="button button-secondary" <?php echo $export_in_progress ? 'disabled' : ''; ?>>
            <?php echo $export_in_progress ? 'Exportación en progreso...' : 'Exportar a CSV'; ?>
        </button>
        <div id="export-progress" style="margin-top:10px;"></div>
        <?php if (!$export_in_progress && $export_url): ?>
            <p>Archivo generado: <a href="<?php echo esc_url($export_url); ?>" target="_blank">Descargar CSV</a></p>
        <?php endif; ?>

        <hr>

        <h2>Configuración de paginación</h2>
        <form method="post">
            <?php wp_nonce_field('engel_sync_action', 'engel_sync_nonce'); ?>
            <input type="hidden" name="action" value="save_pagination_settings" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th><label for="elements_per_page">Productos por página</label></th>
                        <td><input name="elements_per_page" type="number" id="elements_per_page" value="<?php echo esc_attr(get_option('engel_elements_per_page', 10)); ?>" min="1" max="100" required></td>
                    </tr>
                    <tr>
                        <th><label for="max_pages">Número máximo de páginas</label></th>
                        <td><input name="max_pages" type="number" id="max_pages" value="<?php echo esc_attr(get_option('engel_max_pages', 5)); ?>" min="1" max="100" required></td>
                    </tr>
                </tbody>
            </table>
            <button type="submit" class="button button-primary">Guardar configuración</button>
        </form>
    </div>

    <script>
    (function($){
        $('#start-export').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $progress = $('#export-progress');

            $btn.prop('disabled', true);
            $progress.text('Iniciando exportación...');

            $.post(ajaxurl, {
                action: 'engel_start_export',
                _ajax_nonce: '<?php echo wp_create_nonce("engel_export_nonce"); ?>'
            }).done(function(response) {
                if(response.success) {
                    processPage(0);
                } else {
                    $progress.text('Error: ' + response.data);
                    $btn.prop('disabled', false);
                }
            }).fail(function() {
                $progress.text('Error al iniciar exportación.');
                $btn.prop('disabled', false);
            });

            function processPage(page) {
                $progress.text('Procesando página ' + (page + 1) + '...');

                $.post(ajaxurl, {
                    action: 'engel_process_export_page',
                    page: page,
                    _ajax_nonce: '<?php echo wp_create_nonce("engel_export_nonce"); ?>'
                }).done(function(response) {
                    if(response.success) {
                        if(response.data.next_page !== false) {
                            processPage(response.data.next_page);
                        } else {
                            $progress.html('Exportación finalizada. <a href="' + response.data.url + '" target="_blank">Descargar CSV</a>');
                            $btn.prop('disabled', false);
                        }
                    } else {
                        $progress.text('Error: ' + response.data);
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    $progress.text('Error durante la exportación.');
                    $btn.prop('disabled', false);
                });
            }
        });

        $('#start-stock-sync').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $progress = $('#stock-sync-progress');

            $btn.prop('disabled', true);
            $progress.text('Iniciando sincronización...');

            $.post(ajaxurl, {
                action: 'engel_start_stock_sync',
                _ajax_nonce: '<?php echo wp_create_nonce("engel_stock_sync_nonce"); ?>'
            }).done(function(response) {
                if(response.success) {
                    processStockPage(0);
                } else {
                    $progress.text('Error: ' + response.data);
                    $btn.prop('disabled', false);
                }
            }).fail(function() {
                $progress.text('Error al iniciar sincronización.');
                $btn.prop('disabled', false);
            });

            function processStockPage(page) {
                $progress.text('Procesando página ' + (page + 1) + '...');

                $.post(ajaxurl, {
                    action: 'engel_process_stock_page',
                    page: page,
                    _ajax_nonce: '<?php echo wp_create_nonce("engel_stock_sync_nonce"); ?>'
                }).done(function(response) {
                    if(response.success) {
                        if(response.data.next_page !== false) {
                            processStockPage(response.data.next_page);
                        } else {
                            $progress.html('Sincronización finalizada. <a href="' + response.data.log_url + '" target="_blank">Descargar log</a>');
                            $btn.prop('disabled', false);
                        }
                    } else {
                        $progress.text('Error: ' + response.data);
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    $progress.text('Error durante sincronización.');
                    $btn.prop('disabled', false);
                });
            }
        });
    })(jQuery);
    </script>
<?php
}

add_action('wp_ajax_engel_start_export', function() {
    check_ajax_referer('engel_export_nonce');

    try {
        $elements_per_page = intval(get_option('engel_elements_per_page', 100));
        $max_pages = intval(get_option('engel_max_pages', 200));

        $exporter = new Engel_Export_Background($elements_per_page, $max_pages);
        $exporter->start_export();

        wp_send_json_success();
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

add_action('wp_ajax_engel_process_export_page', function() {
    check_ajax_referer('engel_export_nonce');

    try {
        $page = isset($_POST['page']) ? intval($_POST['page']) : 0;
        $elements_per_page = intval(get_option('engel_elements_per_page', 100));
        $max_pages = intval(get_option('engel_max_pages', 200));

        $exporter = new Engel_Export_Background($elements_per_page, $max_pages);
        $has_more = $exporter->process_page($page);

        $filename = get_option('engel_export_filename');
        $url = $filename ? wp_upload_dir()['baseurl'] . '/' . $filename : '';

        wp_send_json_success([
            'next_page' => $has_more ? $page + 1 : false,
            'url' => $url
        ]);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

add_action('wp_ajax_engel_start_stock_sync', function() {
    check_ajax_referer('engel_stock_sync_nonce');

    try {
        $elements_per_page = intval(get_option('engel_elements_per_page', 100));
        $max_pages = intval(get_option('engel_max_pages', 200));

        $sync = engel_get_sync_instance();
        $sync->init_stock_sync($elements_per_page, $max_pages);

        wp_send_json_success();
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});

add_action('wp_ajax_engel_process_stock_page', function() {
    check_ajax_referer('engel_stock_sync_nonce');

    try {
        $page = isset($_POST['page']) ? intval($_POST['page']) : 0;
        $sync = engel_get_sync_instance();

        $has_more = $sync->process_stock_sync_page($page);
        $log_url = get_option('engel_stock_sync_log_url', '');

        wp_send_json_success([
            'next_page' => $has_more ? $page + 1 : false,
            'log_url' => $log_url
        ]);
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
});
