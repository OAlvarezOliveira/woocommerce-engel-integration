<?php
if (!defined('ABSPATH')) exit;

$ajax_nonce = wp_create_nonce('engel_stock_sync_nonce');
?>

<div class="wrap">
    <h1>Engel WooCommerce Sync</h1>

    <h2>Sincronización de Stock</h2>
    <p>
        <button id="engel-start-stock-sync" class="button button-primary">Sincronizar Stock</button>
    </p>
    <div id="engel-stock-progress" style="margin-top: 15px; display:none;">
        <div style="height: 20px; background: #eee; border: 1px solid #ccc;">
            <div id="engel-stock-bar" style="height: 100%; width: 0%; background: #0073aa; color: white; text-align: center; line-height: 20px;">0%</div>
        </div>
        <p id="engel-stock-status" style="margin-top: 10px;">Iniciando sincronización...</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const startButton = document.getElementById('engel-start-stock-sync');
    const progressWrap = document.getElementById('engel-stock-progress');
    const progressBar = document.getElementById('engel-stock-bar');
    const statusText = document.getElementById('engel-stock-status');

    const elementsPerPage = <?php echo intval(get_option('engel_elements_per_page', 100)); ?>;
    const maxPages = <?php echo intval(get_option('engel_max_pages', 200)); ?>;

    function updateProgress(currentPage) {
        const percentage = Math.min(100, Math.round((currentPage / maxPages) * 100));
        progressBar.style.width = percentage + '%';
        progressBar.textContent = percentage + '%';
        statusText.textContent = `Sincronizando página ${currentPage + 1} de ${maxPages}...`;
    }

    function syncPage(page = 0) {
        updateProgress(page);
        const formData = new FormData();
        formData.append('action', 'engel_process_stock_sync_page');
        formData.append('page', page);
        formData.append('_ajax_nonce', '<?php echo $ajax_nonce; ?>');

        fetch(ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
        .then(response => response.json())
        .then(json => {
            if (json.success) {
                const next = json.data.next_page;
                if (next !== false && next < maxPages) {
                    syncPage(next);
                } else {
                    updateProgress(maxPages);
                    statusText.textContent = '✅ Sincronización completada con éxito.';
                    startButton.disabled = false;
                }
            } else {
                throw new Error(json.data || 'Error desconocido');
            }
        })
        .catch(error => {
            statusText.textContent = '❌ Error: ' + error.message;
            console.error(error);
            startButton.disabled = false;
        });
    }

    startButton.addEventListener('click', function () {
        startButton.disabled = true;
        progressWrap.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.textContent = '0%';
        statusText.textContent = 'Iniciando sincronización...';
        syncPage(0);
    });
});
</script>
