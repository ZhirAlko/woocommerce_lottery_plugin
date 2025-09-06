<?php
/**
 * Plugin Name: WooCommerce Contest Plugin
 * Description: Плагин для создания конкурсов в WooCommerce
 * Version: 2.2.2
 * Author: @zhiralko, Grok (xAI)
 * Text Domain: woocommerce-lottery
 * License: GPL-2.0+
 * Generated: July 30, 2025
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WCLP_VERSION', '2.2.2');
define('WCLP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCLP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class WooCommerce_Lottery_Plugin {
    /**
     * Initialize plugin
     */
    public static function init() {
        // Подключение классов
        require_once WCLP_PLUGIN_DIR . 'includes/classes/class-lottery-manager.php';
        require_once WCLP_PLUGIN_DIR . 'includes/classes/class-shortcodes.php';
        require_once WCLP_PLUGIN_DIR . 'includes/classes/class-cart.php';
        require_once WCLP_PLUGIN_DIR . 'includes/classes/class-results.php';
        require_once WCLP_PLUGIN_DIR . 'includes/classes/class-admin.php';
        require_once WCLP_PLUGIN_DIR . 'includes/classes/class-admin-form.php';
        require_once WCLP_PLUGIN_DIR . 'includes/ajax-handlers-front.php';
        require_once WCLP_PLUGIN_DIR . 'includes/ajax-handlers-admin.php';

        // Загрузка переводов
        add_action('init', function() {
            load_plugin_textdomain('woocommerce-lottery', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }, 15);

        // Подключение стилей и скриптов
        add_action('wp_enqueue_scripts', function() {
            wp_enqueue_style('wclp-styles', WCLP_PLUGIN_URL . 'assets/css/styles.css', [], '2.2.2');
            wp_enqueue_style('animate-css', 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css', [], '4.1.1');
            wp_enqueue_script('wclp-front', WCLP_PLUGIN_URL . 'assets/js/front.js', ['jquery'], '2.2.2', true);
            wp_localize_script('wclp-front', 'wclp_params', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wclp_nonce'),
                'add_text' => __('Add', 'woocommerce-lottery'),
                'remove_text' => __('Remove', 'woocommerce-lottery'),
                'ticket_sold_text' => __('Ticket sold', 'woocommerce-lottery'),
                'error_updating_cart' => __('Error updating cart', 'woocommerce-lottery'),
                'error_clearing_cart' => __('Error clearing cart', 'woocommerce-lottery'),
                'error_applying_promo' => __('Error applying promo code', 'woocommerce-lottery'),
                'error_checkout' => __('Error during checkout', 'woocommerce-lottery'),
                'error_duplicate_phone' => __('This phone number is already used with another email', 'woocommerce-lottery'),
                'error_missing_fields' => __('Please fill in phone, first name, and email', 'woocommerce-lottery'),
                'error_empty_cart' => __('No tickets selected', 'woocommerce-lottery'),
                'total_text' => __('Total:', 'woocommerce-lottery'),
                'default_gateway' => get_option('woocommerce_default_gateway', '')
            ]);
        });

        add_action('admin_enqueue_scripts', function($hook) {
            if ($hook === 'toplevel_page_wclp_draws' || (isset($_GET['page']) && $_GET['page'] === 'wclp_draws')) {
                wp_enqueue_media();
                wp_enqueue_style('wclp-admin', WCLP_PLUGIN_URL . 'assets/css/admin.css', [], '2.2.2');
                wp_enqueue_style('spectrum-css', 'https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css', [], '1.8.1');
                wp_enqueue_script('jquery-ui-sortable', false, ['jquery'], null, true);
                wp_enqueue_script('spectrum-js', 'https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.js', ['jquery'], '1.8.1', true);
                wp_enqueue_script('wclp-admin', WCLP_PLUGIN_URL . 'assets/js/admin.js', ['jquery', 'jquery-ui-sortable', 'spectrum-js'], '2.2.2', true);
                wp_enqueue_script('wclp-admin-filter', WCLP_PLUGIN_URL . 'assets/js/admin-filter.js', ['jquery'], '2.2.2', true);
                wp_localize_script('wclp-admin', 'wclp_admin', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wclp_admin_nonce'),
                    'select_image' => __('Выбрать изображение', 'woocommerce-lottery'),
                    'remove_image' => __('Удалить', 'woocommerce-lottery'),
                    'confirm_unsaved' => __('У вас есть несохранённые изменения. Вы уверены, что хотите уйти?', 'woocommerce-lottery'),
                    'copied' => __('Шорткод скопирован!', 'woocommerce-lottery'),
                    'prize_name' => __('Название приза', 'woocommerce-lottery'),
                    'prize_description' => __('Описание приза', 'woocommerce-lottery'),
                    'prize_image_url' => __('Изображение приза', 'woocommerce-lottery'),
                    'prize_product' => __('Товар приза', 'woocommerce-lottery'),
                    'none' => __('Нет', 'woocommerce-lottery'),
                    'remove_prize' => __('Удалить приз', 'woocommerce-lottery'),
                    'remove_prize_confirm' => __('Удалить этот приз?', 'woocommerce-lottery'),
                    'select_product' => __('Выберите товар', 'woocommerce-lottery'),
                    'invalid_ticket_count' => __('Введите корректное количество билетов', 'woocommerce-lottery'),
                    'invalid_ticket_price' => __('Введите корректную цену билета', 'woocommerce-lottery'),
                    'product_created' => __('Товар успешно создан', 'woocommerce-lottery'),
                    'error_creating_product' => __('Ошибка создания товара', 'woocommerce-lottery'),
                    'error_fetching_product' => __('Ошибка получения данных товара', 'woocommerce-lottery'),
                    'fill_required_fields' => __('Заполните все обязательные поля корректно', 'woocommerce-lottery'),
                    'saving' => __('Сохранение...', 'woocommerce-lottery'),
                    'save_lottery' => __('Сохранить конкурс', 'woocommerce-lottery'),
                    'lottery_saved' => __('Конкурс успешно сохранён', 'woocommerce-lottery'),
                    'error_saving' => __('Ошибка сохранения конкурса', 'woocommerce-lottery'),
                    'max_prizes_error' => __('Достигнуто максимальное количество призов (15% от числа билетов)', 'woocommerce-lottery'),
                    'no_prizes_error' => __('Требуется хотя бы один приз', 'woocommerce-lottery'),
                    'loading' => __('Загрузка...', 'woocommerce-lottery'),
                    'products' => function_exists('wc_get_products') ? array_map(function($product) {
                        return ['id' => $product->get_id(), 'name' => $product->get_name()];
                    }, wc_get_products(['type' => 'simple', 'status' => 'publish', 'limit' => -1])) : []
                ]);
                wp_localize_script('wclp-admin-filter', 'wclp_filter', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wclp_admin_nonce')
                ]);
            } elseif ($hook === 'lottery_page_wclp_settings' || (isset($_GET['page']) && $_GET['page'] === 'wclp_settings')) {
                wp_enqueue_style('wclp-admin', WCLP_PLUGIN_URL . 'assets/css/admin.css', [], '2.2.2');
                wp_enqueue_script('wclp-settings', WCLP_PLUGIN_URL . 'assets/js/settings.js', ['jquery'], '2.2.2', true);
                wp_localize_script('wclp-settings', 'wclp_admin', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wclp_admin_nonce'),
                    'error_saving' => __('Ошибка сохранения', 'woocommerce-lottery'),
                    'loading' => __('Загрузка...', 'woocommerce-lottery')
                ]);
            }
        });

        // Добавление интервала cron
        add_filter('cron_schedules', function($schedules) {
            $schedules['fifteen_minutes'] = ['interval' => 900, 'display' => __('Every 15 Minutes')];
            return $schedules;
        });

        // Добавление меню
        add_action('admin_menu', function() {
            add_menu_page(
                __('Конкурсы', 'woocommerce-lottery'),
                __('Конкурсы', 'woocommerce-lottery'),
                'manage_options',
                'wclp_draws',
                ['WCLP_Admin', 'render_admin_page'],
                'dashicons-tickets-alt',
                56
            );
            add_submenu_page(
                'wclp_draws',
                __('Настройки конкурса', 'woocommerce-lottery'),
                __('Настройки', 'woocommerce-lottery'),
                'manage_options',
                'wclp_settings',
                ['WooCommerce_Lottery_Plugin', 'render_settings_page']
            );
        });

        // Инициализация классов
        WCLP_Shortcodes::init();
        WCLP_Cart::init();
        WCLP_Results::init();
        WCLP_Admin::init();

        // Хуки для синхронизации заказов
        add_action('woocommerce_new_order', ['WooCommerce_Lottery_Plugin', 'sync_order_with_lottery'], 10, 1);
        add_action('woocommerce_update_order', ['WooCommerce_Lottery_Plugin', 'sync_order_with_lottery'], 10, 1);
        add_action('woocommerce_order_status_changed', ['WooCommerce_Lottery_Plugin', 'sync_order_with_lottery'], 10, 1);

        // Регистрация настроек
        add_action('admin_init', ['WooCommerce_Lottery_Plugin', 'register_settings']);
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        register_setting('wclp_settings_group', 'wclp_random_org_api_key');
        register_setting('wclp_settings_group', 'wclp_use_random_org');
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Настройки конкурса', 'woocommerce-lottery'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('wclp_settings_group'); ?>
                <?php do_settings_sections('wclp_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="wclp_use_random_org"><?php _e('Использовать Random.org для подведения итогов', 'woocommerce-lottery'); ?></label></th>
                        <td>
                            <input type="checkbox" id="wclp_use_random_org" name="wclp_use_random_org" value="1" <?php checked(get_option('wclp_use_random_org', 0), 1); ?>>
                            <p class="description"><?php _e('Включите, чтобы использовать API Random.org для выбора победителей.', 'woocommerce-lottery'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wclp_random_org_api_key"><?php _e('API-ключ Random.org', 'woocommerce-lottery'); ?></label></th>
                        <td>
                            <input type="text" id="wclp_random_org_api_key" name="wclp_random_org_api_key" value="<?php echo esc_attr(get_option('wclp_random_org_api_key', '')); ?>" style="width: 300px;">
                            <p class="description"><?php _e('Введите ваш API-ключ Random.org. Получите его на <a href="https://api.random.org" target="_blank">api.random.org</a>.', 'woocommerce-lottery'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php _e('Тестирование генератора случайных чисел', 'woocommerce-lottery'); ?></h2>
            <form id="wclp-test-random-generator" class="wclp-test-random-generator" method="post" action="#">
                <table class="form-table">
                    <tr>
                        <th><label for="wclp-total-numbers"><?php _e('Количество номеров', 'woocommerce-lottery'); ?></label></th>
                        <td>
                            <input type="number" id="wclp-total-numbers" name="total_numbers" value="100" min="1" max="1000" required>
                            <p class="description"><?php _e('Общее количество номеров для генерации (1–1000).', 'woocommerce-lottery'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wclp-winners-count"><?php _e('Количество победителей', 'woocommerce-lottery'); ?></label></th>
                        <td>
                            <input type="number" id="wclp-winners-count" name="winners_count" value="6" min="1" max="100" required>
                            <p class="description"><?php _e('Сколько номеров выбрать (1–100).', 'woocommerce-lottery'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="wclp-exclusions"><?php _e('Исключения', 'woocommerce-lottery'); ?></label></th>
                        <td>
                            <input type="text" id="wclp-exclusions" name="exclusions" value="" placeholder="1,3,5">
                            <p class="description"><?php _e('Номера для исключения, через запятую (например, 1,3,5).', 'woocommerce-lottery'); ?></p>
                        </td>
                    </tr>
                </table>
                <button type="button" id="wclp-test-random-button" class="button button-primary"><?php _e('Тестировать генератор', 'woocommerce-lottery'); ?></button>
                <div id="wclp-test-result" style="margin-top: 20px;"></div>
            </form>
        </div>
        <?php
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        // Таблица wp_wclp_lotteries
        $sql = "CREATE TABLE {$wpdb->prefix}wclp_lotteries (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            winners_count int NOT NULL,
            prizes longtext,
            start_date datetime,
            end_date datetime,
            draw_type varchar(20) NOT NULL,
            lottery_status varchar(20) NOT NULL,
            include_unsold tinyint(1) DEFAULT 0,
            auto_draw tinyint(1) DEFAULT 0,
            is_private tinyint(1) DEFAULT 0,
            grid_settings longtext,
            cart_settings longtext,
            ticket_count int NOT NULL,
            ticket_price decimal(10,2) NOT NULL,
            ticket_limit_per_user int DEFAULT 0,
            last_draw_id bigint(20),
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            settings longtext,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY lottery_status (lottery_status)
        ) $charset_collate;";
        dbDelta($sql);

        // Таблица wp_wclp_results
        $sql = "CREATE TABLE {$wpdb->prefix}wclp_results (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            lottery_id bigint(20) NOT NULL,
            draw_date datetime NOT NULL,
            winners longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY lottery_id (lottery_id),
            KEY draw_date (draw_date)
        ) $charset_collate;";
        dbDelta($sql);

        // Таблица wp_wclp_reservations
        $sql = "CREATE TABLE {$wpdb->prefix}wclp_reservations (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            lottery_id bigint(20) NOT NULL,
            nomer varchar(50) NOT NULL,
            user_id bigint(20),
            user_phone varchar(20),
            order_id bigint(20),
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY lottery_id (lottery_id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY expires_at (expires_at),
            KEY nomer (nomer)
        ) $charset_collate;";
        dbDelta($sql);

        // Принудительная миграция столбца user_phone
        $columns = $wpdb->get_col("DESC {$wpdb->prefix}wclp_reservations", 0);
        if (!in_array('user_phone', $columns)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wclp_reservations ADD user_phone varchar(20) AFTER user_id");
        }

        // Принудительная миграция столбца updated_at
        $columns = $wpdb->get_col("DESC {$wpdb->prefix}wclp_lotteries", 0);
        if (!in_array('updated_at', $columns)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wclp_lotteries ADD updated_at datetime NOT NULL AFTER created_at");
        }

        // Принудительная миграция индекса nomer
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}wclp_reservations WHERE Key_name = 'nomer'");
        if (empty($indexes)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wclp_reservations ADD INDEX nomer (nomer)");
        }

        // Schedule crons
        if (!wp_next_scheduled('wclp_check_lottery_status')) {
            wp_schedule_event(time(), 'hourly', 'wclp_check_lottery_status');
        }
        if (!wp_next_scheduled('wclp_sync_reservations')) {
            wp_clear_scheduled_hook('wclp_sync_reservations');
            wp_schedule_event(time(), 'fifteen_minutes', 'wclp_sync_reservations');
        }
    }

    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        global $wpdb;

        $tables = ['wclp_lotteries', 'wclp_results', 'wclp_reservations'];
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }

        delete_transient('wclp_cache_%');
        wp_clear_scheduled_hook('wclp_check_lottery_status');
        wp_clear_scheduled_hook('wclp_sync_reservations');
        delete_option('wclp_random_org_api_key');
        delete_option('wclp_use_random_org');
    }

    /**
     * Check contest status for auto-activation and completion
     */
    public static function check_lottery_status() {
        global $wpdb;
        $active_lotteries = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wclp_lotteries WHERE lottery_status IN ('active', 'ready_to_draw')");
        if (!$active_lotteries) {
            return;
        }

        $lotteries = $wpdb->get_results("SELECT id, start_date, end_date, lottery_status, draw_type, auto_draw, ticket_count FROM {$wpdb->prefix}wclp_lotteries WHERE lottery_status IN ('draft', 'active')");
        foreach ($lotteries as $lottery) {
            $current_time = current_time('timestamp');
            // Auto-activate
            if ($lottery->lottery_status === 'draft' && $lottery->start_date && $current_time >= strtotime($lottery->start_date)) {
                $result = WCLP_Lottery_Manager::activate_lottery($lottery->id);
                if (is_wp_error($result)) {
                    wc_get_logger()->error('WCLP: Failed to auto-activate contest ID ' . $lottery->id . ': ' . $result->get_error_message(), ['source' => 'wclp']);
                }
            }
            // Auto-complete
            if ($lottery->lottery_status === 'active' && $lottery->auto_draw) {
                if ($lottery->draw_type === 'date' && $lottery->end_date && $current_time >= strtotime($lottery->end_date)) {
                    WCLP_Results::check_and_draw($lottery->id);
                } elseif ($lottery->draw_type === 'sold') {
                    $sold_tickets = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND order_id IN (
                            SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-completed', 'wc-processing')
                        )",
                        $lottery->id
                    ));
                    if ($sold_tickets >= $lottery->ticket_count) {
                        WCLP_Results::check_and_draw($lottery->id);
                    }
                }
            }
        }
    }

    /**
     * Sync order with contest data
     */
    public static function sync_order_with_lottery($order_id) {
        global $wpdb;
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_get_logger()->error('WCLP: Invalid order ID ' . $order_id . ' for sync', ['source' => 'wclp']);
            return;
        }

        // Call the unified sync method from class-cart
        WCLP_Cart::sync_and_clean_reservations();

        // Создание резервов для текущего заказа
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $nomer = $item->get_meta('nomer');
            if ($nomer) {
                $lottery = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, product_id FROM {$wpdb->prefix}wclp_lotteries WHERE product_id = %d AND lottery_status = 'active'",
                    $product_id
                ));
                if ($lottery) {
                    update_post_meta($order_id, 'lottery_id', $lottery->id);
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND nomer = %s AND order_id = %d",
                        $lottery->id, $nomer, $order_id
                    ));
                    if (!$exists) {
                        $user_phone = get_user_meta($order->get_customer_id(), 'billing_phone', true) ?: 'N/A';
                        $hold_stock_minutes = get_option('woocommerce_hold_stock_minutes', 15);
                        $expires_at = $order->get_status() === 'pending' 
                            ? date('Y-m-d H:i:s', strtotime(current_time('mysql') . " +$hold_stock_minutes minutes"))
                            : date('Y-m-d H:i:s', strtotime('+1 month'));
                        $wpdb->insert(
                            $wpdb->prefix . 'wclp_reservations',
                            [
                                'lottery_id' => $lottery->id,
                                'nomer' => $nomer,
                                'user_id' => $order->get_customer_id() ?: 0,
                                'user_phone' => $user_phone,
                                'order_id' => $order_id,
                                'expires_at' => $expires_at,
                                'created_at' => current_time('mysql')
                            ],
                            ['%d', '%s', '%d', '%s', '%d', '%s', '%s']
                        );
                        if ($wpdb->last_error) {
                            wc_get_logger()->error('WCLP: Failed to insert reservation for order ID ' . $order_id . ': ' . $wpdb->last_error, ['source' => 'wclp']);
                        }
                        if ($order->get_status() !== 'pending') {
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

// Проверка зависимости WooCommerce
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . __('Плагин WooCommerce Contest требует WooCommerce 9.0+, но WooCommerce не активен.', 'woocommerce-lottery') . '</p></div>';
        });
        return;
    }
    if (version_compare(WC_VERSION, '9.0.0', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . sprintf(__('Плагин WooCommerce Contest требует WooCommerce 9.0+, текущая версия %s', 'woocommerce-lottery'), esc_html(WC_VERSION)) . '</p></div>';
        });
        return;
    }
    WooCommerce_Lottery_Plugin::init();
}, 15);

register_activation_hook(__FILE__, ['WooCommerce_Lottery_Plugin', 'activate']);
register_uninstall_hook(__FILE__, ['WooCommerce_Lottery_Plugin', 'uninstall']);
add_action('wclp_check_lottery_status', ['WooCommerce_Lottery_Plugin', 'check_lottery_status']);
add_action('wclp_sync_reservations', ['WCLP_Cart', 'sync_and_clean_reservations']);
