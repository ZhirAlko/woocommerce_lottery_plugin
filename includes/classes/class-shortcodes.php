<?php
/**
 * Class WCLP_Shortcodes
 * Handles lottery shortcodes
 * WooCommerce Lottery Plugin 2.2.1-beta, Generated: 2025-07-11 08:11:00 +0300
 */

class WCLP_Shortcodes {
    public static function init() {
        add_shortcode('lottery', [__CLASS__, 'lottery_shortcode']);
    }

    /**
     * Lottery shortcode
     * @param array $atts Shortcode attributes
     * @return string
     */
    public static function lottery_shortcode($atts) {
        // Return placeholder in admin editor or REST API
        if (is_admin() || wp_doing_ajax() || (function_exists('is_rest') && is_rest())) {
            return '<div class="wclp-shortcode-placeholder">' . __('Lottery shortcode placeholder', 'woocommerce-lottery') . '</div>';
        }

        $atts = shortcode_atts([
            'type' => 'page',
            'id' => 0,
            'user_id' => 'current'
        ], $atts, 'lottery');

        if (!$atts['id'] || !in_array($atts['type'], ['page', 'grid', 'cart', 'results', 'history', 'terms'])) {
            error_log('WCLP: Invalid shortcode attributes - id: ' . $atts['id'] . ', type: ' . $atts['type']);
            return '';
        }

        // Проверка дублирования
        global $GLOBALS;
        $GLOBALS['wclp_shortcodes'] = $GLOBALS['wclp_shortcodes'] ?? [];
        $key = $atts['type'] . '_' . $atts['id'];
        if (isset($GLOBALS['wclp_shortcodes'][$key])) {
            return '';
        }
        $GLOBALS['wclp_shortcodes'][$key] = true;

        $lottery = WCLP_Lottery_Manager::get_lottery($atts['id']);
        if (!$lottery) {
            error_log('WCLP: Get lottery failed: No lottery found for ID ' . $atts['id']);
            return '<div class="wclp-error">' . __('Lottery not found', 'woocommerce-lottery') . '</div>';
        }
        if ($lottery['is_private'] && !is_user_logged_in()) {
            return '<div class="wclp-error">' . __('Please log in to participate', 'woocommerce-lottery') . '</div>';
        }

        ob_start();
        switch ($atts['type']) {
            case 'page':
                include plugin_dir_path(__FILE__) . '../../templates/page.php';
                break;
            case 'grid':
                include plugin_dir_path(__FILE__) . '../../templates/grid.php';
                break;
            case 'cart':
                include plugin_dir_path(__FILE__) . '../../templates/cart.php';
                break;
            case 'results':
                include plugin_dir_path(__FILE__) . '../../templates/results.php';
                break;
            case 'history':
                if ($atts['user_id'] === 'current' && is_user_logged_in()) {
                    $atts['user_id'] = get_current_user_id();
                }
                include plugin_dir_path(__FILE__) . '../../templates/history.php';
                break;
            case 'terms':
                include plugin_dir_path(__FILE__) . '../../templates/terms.php';
                break;
        }
        return ob_get_clean();
    }
}

// Инициализация шорткодов
add_action('init', ['WCLP_Shortcodes', 'init']);