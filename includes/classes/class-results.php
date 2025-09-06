<?php
/**
 * Class WCLP_Results
 * Handles contest results and drawing
 * WooCommerce Lottery Plugin 2.2.2, Generated: July 30, 2025
 */

class WCLP_Results {
    /**
     * Initialize results handling
     */
    public static function init() {
        add_action('wclp_check_auto_draw', ['WCLP_Results', 'check_auto_draw']);
        add_action('wp_ajax_wclp_test_random_generator', ['WCLP_Results', 'test_random_generator']);
        if (!wp_next_scheduled('wclp_check_auto_draw')) {
            wp_schedule_event(time(), 'hourly', 'wclp_check_auto_draw');
        }
    }

    /**
     * Check all contests for auto-draw
     */
    public static function check_auto_draw() {
        global $wpdb;
        $lotteries = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}wclp_lotteries WHERE auto_draw = 1 AND lottery_status IN ('active', 'ready_to_draw')");
        foreach ($lotteries as $lottery) {
            self::check_and_draw($lottery->id);
        }
    }

    /**
     * Check and draw contest if ready
     * @param int $lottery_id
     */
    public static function check_and_draw($lottery_id) {
        global $wpdb;
        $lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
        if (!$lottery || !in_array($lottery['lottery_status'], ['active', 'ready_to_draw'])) {
            return;
        }

        $is_ready = false;
        if ($lottery['draw_type'] === 'sold') {
            $sold_tickets = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND order_id IS NOT NULL",
                $lottery_id
            ));
            $is_ready = $sold_tickets >= $lottery['ticket_count'];
        } elseif ($lottery['draw_type'] === 'date' && $lottery['end_date']) {
            $is_ready = current_time('timestamp') >= strtotime($lottery['end_date']);
        }

        if ($is_ready && $lottery['auto_draw']) {
            self::draw_lottery($lottery_id, get_option('wclp_use_random_org', 0));
        }
    }

    /**
     * Draw contest
     * @param int $lottery_id
     * @param bool $use_random_org
     * @return bool|WP_Error
     */
    public static function draw_lottery($lottery_id, $use_random_org = false) {
        global $wpdb;
        $lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
        if (!$lottery) {
            return new WP_Error('invalid_lottery', __('Неверный конкурс', 'woocommerce-lottery'));
        }

        if (!in_array($lottery['lottery_status'], ['active', 'ready_to_draw'])) {
            return new WP_Error('invalid_status', __('Конкурс не в статусе для подведения итогов', 'woocommerce-lottery'));
        }

        // Collect nomers
        $orders = wc_get_orders(['meta_key' => 'lottery_id', 'meta_value' => $lottery_id, 'status' => ['wc-completed', 'wc-processing']]);
        $nomers = [];
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $nomer = $item->get_meta('nomer');
                if ($nomer) {
                    $nomers[] = $nomer;
                }
            }
        }
        if ($lottery['include_unsold']) {
            $product = wc_get_product($lottery['product_id']);
            if ($product) {
                $variations = $product->get_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if ($variation && $variation->get_stock_quantity() > 0) {
                        $nomer = $variation->get_attribute('nomer');
                        if ($nomer) {
                            $nomers[] = $nomer;
                        }
                    }
                }
            }
        }

        $nomers = array_unique($nomers);
        if (empty($nomers)) {
            return new WP_Error('no_tickets', __('Не найдено действительных билетов', 'woocommerce-lottery'));
        }

        // Select winners
        $winners = [];
        $winners_count = min(count($lottery['prizes']), count($nomers));

        if ($use_random_org) {
            $api_key = get_option('wclp_random_org_api_key', '');
            if (!$api_key) {
                return new WP_Error('no_api_key', __('Ключ API Random.org не настроен', 'woocommerce-lottery'));
            }

            $response = wp_remote_post('https://api.random.org/json-rpc/4/invoke', [
                'body' => wp_json_encode([
                    'jsonrpc' => '2.0',
                    'method' => 'generateIntegers',
                    'params' => [
                        'apiKey' => $api_key,
                        'n' => $winners_count,
                        'min' => 0,
                        'max' => count($nomers) - 1,
                        'replacement' => false
                    ],
                    'id' => 1
                ]),
                'headers' => ['Content-Type' => 'application/json']
            ]);

            if (is_wp_error($response)) {
                return new WP_Error('api_error', __('Не удалось подключиться к Random.org', 'woocommerce-lottery'));
            }

            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);
            if (isset($result['result']['random']['data'])) {
                $indices = $result['result']['random']['data'];
                foreach ($indices as $index) {
                    $nomer = $nomers[$index];
                    $order = self::get_order_by_nomer($lottery_id, $nomer);
                    $winners[] = [
                        'nomer' => $nomer,
                        'prize_id' => $lottery['prizes'][count($winners)]['id'],
                        'user_id' => $order ? $order->get_user_id() : 0,
                        'user_name' => $order ? $order->get_billing_first_name() : __('Непродан', 'woocommerce-lottery'),
                        'user_phone' => $order ? substr($order->get_billing_phone(), -4) : ''
                    ];
                }
            } else {
                return new WP_Error('api_error', __('Неверный ответ от Random.org', 'woocommerce-lottery'));
            }
        } else {
            $indices = [];
            for ($i = 0; $i < $winners_count; $i++) {
                $index = random_int(0, count($nomers) - 1);
                while (in_array($index, $indices)) {
                    $index = random_int(0, count($nomers) - 1);
                }
                $indices[] = $index;
                $nomer = $nomers[$index];
                $order = self::get_order_by_nomer($lottery_id, $nomer);
                $winners[] = [
                    'nomer' => $nomer,
                    'prize_id' => $lottery['prizes'][$i]['id'],
                    'user_id' => $order ? $order->get_user_id() : 0,
                    'user_name' => $order ? $order->get_billing_first_name() : __('Непродан', 'woocommerce-lottery'),
                    'user_phone' => $order ? substr($order->get_billing_phone(), -4) : ''
                ];
            }
        }

        // Save results
        $result = $wpdb->insert(
            $wpdb->prefix . 'wclp_results',
            [
                'lottery_id' => $lottery_id,
                'draw_date' => current_time('mysql'),
                'winners' => wp_json_encode($winners, JSON_UNESCAPED_SLASHES),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            wc_get_logger()->error('WCLP: Failed to save results for contest ID: ' . $lottery_id, ['source' => 'wclp']);
            return new WP_Error('db_error', __('Не удалось сохранить результаты конкурса', 'woocommerce-lottery'));
        }

        // Update contest status
        $result = $wpdb->update(
            $wpdb->prefix . 'wclp_lotteries',
            ['lottery_status' => 'completed', 'last_draw_id' => $wpdb->insert_id, 'updated_at' => current_time('mysql')],
            ['id' => $lottery_id],
            ['%s', '%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            wc_get_logger()->error('WCLP: Failed to update contest status for ID: ' . $lottery_id, ['source' => 'wclp']);
            return new WP_Error('db_error', __('Не удалось обновить статус конкурса', 'woocommerce-lottery'));
        }

        // Send notifications
        self::send_notifications($lottery_id, $winners);
        return true;
    }

    /**
     * Test random generator
     */
    public static function test_random_generator() {
        check_ajax_referer('wclp_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
        }

        $total_numbers = absint($_POST['total_numbers'] ?? 10);
        $winners_count = absint($_POST['winners_count'] ?? 1);
        $exclusions = isset($_POST['exclusions']) ? sanitize_text_field($_POST['exclusions']) : '';

        if ($total_numbers < 1 || $winners_count < 1 || $winners_count > $total_numbers) {
            wp_send_json_error(['message' => __('Неверное количество номеров или победителей', 'woocommerce-lottery')]);
        }

        $exclusions_array = array_filter(array_map('intval', explode(',', $exclusions)));
        $numbers = range(1, $total_numbers);
        $numbers = array_diff($numbers, $exclusions_array);
        $numbers = array_values($numbers);

        if (count($numbers) < $winners_count) {
            wp_send_json_error(['message' => __('Слишком много исключений для указанного количества победителей', 'woocommerce-lottery')]);
        }

        $use_random_org = get_option('wclp_use_random_org', 0);
        $selected_numbers = [];

        if ($use_random_org) {
            $api_key = get_option('wclp_random_org_api_key', '');
            if (!$api_key) {
                wp_send_json_error(['message' => __('Ключ API Random.org не настроен', 'woocommerce-lottery')]);
            }

            $response = wp_remote_post('https://api.random.org/json-rpc/4/invoke', [
                'body' => wp_json_encode([
                    'jsonrpc' => '2.0',
                    'method' => 'generateIntegers',
                    'params' => [
                        'apiKey' => $api_key,
                        'n' => $winners_count,
                        'min' => 0,
                        'max' => count($numbers) - 1,
                        'replacement' => false
                    ],
                    'id' => 1
                ]),
                'headers' => ['Content-Type' => 'application/json']
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error(['message' => __('Не удалось подключиться к Random.org: ', 'woocommerce-lottery') . $response->get_error_message()]);
            }

            $body = wp_remote_retrieve_body($response);
            $result = json_decode($body, true);
            if (isset($result['result']['random']['data'])) {
                $indices = $result['result']['random']['data'];
                foreach ($indices as $index) {
                    $selected_numbers[] = $numbers[$index];
                }
            } else {
                wp_send_json_error(['message' => __('Неверный ответ от Random.org', 'woocommerce-lottery')]);
            }
        } else {
            $indices = [];
            for ($i = 0; $i < $winners_count; $i++) {
                $index = random_int(0, count($numbers) - 1);
                while (in_array($index, $indices)) {
                    $index = random_int(0, count($numbers) - 1);
                }
                $indices[] = $index;
                $selected_numbers[] = $numbers[$index];
            }
        }

        wp_send_json_success([
            'numbers' => $selected_numbers,
            'generator' => $use_random_org ? 'Random.org' : 'Internal (random_int)'
        ]);
    }

    /**
     * Get order by nomer
     * @param int $lottery_id
     * @param string $nomer
     * @return WC_Order|null
     */
    private static function get_order_by_nomer($lottery_id, $nomer) {
        $orders = wc_get_orders(['meta_key' => 'lottery_id', 'meta_value' => $lottery_id, 'status' => ['wc-completed', 'wc-processing']]);
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_meta('nomer') === $nomer) {
                    return $order;
                }
            }
        }
        return null;
    }

    /**
     * Send notifications
     */
    private static function send_notifications($lottery_id, $winners) {
        $lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
        if (!$lottery) {
            wc_get_logger()->error('WCLP: Failed to send notifications, invalid contest ID: ' . $lottery_id, ['source' => 'wclp']);
            return;
        }
        $settings = $lottery['settings'] ?? [];
        $headers = 'Content-Type: text/html; charset=UTF-8';
        if (!empty($settings['notify_winners'])) {
            foreach ($winners as $winner) {
                if ($winner['user_id']) {
                    $user = get_userdata($winner['user_id']);
                    if ($user && $user->user_email) {
                        $email_content = include WCLP_PLUGIN_DIR . 'templates/emails/winner-notification.php';
                        wc_mail($user->user_email, __('Вы выиграли в конкурсе!', 'woocommerce-lottery'), $email_content, $headers, '');
                    }
                }
            }
        }
        if (!empty($settings['notify_admin'])) {
            $email_content = include WCLP_PLUGIN_DIR . 'templates/emails/admin-notification.php';
            wc_mail(get_option('admin_email'), __('Результаты конкурса', 'woocommerce-lottery'), $email_content, $headers, '');
        }
    }
}

add_action('plugins_loaded', ['WCLP_Results', 'init']);