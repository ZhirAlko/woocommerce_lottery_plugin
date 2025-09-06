<?php
/**
 * Email template for winner notification
 * WooCommerce Lottery Plugin 2.2.2, Generated: July 22, 2025
 */
if (!defined('ABSPATH')) {
    exit;
}

$lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
$winner = $winner; // From WCLP_Results::send_notifications()
?>
<p><?php printf(__('Dear %s,', 'woocommerce-lottery'), esc_html($winner['user_name'])); ?></p>
<p><?php printf(__('Congratulations! You have won a prize in the "%s" lottery!', 'woocommerce-lottery'), esc_html($lottery['name'])); ?></p>
<p>
    <?php _e('Your winning ticket:', 'woocommerce-lottery'); ?> <?php echo esc_html($winner['nomer']); ?><br>
    <?php _e('Prize:', 'woocommerce-lottery'); ?> <?php echo esc_html($lottery['prizes'][$winner['prize_id']]['name']); ?><br>
    <?php _e('Draw Date:', 'woocommerce-lottery'); ?> <?php echo esc_html($result['draw_date']); ?>
</p>
<p><?php _e('Please contact us to claim your prize.', 'woocommerce-lottery'); ?></p>
<p><?php _e('Thank you for participating!', 'woocommerce-lottery'); ?></p>