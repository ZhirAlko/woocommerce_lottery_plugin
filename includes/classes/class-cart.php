<?php
/**
 * Class WCLP_Cart
 * Handles contest cart operations
 * WooCommerce Lottery Plugin 2.2.2, Generated: July 30, 2025
 */

class WCLP_Cart {
    /**
     * Initialize cart actions
     */
    public static function init() {
        add_action('wp_ajax_wclp_add_to_cart', [__CLASS__, 'add_to_cart']);
        add_action('wp_ajax_nopriv_wclp_add_to_cart', [__CLASS__, 'add_to_cart']);
        add_action('wp_ajax_wclp_remove_from_cart', [__CLASS__, 'remove_from_cart']);
        add_action('wp_ajax_nopriv_wclp_remove_from_cart', [__CLASS__, 'remove_from_cart']);
        add_action('wp_ajax_wclp_clear_cart', [__CLASS__, 'clear_cart']);
        add_action('wp_ajax_nopriv_wclp_clear_cart', [__CLASS__, 'clear_cart']);
        add_action('wp_cron', [__CLASS__, 'sync_and_clean_reservations']);
    }

    /**
     * Add ticket to cart
     */
    public static function add_to_cart() {
        check_ajax_referer('wclp_nonce', 'nonce');
        $lottery_id = intval($_POST['lottery_id']);
        $nomer = sanitize_text_field($_POST['nomer']);
        $user_id = get_current_user_id();

        $lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
        if (!$lottery || !in_array($lottery['lottery_status'], ['active', 'ready_to_draw'])) {
            wp_send_json_error(['message' => __('Invalid or inactive contest', 'woocommerce-lottery')]);
        }

        global $wpdb;
        $reserved = wp_cache_get('wclp_reserved_' . $lottery_id . '_' . $nomer);
        if ($reserved === false) {
            $reserved = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND nomer = %s AND expires_at > %s",
                $lottery_id, $nomer, current_time('mysql')
            ));
            wp_cache_set('wclp_reserved_' . $lottery_id . '_' . $nomer, $reserved, '', 300); // Cache for 5 min
        }

        if ($reserved) {
            wp_send_json_error(['message' => __('Ticket already reserved', 'woocommerce-lottery')]);
        }

        if ($lottery['ticket_limit_per_user'] > 0) {
            $reserved_count = 0;
            if ($user_id) {
                $reserved_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND user_id = %d AND expires_at > %s",
                    $lottery_id, $user_id, current_time('mysql')
                ));
            } else {
                $guest_tickets = WC()->session->get('wclp_guest_tickets', []);
                $reserved_count = $guest_tickets[$lottery_id] ?? 0;
            }
            if ($reserved_count >= $lottery['ticket_limit_per_user']) {
                wp_send_json_error(['message' => __('Ticket limit reached', 'woocommerce-lottery')]);
            }
        }

        $hold_stock_minutes = get_option('woocommerce_hold_stock_minutes', 15);
        $expires_at = date('Y-m-d H:i:s', strtotime(current_time('mysql') . " +$hold_stock_minutes minutes"));
        $wpdb->insert(
            $wpdb->prefix . 'wclp_reservations',
            [
                'lottery_id' => $lottery_id,
                'nomer' => $nomer,
                'user_id' => $user_id ?: null,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%s', '%s']
        );
        if ($wpdb->last_error) {
            wc_get_logger()->error('WCLP: Failed to insert reservation for contest ID ' . $lottery_id . ', nomer ' . $nomer . ': ' . $wpdb->last_error, ['source' => 'wclp']);
            wp_send_json_error(['message' => __('Failed to reserve ticket', 'woocommerce-lottery')]);
        }

        $cart = WC()->session->get('wclp_cart', []);
        $cart[$lottery_id][] = $nomer;
        WC()->session->set('wclp_cart', $cart);
        WC()->session->save_data();

        wp_send_json_success(['message' => __('Ticket added', 'woocommerce-lottery')]);
    }

    /**
     * Remove ticket from cart
     */
    public static function remove_from_cart() {
        check_ajax_referer('wclp_nonce', 'nonce');
        $lottery_id = intval($_POST['lottery_id']);
        $nomer = sanitize_text_field($_POST['nomer']);

        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'wclp_reservations',
            ['lottery_id' => $lottery_id, 'nomer' => $nomer],
            ['%d', '%s']
        );

        $cart = WC()->session->get('wclp_cart', []);
        if (isset($cart[$lottery_id])) {
            $cart[$lottery_id] = array_diff($cart[$lottery_id], [$nomer]);
            if (empty($cart[$lottery_id])) {
                unset($cart[$lottery_id]);
            }
            WC()->session->set('wclp_cart', $cart);
            WC()->session->save_data();
        }

        $variation_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_parent = (SELECT product_id FROM {$wpdb->prefix}wclp_lotteries WHERE id = %d) AND post_type = 'product_variation' AND ID IN (
                SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'attribute_nomer' AND meta_value = %s
            )",
            $lottery_id, $nomer
        ));
        if ($variation_id) {
            $current_stock = get_post_meta($variation_id, '_stock', true);
            if ($current_stock <= 0 || $current_stock === '') {
                wc_update_product_stock($variation_id, 1);
            }
        }

        wp_send_json_success(['message' => __('Ticket removed', 'woocommerce-lottery')]);
    }

    /**
     * Clear cart
     */
    public static function clear_cart() {
        check_ajax_referer('wclp_nonce', 'nonce');
        $lottery_id = intval($_POST['lottery_id']);
        $user_id = get_current_user_id();

        global $wpdb;
        $reservations = $wpdb->get_results($wpdb->prepare(
            "SELECT nomer FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND user_id = %d",
            $lottery_id, $user_id
        ));
        foreach ($reservations as $reservation) {
            $variation_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_parent = (SELECT product_id FROM {$wpdb->prefix}wclp_lotteries WHERE id = %d) AND post_type = 'product_variation' AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'attribute_nomer' AND meta_value = %s
                )",
                $lottery_id, $reservation->nomer
            ));
            if ($variation_id) {
                $current_stock = get_post_meta($variation_id, '_stock', true);
                if ($current_stock <= 0 || $current_stock === '') {
                    wc_update_product_stock($variation_id, 1);
                }
            }
        }

        $wpdb->delete(
            $wpdb->prefix . 'wclp_reservations',
            ['lottery_id' => $lottery_id, 'user_id' => $user_id],
            ['%d', '%d']
        );

        $cart = WC()->session->get('wclp_cart', []);
        unset($cart[$lottery_id]);
        WC()->session->set('wclp_cart', $cart);
        WC()->session->save_data();

        wp_send_json_success(['message' => __('Cart cleared', 'woocommerce-lottery')]);
    }

    /**
     * Sync and clean expired reservations
     */
    public static function sync_and_clean_reservations() {
        global $wpdb;
        $active_lotteries = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wclp_lotteries WHERE lottery_status IN ('active', 'ready_to_draw')");
        if (!$active_lotteries) {
            return;
        }

        // Clean expired or invalid reservations
        $reservations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id IN (SELECT id FROM {$wpdb->prefix}wclp_lotteries WHERE lottery_status IN ('active', 'ready_to_draw'))");
        foreach ($reservations as $reservation) {
            $order = $reservation->order_id ? wc_get_order($reservation->order_id) : false;
            $should_delete = false;

            if ($order) {
                $status = $order->get_status();
                if (in_array($status, ['cancelled', 'failed']) || ($status === 'pending' && strtotime($reservation->expires_at) < time())) {
                    $should_delete = true;
                }
            } else {
                if (strtotime($reservation->expires_at) < time()) {
                    $should_delete = true;
                }
            }

            if ($should_delete) {
                if ($order && $order->get_status() === 'pending') {
                    wc_cancel_order($order->get_id());
                }

                $wpdb->delete(
                    $wpdb->prefix . 'wclp_reservations',
                    ['id' => $reservation->id],
                    ['%d']
                );

                $variation_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_parent = (SELECT product_id FROM {$wpdb->prefix}wclp_lotteries WHERE id = %d) AND post_type = 'product_variation' AND ID IN (
                        SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'attribute_nomer' AND meta_value = %s
                    )",
                    $reservation->lottery_id, $reservation->nomer
                ));
                if ($variation_id) {
                    $current_stock = get_post_meta($variation_id, '_stock', true);
                    if ($current_stock <= 0 || $current_stock === '') {
                        wc_update_product_stock($variation_id, 1);
                    }
                }
            }
        }

        // Sync completed orders (including manual)
        $lotteries = $wpdb->get_results("SELECT id, product_id FROM {$wpdb->prefix}wclp_lotteries WHERE lottery_status IN ('active', 'ready_to_draw')");
        foreach ($lotteries as $lottery) {
            $orders = wc_get_orders([
                'status' => ['wc-completed', 'wc-processing'],
                'limit' => -1
            ]);
            foreach ($orders as $order) {
                $has_lottery_product = false;
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    if ($product_id == $lottery->product_id) {
                        $has_lottery_product = true;
                        break;
                    }
                }
                if ($has_lottery_product) {
                    update_post_meta($order->get_id(), 'lottery_id', $lottery->id);
                    foreach ($order->get_items() as $item) {
                        $nomer = $item->get_meta('nomer');
                        if ($nomer) {
                            $exists = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND nomer = %s",
                                $lottery->id, $nomer
                            ));
                            if (!$exists) {
                                $user_phone = get_user_meta($order->get_customer_id(), 'billing_phone', true) ?: 'N/A';
                                $wpdb->insert(
                                    $wpdb->prefix . 'wclp_reservations',
                                    [
                                        'lottery_id' => $lottery->id,
                                        'nomer' => $nomer,
                                        'user_id' => $order->get_customer_id() ?: 0,
                                        'user_phone' => $user_phone,
                                        'order_id' => $order->get_id(),
                                        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
                                        'created_at' => current_time('mysql')
                                    ],
                                    ['%d', '%s', '%d', '%s', '%d', '%s', '%s']
                                );
                                if ($wpdb->last_error) {
                                    wc_get_logger()->error('WCLP: Failed to insert reservation for contest ID ' . $lottery->id . ': ' . $wpdb->last_error, ['source' => 'wclp']);
                                }
                            }
                            $variation_id = $wpdb->get_var($wpdb->prepare(
                                "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation' AND ID IN (
                                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'attribute_nomer' AND meta_value = %s
                                )",
                                $lottery->product_id, $nomer
                            ));
                            if ($variation_id) {
                                wc_update_product_stock($variation_id, 0);
                            }
                        }
                    }
                }
            }
        }
    }
}

// Инициализация
add_action('init', ['WCLP_Cart', 'init']);