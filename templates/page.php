<?php
/**
 * Template for [lottery type="page"] shortcode
 * WooCommerce Lottery Plugin 2.2.2, Updated: 2025-07-20 10:00:00 EEST
 */
if (!defined('ABSPATH')) {
    exit;
}

$lottery = WCLP_Lottery_Manager::get_lottery($atts['id']);
if (!$lottery || ($lottery['is_private'] && !is_user_logged_in())) {
    echo $lottery['is_private'] ? __('Please log in to participate', 'woocommerce-lottery') : '';
    return;
}
?>
<div class="wclp-lottery-page">
    <!-- Terms (Info and Prizes) -->
    <?php echo do_shortcode('[lottery type="terms" id="' . esc_attr($atts['id']) . '"]'); ?>

    <!-- Grid and Cart -->
    <div class="wclp-lottery-content <?php echo $lottery['cart_settings']['sticky'] ? 'sticky-cart' : ''; ?>">
        <?php if (!$lottery['cart_settings']['sticky'] && $lottery['cart_settings']['position'] === 'left'): ?>
            <div class="wclp-lottery-cart">
                <?php echo do_shortcode('[lottery type="cart" id="' . esc_attr($atts['id']) . '"]'); ?>
            </div>
            <div class="wclp-lottery-grid">
                <?php echo do_shortcode('[lottery type="grid" id="' . esc_attr($atts['id']) . '"]'); ?>
            </div>
        <?php else: ?>
            <div class="wclp-lottery-grid">
                <?php echo do_shortcode('[lottery type="grid" id="' . esc_attr($atts['id']) . '"]'); ?>
            </div>
            <div class="wclp-lottery-cart">
                <?php echo do_shortcode('[lottery type="cart" id="' . esc_attr($atts['id']) . '"]'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Results and Prizes for completed lotteries -->
    <?php if ($lottery['lottery_status'] === 'completed'): ?>
        <div class="wclp-lottery-results-prizes">
            <h2><?php _e('Результаты и призы', 'woocommerce-lottery'); ?></h2>
            <?php global $wpdb; ?>
            <?php $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wclp_results WHERE lottery_id = %d ORDER BY draw_date DESC LIMIT 1", $lottery['id']), ARRAY_A); ?>
            <?php if ($result): ?>
                <?php $winners = json_decode($result['winners'], true); ?>
                <p><?php printf(__('Дата розыгрыша: %s', 'woocommerce-lottery'), esc_html($result['draw_date'])); ?></p>
                <div class="wclp-prizes">
                    <?php foreach ($lottery['prizes'] as $prize_id => $prize): ?>
                        <div class="wclp-prize">
                            <h3><?php echo esc_html($prize['name']); ?></h3>
                            <?php foreach ($winners as $winner): ?>
                                <?php if ($winner['prize_id'] == $prize_id): ?>
                                    <p><?php printf(__('Выигрышный номер: %s', 'woocommerce-lottery'), esc_html($winner['nomer'])); ?></p>
                                    <p><?php printf(__('Победитель: %s (****%s)', 'woocommerce-lottery'), esc_html($winner['user_name']), esc_html(substr($winner['user_phone'], -4))); ?></p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="wclp-tickets-grid">
                    <h3><?php _e('Сетка номеров', 'woocommerce-lottery'); ?></h3>
                    <?php $reservations = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d", $lottery['id'])); ?>
                    <div class="wclp-grid" data-lottery-id="<?php echo esc_attr($lottery['id']); ?>">
                        <?php foreach ($reservations as $reservation): ?>
                            <div class="wclp-ticket" data-nomer="<?php echo esc_attr($reservation->nomer); ?>" title="<?php echo esc_attr($reservation->user_name); ?>">
                                <?php echo esc_html($reservation->nomer); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>