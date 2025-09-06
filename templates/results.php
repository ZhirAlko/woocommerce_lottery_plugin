<?php
/**
 * Template for [lottery type="results"] shortcode
 * WooCommerce Lottery Plugin 2.2.1-beta, Updated: 2025-07-13
 */
if (!defined('ABSPATH')) {
    exit;
}

$lottery = WCLP_Lottery_Manager::get_lottery($atts['id']);
if (!$lottery || ($lottery['is_private'] && !is_user_logged_in())) {
    return;
}

global $wpdb;
$result = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}wclp_results WHERE lottery_id = %d ORDER BY draw_date DESC LIMIT 1",
    $lottery['id']
), ARRAY_A);

if (!$result) {
    return;
}

$winners = json_decode($result['winners'], true);
?>
<div class="wclp-results animate__animated animate__fadeIn" data-lottery-id="<?php echo esc_attr($lottery['id']); ?>">
    <h3><?php _e('Результаты лотереи', 'woocommerce-lottery'); ?></h3>
    <p><?php printf(__('Дата розыгрыша: %s', 'woocommerce-lottery'), esc_html($result['draw_date'])); ?></p>
    <!-- Условия -->
    <?php echo do_shortcode('[lottery type="terms" id="' . esc_attr($atts['id']) . '"]'); ?>
    <!-- Призы и победители -->
    <div class="wclp-prizes">
        <?php foreach ($lottery['prizes'] as $prize_id => $prize): ?>
            <div class="wclp-prize">
                <h4><?php echo esc_html($prize['name']); ?></h4>
                <?php foreach ($winners as $winner): ?>
                    <?php if ($winner['prize_id'] == $prize_id): ?>
                        <p><?php printf(__('Выигрышный номер: %s', 'woocommerce-lottery'), esc_html($winner['nomer'])); ?></p>
                        <p><?php printf(__('Победитель: %s (****%s)', 'woocommerce-lottery'), esc_html($winner['user_name']), esc_html(substr($winner['user_phone'], -4))); ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <!-- Сетка номеров -->
    <div class="wclp-tickets-grid">
        <h4><?php _e('Сетка номеров', 'woocommerce-lottery'); ?></h4>
        <?php $reservations = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d", $lottery['id'])); ?>
        <div class="wclp-grid" data-lottery-id="<?php echo esc_attr($lottery['id']); ?>">
            <?php foreach ($reservations as $reservation): ?>
                <div class="wclp-ticket" data-nomer="<?php echo esc_attr($reservation->nomer); ?>" title="<?php echo esc_attr($reservation->user_name); ?>">
                    <?php echo esc_html($reservation->nomer); ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>