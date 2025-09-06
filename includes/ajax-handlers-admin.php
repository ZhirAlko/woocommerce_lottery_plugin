<?php
/**
 * AJAX handlers for admin operations in WooCommerce Lottery Plugin
 * WooCommerce Lottery Plugin 2.2.1-beta, Generated: 2025-07-02 08:15:00 EEST
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create lottery product
 */
function wclp_create_lottery_product() {
    check_ajax_referer('wclp_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        error_log('WCLP: Create lottery product failed: Insufficient permissions');
        wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
    }

    $ticket_count = absint($_POST['ticket_count'] ?? 100);
    $ticket_price = absint($_POST['ticket_price'] ?? 0);
    $base_name = sanitize_text_field($_POST['base_name'] ?? '');

    if ($ticket_count <= 0 || $ticket_price < 0) {
        error_log('WCLP: Create lottery product failed: Invalid ticket count or price');
        wp_send_json_error(['message' => __('Неверное количество или цена билетов', 'woocommerce-lottery')]);
    }

    $product_id = WCLP_Lottery_Manager::create_lottery_product($ticket_count, $ticket_price, $base_name);
    if (is_wp_error($product_id)) {
        error_log('WCLP: Create lottery product failed: ' . $product_id->get_error_message());
        wp_send_json_error(['message' => $product_id->get_error_message()]);
    }

    $product = wc_get_product($product_id);
    wp_send_json_success([
        'product_id' => $product_id,
        'product_name' => $product->get_name(),
        'ticket_count' => $ticket_count,
        'ticket_price' => $ticket_price
    ]);
}
add_action('wp_ajax_wclp_create_lottery_product', 'wclp_create_lottery_product');

/**
 * Activate lottery
 */
function wclp_activate_lottery() {
    check_ajax_referer('wclp_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        error_log('WCLP: Activate lottery failed: Insufficient permissions');
        wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
    }

    $lottery_id = absint($_POST['lottery_id'] ?? 0);
    if (!$lottery_id) {
        error_log('WCLP: Activate lottery failed: Invalid lottery ID');
        wp_send_json_error(['message' => __('Неверный ID лотереи', 'woocommerce-lottery')]);
    }

    $result = WCLP_Lottery_Manager::activate_lottery($lottery_id);
    if (is_wp_error($result)) {
        error_log('WCLP: Activate lottery failed: ' . $result->get_error_message());
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success(['message' => __('Лотерея активирована', 'woocommerce-lottery')]);
}
add_action('wp_ajax_wclp_activate_lottery', 'wclp_activate_lottery');

/**
 * Draw lottery
 */
function wclp_draw_lottery() {
    check_ajax_referer('wclp_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        error_log('WCLP: Draw lottery failed: Insufficient permissions');
        wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
    }

    $lottery_id = absint($_POST['lottery_id'] ?? 0);
    if (!$lottery_id) {
        error_log('WCLP: Draw lottery failed: Invalid lottery ID');
        wp_send_json_error(['message' => __('Неверный ID лотереи', 'woocommerce-lottery')]);
    }

    $use_random_org = get_option('wclp_use_random_org', 0);
    $result = WCLP_Results::draw_lottery($lottery_id, $use_random_org);
    if (is_wp_error($result)) {
        error_log('WCLP: Draw lottery failed: ' . $result->get_error_message());
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success(['message' => __('Лотерея успешно разыграна', 'woocommerce-lottery')]);
}
add_action('wp_ajax_wclp_draw_lottery', 'wclp_draw_lottery');

/**
 * Test random generator
 */
function wclp_test_random_generator() {
    check_ajax_referer('wclp_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        error_log('WCLP: Test random generator failed: Insufficient permissions');
        wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
    }

    $total_numbers = absint($_POST['total_numbers'] ?? 10);
    $winners_count = absint($_POST['winners_count'] ?? 1);
    $exclusions = isset($_POST['exclusions']) ? sanitize_text_field($_POST['exclusions']) : '';

    if ($total_numbers < 1 || $winners_count < 1 || $winners_count > $total_numbers) {
        error_log('WCLP: Test random generator failed: Invalid input parameters');
        wp_send_json_error(['message' => __('Неверные параметры ввода', 'woocommerce-lottery')]);
    }

    $exclusions_array = array_filter(array_map('intval', explode(',', $exclusions)));
    $numbers = range(1, $total_numbers);
    $numbers = array_diff($numbers, $exclusions_array);
    $numbers = array_values($numbers);

    if (count($numbers) < $winners_count) {
        error_log('WCLP: Test random generator failed: Too many exclusions');
        wp_send_json_error(['message' => __('Слишком много исключений для указанного количества выигрышей', 'woocommerce-lottery')]);
    }

    $use_random_org = get_option('wclp_use_random_org', 0);
    $selected_numbers = [];

    if ($use_random_org) {
        $api_key = get_option('wclp_random_org_api_key', '');
        if (!$api_key) {
            error_log('WCLP: Test random generator failed: Random.org API key not set');
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
            error_log('WCLP: Test random generator failed: Random.org API error: ' . $response->get_error_message());
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
            error_log('WCLP: Test random generator failed: Invalid Random.org response: ' . print_r($body, true));
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
add_action('wp_ajax_wclp_test_random_generator', 'wclp_test_random_generator');

/**
 * Get product variation data
 */
function wclp_get_product_variation_data() {
    check_ajax_referer('wclp_admin_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        error_log('WCLP: Get product variation data failed: Insufficient permissions');
        wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
    }

    $product_id = absint($_POST['product_id'] ?? 0);
    $lottery_id = absint($_POST['lottery_id'] ?? 0);
    if (!$product_id) {
        error_log('WCLP: Get product variation data failed: Invalid product ID');
        wp_send_json_error(['message' => __('Неверный ID товара', 'woocommerce-lottery')]);
    }

    $product = wc_get_product($product_id);
    if (!$product || $product->get_type() !== 'variable') {
        error_log('WCLP: Get product variation data failed: Invalid product type');
        wp_send_json_error(['message' => __('Неверный вариативный товар', 'woocommerce-lottery')]);
    }

    global $wpdb;
    $query = $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}wclp_lotteries WHERE product_id = %d AND lottery_status IN ('active', 'ready_to_draw') AND id != %d",
        $product_id, $lottery_id
    );
    $active_lottery = $wpdb->get_var($query);
    if ($active_lottery && $lottery_id != $active_lottery) {
        error_log('WCLP: Get product variation data failed: Product used in another active lottery');
        wp_send_json_error(['message' => __('Товар уже используется в другой активной лотерее', 'woocommerce-lottery')]);
    }

    $variations = $product->get_children();
    $nomer_variations = array_filter($variations, function($vid) {
        $variation = wc_get_product($vid);
        return $variation && $variation->get_attribute('nomer') !== '';
    });

    if (empty($nomer_variations)) {
        error_log('WCLP: Get product variation data failed: No variations with nomer attribute');
        wp_send_json_error(['message' => __('Не найдены вариации с атрибутом nomer', 'woocommerce-lottery')]);
    }

    $prices = array_map(function($vid) {
        $variation = wc_get_product($vid);
        return floatval($variation->get_regular_price() ?: 0);
    }, $nomer_variations);
    $average_price = array_sum($prices) / count($prices);

    $data = [
        'ticket_count' => count($nomer_variations),
        'ticket_price' => floor($average_price)
    ];
    wp_send_json_success($data);
}
add_action('wp_ajax_wclp_get_product_variation_data', 'wclp_get_product_variation_data');

/**
 * Get lotteries table
 */
function wclp_get_lotteries() {
    check_ajax_referer('wclp_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        error_log('WCLP: Get lotteries failed: Insufficient permissions');
        wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
    }

    global $wpdb;
    $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'updated_at';
    $sort_order = isset($_POST['sort_order']) ? sanitize_text_field($_POST['sort_order']) : 'DESC';

    $valid_sort_columns = ['id', 'lottery_status', 'updated_at'];
    if (!in_array($sort_by, $valid_sort_columns)) {
        $sort_by = 'updated_at';
    }
    $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

    $query = "SELECT l.id, l.name, l.product_id, l.lottery_status, l.ticket_count, l.ticket_price, l.prizes, l.draw_type, l.start_date, l.end_date, l.auto_draw, COUNT(r.id) as sold_tickets 
              FROM {$wpdb->prefix}wclp_lotteries l 
              LEFT JOIN {$wpdb->prefix}wclp_reservations r ON l.id = r.lottery_id AND r.order_id IS NOT NULL";
    if ($status_filter) {
        $query .= $wpdb->prepare(" WHERE l.lottery_status = %s", $status_filter);
    }
    $query .= " GROUP BY l.id ORDER BY $sort_by $sort_order";
    $lotteries = $wpdb->get_results($query);

    $results = $wpdb->get_results("SELECT lottery_id, winners FROM {$wpdb->prefix}wclp_results WHERE winners IS NOT NULL");
    $winners = [];
    foreach ($results as $result) {
        $winner_data = json_decode($result->winners, true);
        if ($winner_data) {
            foreach ($winner_data as &$winner) {
                $reservation = $wpdb->get_row($wpdb->prepare(
                    "SELECT user_id, user_phone FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND nomer = %s",
                    $result->lottery_id, $winner['nomer']
                ));
                if ($reservation && $reservation->user_id) {
                    $user = get_userdata($reservation->user_id);
                    $winner['name'] = $user ? $user->display_name : __('Неизвестный', 'woocommerce-lottery');
                    $winner['phone'] = $reservation->user_phone ?: get_user_meta($reservation->user_id, 'billing_phone', true) ?: __('N/A', 'woocommerce-lottery');
                    if ($winner['phone'] !== __('N/A', 'woocommerce-lottery')) {
                        $winner['phone'] = substr($winner['phone'], -4);
                    }
                } else {
                    $winner['name'] = __('Неизвестный', 'woocommerce-lottery');
                    $winner['phone'] = __('N/A', 'woocommerce-lottery');
                }
            }
            $winners[$result->lottery_id] = $winner_data;
        }
    }

    ob_start();
    ?>
    <table class="wp-list-table widefat striped wclp-admin-table">
        <thead>
            <tr>
                <th class="id column-id" data-sort="id"><?php _e('ID', 'woocommerce-lottery'); ?> <span class="sorting-indicator"></span></th>
                <th class="name column-name"><?php _e('Название', 'woocommerce-lottery'); ?></th>
                <th class="product column-product"><?php _e('Товар', 'woocommerce-lottery'); ?></th>
                <th class="prizes column-prizes"><?php _e('Призы', 'woocommerce-lottery'); ?></th>
                <th class="status column-status" data-sort="lottery_status"><?php _e('Статус', 'woocommerce-lottery'); ?> <span class="sorting-indicator"></span></th>
                <th class="tickets-sold column-tickets-sold"><?php _e('Билеты/Проданы', 'woocommerce-lottery'); ?></th>
                <th class="draw-details column-draw-details"><?php _e('Детали розыгрыша', 'woocommerce-lottery'); ?></th>
                <th class="winners column-winners"><?php _e('Победители', 'woocommerce-lottery'); ?></th>
                <th class="shortcode column-shortcode"><?php _e('Шорткод', 'woocommerce-lottery'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lotteries as $lottery): ?>
                <tr>
                    <td class="id column-id"><?php echo esc_html($lottery->id); ?></td>
                    <td class="name column-name">
                        <?php echo esc_html($lottery->name); ?>
                        <div class="row-actions">
                            <span class="edit"><a href="#" class="wclp-edit-lottery" data-id="<?php echo esc_attr($lottery->id); ?>"><?php _e('Редактировать', 'woocommerce-lottery'); ?></a> | </span>
                            <span class="clone"><a href="#" class="wclp-clone-lottery" data-id="<?php echo esc_attr($lottery->id); ?>"><?php _e('Клонировать', 'woocommerce-lottery'); ?></a> | </span>
                            <span class="delete"><a href="?page=wclp_draws&action=delete&id=<?php echo esc_attr($lottery->id); ?>" onclick="return confirm('<?php _e('Вы уверены?', 'woocommerce-lottery'); ?>');"><?php _e('Удалить', 'woocommerce-lottery'); ?></a> | </span>
                            <?php if ($lottery->lottery_status === 'draft'): ?>
                                <span class="activate"><a href="#" class="wclp-activate-lottery" data-lottery-id="<?php echo esc_attr($lottery->id); ?>"><?php _e('Активировать', 'woocommerce-lottery'); ?></a></span>
                            <?php elseif (in_array($lottery->lottery_status, ['active', 'ready_to_draw'])): ?>
                                <span class="draw"><a href="#" class="wclp-draw-lottery" data-lottery-id="<?php echo esc_attr($lottery->id); ?>"><?php _e('Провести розыгрыш', 'woocommerce-lottery'); ?></a></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="product column-product">
                        <?php
                        $product = wc_get_product($lottery->product_id);
                        echo $product ? esc_html($product->get_name()) : __('Нет товара', 'woocommerce-lottery');
                        ?>
                    </td>
                    <td class="prizes column-prizes" data-colname="Призы">
                        <?php
                        $prizes = json_decode($lottery->prizes ?: '[]', true);
                        if (!empty($prizes)):
                            echo '<ol>';
                            foreach ($prizes as $prize):
                                if (!empty($prize['name'])):
                                    echo '<li>' . esc_html($prize['name']) . '</li>';
                                endif;
                            endforeach;
                            echo '</ol>';
                        else:
                            _e('Нет призов', 'woocommerce-lottery');
                        endif;
                        ?>
                    </td>
                    <td class="status column-status"><?php echo esc_html(ucfirst($lottery->lottery_status)); ?></td>
                    <td class="tickets-sold column-tickets-sold"><?php echo esc_html($lottery->ticket_count); ?> @ <?php echo wc_price($lottery->ticket_price); ?> (<?php echo esc_html($lottery->sold_tickets); ?> <?php _e('продано', 'woocommerce-lottery'); ?>)</td>
                    <td class="draw-details column-draw-details">
                        <?php
                        echo esc_html($lottery->draw_type === 'sold' ? __('Продано', 'woocommerce-lottery') : __('По дате', 'woocommerce-lottery'));
                        echo '<br>';
                        echo __('Авто-розыгрыш: ', 'woocommerce-lottery') . ($lottery->auto_draw ? __('Да', 'woocommerce-lottery') : __('Нет', 'woocommerce-lottery'));
                        if ($lottery->draw_type === 'date'):
                            if ($lottery->start_date) {
                                echo '<br>' . __('Начало: ', 'woocommerce-lottery') . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($lottery->start_date)));
                            }
                            if ($lottery->end_date) {
                                echo '<br>' . __('Конец: ', 'woocommerce-lottery') . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($lottery->end_date)));
                            }
                        endif;
                        ?>
                    </td>
                    <td class="winners column-winners">
                        <?php
                        if ($lottery->lottery_status === 'completed' && isset($winners[$lottery->id])):
                            echo '<ol>';
                            foreach ($winners[$lottery->id] as $winner):
                                echo '<li>' . esc_html($winner['nomer']) . ': ' . esc_html($winner['name']) . ' (' . esc_html($winner['phone']) . ')</li>';
                            endforeach;
                            echo '</ol>';
                        else:
                            _e('N/A', 'woocommerce-lottery');
                        endif;
                        ?>
                    </td>
                    <td class="shortcode column-shortcode">
                        <select class="wclp-shortcode-select">
                            <option value="[lottery id=<?php echo esc_attr($lottery->id); ?> type=page]"><?php _e('Страница', 'woocommerce-lottery'); ?></option>
                            <option value="[lottery id=<?php echo esc_attr($lottery->id); ?> type=terms]"><?php _e('Правила', 'woocommerce-lottery'); ?></option>
                            <option value="[lottery id=<?php echo esc_attr($lottery->id); ?> type=grid]"><?php _e('Сетка', 'woocommerce-lottery'); ?></option>
                            <option value="[lottery id=<?php echo esc_attr($lottery->id); ?> type=cart]"><?php _e('Корзина', 'woocommerce-lottery'); ?></option>
                            <option value="[lottery id=<?php echo esc_attr($lottery->id); ?> type=results]"><?php _e('Результаты', 'woocommerce-lottery'); ?></option>
                            <option value="[lottery id=<?php echo esc_attr($lottery->id); ?> type=history]"><?php _e('История', 'woocommerce-lottery'); ?></option>
                        </select>
                        <button class="button wclp-copy-shortcode"><?php _e('Копировать', 'woocommerce-lottery'); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    $content = ob_get_clean();
    wp_send_json_success(['content' => $content]);
}
add_action('wp_ajax_wclp_get_lotteries', 'wclp_get_lotteries');

/**
 * Render lottery form via AJAX
 */
function wclp_render_form() {
    check_ajax_referer('wclp_admin_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        error_log('WCLP: Render form failed: Insufficient permissions');
        wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
    }

    $id = absint($_POST['id'] ?? 0);
    $clone_id = absint($_POST['clone_id'] ?? 0);

    error_log('WCLP: Rendering form for id: ' . $id . ', clone_id: ' . $clone_id);

    ob_start();
    WCLP_Admin_Form::render_form($id, $clone_id);
    $content = ob_get_clean();

    if (empty($content)) {
        error_log('WCLP: Render form failed: Empty content');
        wp_send_json_error(['message' => __('Ошибка генерации формы', 'woocommerce-lottery')]);
    }

    wp_send_json_success(['content' => $content]);
}
add_action('wp_ajax_wclp_render_form', 'wclp_render_form');

/**
 * Get product data for prize autofill
 */
function wclp_get_product_data() {
    check_ajax_referer('wclp_admin_nonce', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        error_log('WCLP: Get product data failed: Insufficient permissions');
        wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
    }

    $product_id = absint($_POST['product_id'] ?? 0);
    if (!$product_id) {
        error_log('WCLP: Get product data failed: Invalid product ID');
        wp_send_json_error(['message' => __('Неверный ID товара', 'woocommerce-lottery')]);
    }

    $product = wc_get_product($product_id);
    if (!$product) {
        error_log('WCLP: Get product data failed: Invalid product');
        wp_send_json_error(['message' => __('Неверный товар', 'woocommerce-lottery')]);
    }

    $data = [
        'name' => $product->get_name(),
        'description' => wp_strip_all_tags($product->get_description()),
        'image' => wp_get_attachment_url($product->get_image_id()) ?: ''
    ];
    wp_send_json_success($data);
}
add_action('wp_ajax_wclp_get_product_data', 'wclp_get_product_data');

/**
 * Transliterate string for username
 */
function wclp_transliterate($string) {
    $trans = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
        'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
        'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts',
        'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch', 'Ы' => 'Y', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya'
    ];
    return preg_replace('/[^a-zA-Z0-9]/', '', strtr($string, $trans));
}