/**
 * Admin scripts for WooCommerce Lottery Plugin
 * Version: 2.2.2
 * Generated: 2025-07-20 10:00:00 EEST
 */
jQuery(document).ready(function($) {
    if (typeof wclp_admin === 'undefined') {
        $('.wclp-form-container, .wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error: Localization data missing. Please refresh the page or contact support.</p></div>');
        setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
        return;
    }

    let formChanged = false;
    let isSubmitting = false;

    function initFormElements() {
        $('.wclp-color-picker').spectrum({
            showInput: true,
            preferredFormat: 'hex',
            showPalette: true,
            palette: [['#28a745', '#dc3545', '#007bff'], ['#000000', '#ffffff', '#dddddd']],
            change: function() {
                formChanged = true;
            }
        });

        $(document).on('change', '.wclp-sticky-toggle', function() {
            formChanged = true;
        });

        $(document).on('change', '.wclp-prize-product', function() {
            const $this = $(this);
            const productId = $this.val();
            const $prize = $this.closest('.wclp-prize');
            if (!productId) {
                return;
            }

            $.ajax({
                url: wclp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wclp_get_product_data',
                    nonce: wclp_admin.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        $prize.find('input[name*="name"]').val(response.data.name);
                        $prize.find('textarea[name*="description"]').val(response.data.description);
                        $prize.find('input[name*="image"]').val(response.data.image).trigger('change');
                        $prize.find('.wclp-remove-image').show();
                        formChanged = true;
                    }
                },
                error: function(xhr, status, error) {
                    // Silent
                }
            });
        });

        $('input[name="draw[ticket_count]"]').off('change').on('change', function() {
            const ticketCount = parseInt($(this).val() || 100);
            const maxPrizes = Math.floor(ticketCount * 0.15);
            $('.wclp-max-prizes').text(maxPrizes);
            formChanged = true;
        });

        $('input[name="draw[ticket_count]"]').trigger('change');

        if ($('input[name="draw[id]"]').val() && $('.wclp-lottery-form').find('input[name="draw[ticket_count]"]').is(':disabled')) {
            // Silent
        }

        $('.wclp-lottery-form').off('change').on('change', ':input', function() {
            formChanged = true;
        });

        $('.wclp-lottery-form').off('submit').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $submit = $form.find('.wclp-save-lottery');
            const $spinner = $form.find('.wclp-spinner');
            if ($submit.prop('disabled') || isSubmitting) {
                return;
            }

            $form.attr('novalidate', 'novalidate');

            let isValid = true;
            const invalidFields = [];
            $form.find('input[required], select[required]').each(function() {
                const value = $(this).val();
                const name = $(this).attr('name');
                if (!value || value === 'create') {
                    if (name !== 'draw[product_id_select]') {
                        $(this).addClass('wclp-error');
                        isValid = false;
                        invalidFields.push(name);
                    }
                } else {
                    $(this).removeClass('wclp-error');
                }
            });

            $form.find('input[name="draw[name]"]').each(function() {
                const value = $(this).val();
                if (value.length < 3 || value.length > 255) {
                    $(this).addClass('wclp-error');
                    isValid = false;
                    invalidFields.push('draw[name] (length: ' + value.length + ')');
                } else {
                    $(this).removeClass('wclp-error');
                }
            });

            if (!isValid) {
                $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>' + (wclp_admin.fill_required_fields || 'Please fill in all required fields correctly.') + '</p></div>');
                setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                return;
            }

            isSubmitting = true;
            formChanged = false;
            $submit.prop('disabled', true).text(wclp_admin.saving || 'Saving...');
            $spinner.show();

            let formData = $form.serializeArray();
            const isActiveLottery = $('input[name="draw[id]"]').val() && $('.wclp-lottery-form').find('input[name="draw[ticket_count]"]').is(':disabled');
            if (isActiveLottery) {
                formData = formData.filter(item => 
                    item.name !== 'draw[ticket_count]' &&
                    item.name !== 'draw[ticket_price]' &&
                    item.name !== 'draw[name]' &&
                    item.name !== 'draw[draw_type]' &&
                    item.name !== 'draw[product_id_select]' &&
                    !item.name.match(/draw\[prizes\]\[\d+\]\[name\]/) &&
                    !item.name.match(/draw\[prizes\]\[\d+\]\[product_id\]/)
                );
            } else {
                const productIdSelect = $form.find('select[name="draw[product_id_select]"]').val();
                formData = formData.map(item => {
                    if (item.name === 'draw[product_id]' && productIdSelect !== 'create') {
                        return { name: 'draw[product_id]', value: productIdSelect };
                    }
                    return item;
                });
            }

            $.ajax({
                url: wclp_admin.ajax_url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    isSubmitting = false;
                    $submit.prop('disabled', false).text(wclp_admin.save_lottery || 'Save Lottery');
                    $spinner.hide();
                    if (response.success) {
                        $('.wclp-table-container').prepend('<div class="notice notice-success is-dismissible"><p>' + (wclp_admin.lottery_saved || 'Lottery saved successfully') + '</p></div>');
                        setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                        $('.wclp-form-container').hide().empty();
                        $.ajax({
                            url: wclp_filter.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'wclp_get_lotteries',
                                nonce: wclp_filter.nonce,
                                status: $('#wclp-status-filter').val()
                            },
                            success: function(tableResponse) {
                                if (tableResponse.success && tableResponse.data && tableResponse.data.content) {
                                    $('.wclp-table-container').html(tableResponse.data.content);
                                } else {
                                    $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>' + (tableResponse.data && tableResponse.data.message || 'Error updating table') + '</p></div>');
                                    setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                                }
                            },
                            error: function(xhr, status, error) {
                                $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error updating table: ' + (xhr.responseText || error) + '</p></div>');
                                setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                            }
                        });
                    } else {
                        $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>' + (response.data && response.data.message ? response.data.message : (wclp_admin.error_saving || 'Error saving lottery')) + '</p></div>');
                        setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                    }
                },
                error: function(xhr, status, error) {
                    $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>' + (wclp_admin.error_saving || 'Error saving lottery') + ': ' + (xhr.responseText || error) + '</p></div>');
                    setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                    isSubmitting = false;
                    $submit.prop('disabled', false).text(wclp_admin.save_lottery || 'Save Lottery');
                    $spinner.hide();
                }
            });
        });
    }

    $(window).on('beforeunload', function(e) {
        if (formChanged && !isSubmitting) {
            e.preventDefault();
            return wclp_admin.confirm_unsaved || 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    if ($('.wclp-lottery-form').length) {
        $('.wclp-lottery-form').addClass('wclp-initialized');
        initFormElements();
    }

    setTimeout(function() {
        if ($('.wclp-lottery-form').length && !$('.wclp-lottery-form').hasClass('wclp-initialized')) {
            $('.wclp-lottery-form').addClass('wclp-initialized');
            initFormElements();
        }
    }, 2000);

    $('.wclp-create-lottery').on('click', function(e) {
        e.preventDefault();
        const $formContainer = $('.wclp-form-container');
        if ($formContainer.length) {
            $formContainer.show().html('<div class="wclp-form-loading">' + (wclp_admin.loading || 'Loading...') + '</div>');
            $.ajax({
                url: wclp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wclp_render_form',
                    nonce: wclp_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $formContainer.html(response.data.content);
                        if ($('.wclp-lottery-form').length) {
                            $('.wclp-lottery-form').addClass('wclp-initialized');
                            initFormElements();
                        } else {
                            $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error: Form not loaded</p></div>');
                            setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                        }
                    } else {
                        $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error: ' + (response.data && response.data.message || 'Unknown error') + '</p></div>');
                        setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                    }
                },
                error: function(xhr, status, error) {
                    $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error: ' + (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message || 'Unknown error') + '</p></div>');
                    setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                }
            });
            $('html, body').animate({ scrollTop: $formContainer.offset().top }, 500);
        }
    });

    $(document).on('click', '.wclp-edit-lottery, .wclp-clone-lottery', function(e) {
        e.preventDefault();
        const $this = $(this);
        const id = $this.data('id');
        const action = $this.hasClass('wclp-clone-lottery') ? 'clone' : 'edit';
        const $formContainer = $('.wclp-form-container');

        if ($formContainer.length) {
            $formContainer.show().html('<div class="wclp-form-loading">' + (wclp_admin.loading || 'Loading...') + '</div>');
            $.ajax({
                url: wclp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wclp_render_form',
                    nonce: wclp_admin.nonce,
                    id: action === 'edit' ? id : 0,
                    clone_id: action === 'clone' ? id : 0
                },
                success: function(response) {
                    if (response.success) {
                        $formContainer.html(response.data.content);
                        if ($('.wclp-lottery-form').length) {
                            $('.wclp-lottery-form').addClass('wclp-initialized');
                            initFormElements();
                        } else {
                            $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error: Form not loaded</p></div>');
                            setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                        }
                    } else {
                        $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error: ' + (response.data && response.data.message || 'Unknown error') + '</p></div>');
                        setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                    }
                },
                error: function(xhr, status, error) {
                    $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error: ' + (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message || 'Unknown error') + '</p></div>');
                    setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                }
            });
            $('html, body').animate({ scrollTop: $formContainer.offset().top }, 500);
        }
    });

    $(document).on('click', '.wclp-upload-image', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $input = $button.prevAll('.wclp-image-url');
        const $inputId = $button.prevAll('.wclp-image-id');
        const $removeButton = $button.next('.wclp-remove-image');
        const frame = wp.media({
            title: wclp_admin.select_image || 'Select Image',
            multiple: false
        });

        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            $input.val(attachment.url).trigger('change');
            $inputId.val(attachment.id);
            $removeButton.show();
            formChanged = true;
        });

        frame.open();
    });

    $(document).on('click', '.wclp-remove-image', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $input = $button.prevAll('.wclp-image-url');
        const $inputId = $button.prevAll('.wclp-image-id');
        $input.val('').trigger('change');
        $inputId.val('');
        $button.hide();
        formChanged = true;
    });

    $(document).on('click', '.wclp-add-prize', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $prizes = $(this).closest('.wclp-form-column').find('.wclp-prizes');
        const ticketCount = parseInt($('input[name="draw[ticket_count]"]').val() || 100);
        const maxPrizes = Math.floor(ticketCount * 0.15);
        const index = $prizes.find('.wclp-prize').length;

        if (index >= maxPrizes) {
            $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>' + (wclp_admin.max_prizes_error || 'Maximum number of prizes reached') + '</p></div>');
            setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
            return;
        }

        const html = `
            <div class="wclp-prize">
                <input type="hidden" name="draw[prizes][${index}][id]" value="${Date.now()}">
                <div class="wclp-form-grid">
                    <div class="wclp-form-field">
                        <label>${wclp_admin.prize_name || 'Prize Name'}</label>
                        <input type="text" name="draw[prizes][${index}][name]" required>
                    </div>
                    <div class="wclp-form-field">
                        <label>${wclp_admin.prize_description || 'Prize Description'}</label>
                        <textarea name="draw[prizes][${index}][description]"></textarea>
                    </div>
                    <div class="wclp-form-field">
                        <label>${wclp_admin.prize_image_url || 'Prize Image'}</label>
                        <input type="text" name="draw[prizes][${index}][image]" class="wclp-image-url" readonly>
                        <input type="hidden" name="draw[prizes][${index}][image_id]" class="wclp-image-id">
                        <button type="button" class="wclp-upload-image">${wclp_admin.select_image || 'Select Image'}</button>
                        <button type="button" class="wclp-remove-image" style="display:none;">${wclp_admin.remove_image || 'Remove'}</button>
                    </div>
                    <div class="wclp-form-field">
                        <label>${wclp_admin.prize_product || 'Prize Product'}</label>
                        <select name="draw[prizes][${index}][product_id]" class="wclp-prize-product">
                            <option value="0">${wclp_admin.none || 'None'}</option>
                            ${wclp_admin.products.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="wclp-form-field">
                        <a href="#" class="wclp-remove-prize">${wclp_admin.remove_prize || 'Remove Prize'}</a>
                    </div>
                </div>
            </div>
        `;
        $prizes.append(html);
        $('.wclp-prize-count .wclp-current-prizes').text(index + 1);
        $('.wclp-max-prizes').text(maxPrizes);
        initFormElements();
        formChanged = true;
    });

    $(document).on('click', '.wclp-remove-prize', function(e) {
        e.preventDefault();
        const $prize = $(this).closest('.wclp-prize');
        const currentCount = $('.wclp-prizes .wclp-prize').length;
        if (currentCount <= 1) {
            $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>' + (wclp_admin.no_prizes_error || 'At least one prize is required') + '</p></div>');
            setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
            return;
        }
        if (confirm(wclp_admin.remove_prize_confirm || 'Are you sure you want to remove this prize?')) {
            $prize.remove();
            $('.wclp-prize-count .wclp-current-prizes').text(currentCount - 1);
            formChanged = true;
        }
    });

    $(document).on('sortable', '.wclp-prizes', {
        handle: '.wclp-prize',
        update: function() {
            $('.wclp-prizes .wclp-prize').each(function(i) {
                $(this).find('input, textarea, select').each(function() {
                    const name = $(this).attr('name');
                    if (name) {
                        $(this).attr('name', name.replace(/\[\d+\]/, `[${i}]`));
                    }
                });
            });
            formChanged = true;
        }
    });

    $(document).on('click', '.wclp-copy-shortcode', function(e) {
        e.preventDefault();
        const shortcode = $(this).prev('select').val();
        const $temp = $('<input>').val(shortcode).appendTo('body').select();
        document.execCommand('copy');
        $temp.remove();
        $('.wclp-table-container').prepend('<div class="notice notice-success is-dismissible"><p>' + (wclp_admin.copied || 'Shortcode copied to clipboard') + '</p></div>');
        setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
    });

    $(document).on('click', '.wclp-activate-lottery', function(e) {
        e.preventDefault();
        const $this = $(this);
        const lotteryId = $this.data('lottery-id');

        $.ajax({
            url: wclp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wclp_activate_lottery',
                nonce: wclp_admin.nonce,
                lottery_id: lotteryId
            },
            success: function(response) {
                if (response.success) {
                    $this.closest('tr').find('.column-status').text('Active');
                    $this.remove();
                    $('.wclp-table-container').prepend('<div class="notice notice-success is-dismissible"><p>' + (response.data.message || 'Lottery activated') + '</p></div>');
                    setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                } else {
                    $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>' + (response.data.message || wclp_admin.error_saving || 'Error saving lottery') + '</p></div>');
                    setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                }
            },
            error: function(xhr, status, error) {
                $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error activating lottery: ' + (xhr.responseText || error) + '</p></div>');
                setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
            }
        });
    });

    $(document).on('change', '.wclp-product-select', function() {
        const productId = $(this).val();
        const lotteryId = $('input[name="draw[id]"]').val() || 0;
        if (!productId || productId === 'create') {
            $('input[name="draw[ticket_count]"]').val('');
            $('input[name="draw[ticket_price]"]').val('');
            $('input[name="draw[product_id]"]').val('');
            $('.wclp-prize-count .wclp-current-prizes').text($('.wclp-prizes .wclp-prize').length);
            $('.wclp-max-prizes').text(Math.floor(100 * 0.15));
            return;
        }

        $.ajax({
            url: wclp_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'wclp_get_product_variation_data',
                nonce: wclp_admin.nonce,
                product_id: productId,
                lottery_id: lotteryId
            },
            success: function(response) {
                if (response.success) {
                    $('input[name="draw[ticket_count]"]').val(response.data.ticket_count).trigger('change');
                    $('input[name="draw[ticket_price]"]').val(Math.floor(response.data.ticket_price));
                    $('input[name="draw[product_id]"]').val(productId);
                    $('.wclp-prize-count .wclp-current-prizes').text($('.wclp-prizes .wclp-prize').length);
                    $('.wclp-max-prizes').text(Math.floor(response.data.ticket_count * 0.15));
                    formChanged = true;
                } else {
                    $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>' + (response.data.message || wclp_admin.error_fetching_product || 'Error fetching product data') + '</p></div>');
                    setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
                }
            },
            error: function(xhr, status, error) {
                $('.wclp-table-container').prepend('<div class="notice notice-error is-dismissible"><p>Error fetching product data: ' + (xhr.responseText || error) + '</p></div>');
                setTimeout(function() { $('.notice').fadeOut(500, function() { $(this).remove(); }); }, 5000);
            }
        });
    });
});