jQuery(document).ready(function($) {
    let currentPage = 0;
    let syncing = false;

    function syncPage(page) {
        syncing = true;
        $('#sync-status').html(`Sincronizando página ${page + 1}...`);

        $.ajax({
            url: engelSync.ajax_url,
            type: 'POST',
            data: {
                action: 'engel_process_stock_sync_page',
                page: page,
                _ajax_nonce: engelSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.next_page !== false) {
                        syncPage(response.data.next_page);
                    } else {
                        $('#sync-status').html('<span style="color:green;">Sincronización de stock completada.</span>');
                        syncing = false;
                    }
                } else {
                    $('#sync-status').html('<span style="color:red;">Error: ' + response.data + '</span>');
                    syncing = false;
                }
            },
            error: function(xhr, status, error) {
                $('#sync-status').html('<span style="color:red;">AJAX Error: ' + error + '</span>');
                syncing = false;
            }
        });
    }

    $('#sync-stock-btn').on('click', function(e) {
        e.preventDefault();

        if (syncing) {
            alert('La sincronización ya está en curso.');
            return;
        }

        currentPage = 0;
        syncPage(currentPage);
    });
});
