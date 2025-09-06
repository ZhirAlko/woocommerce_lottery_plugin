/*
 * WooCommerce Contest Plugin 2.2.2, Generated: July 30, 2025
 * Front-end JavaScript for contest interactions
 */
jQuery(document).ready(function($) {
    // Track processing state
    let isProcessing = {};

    // Sync cart with localStorage for non-logged-in users
    function syncLocalStorage(lotteryId, numbers) {
        if (!get_current_user_id()) {
            localStorage.setItem(`wclp_cart_${lotteryId}`, JSON.stringify(numbers || []));
        }
    }

    // Get cart from localStorage
    function getLocalStorageCart(lotteryId) {
        const stored = localStorage.getItem(`wclp_cart_${lotteryId}`);
        try {
            const cart = stored ? JSON.parse(stored) : [];
            return Array.isArray(cart) ? cart.filter(item => /^[0-9]+$/.test(String(item))) : [];
        } catch (e) {
            return [];
        }
    }

    // Update coupon visibility
    function updateCouponVisibility(lotteryId) {
        const $couponToggle = $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-coupon-toggle`);
        const $couponForm = $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-coupon-form`);
        const $couponsList = $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-coupons-list`);
        $.ajax({
            url: wclp_params.ajax_url,
            method: 'POST',
            data: {
                action: 'wclp_get_coupon',
                nonce: wclp_params.nonce,
                lottery_id: lotteryId
            },
            success: function(response) {
                if (response.success && response.data.coupons.length > 0) {
                    $couponToggle.addClass('hidden');
                    $couponForm.addClass('active');
                    $couponsList.empty();
                    response.data.coupons.forEach(code => {
                        $couponsList.append(`<div class="wclp-coupon-item">${code}</div>`);
                    });
                } else {
                    $couponToggle.removeClass('hidden');
                    $couponForm.removeClass('active');
                    $couponsList.empty();
                }
            },
            error: function(xhr, status, error) {
                // Silent
            }
        });
    }

    // Check user by field
    function checkUser(lotteryId, field, value) {
        const $field = $(`.wclp-cart[data-lottery-id="${lotteryId}"] input[name="${field}"]`);
        const $message = $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-coupon-message`);
        $.ajax({
            url: wclp_params.ajax_url,
            method: 'POST',
            data: {
                action: 'wclp_check_user',
                nonce: wclp_params.nonce,
                field: field,
                value: value,
                email: $(`.wclp-cart[data-lottery-id="${lotteryId}"] input[name="email"]`).val()
            },
            success: function(response) {
                if (response.success) {
                    $field.css('border-color', '#28a745').data('validated', true);
                    if (response.data.user) {
                        $(`.wclp-cart[data-lottery-id="${lotteryId}"] input[name="phone"]`).val(response.data.user.phone).data('validated', true);
                        $(`.wclp-cart[data-lottery-id="${lotteryId}"] input[name="first_name"]`).val(response.data.user.first_name).data('validated', true);
                        $(`.wclp-cart[data-lottery-id="${lotteryId}"] input[name="email"]`).val(response.data.user.email).data('validated', true);
                    }
                } else {
                    $field.css('border-color', '').data('validated', false).removeClass('error');
                    if (response.data.message === wclp_params.error_duplicate_phone) {
                        $message.html(`<span class="error">${response.data.message}</span>`).show();
                        setTimeout(() => $message.hide(), 4000);
                    }
                }
            },
            error: function(xhr, status, error) {
                $field.css('border-color', '').removeClass('error');
                $message.html(`<span class="error">${wclp_params.error_checkout}</span>`).show();
                setTimeout(() => $message.hide(), 4000);
            }
        });
    }

    // Initialize cart
    $('.wclp-cart').each(function() {
        const lotteryId = $(this).data('lottery-id');
        isProcessing[lotteryId] = false;

        // Initialize grid from localStorage for non-logged-in users
        if (!get_current_user_id() && wclp_params.cart && !wclp_params.cart[lotteryId]) {
            wclp_params.cart[lotteryId] = getLocalStorageCart(lotteryId);
        }

        // Sync cart with server
        updateCart(lotteryId, 'init');
        if (window.innerWidth < 479 && !localStorage.getItem('wclp_cart_collapsed_' + lotteryId)) {
            $(this).addClass('wclp-cart-collapsed');
            localStorage.setItem('wclp_cart_collapsed_' + lotteryId, true);
        }

        // Initialize coupon visibility only on load
        if ($(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-coupon-form`).hasClass('active')) {
            return;
        }
        updateCouponVisibility(lotteryId);

        // Set default payment method on load
        const $paymentSelect = $(this).find('.wclp-cart-payment select');
        if ($paymentSelect.length && $paymentSelect.val() === '') {
            $paymentSelect.val(wclp_params.default_gateway || $paymentSelect.find('option').eq(0).val());
            $paymentSelect.trigger('change');
        }
    });

    // Debounce clicks (max ~2.5 clicks per second)
    function debounceClick(lotteryId, callback) {
        if (isProcessing[lotteryId]) return;
        isProcessing[lotteryId] = true;
        callback();
    }

    // Update cart (add, remove, clear, init)
    function updateCart(lotteryId, action, nomer = null) {
        const $cartItems = $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-cart-items`);
        const $cartTotal = $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-cart-total`);
        const $grid = $(`.wclp-grid[data-lottery-id="${lotteryId}"]`);
        const $message = $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-coupon-message`);
        $cartItems.addClass('pending');
        $grid.addClass('pending');

        // Update only relevant tickets
        const updateTickets = (numbers) => {
            if (action === 'add' && nomer) {
                const ticketNomer = String(nomer);
                $(`.wclp-grid[data-lottery-id="${lotteryId}"] .wclp-ticket[data-nomer="${ticketNomer}"]`)
                    .addClass('selected').removeClass('available').attr('title', wclp_params.remove_text);
            } else if (action === 'remove' && nomer) {
                const ticketNomer = String(nomer);
                $(`.wclp-grid[data-lottery-id="${lotteryId}"] .wclp-ticket[data-nomer="${ticketNomer}"]`)
                    .addClass('available').removeClass('selected').attr('title', wclp_params.add_text);
            } else if (action === 'clear') {
                $(`.wclp-grid[data-lottery-id="${lotteryId}"] .wclp-ticket:not(.sold)`)
                    .addClass('available').removeClass('selected').attr('title', wclp_params.add_text);
            } else if (action === 'init') {
                $(`.wclp-grid[data-lottery-id="${lotteryId}"] .wclp-ticket`).each(function() {
                    const ticketNomer = String($(this).data('nomer'));
                    if (numbers.map(String).includes(ticketNomer)) {
                        $(this).addClass('selected').removeClass('available').attr('title', wclp_params.remove_text);
                    } else if (!$(this).hasClass('sold')) {
                        $(this).addClass('available').removeClass('selected').attr('title', wclp_params.add_text);
                    }
                });
            }
        };

        // Initialize from wclp_params.cart
        if (action === 'init' && wclp_params.cart && wclp_params.cart[lotteryId]) {
            updateTickets(wclp_params.cart[lotteryId]);
        }

        const clientCart = getLocalStorageCart(lotteryId);

        setTimeout(() => {
            $.ajax({
                url: wclp_params.ajax_url,
                method: 'POST',
                data: {
                    action: 'wclp_update_cart',
                    nonce: wclp_params.nonce,
                    lottery_id: lotteryId,
                    update_action: action,
                    nomer: nomer,
                    cart: clientCart
                },
                success: function(response) {
                    if (response.success) {
                        $cartItems.empty();
                        response.data.numbers.forEach(nomer => {
                            const ticketNomer = String(nomer);
                            $cartItems.append(`
                                <div class="wclp-cart-item animate__animated animate__slideIn" data-nomer="${ticketNomer}">
                                    ${ticketNomer} <span class="wclp-remove" title="${wclp_params.remove_text}">×</span>
                                </div>
                            `);
                        });
                        $cartTotal.html(`${wclp_params.total_text} ${response.data.total}`);
                        updateTickets(response.data.numbers);
                        syncLocalStorage(lotteryId, response.data.numbers);
                    } else {
                        $message.html(`<span class="error">${response.data.message || wclp_params.error_updating_cart}</span>`).show();
                        setTimeout(() => $message.hide(), 4000);
                    }
                    $cartItems.removeClass('pending');
                    $grid.removeClass('pending');
                    isProcessing[lotteryId] = false;
                },
                error: function(xhr, status, error) {
                    $message.html(`<span class="error">${wclp_params.error_updating_cart}</span>`).show();
                    setTimeout(() => $message.hide(), 4000);
                    $cartItems.removeClass('pending');
                    $grid.removeClass('pending');
                    isProcessing[lotteryId] = false;
                }
            });
        }, 500);
    }

    // Toggle cart
    $(document).on('click', '.wclp-toggle-cart', function() {
        const $cart = $(this).closest('.wclp-cart');
        $cart.toggleClass('wclp-cart-collapsed animate__animated animate__slideInUp');
        localStorage.setItem('wclp_cart_collapsed_' + $cart.data('lottery-id'), $cart.hasClass('wclp-cart-collapsed'));
    });

    // Toggle coupon form
    $(document).on('click', '.wclp-coupon-toggle', function(e) {
        e.preventDefault();
        const $cart = $(this).closest('.wclp-cart');
        const lotteryId = $cart.data('lottery-id');
        $cart.find('.wclp-coupon-toggle').addClass('hidden');
        $cart.find('.wclp-coupon-form').addClass('active');
    });

    // Grid ticket click
    $(document).on('click', '.wclp-ticket.available, .wclp-ticket.selected', function() {
        if ($(this).closest('.wclp-grid').hasClass('pending') || $(this).closest('.wclp-grid').hasClass('wclp-grid-disabled')) return;
        if ($(this).hasClass('sold')) {
            const $message = $(`.wclp-cart[data-lottery-id="${$(this).closest('.wclp-grid').data('lottery-id')}"] .wclp-coupon-message`);
            $message.html(`<span class="error">${wclp_params.ticket_sold_text}</span>`).show();
            setTimeout(() => $message.hide(), 4000);
            return;
        }

        const $ticket = $(this);
        const lotteryId = $ticket.closest('.wclp-grid').data('lottery-id');
        const nomer = String($ticket.data('nomer'));
        const action = $ticket.hasClass('selected') ? 'remove' : 'add';

        debounceClick(lotteryId, () => {
            $ticket.addClass('pending');
            if (action === 'add') {
                $ticket.removeClass('available').addClass('selected').attr('title', wclp_params.remove_text);
                $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-cart-items`).append(
                    `<div class="wclp-cart-item animate__animated animate__slideIn" data-nomer="${nomer}">
                        ${nomer} <span class="wclp-remove" title="${wclp_params.remove_text}">×</span>
                    </div>`
                );
            } else {
                const $item = $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-cart-items .wclp-cart-item[data-nomer="${nomer}"]`);
                $item.addClass('animate__animated animate__fadeOut');
                setTimeout(() => $item.remove(), 500);
                $ticket.removeClass('selected').addClass('available pending').attr('title', wclp_params.add_text);
            }
            updateCart(lotteryId, action, nomer);
        });
    });

    // Remove ticket from cart
    $(document).on('click', '.wclp-cart-item, .wclp-cart-item .wclp-remove', function() {
        if ($(this).closest('.wclp-cart-items').hasClass('pending')) return;
        const $item = $(this).hasClass('wclp-cart-item') ? $(this) : $(this).closest('.wclp-cart-item');
        const lotteryId = $item.closest('.wclp-cart').data('lottery-id');
        const nomer = String($item.data('nomer'));

        debounceClick(lotteryId, () => {
            $item.addClass('animate__animated animate__fadeOut');
            setTimeout(() => $item.remove(), 500);
            $(`.wclp-grid[data-lottery-id="${lotteryId}"] .wclp-ticket[data-nomer="${nomer}"]`)
                .removeClass('selected').addClass('available pending').attr('title', wclp_params.add_text);
            updateCart(lotteryId, 'remove', nomer);
        });
    });

    // Clear cart
    $(document).on('click', '.wclp-clear-cart', function() {
        if ($(this).closest('.wclp-cart-items').hasClass('pending')) return;
        const lotteryId = $(this).closest('.wclp-cart').data('lottery-id');

        debounceClick(lotteryId, () => {
            $(`.wclp-cart[data-lottery-id="${lotteryId}"] .wclp-cart-items .wclp-cart-item`).addClass('animate__animated animate__fadeOut');
            setTimeout(() => updateCart(lotteryId, 'clear'), 500);
        });
    });

    // Apply coupon
    $(document).on('click', '.wclp-apply-coupon', function() {
        if ($(this).closest('.wclp-cart-items').hasClass('pending')) return;
        const $cart = $(this).closest('.wclp-cart');
        const lotteryId = $cart.data('lottery-id');
        const $couponCode = $cart.find('.wclp-coupon-code');
        const code = $couponCode.val();
        const $message = $cart.find('.wclp-coupon-message');

        $.ajax({
            url: wclp_params.ajax_url,
            method: 'POST',
            data: {
                action: 'wclp_apply_coupon',
                nonce: wclp_params.nonce,
                lottery_id: lotteryId,
                code: code
            },
            success: function(response) {
                if (response.success) {
                    $message.html(`<span class="success">Применён купон ${code}</span>`).show();
                    $couponCode.val('');
                    $cart.find('.wclp-coupon-form').removeClass('active');
                    $cart.find('.wclp-coupon-toggle').removeClass('hidden');
                    updateCouponVisibility(lotteryId);
                    updateCart(lotteryId, 'init');
                } else {
                    $couponCode.css('border-color', '#dc3545').addClass('error');
                    $message.html(`<span class="error">${response.data.message || wclp_params.error_applying_coupon}</span>`).show();
                    $cart.addClass('animate__shakeX');
                    setTimeout(() => {
                        $cart.removeClass('animate__shakeX');
                        $message.hide();
                    }, 4000);
                }
            },
            error: function(xhr, status, error) {
                $couponCode.css('border-color', '#dc3545').addClass('error');
                $message.html(`<span class="error">${wclp_params.error_applying_coupon}</span>`).show();
                setTimeout(() => $message.hide(), 4000);
            }
        });
    });

    // Check user fields
    $(document).on('input', '.wclp-checkout-form input[name="phone"], .wclp-checkout-form input[name="email"]', function() {
        const $input = $(this);
        const lotteryId = $input.closest('.wclp-cart').data('lottery-id');
        const field = $input.attr('name');
        const value = $input.val();
        if (value.length > 2) {
            checkUser(lotteryId, field, value);
        } else {
            $input.css('border-color', '').data('validated', false).removeClass('error');
        }
    });

    // Checkout
    $(document).on('submit', '.wclp-checkout-form', function(e) {
        e.preventDefault();
        if ($(this).closest('.wclp-cart-items').hasClass('pending')) return;
        const $form = $(this);
        const lotteryId = $form.data('lottery-id');
        const $message = $form.closest('.wclp-cart').find('.wclp-coupon-message');
        const $button = $form.find('.wclp-checkout');
        const originalText = $button.text();
        $button.text('Обработка...').prop('disabled', true);
        $form.addClass('pending');
        const userId = get_current_user_id();
        const cartNumbers = (wclp_params.cart && wclp_params.cart[lotteryId]) ? wclp_params.cart[lotteryId] : getLocalStorageCart(lotteryId);

        if (!cartNumbers || cartNumbers.length === 0) {
            const emptyMessage = wclp_params.error_empty_cart || 'Не выбрано ни одного номера';
            $message.html(`<span class="error">${emptyMessage}</span>`).show();
            setTimeout(() => $message.hide(), 4000);
            $button.text(originalText).prop('disabled', false);
            $form.removeClass('pending');
            return;
        }

        if (!userId && $form.find('input[name="phone"]').length) {
            const $inputs = $form.find('input[name="phone"], input[name="first_name"], input[name="email"]');
            const validated = $inputs.filter('[data-validated="true"]').length > 0;
            const allFilled = $inputs.filter(function() { return $(this).val().length > 0; }).length === 3;

            if (!validated && !allFilled) {
                $inputs.each(function() {
                    if ($(this).val().length === 0) {
                        $(this).css('border-color', '#dc3545').addClass('error');
                    }
                });
                $message.html(`<span class="error">${wclp_params.error_missing_fields}</span>`).show();
                setTimeout(() => $message.hide(), 4000);
                $button.text(originalText).prop('disabled', false);
                $form.removeClass('pending');
                return;
            }
        }

        const formData = $form.serializeArray();
        const data = {
            action: 'wclp_create_order',
            nonce: wclp_params.nonce,
            lottery_id: lotteryId
        };

        formData.forEach(function(item) {
            data[item.name] = item.value;
        });

        $.ajax({
            url: wclp_params.ajax_url,
            method: 'POST',
            data: data,
            success: function(response) {
                $button.text(originalText).prop('disabled', false);
                $form.removeClass('pending');
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    $message.html(`<span class="error">${response.data.message || wclp_params.error_checkout}</span>`).show();
                    setTimeout(() => $message.hide(), 4000);
                }
            },
            error: function(xhr, status, error) {
                $button.text(originalText).prop('disabled', false);
                $form.removeClass('pending');
                $message.html(`<span class="error">${wclp_params.error_checkout}</span>`).show();
                setTimeout(() => $message.hide(), 4000);
            }
        });
    });

    // Set default payment method
    $(document).on('change', '.wclp-cart-payment select', function() {
        const $select = $(this);
        if (wclp_params.default_gateway && $select.find(`option[value="${wclp_params.default_gateway}"]`).length) {
            $select.val(wclp_params.default_gateway);
        } else if ($select.find('option').length > 0) {
            $select.val($select.find('option').eq(0).val());
        }
    });

    // Mock get_current_user_id for client-side
    function get_current_user_id() {
        return wclp_params.user_id || 0;
    }
});