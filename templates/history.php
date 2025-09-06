<?php
/**
 * Template for [lottery type="history"] shortcode
 * WooCommerce Lottery Plugin 2.2.1-beta, Updated: 2025-06-30
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb; // Добавляем глобальную переменную $wpdb

if (!is_user_logged_in()) {
    echo '<p>' . __('Please log in to view your lottery history.', 'woocommerce-lottery') . '</p>';
    return;
}

$user_id = $atts['user_id'] === 'current' ? get_current_user_id() : intval($atts['user_id']);
$orders = wc_get_orders([
    'customer_id' => $user_id,
    'meta_key' => 'lottery_id',
    'meta_compare' => 'EXISTS',
    'status' => ['wc-completed', 'wc-processing']
]);
?>
<div class="wclp-history">
    <h3><?php _e('Your Lottery History', 'woocommerce-lottery'); ?></h3>
    <table class="wclp-history-table">
        <thead>
            <tr>
                <th><?php _e('Lottery', 'woocommerce-lottery'); ?></th>
                <th><?php _e('Numbers', 'woocommerce-lottery'); ?></th>
                <th><?php _e('Order', 'woocommerce-lottery'); ?></th>
                <th><?php _e('Status', 'woocommerce-lottery'); ?></th>
                <th><?php _e('Results', 'woocommerce-lottery'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="5"><?php _e('No lotteries found.', 'woocommerce-lottery'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $lottery_id = $order->get_meta('lottery_id');
                    $lottery = WCLP_Lottery_Manager::get_lottery($lottery_id);
                    if (!$lottery) continue;
                    $numbers = [];
                    foreach ($order->get_items() as $item) {
                        $numbers[] = $item->get_meta('nomer');
                    }
                    $results = $wpdb->get_row($wpdb->prepare(
                        "SELECT winners FROM {$wpdb->prefix}wclp_results WHERE lottery_id = %d ORDER BY draw_date DESC LIMIT 1",
                        $lottery_id
                    ), ARRAY_A);
                    $winners = $results ? json_decode($results['winners'], true) : [];
                    $is_winner = false;
                    foreach ($winners as $winner) {
                        if (in_array($winner['nomer'], $numbers)) {
                            $is_winner = true;
                            break;
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html($lottery['name']); ?></td>
                        <td><?php echo esc_html(implode(', ', $numbers)); ?></td>
                        <td><a href="<?php echo esc_url($order->get_view_order_url()); ?>">#<?php echo esc_html($order->get_order_number()); ?></a></td>
                        <td><?php echo esc_html($lottery['lottery_status']); ?></td>
                        <td><?php echo $is_winner ? __('Winner', 'woocommerce-lottery') : ($lottery['lottery_status'] === 'completed' ? __('Not won', 'woocommerce-lottery') : __('Pending', 'woocommerce-lottery')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>