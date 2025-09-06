<?php
/**
 * Template for [lottery type="terms"] shortcode
 * WooCommerce Lottery Plugin 2.2.1-beta, Updated: 2025-07-13
 */
if (!defined('ABSPATH')) {
    exit;
}

$lottery = WCLP_Lottery_Manager::get_lottery($atts['id']);
if (!$lottery || ($lottery['is_private'] && !is_user_logged_in())) {
    echo $lottery['is_private'] ? __('Please log in to participate', 'woocommerce-lottery') : '';
    return;
}

// Определение статуса лотереи
$status_text = '';
$current_time = time();
if (strtotime($lottery['start_date']) > $current_time) {
    $status_text = __('Конкурс ещё не начался (начнётся ', 'woocommerce-lottery') . date('d.m.Y H:i', strtotime($lottery['start_date'])) . ')';
} elseif ($lottery['lottery_status'] === 'active') {
    $status_text = __('Конкурс в процессе', 'woocommerce-lottery');
} elseif ($lottery['lottery_status'] === 'ready_to_draw') {
    $status_text = __('Конкурс готов к подведению итогов', 'woocommerce-lottery');
} elseif ($lottery['lottery_status'] === 'completed') {
    $status_text = __('Конкурс завершён', 'woocommerce-lottery');
}
?>
<div class="wclp-lottery-terms">
    <!-- Lottery Info -->
    <div class="wclp-lottery-info">
        <div class="wclp-lottery-header">
            <h1><?php echo esc_html($lottery['name']); ?></h1>
            <?php if ($status_text): ?>
                <div class="wclp-lottery-status"><?php echo esc_html($status_text); ?></div>
            <?php endif; ?>
            <?php if (!empty($lottery['settings']['social_buttons'])): ?>
                <div class="wclp-social-buttons">
                    <?php 
                    $social_icons = [
                        'whatsapp' => 'whatsapp.png',
                        'vk' => 'vk.png',
                        'telegram' => 'telegram.png',
                        'facebook' => 'facebook.png',
                        'x' => 'x.png'
                    ];
                    foreach ($lottery['settings']['social_buttons'] as $social): ?>
                        <?php if (!empty($lottery['settings']['social_urls'][$social]) && isset($social_icons[$social]) && file_exists(WCLP_PLUGIN_DIR . 'assets/images/social/' . $social_icons[$social])): ?>
                            <a href="<?php echo esc_url($lottery['settings']['social_urls'][$social]); ?>" class="wclp-social-<?php echo esc_attr($social); ?>" target="_blank">
                                <img src="<?php echo esc_url(WCLP_PLUGIN_URL . 'assets/images/social/' . $social_icons[$social]); ?>" alt="<?php echo esc_attr(ucfirst($social)); ?>">
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($lottery['description']): ?>
            <div class="wclp-lottery-description"><?php echo wp_kses_post($lottery['description']); ?></div>
        <?php endif; ?>
        <div class="wclp-lottery-details">
            <p><?php printf(__('Ticket Count: %d', 'woocommerce-lottery'), esc_html($lottery['ticket_count'])); ?></p>
            <p><?php printf(__('Ticket Price: %s', 'woocommerce-lottery'), wc_price($lottery['ticket_price'])); ?></p>
            <?php if ($lottery['draw_type'] === 'date' && $lottery['start_date']): ?>
                <p><?php printf(__('Start Date: %s', 'woocommerce-lottery'), date_i18n('Y-m-d H:i', strtotime($lottery['start_date']))); ?></p>
            <?php endif; ?>
            <?php if ($lottery['draw_type'] === 'date' && $lottery['end_date']): ?>
                <p><?php printf(__('End Date: %s', 'woocommerce-lottery'), date_i18n('Y-m-d H:i', strtotime($lottery['end_date']))); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Prizes -->
    <?php if (!empty($lottery['prizes'])): ?>
        <div class="wclp-lottery-prizes">
            <h2><?php _e('Prizes', 'woocommerce-lottery'); ?></h2>
            <div class="wclp-prize-grid">
                <?php foreach ($lottery['prizes'] as $prize): ?>
                    <div class="wclp-prize-card animate__animated animate__fadeIn">
                        <?php if ($prize['image']): ?>
                            <img src="<?php echo esc_url($prize['image']); ?>" alt="<?php echo esc_attr($prize['name']); ?>" class="wclp-prize-image">
                        <?php endif; ?>
                        <div class="wclp-prize-content">
                            <h3><?php echo esc_html($prize['name']); ?></h3>
                            <?php if ($prize['description']): ?>
                                <p><?php echo wp_kses_post($prize['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>