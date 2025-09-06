/*
 * WooCommerce Lottery Plugin 2.2.2, Generated: July 22, 2025
 * Admin JavaScript for filtering and sorting lotteries table
 */
jQuery(document).ready(function($) {
    if (typeof wclp_filter === 'undefined') {
        return;
    }

    // Filter by status
    $('#wclp-status-filter').on('change', function() {
        const status = $(this).val();
        updateTable({ status: status });
    });

    // Sort by column
    $('.wclp-admin-table th[data-sort]').on('click', function() {
        const $th = $(this);
        const sortBy = $th.data('sort');
        const currentOrder = $th.hasClass('sorted-asc') ? 'DESC' : 'ASC';
        
        $('.wclp-admin-table th').removeClass('sorted-asc sorted-desc');
        $th.addClass('sorted-' + currentOrder.toLowerCase());
        
        updateTable({
            status: $('#wclp-status-filter').val(),
            sort_by: sortBy,
            sort_order: currentOrder
        });
    });

    // Update table via AJAX
    function updateTable(params) {
        $.ajax({
            url: wclp_filter.ajax_url,
            type: 'POST',
            data: {
                action: 'wclp_get_lotteries',
                nonce: wclp_filter.nonce,
                status: params.status || '',
                sort_by: params.sort_by || 'updated_at',
                sort_order: params.sort_order || 'DESC'
            },
            success: function(response) {
                if (response.success) {
                    $('.wclp-admin-table').replaceWith(response.data.content);
                }
            },
            error: function(xhr, status, error) {
                // Silent
            }
        });
    }
});