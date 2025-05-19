jQuery(document).ready(function($){
    $('#sync-stock-btn').click(function(){
        var $btn = $(this);
        var $status = $('#sync-status');

        $btn.prop('disabled', true);
        $status.text('Iniciando sincronización...');

        function syncPage(page) {
            $.ajax({
                url: engelSync.ajax_url,
                method: 'POST',
                data: {
                    action: 'engel_process_stock_sync_page',
                    nonce: engelSync.nonce,
                    page: page
                },
                success: function(response) {
                    if(response.success) {
                        if(response.data.next_page !== false) {
                            $status.text('Sincronizando página ' + page);
                            syncPage(response.data.next_page);
                        } else {
                            $status.text('Sincronización completada.');
                            $btn.prop('disabled', false);
                        }
                    } else {
                        $status.text('Error en AJAX: ' + response.data);
                        $btn.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    $status.text('Error AJAX: ' + xhr.status + ' ' + xhr.statusText);
                    $btn.prop('disabled', false);
                }
            });
        }

        syncPage(0);
    });
});
