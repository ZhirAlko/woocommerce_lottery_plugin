<?php
/**
 * Email template for admin notification
 * WooCommerce Lottery Plugin 2.2.2, Generated: July 22, 2025
 */
if (!defined('ABSPATH')) {
    exit;
}

$lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
$winners = $winners; // From WCLP_Results::send_notifications()
?>
<p><?php printf(__('The "%s" lottery has been drawn.', 'woocommerce-lottery'), esc_html($lottery['name'])); ?></p>
<p><?php _e('Draw Date:', 'woocommerce-lottery'); ?> <?php echo esc_html($result['draw_date']); ?></p>
<h3><?php _e('Winners:', 'woocommerce-lottery'); ?></h3>
<ul>
    <?php foreach ($winners as $winner): ?>
        <li>
            <?php printf(__('Ticket: %s, Winner: %s (%s), Prize: %s', 'woocommerce-lottery'),
                esc_html($winner['nomer']),
                esc_html($winner['user_name']),
                esc_html($winner['user_phone']),
                esc_html($lottery['prizes'][$winner['prize_id']]['name'])
            ); ?>
        </li>
    <?php endforeach; ?>
</ul>
<p><?php _e('Please review the results in the admin panel.', 'woocommerce-lottery'); ?></p>