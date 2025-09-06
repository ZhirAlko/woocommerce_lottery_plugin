<?php
/**
 * Template for [lottery type="grid"] shortcode
 * WooCommerce Lottery Plugin 2.2.1-beta, Generated: 2025-07-07 03:15:00 +1200
 */
if (!defined('ABSPATH')) {
    exit;
}

if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
    return '<div class="wclp-shortcode-placeholder">' . __('Grid placeholder', 'woocommerce-lottery') . '</div>';
}

$lottery = WCLP_Lottery_Manager::get_lottery($atts['id']);
if (!$lottery || ($lottery['is_private'] && !is_user_logged_in())) {
    return $lottery['is_private'] ? __('Please log in to participate', 'woocommerce-lottery') : '';
}

$product = wc_get_product($lottery['product_id']);
if (!$product || $product->get_type() !== 'variable') {
    return __('Invalid product configuration', 'woocommerce-lottery');
}

$variations = $product->get_children();
$grid_settings = $lottery['grid_settings'];
$cart = (function_exists('WC') && WC()->session) ? WC()->session->get('wclp_cart', []) : [];
$selected_numbers = $cart[$lottery['id']] ?? [];

global $wpdb;
$reserved_numbers = $wpdb->get_results($wpdb->prepare(
    "SELECT r.nomer, m.meta_value AS billing_phone 
     FROM {$wpdb->prefix}wclp_reservations r
     INNER JOIN {$wpdb->posts} o ON r.order_id = o.ID
     INNER JOIN {$wpdb->postmeta} lm ON o.ID = lm.post_id AND lm.meta_key = 'lottery_id' AND lm.meta_value = %d
     LEFT JOIN {$wpdb->postmeta} m ON o.ID = m.post_id AND m.meta_key = '_billing_phone'
     WHERE r.lottery_id = %d AND r.expires_at > %s AND o.post_status = 'wc-completed'",
    $lottery['id'], $lottery['id'], current_time('mysql')
), OBJECT_K);

// Preview mode for admins
if (empty($variations) && current_user_can('manage_options')) {
    $variations = array_map(function($i) use ($lottery) {
        return [
            'id' => $i,
            'nomer' => str_pad($i, strlen($lottery['ticket_count']), '0', STR_PAD_LEFT),
            'stock_quantity' => 1
        ];
    }, range(1, min($lottery['ticket_count'], 10)));
}

$is_disabled = !in_array($lottery['lottery_status'], ['active', 'ready_to_draw']);
?>
<div class="wclp-grid <?php echo $is_disabled ? 'wclp-grid-disabled' : ''; ?>" data-lottery-id="<?php echo esc_attr($lottery['id']); ?>" data-status="<?php echo esc_attr($lottery['lottery_status']); ?>" style="
    --ticket-size-large: <?php echo esc_attr($grid_settings['large']['ticket_size'] ?? '60'); ?>px;
    --ticket-size-medium: <?php echo esc_attr($grid_settings['medium']['ticket_size'] ?? '50'); ?>px;
    --ticket-size-small: <?php echo esc_attr($grid_settings['small']['ticket_size'] ?? '40'); ?>px;
    --columns-large: <?php echo esc_attr($grid_settings['large']['columns'] ?? '10'); ?>;
    --columns-medium: <?php echo esc_attr($grid_settings['medium']['columns'] ?? '8'); ?>;
    --columns-small: <?php echo esc_attr($grid_settings['small']['columns'] ?? '5'); ?>;
    --color-available: <?php echo esc_attr($grid_settings['color_available'] ?? '#28a745'); ?>;
    --color-sold: <?php echo esc_attr($grid_settings['color_sold'] ?? '#dc3545'); ?>;
    --color-selected: <?php echo esc_attr($grid_settings['color_selected'] ?? '#007bff'); ?>;
    --font-family: <?php echo esc_attr($grid_settings['font_family'] ?? 'inherit'); ?>;
    --font-size: <?php echo esc_attr($grid_settings['font_size'] ?? '16'); ?>px;
    --font-color: <?php echo esc_attr($grid_settings['font_color'] ?? '#fff'); ?>;
    --border-width: <?php echo esc_attr($grid_settings['border_width'] ?? '1'); ?>px;
    --border-color: <?php echo esc_attr($grid_settings['border_color'] ?? '#ddd'); ?>;
    --border-radius: <?php echo esc_attr($grid_settings['border_radius'] ?? '4'); ?>px;
    <?php if (!empty($grid_settings['background_image']) && file_exists(WP_CONTENT_DIR . parse_url($grid_settings['background_image'], PHP_URL_PATH))): ?>
    background-image: url('<?php echo esc_url($grid_settings['background_image']); ?>');
    <?php endif; ?>
">
    <?php foreach ($variations as $variation): ?>
        <?php
        if (is_array($variation)) {
            $nomer = $variation['nomer'];
            $stock = $variation['stock_quantity'];
        } else {
            $variation_obj = wc_get_product($variation);
            if (!$variation_obj) {
                continue;
            }
            $nomer = $variation_obj->get_attribute('nomer');
            $stock = $variation_obj->get_stock_quantity();
        }
        if (empty($nomer)) continue;
        $is_available = $stock > 0 && !isset($reserved_numbers[$nomer]);
        $is_selected = in_array($nomer, $selected_numbers);
        $class = $is_selected ? 'selected' : ($is_available ? 'available' : 'sold');
        $tooltip = $is_selected ? __('Remove', 'woocommerce-lottery') : ($is_available && !$is_disabled ? __('Add', 'woocommerce-lottery') : __('Sold', 'woocommerce-lottery'));
        ?>
        <div class="wclp-ticket animate__animated animate__fadeIn <?php echo esc_attr($class); ?>" 
             data-nomer="<?php echo esc_attr($nomer); ?>" 
             title="<?php echo esc_attr($tooltip); ?>">
            <?php echo esc_html($nomer); ?>
            <?php if (!empty($lottery['settings']['show_buyer_info']) && !$is_available && !$is_selected): ?>
                <span class="wclp-buyer-info"><?php echo esc_html(isset($reserved_numbers[$nomer]) && $reserved_numbers[$nomer]->billing_phone ? substr($reserved_numbers[$nomer]->billing_phone, -4) : __('Reserve', 'woocommerce-lottery')); ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>