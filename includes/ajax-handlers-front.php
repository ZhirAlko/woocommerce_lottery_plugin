<?php
/**
 * AJAX handlers for frontend operations in WooCommerce Contest Plugin
 * WooCommerce Lottery Plugin 2.2.2, Generated: July 30, 2025
 */

if (!defined('ABSPATH')) {
    exit;
}

function wclp_normalize_phone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    $phone = substr($phone, -10);
    if (strlen($phone) !== 10) {
        return false;
    }
    return '+7' . $phone;
}

function wclp_get_coupon() {
    check_ajax_referer('wclp_nonce', 'nonce');
    $lottery_id = absint($_POST['lottery_id'] ?? 0);

    if (!$lottery_id) {
        wp_send_json_error(['message' => __('Invalid request', 'woocommerce-lottery')]);
    }

    if (function_exists('WC') && WC()->session) {
        if (!WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    }

    $coupons = WC()->session ? WC()->session->get('wclp_promo_codes', []) : [];
    wp_send_json_success(['coupons' => $coupons]);
}
add_action('wp_ajax_wclp_get_coupon', 'wclp_get_coupon');
add_action('wp_ajax_nopriv_wclp_get_coupon', 'wclp_get_coupon');

function wclp_check_user() {
    check_ajax_referer('wclp_nonce', 'nonce');
    $field = sanitize_text_field($_POST['field'] ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');

    if (!$field || !$value || !in_array($field, ['phone', 'email'])) {
        wp_send_json_error(['message' => __('Invalid request', 'woocommerce-lottery')]);
    }

    global $wpdb;
    $user = null;
    if ($field === 'phone') {
        $normalized_phone = wclp_normalize_phone($value);
        if (!$normalized_phone) {
            wp_send_json_error(['message' => __('Invalid phone number', 'woocommerce-lottery')]);
        }
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'billing_phone' AND meta_value = %s",
            $normalized_phone
        ));
        if ($users) {
            $user = get_user_by('id', $users[0]->user_id);
            if ($email && $user && $user->user_email !== $email) {
                wp_send_json_error(['message' => __('This phone number is already used with another email', 'woocommerce-lottery')]);
            }
        }
    } elseif ($field === 'email') {
        $user = get_user_by('email', $value);
        if ($user) {
            wp_send_json_error(['message' => __('This email is already registered', 'woocommerce-lottery')]);
        }
    }

    if ($user) {
        wp_send_json_success([
            'user' => [
                'phone' => get_user_meta($user->ID, 'billing_phone', true),
                'first_name' => get_user_meta($user->ID, 'first_name', true) ?: get_user_meta($user->ID, 'billing_first_name', true),
                'email' => $user->user_email,
                'user_id' => $user->ID
            ]
        ]);
    } else {
        wp_send_json_error(['message' => __('User not found', 'woocommerce-lottery')]);
    }
}
add_action('wp_ajax_wclp_check_user', 'wclp_check_user');
add_action('wp_ajax_nopriv_wclp_check_user', 'wclp_check_user');

function wclp_update_cart() {
    check_ajax_referer('wclp_nonce', 'nonce');
    $lottery_id = absint($_POST['lottery_id'] ?? 0);
    $action = sanitize_text_field($_POST['update_action'] ?? 'init');
    $nomer = sanitize_text_field($_POST['nomer'] ?? '');
    $client_cart = isset($_POST['cart']) && is_array($_POST['cart']) ? array_map('sanitize_text_field', $_POST['cart']) : [];

    if (!$lottery_id) {
        wp_send_json_error(['message' => __('Invalid request', 'woocommerce-lottery')]);
    }

    $lottery = wp_cache_get('wclp_lottery_' . $lottery_id);
    if ($lottery === false) {
        $lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
        if (!$lottery) {
            wp_send_json_error(['message' => __('Invalid contest', 'woocommerce-lottery')]);
        }
        wp_cache_set('wclp_lottery_' . $lottery_id, $lottery, '', 300);
    }

    if (function_exists('WC') && WC()->session) {
        if (!WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    } else {
        wp_send_json_error(['message' => __('Session error', 'woocommerce-lottery')]);
    }

    global $wpdb;
    $cart = WC()->session->get('wclp_cart', []);
    $cart[$lottery_id] = $cart[$lottery_id] ?? [];
    $numbers = $cart[$lottery_id];

    if (!get_current_user_id() && !empty($client_cart)) {
        $numbers = array_unique(array_merge($numbers, array_filter($client_cart, function($item) {
            return preg_match('/^[0-9]+$/', $item);
        })));
    }

    if ($action === 'add' && $nomer) {
        $reserved = wp_cache_get('wclp_reserved_' . $lottery_id . '_' . $nomer);
        if ($reserved === false) {
            $reserved = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND nomer = %s AND expires_at > %s AND order_id IN (
                    SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-completed', 'wc-processing')
                )",
                $lottery_id, $nomer, current_time('mysql')
            ));
            wp_cache_set('wclp_reserved_' . $lottery_id . '_' . $nomer, $reserved, '', 300);
        }
        if ($reserved) {
            wp_send_json_error(['message' => __('Ticket already reserved', 'woocommerce-lottery')]);
        }

        if ($lottery['ticket_limit_per_user'] > 0) {
            $reserved_count = 0;
            if (get_current_user_id()) {
                $reserved_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND user_id = %d AND expires_at > %s AND order_id IN (
                        SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-completed', 'wc-processing')
                    )",
                    $lottery_id, get_current_user_id(), current_time('mysql')
                ));
            } else {
                $guest_tickets = WC()->session->get('wclp_guest_tickets', []);
                $reserved_count = $guest_tickets[$lottery_id] ?? 0;
            }
            if (count($numbers) + $reserved_count >= $lottery['ticket_limit_per_user']) {
                wp_send_json_error(['message' => __('Ticket limit reached', 'woocommerce-lottery')]);
            }
        }

        if (!in_array($nomer, $numbers)) {
            $numbers[] = $nomer;
            if (!get_current_user_id()) {
                $guest_tickets = WC()->session->get('wclp_guest_tickets', []);
                $guest_tickets[$lottery_id] = ($guest_tickets[$lottery_id] ?? 0) + 1;
                WC()->session->set('wclp_guest_tickets', $guest_tickets);
            }
        }
    } elseif ($action === 'remove' && $nomer) {
        $numbers = array_diff($numbers, [$nomer]);
        if (!get_current_user_id()) {
            $guest_tickets = WC()->session->get('wclp_guest_tickets', []);
            if (isset($guest_tickets[$lottery_id]) && $guest_tickets[$lottery_id] > 0) {
                $guest_tickets[$lottery_id]--;
                WC()->session->set('wclp_guest_tickets', $guest_tickets);
            }
        }
    } elseif ($action === 'clear') {
        $numbers = [];
        if (!get_current_user_id()) {
            $guest_tickets = WC()->session->get('wclp_guest_tickets', []);
            $guest_tickets[$lottery_id] = 0;
            WC()->session->set('wclp_guest_tickets', $guest_tickets);
        }
    }

    $numbers = array_unique($numbers);
    $cart[$lottery_id] = $numbers;
    WC()->session->set('wclp_cart', $cart);
    WC()->session->save_data();

    $total = count($numbers) * $lottery['ticket_price'];
    $coupons = WC()->session->get('wclp_promo_codes', []);
    $discount = 0;
    foreach ($coupons as $code) {
        $coupon = new WC_Coupon($code);
        if ($coupon->is_valid()) {
            $discount += $coupon->get_discount_amount($total);
        }
    }

    wp_send_json_success([
        'numbers' => array_values(array_map('strval', $numbers)),
        'total' => wc_price($total - $discount)
    ]);
}
add_action('wp_ajax_wclp_update_cart', 'wclp_update_cart');
add_action('wp_ajax_nopriv_wclp_update_cart', 'wclp_update_cart');

function wclp_apply_coupon() {
    check_ajax_referer('wclp_nonce', 'nonce');
    $code = sanitize_text_field($_POST['code'] ?? '');
    $lottery_id = absint($_POST['lottery_id'] ?? 0);

    if (!$lottery_id || !$code) {
        wp_send_json_error(['message' => __('Invalid request', 'woocommerce-lottery'), 'animation' => 'shake']);
    }

    $lottery = wp_cache_get('wclp_lottery_' . $lottery_id);
    if ($lottery === false) {
        $lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
        if (!$lottery) {
            wp_send_json_error(['message' => __('Invalid contest', 'woocommerce-lottery'), 'animation' => 'shake']);
        }
        wp_cache_set('wclp_lottery_' . $lottery_id, $lottery, '', 300);
    }

    if (function_exists('WC') && WC()->session) {
        if (!WC()->session->has_session()) {
            WC()->session->set_customer_session_cookie(true);
        }
    }

    $coupon = new WC_Coupon($code);
    if (!$coupon->get_id() || !$coupon->is_valid()) {
        wp_send_json_error(['message' => __('Invalid coupon', 'woocommerce-lottery'), 'animation' => 'shake']);
    }

    $coupons = WC()->session->get('wclp_promo_codes', []);
    if (!in_array($code, $coupons)) {
        $coupons[] = $code;
        WC()->session->set('wclp_promo_codes', $coupons);
    }

    $cart = WC()->session->get('wclp_cart', []);
    $numbers = $cart[$lottery_id] ?? [];
    $total = count($numbers) * $lottery['ticket_price'];
    $discount = 0;
    foreach ($coupons as $c) {
        $coupon = new WC_Coupon($c);
        if ($coupon->is_valid()) {
            $discount += $coupon->get_discount_amount($total);
        }
    }

    WC()->session->save_data();

    wp_send_json_success([
        'message' => __('Coupon applied', 'woocommerce-lottery'),
        'total' => wc_price($total - $discount),
        'coupons' => $coupons
    ]);
}
add_action('wp_ajax_wclp_apply_coupon', 'wclp_apply_coupon');
add_action('wp_ajax_nopriv_wclp_apply_coupon', 'wclp_apply_coupon');

function wclp_create_order() {
    check_ajax_referer('wclp_nonce', 'nonce');
    $lottery_id = absint($_POST['lottery_id'] ?? 0);
    $payment_method = sanitize_text_field($_POST['payment_method'] ?? '');
    $user_data = [
        'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
        'email' => sanitize_email($_POST['email'] ?? ''),
        'terms' => isset($_POST['terms']) && $_POST['terms'] === '1' ? 1 : 0
    ];

    try {
        if (!$lottery_id || !$payment_method || !$user_data['terms']) {
            throw new Exception(__('Invalid request, payment method, or terms not accepted', 'woocommerce-lottery'));
        }

        $lottery = wp_cache_get('wclp_lottery_' . $lottery_id);
        if ($lottery === false) {
            $lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
            if (!$lottery) {
                throw new Exception(__('Invalid contest', 'woocommerce-lottery'));
            }
            wp_cache_set('wclp_lottery_' . $lottery_id, $lottery, '', 300);
        }

        if (function_exists('WC') && WC()->session) {
            if (!WC()->session->has_session()) {
                WC()->session->set_customer_session_cookie(true);
            }
        } else {
            throw new Exception(__('Session error', 'woocommerce-lottery'));
        }

        $cart = WC()->session->get('wclp_cart', []);
        $numbers = $cart[$lottery_id] ?? [];
        if (empty($numbers)) {
            throw new Exception(__('No tickets selected', 'woocommerce-lottery'));
        }

        // Проверка всех резервов перед созданием заказа
        WCLP_Cart::sync_and_clean_reservations();

        global $wpdb;
        foreach ($numbers as $nomer) {
            $reserved = wp_cache_get('wclp_reserved_' . $lottery_id . '_' . $nomer);
            if ($reserved === false) {
                $reserved = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND nomer = %s AND expires_at > %s AND order_id IN (
                        SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-completed', 'wc-processing')
                    )",
                    $lottery_id, $nomer, current_time('mysql')
                ));
                wp_cache_set('wclp_reserved_' . $lottery_id . '_' . $nomer, $reserved, '', 300);
            }
            if ($reserved) {
                throw new Exception(sprintf(__('Ticket %s is no longer available', 'woocommerce-lottery'), $nomer));
            }
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            $normalized_phone = wclp_normalize_phone($user_data['phone']);
            if (!$normalized_phone) {
                throw new Exception(__('Invalid phone number', 'woocommerce-lottery'));
            }
            $user_data['phone'] = $normalized_phone;

            if (empty($user_data['email']) || empty($user_data['first_name']) || empty($user_data['phone'])) {
                throw new Exception(__('Please fill in phone, first name, and email', 'woocommerce-lottery'));
            }

            $user = get_user_by('email', $user_data['email']);
            if ($user) {
                $user_phone = get_user_meta($user->ID, 'billing_phone', true);
                if ($user_phone === $normalized_phone) {
                    // Log in existing user
                    wp_set_current_user($user->ID);
                    wp_set_auth_cookie($user->ID);
                    $user_id = $user->ID;
                } else {
                    throw new Exception(__('This email is already registered with another phone', 'woocommerce-lottery'));
                }
            } else {
                // Create new user
                $email_parts = explode('@', $user_data['email']);
                $username = sanitize_user($email_parts[0] ?: 'user', true);
                $base_username = $username;
                $counter = 1;
                while (username_exists($username)) {
                    $username = $base_username . $counter++;
                }

                $temp_password = wp_generate_password(12, true);
                $user_id = wp_create_user($username, $temp_password, $user_data['email']);
                if (is_wp_error($user_id)) {
                    throw new Exception($user_id->get_error_message());
                }

                update_user_meta($user_id, 'first_name', $user_data['first_name']);
                update_user_meta($user_id, 'billing_first_name', $user_data['first_name']);
                update_user_meta($user_id, 'billing_email', $user_data['email']);
                update_user_meta($user_id, 'billing_phone', $normalized_phone);
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);

                // Send welcome email
                $headers = 'Content-Type: text/html; charset=UTF-8';
                $email_content = include WCLP_PLUGIN_DIR . 'templates/emails/welcome.php';
                wc_mail($user_data['email'], __('Ваш аккаунт создан', 'woocommerce-lottery'), $email_content, $headers, '');
            }
        }

        $order = wc_create_order();
        $order->set_customer_id($user_id);
        $order->set_billing_first_name($user_data['first_name'] ?: get_user_meta($user_id, 'first_name', true) ?: get_user_meta($user_id, 'billing_first_name', true));
        $order->set_billing_email($user_data['email'] ?: get_user_meta($user_id, 'billing_email', true));
        $order->set_billing_phone($user_data['phone'] ?: get_user_meta($user_id, 'billing_phone', true));

        $product = wc_get_product($lottery['product_id']);
        if (!$product) {
            throw new Exception(__('Invalid product', 'woocommerce-lottery'));
        }

        $hold_stock_minutes = get_option('woocommerce_hold_stock_minutes', 15);
        $expires_at = date('Y-m-d H:i:s', strtotime(current_time('mysql') . " +$hold_stock_minutes minutes"));
        $total = 0;
        foreach ($numbers as $nomer) {
            $variation_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation' AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'attribute_nomer' AND meta_value = %s
                )",
                $lottery['product_id'], $nomer
            ));
            if ($variation_id) {
                $variation = wc_get_product($variation_id);
                if ($variation && $variation->get_stock_quantity() > 0) {
                    $item_id = $order->add_product($variation, 1, [
                        'subtotal' => $lottery['ticket_price'],
                        'total' => $lottery['ticket_price']
                    ]);
                    wc_add_order_item_meta($item_id, 'nomer', $nomer);

                    $user_phone = $user_data['phone'] ?: get_user_meta($user_id, 'billing_phone', true) ?: 'N/A';
                    $wpdb->insert(
                        $wpdb->prefix . 'wclp_reservations',
                        [
                            'lottery_id' => $lottery_id,
                            'nomer' => $nomer,
                            'user_id' => $user_id,
                            'user_phone' => $user_phone,
                            'order_id' => $order->get_id(),
                            'expires_at' => $expires_at,
                            'created_at' => current_time('mysql')
                        ],
                        ['%d', '%s', '%d', '%s', '%d', '%s', '%s']
                    );
                    if ($wpdb->last_error) {
                        throw new Exception(__('Failed to save reservation', 'woocommerce-lottery'));
                    }
                    $total += $lottery['ticket_price'];
                }
            }
        }

        $coupons = WC()->session->get('wclp_promo_codes', []);
        foreach ($coupons as $code) {
            $order->apply_coupon($code);
        }

        $order->add_meta_data('lottery_id', $lottery_id);
        $order->set_payment_method($payment_method);
        $order->calculate_totals();
        $order->save();

        unset($cart[$lottery_id]);
        WC()->session->set('wclp_cart', $cart);
        WC()->session->set('wclp_promo_codes', []);
        WC()->session->save_data();

        if ($order->get_total() == 0) {
            $order->update_status('processing', __('Free order processed', 'woocommerce-lottery'));
            wp_send_json_success(['redirect' => $order->get_view_order_url()]);
        } else {
            $gateway = WC()->payment_gateways()->payment_gateways()[$payment_method];
            $result = $gateway->process_payment($order->get_id());
            if ($result && isset($result['redirect'])) {
                wp_send_json_success(['redirect' => $result['redirect']]);
            } else {
                throw new Exception(__('Payment processing failed', 'woocommerce-lottery'));
            }
        }
    } catch (Exception $e) {
        wc_get_logger()->error('WCLP: Create order failed: ' . $e->getMessage(), ['source' => 'wclp']);
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
add_action('wp_ajax_wclp_create_order', 'wclp_create_order');
add_action('wp_ajax_nopriv_wclp_create_order', 'wclp_create_order');