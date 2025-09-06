<?php
/**
 * Template for [lottery type="cart"] shortcode
 * WooCommerce Lottery Plugin 2.2.2, Generated: 2025-07-20 10:00:00 EEST
 */
if (!defined('ABSPATH')) {
    exit;
}

if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
    return '<div class="wclp-shortcode-placeholder">' . __('Cart placeholder', 'woocommerce-lottery') . '</div>';
}

$lottery = WCLP_Lottery_Manager::get_lottery($atts['id']);
if (!$lottery || ($lottery['is_private'] && !is_user_logged_in()) || !in_array($lottery['lottery_status'], ['active', 'ready_to_draw'])) {
    return;
}

// Initialize WooCommerce session
if (function_exists('WC') && WC()->session && !WC()->session->has_session()) {
    WC()->session->set_customer_session_cookie(true);
}

$cart = (function_exists('WC') && WC()->session) ? WC()->session->get('wclp_cart', []) : [];
$cart_numbers = $cart[$lottery['id']] ?? [];
$promo_codes = (function_exists('WC') && WC()->session) ? WC()->session->get('wclp_promo_codes', []) : [];
$total = count($cart_numbers) * $lottery['ticket_price'];
$discount = 0;
foreach ($promo_codes as $code) {
    $coupon = new WC_Coupon($code);
    if ($coupon->is_valid()) {
        $discount += $coupon->get_discount_amount($total);
    }
}

$user_id = get_current_user_id();
$user_data = [
    'phone' => $user_id ? get_user_meta($user_id, 'billing_phone', true) : '',
    'first_name' => $user_id ? get_user_meta($user_id, 'first_name', true) ?: get_user_meta($user_id, 'billing_first_name', true) : '',
    'email' => $user_id ? get_user_meta($user_id, 'billing_email', true) : ''
];

$cart_settings = $lottery['cart_settings'];
$grid_settings = $lottery['grid_settings'];
$ticket_size_large = $grid_settings['large']['ticket_size'] ?? 60;
$ticket_size_medium = $grid_settings['medium']['ticket_size'] ?? 50;
$ticket_size_small = $grid_settings['small']['ticket_size'] ?? 40;
$columns = $cart_settings['columns'] ?? 3;
$gap = 10;
$padding = 40;
$adaptive_width_large = ($ticket_size_large * $columns) + ($gap * ($columns - 1)) + $padding;
$adaptive_width_medium = ($ticket_size_medium * $columns) + ($gap * ($columns - 1)) + $padding;
$adaptive_width_small = ($ticket_size_small * $columns) + ($gap * ($columns - 1)) + $padding;
$min_width = $cart_settings['width'] ?? 250;

$payment_gateways = WC()->payment_gateways()->get_available_payment_gateways();
$default_gateway = array_key_first($payment_gateways) ?? 'cod';

wp_enqueue_script('wclp-front', plugin_dir_url(__FILE__) . '../assets/js/front.js', ['jquery', 'woocommerce'], '2.2.2', true);
wp_localize_script('wclp-front', 'wclp_params', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('wclp_nonce'),
    'cart' => $cart ?: [], // Ensure cart is not undefined
    'user_id' => $user_id, // Add user_id for client-side
    'default_gateway' => $default_gateway,
    'add_text' => __('Add', 'woocommerce-lottery'),
    'remove_text' => __('Remove', 'woocommerce-lottery'),
    'ticket_sold_text' => __('This ticket is sold', 'woocommerce-lottery'),
    'error_updating_cart' => __('Error updating cart', 'woocommerce-lottery'),
    'error_clearing_cart' => __('Error clearing cart', 'woocommerce-lottery'),
    'error_applying_coupon' => __('Error applying coupon', 'woocommerce-lottery'),
    'error_checkout' => __('Error during checkout', 'woocommerce-lottery'),
    'error_duplicate_phone' => __('This phone number is already used with another email', 'woocommerce-lottery'),
    'error_missing_fields' => __('Please fill in phone, first name, and email', 'woocommerce-lottery'),
    'error_empty_cart' => __('No tickets selected', 'woocommerce-lottery')
]);
?>
<div class="wclp-cart <?php echo $cart_settings['sticky'] ? 'sticky' : ''; ?>" 
     data-lottery-id="<?php echo esc_attr($lottery['id']); ?>" 
     data-position="<?php echo esc_attr($cart_settings['position']); ?>"
     style="
         min-width: <?php echo esc_attr($min_width); ?>px;
         --cart-width-large: <?php echo esc_attr($adaptive_width_large); ?>px;
         --cart-width-medium: <?php echo esc_attr($adaptive_width_medium); ?>px;
         --cart-width-small: <?php echo esc_attr($adaptive_width_small); ?>px;
         --columns: <?php echo esc_attr($cart_settings['columns']); ?>;
         --ticket-size-large: <?php echo esc_attr($grid_settings['large']['ticket_size'] ?? '60'); ?>px;
         --ticket-size-medium: <?php echo esc_attr($grid_settings['medium']['ticket_size'] ?? '50'); ?>px;
         --ticket-size-small: <?php echo esc_attr($grid_settings['small']['ticket_size'] ?? '40'); ?>px;
         --color-available: <?php echo esc_attr($grid_settings['color_available'] ?? '#28a745'); ?>;
         --color-selected: <?php echo esc_attr($grid_settings['color_selected'] ?? '#007bff'); ?>;
         --font-family: inherit;
         --font-size: <?php echo esc_attr($grid_settings['font_size'] ?? '16'); ?>px;
         --font-color: <?php echo esc_attr($grid_settings['font_color'] ?? '#fff'); ?>;
         --border-width: <?php echo esc_attr($grid_settings['border_width'] ?? '1'); ?>px;
         --border-color: <?php echo esc_attr($grid_settings['border_color'] ?? '#ddd'); ?>;
         --border-radius: <?php echo esc_attr($grid_settings['border_radius'] ?? '4'); ?>px;
     ">
    <h3><?php _e('Your Tickets', 'woocommerce-lottery'); ?> 
        <span class="wclp-toggle-cart"></span>
    </h3>
    <div class="wclp-cart-content">
        <?php if ($user_id): ?>
            <div class="wclp-cart-user">
                <p><?php echo esc_html($user_data['phone']); ?></p>
                <p><?php echo esc_html($user_data['first_name']); ?></p>
                <p><?php echo esc_html($user_data['email']); ?></p>
            </div>
        <?php endif; ?>
        <div class="wclp-cart-items">
            <?php foreach ($cart_numbers as $nomer): ?>
                <div class="wclp-cart-item animate__animated animate__slideIn" data-nomer="<?php echo esc_attr($nomer); ?>" title="<?php _e('Remove', 'woocommerce-lottery'); ?>">
                    <?php echo esc_html($nomer); ?>
                    <span class="wclp-remove">×</span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="wclp-cart-total"><?php echo __('Total:', 'woocommerce-lottery') . ' ' . wc_price($total - $discount); ?></div>
        <button class="wclp-clear-cart"><?php _e('Clear', 'woocommerce-lottery'); ?></button>
        <?php if (wc_coupons_enabled()): ?>
            <div class="wclp-coupon-container">
                <div class="wclp-coupon-message"></div>
                <div class="wclp-coupons-list">
                    <?php foreach ($promo_codes as $code): ?>
                        <div class="wclp-coupon-item"><?php echo esc_html($code); ?></div>
                    <?php endforeach; ?>
                </div>
                <a href="#" class="wclp-coupon-toggle <?php echo !empty($promo_codes) ? 'hidden' : ''; ?>"><?php _e('Add Coupon', 'woocommerce-lottery'); ?></a>
                <div class="wclp-coupon-form <?php echo !empty($promo_codes) ? 'active' : ''; ?>">
                    <input type="text" class="wclp-coupon-code" placeholder="<?php _e('Coupon code', 'woocommerce-lottery'); ?>" value="">
                    <button class="wclp-apply-coupon"><?php _e('Apply', 'woocommerce-lottery'); ?></button>
                </div>
            </div>
        <?php endif; ?>
        <form class="wclp-checkout-form" data-lottery-id="<?php echo esc_attr($lottery['id']); ?>">
            <?php if (!$user_id): ?>
                <input type="text" name="phone" placeholder="<?php _e('Phone', 'woocommerce-lottery'); ?>" value="<?php echo esc_attr($user_data['phone']); ?>" data-validated="false" required>
                <input type="text" name="first_name" placeholder="<?php _e('First Name', 'woocommerce-lottery'); ?>" value="<?php echo esc_attr($user_data['first_name']); ?>" data-validated="false" required>
                <input type="email" name="email" placeholder="<?php _e('Email', 'woocommerce-lottery'); ?>" value="<?php echo esc_attr($user_data['email']); ?>" data-validated="false" required>
            <?php endif; ?>
            <div class="wclp-cart-terms">
                <input type="checkbox" name="terms" id="terms-<?php echo esc_attr($lottery['id']); ?>" value="1" required>
                <label for="terms-<?php echo esc_attr($lottery['id']); ?>"><?php _e('I agree to terms', 'woocommerce-lottery'); ?></label>
            </div>
            <div class="wclp-cart-payment">
                <select name="payment_method" required>
                    <?php foreach ($payment_gateways as $gateway_id => $gateway): ?>
                        <option value="<?php echo esc_attr($gateway_id); ?>" <?php selected($gateway_id, $default_gateway); ?>>
                            <?php echo esc_html($gateway->get_title()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="wclp-checkout"><?php _e('Checkout', 'woocommerce-lottery'); ?></button>
        </form>
    </div>
</div>