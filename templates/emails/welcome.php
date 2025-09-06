<?php
/**
 * Email template for new guest user
 * WooCommerce Lottery Plugin 2.2.2, Generated: July 22, 2025
 */
if (!defined('ABSPATH')) {
    exit;
}

$username = $username; // From create_order
$temp_password = $temp_password;
$user_email = $user_data['email'];
?>
<p><?php _e('Добро пожаловать!', 'woocommerce-lottery'); ?></p>
<p><?php _e('Ваш аккаунт создан для участия в конкурсе.', 'woocommerce-lottery'); ?></p>
<p>
    <?php _e('Логин:', 'woocommerce-lottery'); ?> <?php echo esc_html($username); ?><br>
    <?php _e('Пароль:', 'woocommerce-lottery'); ?> <?php echo esc_html($temp_password); ?><br>
    <?php _e('Email:', 'woocommerce-lottery'); ?> <?php echo esc_html($user_email); ?>
</p>
<p><?php _e('Измените пароль в личном кабинете.', 'woocommerce-lottery'); ?></p>