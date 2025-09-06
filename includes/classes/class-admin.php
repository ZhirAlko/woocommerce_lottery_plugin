<?php
/**
 * Class WCLP_Admin
 * Handles admin page rendering
 * WooCommerce Lottery Plugin 2.2.1-beta, Generated: 2025-07-02 05:34:00 EEST
 */

if (!class_exists('WCLP_Admin')) {
    class WCLP_Admin {
        /**
         * Initialize admin
         */
        public static function init() {
            add_action('wp_ajax_wclp_draw_lottery', [self::class, 'ajax_draw_lottery']);
        }

        /**
         * Render admin page
         */
        public static function render_admin_page() {
            global $wpdb;
            $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
            $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
            $clone_id = isset($_GET['clone_id']) ? absint($_GET['clone_id']) : 0;

            if ($action === 'delete' && $id) {
                WCLP_Lottery_Manager::delete_lottery($id);
                wp_safe_redirect(add_query_arg(['page' => 'wclp_draws'], admin_url('admin.php')));
                exit;
            }

            // Handle edit/clone via AJAX in admin.js
            $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
            $sort_by = isset($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : 'updated_at';
            $sort_order = isset($_GET['sort_order']) ? sanitize_text_field($_GET['sort_order']) : 'DESC';

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
            ?>
            <div class="wrap wclp-admin-wrap">
                <h1><?php _e('Лотереи', 'woocommerce-lottery'); ?> <a href="#" class="page-title-action wclp-create-lottery"><?php _e('Добавить новую', 'woocommerce-lottery'); ?></a></h1>
                <?php if (isset($_GET['saved'])): ?>
                    <div class="notice notice-success is-dismissible"><p><?php _e('Лотерея успешно сохранена', 'woocommerce-lottery'); ?></p></div>
                <?php endif; ?>
                <div class="wclp-filter-controls" style="margin: 20px 0;">
                    <label for="wclp-status-filter"><?php _e('Фильтр по статусу', 'woocommerce-lottery'); ?>:</label>
                    <select id="wclp-status-filter">
                        <option value=""><?php _e('Все', 'woocommerce-lottery'); ?></option>
                        <option value="draft" <?php selected($status_filter, 'draft'); ?>><?php _e('Черновик', 'woocommerce-lottery'); ?></option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>><?php _e('Активна', 'woocommerce-lottery'); ?></option>
                        <option value="ready_to_draw" <?php selected($status_filter, 'ready_to_draw'); ?>><?php _e('Готова к розыгрышу', 'woocommerce-lottery'); ?></option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Завершена', 'woocommerce-lottery'); ?></option>
                    </select>
                </div>
                <div class="wclp-form-container" style="display:none;"></div>
                <div class="wclp-table-container" style="overflow-x: auto;">
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
                </div>
            </div>
            <?php
        }

        /**
         * AJAX handler for drawing lottery
         */
        public static function ajax_draw_lottery() {
            check_ajax_referer('wclp_admin_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Недостаточно прав', 'woocommerce-lottery')]);
            }

            $lottery_id = isset($_POST['lottery_id']) ? absint($_POST['lottery_id']) : 0;
            if (!$lottery_id) {
                wp_send_json_error(['message' => __('Неверный ID лотереи', 'woocommerce-lottery')]);
            }

            $use_random_org = get_option('wclp_use_random_org', 0);
            $result = WCLP_Results::draw_lottery($lottery_id, $use_random_org);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success(['message' => __('Лотерея успешно разыграна', 'woocommerce-lottery')]);
        }
    }
}

add_action('plugins_loaded', ['WCLP_Admin', 'init']);