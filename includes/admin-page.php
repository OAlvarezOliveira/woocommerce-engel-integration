<?php

add_action('admin_menu', function () {
    add_menu_page('Engel Sync', 'Engel Sync', 'manage_options', 'engel-sync', 'engel_sync_admin_page');
});

function engel_sync_admin_page() {
    if (isset($_POST['engel_login'])) {
        Engel_Auth::login(sanitize_text_field($_POST['username']), sanitize_text_field($_POST['password']));
    }

    if (isset($_POST['engel_logout'])) {
        Engel_Auth::logout();
    }

    $token = Engel_Auth::get_token();
    ?>

    <div class="wrap">
        <h1>Engel Sync</h1>

        <?php if ($token): ?>
            <p><strong>Token:</strong> <?php echo esc_html($token); ?></p>
            <form method="post">
                <button type="submit" name="engel_logout" class="button button-secondary">Logout</button>
            </form>
        <?php else: ?>
            <form method="post">
                <table class="form-table">
                    <tr><th><label for="username">Usuario</label></th><td><input type="text" name="username" id="username" class="regular-text" /></td></tr>
                    <tr><th><label for="password">Password</label></th><td><input type="password" name="password" id="password" class="regular-text" /></td></tr>
                </table>
                <button type="submit" name="engel_login" class="button button-primary">Login</button>
            </form>
        <?php endif; ?>
    </div>

    <?php
}
