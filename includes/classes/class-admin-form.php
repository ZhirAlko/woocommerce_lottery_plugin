<?php
/**
 * Class WCLP_Admin_Form
 * Handles lottery form rendering and saving
 * WooCommerce Lottery Plugin 2.2.1-beta, Generated: 2025-07-02 10:30:00 EEST
 */

if (!class_exists('WCLP_Admin_Form')) {
    class WCLP_Admin_Form {
        /**
         * Render lottery form
         * @param int $id Lottery ID for editing
         * @param int $clone_id Lottery ID for cloning
         */
        public static function render_form($id = 0, $clone_id = 0) {
            global $wpdb;
            $lottery = $id ? WCLP_Lottery_Manager::get_lottery($id) : ($clone_id ? WCLP_Lottery_Manager::get_lottery($clone_id) : []);

            if ($clone_id) {
                $lottery['id'] = 0;
                $lottery['name'] = !empty($lottery['name']) ? $lottery['name'] . ' (Copy)' : '';
                $lottery['lottery_status'] = 'draft';
                $lottery['last_draw_id'] = null;
            }

            $is_active = !empty($lottery['lottery_status']) && in_array($lottery['lottery_status'], ['active', 'ready_to_draw', 'completed']) && !$clone_id;

            if (!$id && !$clone_id && empty($lottery['prizes'])) {
                $lottery['prizes'] = [[
                    'id' => uniqid(),
                    'name' => '',
                    'description' => '',
                    'image' => '',
                    'product_id' => 0
                ]];
            }

            $lottery = wp_parse_args($lottery, [
                'product_id' => 0,
                'name' => '',
                'description' => '',
                'prizes' => [],
                'start_date' => '',
                'end_date' => '',
                'draw_type' => 'sold',
                'lottery_status' => 'draft',
                'include_unsold' => 0,
                'auto_draw' => 0,
                'is_private' => 0,
                'grid_settings' => [
                    'large' => ['ticket_size' => 35, 'columns' => 10],
                    'medium' => ['ticket_size' => 27, 'columns' => 6],
                    'small' => ['ticket_size' => 20, 'columns' => 3],
                    'color_available' => '#28a745',
                    'color_sold' => '#dc3545',
                    'color_selected' => '#007bff',
                    'font_family' => 'Default',
                    'font_size' => 16,
                    'font_color' => '#fff',
                    'border_width' => 1,
                    'border_color' => '#ddd',
                    'border_radius' => 4,
                    'background_image' => ''
                ],
                'cart_settings' => [
                    'width' => 250,
                    'columns' => 3,
                    'position' => 'right',
                    'sticky' => 0
                ],
                'ticket_count' => 50,
                'ticket_price' => 99,
                'ticket_limit_per_user' => 0,
                'settings' => [
                    'notify_winners' => 0,
                    'notify_admin' => 0,
                    'show_buyer_info' => 1,
                    'social_buttons' => [],
                    'social_urls' => []
                ]
            ]);

            $products = function_exists('wc_get_products') ? WCLP_Lottery_Manager::get_available_products($id) : [];
            $simple_products = function_exists('wc_get_products') ? wc_get_products(['type' => 'simple', 'status' => 'publish', 'limit' => -1]) : [];
            $social_options = [
                'whatsapp' => __('WhatsApp', 'woocommerce-lottery'),
                'vk' => __('VK', 'woocommerce-lottery'),
                'telegram' => __('Telegram', 'woocommerce-lottery'),
                'facebook' => __('Facebook', 'woocommerce-lottery'),
                'x' => __('X', 'woocommerce-lottery')
            ];
            $fonts = ['Default', 'Arial', 'Helvetica', 'Times New Roman', 'Georgia', 'Verdana', 'Courier New'];
            $nonce = wp_create_nonce('wclp_save_draw_' . ($id ?: 'new'));
            $start_date = ($lottery['start_date'] && strtotime($lottery['start_date']) && date('Y', strtotime($lottery['start_date'])) > 0) ? date('Y-m-d\TH:i', strtotime($lottery['start_date'])) : '';
            $end_date = ($lottery['end_date'] && strtotime($lottery['end_date']) && date('Y', strtotime($lottery['end_date'])) > 0) ? date('Y-m-d\TH:i', strtotime($lottery['end_date'])) : '';
            $start_disabled = $is_active || $lottery['draw_type'] === 'sold' ? ' disabled' : '';
            $end_disabled = $is_active || $lottery['draw_type'] === 'sold' ? ' disabled' : '';

            ob_start();
            ?>
            <div class="wclp-form-container active">
                <h2><?php echo esc_html($clone_id ? __('Clone Lottery', 'woocommerce-lottery') : ($id ? __('Edit Lottery', 'woocommerce-lottery') : __('Create Lottery', 'woocommerce-lottery'))); ?></h2>
                <div class="wclp-form-loading" style="display:none;"><?php echo esc_html__('Loading...', 'woocommerce-lottery'); ?></div>
                <form method="post" class="wclp-lottery-form" data-ajax-action="wclp_save_lottery" novalidate>
                    <input type="hidden" name="action" value="wclp_save_lottery">
                    <input type="hidden" name="draw[id]" value="<?php echo esc_attr($id); ?>">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
                    <?php if (!$is_active): ?>
                        <input type="hidden" name="draw[product_id]" value="<?php echo esc_attr($lottery['product_id']); ?>">
                    <?php endif; ?>

                    <div class="wclp-form-columns">
                        <!-- First column: General Settings -->
                        <div class="wclp-form-column">
                            <h3><?php echo esc_html__('General Settings', 'woocommerce-lottery'); ?></h3>
                            <div class="wclp-form-grid">
                                <!-- Name -->
                                <div class="wclp-form-field">
                                    <label><?php echo esc_html__('Name', 'woocommerce-lottery'); ?></label>
                                    <input type="text" name="draw[name]" value="<?php echo esc_attr($lottery['name']); ?>" required minlength="3" maxlength="255" <?php echo $is_active ? 'disabled' : ''; ?>>
                                </div>
                                <!-- Description -->
                                <div class="wclp-form-field">
                                    <label><?php echo esc_html__('Description', 'woocommerce-lottery'); ?></label>
                                    <textarea name="draw[description]"><?php echo esc_textarea($lottery['description']); ?></textarea>
                                </div>
                                <!-- Product -->
                                <div class="wclp-form-field">
                                    <label><?php echo esc_html__('Product', 'woocommerce-lottery'); ?></label>
                                    <select name="draw[product_id_select]" class="wclp-product-select" required <?php echo $is_active ? 'disabled' : ''; ?>>
                                        <option value="create"><?php echo esc_html__('Create new product', 'woocommerce-lottery'); ?></option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?php echo esc_attr($product->get_id()); ?>" <?php echo $lottery['product_id'] == $product->get_id() ? 'selected' : ''; ?>><?php echo esc_html($product->get_name()); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Ticket Price, Count, Limit -->
                                <div class="wclp-form-grid wclp-form-grid-row">
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Ticket Price', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[ticket_price]" value="<?php echo esc_attr($lottery['ticket_price']); ?>" min="0" step="1" required <?php echo $is_active ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Ticket Count', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[ticket_count]" value="<?php echo esc_attr($lottery['ticket_count']); ?>" min="1" max="999" required <?php echo $is_active ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Ticket Limit per User', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[ticket_limit_per_user]" value="<?php echo esc_attr($lottery['ticket_limit_per_user']); ?>" min="0">
                                    </div>
                                </div>
                                <!-- Draw Type, Start Date, End Date -->
                                <div class="wclp-form-grid wclp-form-grid-row">
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Draw Type', 'woocommerce-lottery'); ?></label>
                                        <select name="draw[draw_type]" class="wclp-draw-type" <?php echo $is_active ? 'disabled' : ''; ?>>
                                            <option value="sold" <?php echo $lottery['draw_type'] === 'sold' ? 'selected' : ''; ?>><?php echo esc_html__('Sold Out', 'woocommerce-lottery'); ?></option>
                                            <option value="date" <?php echo $lottery['draw_type'] === 'date' ? 'selected' : ''; ?>><?php echo esc_html__('Date', 'woocommerce-lottery'); ?></option>
                                        </select>
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Start Date', 'woocommerce-lottery'); ?></label>
                                        <input type="datetime-local" name="draw[start_date]" value="<?php echo esc_attr($start_date); ?>" <?php echo $start_disabled; ?>>
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('End Date', 'woocommerce-lottery'); ?></label>
                                        <input type="datetime-local" name="draw[end_date]" value="<?php echo esc_attr($end_date); ?>" <?php echo $end_disabled; ?>>
                                    </div>
                                </div>
                                <!-- Options -->
                                <h3><?php echo esc_html__('Options', 'woocommerce-lottery'); ?></h3>
                                <div class="wclp-form-grid wclp-form-grid-two-columns">
                                    <div class="wclp-form-field">
                                        <label><input type="checkbox" name="draw[auto_draw]" value="1" <?php checked($lottery['auto_draw'], 1); ?>><?php echo esc_html__('Auto Draw', 'woocommerce-lottery'); ?></label>
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><input type="checkbox" name="draw[include_unsold]" value="1" <?php checked($lottery['include_unsold'], 1); ?>><?php echo esc_html__('Include Unsold Tickets', 'woocommerce-lottery'); ?></label>
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><input type="checkbox" name="draw[is_private]" value="1" <?php checked($lottery['is_private'], 1); ?>><?php echo esc_html__('Private Lottery', 'woocommerce-lottery'); ?></label>
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><input type="checkbox" name="draw[settings][show_buyer_info]" value="1" <?php checked($lottery['settings']['show_buyer_info'] ?? 0, 1); ?>><?php echo esc_html__('Show Buyer Info', 'woocommerce-lottery'); ?></label>
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><input type="checkbox" name="draw[settings][notify_winners]" value="1" <?php checked($lottery['settings']['notify_winners'] ?? 0, 1); ?>><?php echo esc_html__('Notify Winners', 'woocommerce-lottery'); ?></label>
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><input type="checkbox" name="draw[settings][notify_admin]" value="1" <?php checked($lottery['settings']['notify_admin'] ?? 0, 1); ?>><?php echo esc_html__('Notify Admin', 'woocommerce-lottery'); ?></label>
                                    </div>
                                </div>
                                <!-- Social Buttons -->
                                <h3><?php echo esc_html__('Social Buttons', 'woocommerce-lottery'); ?></h3>
                                <div class="wclp-form-grid">
                                    <?php foreach ($social_options as $key => $label): ?>
                                        <div class="wclp-form-field">
                                            <label><input type="checkbox" name="draw[settings][social_buttons][]" value="<?php echo esc_attr($key); ?>" <?php echo in_array($key, $lottery['settings']['social_buttons'] ?? []) ? 'checked' : ''; ?>><?php echo esc_html($label); ?></label>
                                            <input type="url" name="draw[settings][social_urls][<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($lottery['settings']['social_urls'][$key] ?? ''); ?>" placeholder="<?php echo esc_attr(sprintf(__('Enter %s URL', 'woocommerce-lottery'), $label)); ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Second column: Prizes -->
                        <div class="wclp-form-column">
                            <h3><?php echo esc_html__('Prizes', 'woocommerce-lottery'); ?> <span class="wclp-prize-count">(<span class="wclp-current-prizes"><?php echo count($lottery['prizes']); ?></span>/<span class="wclp-max-prizes"><?php echo floor($lottery['ticket_count'] * 0.15); ?></span>)</span></h3>
                            <div class="wclp-prizes">
                                <?php foreach ($lottery['prizes'] as $index => $prize): ?>
                                    <div class="wclp-prize">
                                        <input type="hidden" name="draw[prizes][<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($prize['id']); ?>">
                                        <div class="wclp-form-grid">
                                            <div class="wclp-form-field">
                                                <label><?php echo esc_html__('Name', 'woocommerce-lottery'); ?></label>
                                                <input type="text" name="draw[prizes][<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($prize['name']); ?>" required <?php echo $is_active ? 'disabled' : ''; ?>>
                                            </div>
                                            <div class="wclp-form-field">
                                                <label><?php echo esc_html__('Description', 'woocommerce-lottery'); ?></label>
                                                <textarea name="draw[prizes][<?php echo esc_attr($index); ?>][description]"><?php echo esc_textarea($prize['description']); ?></textarea>
                                            </div>
                                            <div class="wclp-form-field">
                                                <label><?php echo esc_html__('Image', 'woocommerce-lottery'); ?></label>
                                                <input type="text" name="draw[prizes][<?php echo esc_attr($index); ?>][image]" value="<?php echo esc_attr($prize['image']); ?>" class="wclp-image-url" readonly>
                                                <input type="hidden" name="draw[prizes][<?php echo esc_attr($index); ?>][image_id]" class="wclp-image-id">
                                                <button type="button" class="wclp-upload-image"><?php echo esc_html__('Select Image', 'woocommerce-lottery'); ?></button>
                                                <button type="button" class="wclp-remove-image" style="<?php echo empty($prize['image']) ? 'display:none;' : ''; ?>"><?php echo esc_html__('Remove', 'woocommerce-lottery'); ?></button>
                                            </div>
                                            <div class="wclp-form-field">
                                                <label><?php echo esc_html__('Product', 'woocommerce-lottery'); ?></label>
                                                <select name="draw[prizes][<?php echo esc_attr($index); ?>][product_id]" class="wclp-prize-product" <?php echo $is_active ? 'disabled' : ''; ?>>
                                                    <option value="0"><?php echo esc_html__('None', 'woocommerce-lottery'); ?></option>
                                                    <?php foreach ($simple_products as $product): ?>
                                                        <option value="<?php echo esc_attr($product->get_id()); ?>" <?php echo $prize['product_id'] == $product->get_id() ? 'selected' : ''; ?>><?php echo esc_html($product->get_name()); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <?php if (!$is_active && count($lottery['prizes']) > 1): ?>
                                                <div class="wclp-form-field">
                                                    <a href="#" class="wclp-remove-prize"><?php echo esc_html__('Remove Prize', 'woocommerce-lottery'); ?></a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!$is_active): ?>
                                <p><a href="#" class="wclp-add-prize button"><?php echo esc_html__('Add Prize', 'woocommerce-lottery'); ?></a></p>
                            <?php endif; ?>
                        </div>

                        <!-- Third column: Grid Settings, Cart Settings -->
                        <div class="wclp-form-column">
                            <h3><?php echo esc_html__('Grid Settings', 'woocommerce-lottery'); ?></h3>
                            <div class="wclp-form-subsection">
                                <h4><?php echo esc_html__('Large Screens (>800px)', 'woocommerce-lottery'); ?></h4>
                                <div class="wclp-form-grid wclp-form-grid-row">
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Ticket Size (px)', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[grid_settings][large][ticket_size]" value="<?php echo esc_attr($lottery['grid_settings']['large']['ticket_size']); ?>" min="20" max="100">
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Columns', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[grid_settings][large][columns]" value="<?php echo esc_attr($lottery['grid_settings']['large']['columns']); ?>" min="5" max="40">
                                    </div>
                                </div>
                            </div>
                            <div class="wclp-form-subsection">
                                <h4><?php echo esc_html__('Medium Screens (480-799px)', 'woocommerce-lottery'); ?></h4>
                                <div class="wclp-form-grid wclp-form-grid-row">
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Ticket Size (px)', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[grid_settings][medium][ticket_size]" value="<?php echo esc_attr($lottery['grid_settings']['medium']['ticket_size']); ?>" min="20" max="80">
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Columns', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[grid_settings][medium][columns]" value="<?php echo esc_attr($lottery['grid_settings']['medium']['columns']); ?>" min="3" max="10">
                                    </div>
                                </div>
                            </div>
                            <div class="wclp-form-subsection">
                                <h4><?php echo esc_html__('Small Screens (<480px)', 'woocommerce-lottery'); ?></h4>
                                <div class="wclp-form-grid wclp-form-grid-row">
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Ticket Size (px)', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[grid_settings][small][ticket_size]" value="<?php echo esc_attr($lottery['grid_settings']['small']['ticket_size']); ?>" min="20" max="60">
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Columns', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[grid_settings][small][columns]" value="<?php echo esc_attr($lottery['grid_settings']['small']['columns']); ?>" min="1" max="5">
                                    </div>
                                </div>
                            </div>
                            <div class="wclp-form-grid wclp-form-grid-row">
                                <div class="wclp-form-field">
                                    <label><?php echo esc_html__('Available Color', 'woocommerce-lottery'); ?></label>
                                    <input type="text" name="draw[grid_settings][color_available]" value="<?php echo esc_attr($lottery['grid_settings']['color_available']); ?>" class="wclp-color-picker">
                                </div>
                                <div class="wclp-form-field">
                                    <label><?php echo esc_html__('Sold Color', 'woocommerce-lottery'); ?></label>
                                    <input type="text" name="draw[grid_settings][color_sold]" value="<?php echo esc_attr($lottery['grid_settings']['color_sold']); ?>" class="wclp-color-picker">
                                </div>
                                <div class="wclp-form-field">
                                    <label><?php echo esc_html__('Selected Color', 'woocommerce-lottery'); ?></label>
                                    <input type="text" name="draw[grid_settings][color_selected]" value="<?php echo esc_attr($lottery['grid_settings']['color_selected']); ?>" class="wclp-color-picker">
                                </div>
                            </div>
                            <div class="wclp-form-grid wclp-form-grid-row">
                                <div class="wclp-form-field wclp-form-field-wide">
                                    <label><?php echo esc_html__('Font Family', 'woocommerce-lottery'); ?></label>
                                    <select name="draw[grid_settings][font_family]">
                                        <?php foreach ($fonts as $font): ?>
                                            <option value="<?php echo esc_attr($font); ?>" <?php echo $lottery['grid_settings']['font_family'] === $font ? 'selected' : ''; ?>><?php echo esc_html($font); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="wclp-form-field wclp-form-field-narrow">
                                    <label><?php echo esc_html__('Font Size (px)', 'woocommerce-lottery'); ?></label>
                                    <input type="number" name="draw[grid_settings][font_size]" value="<?php echo esc_attr($lottery['grid_settings']['font_size']); ?>" min="10" max="24">
                                </div>
                                <div class="wclp-form-field wclp-form-field-narrow">
                                    <label><?php echo esc_html__('Font Color', 'woocommerce-lottery'); ?></label>
                                    <input type="text" name="draw[grid_settings][font_color]" value="<?php echo esc_attr($lottery['grid_settings']['font_color']); ?>" class="wclp-color-picker">
                                </div>
                            </div>
                            <div class="wclp-form-grid wclp-form-grid-row">
                                <div class="wclp-form-field wclp-form-field-narrow">
                                    <label><?php echo esc_html__('Border Width (px)', 'woocommerce-lottery'); ?></label>
                                    <input type="number" name="draw[grid_settings][border_width]" value="<?php echo esc_attr($lottery['grid_settings']['border_width']); ?>" min="0" max="5">
                                </div>
                                <div class="wclp-form-field wclp-form-field-narrow">
                                    <label><?php echo esc_html__('Border Color', 'woocommerce-lottery'); ?></label>
                                    <input type="text" name="draw[grid_settings][border_color]" value="<?php echo esc_attr($lottery['grid_settings']['border_color']); ?>" class="wclp-color-picker">
                                </div>
                                <div class="wclp-form-field wclp-form-field-narrow">
                                    <label><?php echo esc_html__('Border Radius (px)', 'woocommerce-lottery'); ?></label>
                                    <input type="number" name="draw[grid_settings][border_radius]" value="<?php echo esc_attr($lottery['grid_settings']['border_radius']); ?>" min="0" max="20">
                                </div>
                            </div>
                            <div class="wclp-form-field">
                                <label><?php echo esc_html__('Background Image', 'woocommerce-lottery'); ?></label>
                                <input type="text" name="draw[grid_settings][background_image]" value="<?php echo esc_attr($lottery['grid_settings']['background_image']); ?>" class="wclp-image-url" readonly>
                                <input type="hidden" name="draw[grid_settings][background_image_id]" class="wclp-image-id">
                                <button type="button" class="wclp-upload-image"><?php echo esc_html__('Select Image', 'woocommerce-lottery'); ?></button>
                                <button type="button" class="wclp-remove-image" style="<?php echo empty($lottery['grid_settings']['background_image']) ? 'display:none;' : ''; ?>"><?php echo esc_html__('Remove', 'woocommerce-lottery'); ?></button>
                            </div>
                            <!-- Cart Settings -->
                            <h3><?php echo esc_html__('Cart Settings', 'woocommerce-lottery'); ?></h3>
                            <div class="wclp-form-grid">
                                <div class="wclp-form-grid wclp-form-grid-row">
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Width (px)', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[cart_settings][width]" value="<?php echo esc_attr($lottery['cart_settings']['width']); ?>" min="150" max="400">
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Columns', 'woocommerce-lottery'); ?></label>
                                        <input type="number" name="draw[cart_settings][columns]" value="<?php echo esc_attr($lottery['cart_settings']['columns']); ?>" min="1" max="5">
                                    </div>
                                    <div class="wclp-form-field">
                                        <label><?php echo esc_html__('Position', 'woocommerce-lottery'); ?></label>
                                        <select name="draw[cart_settings][position]" class="wclp-cart-position">
                                            <option value="left" <?php echo $lottery['cart_settings']['position'] === 'left' ? 'selected' : ''; ?>><?php echo esc_html__('Left', 'woocommerce-lottery'); ?></option>
                                            <option value="right" <?php echo $lottery['cart_settings']['position'] === 'right' ? 'selected' : ''; ?>><?php echo esc_html__('Right', 'woocommerce-lottery'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="wclp-form-field">
                                    <label><input type="checkbox" name="draw[cart_settings][sticky]" class="wclp-sticky-toggle" value="1" <?php checked($lottery['cart_settings']['sticky'], 1); ?>><?php echo esc_html__('Sticky Cart', 'woocommerce-lottery'); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="submit">
                        <button type="submit" class="wclp-save-lottery button button-primary"><?php echo esc_html__('Save Lottery', 'woocommerce-lottery'); ?></button>
                        <span class="spinner wclp-spinner" style="display:none;"></span>
                    </p>
                </form>
            </div>
            <?php
            $content = ob_get_clean();
            error_log('WCLP: Rendered form content length: ' . strlen($content));
            if (empty($content)) {
                error_log('WCLP: Render form failed: Empty content');
            }
            echo $content;
        }

        /**
         * Save lottery form data
         */
        public static function save_form() {
            check_admin_referer('wclp_save_draw_' . ($_POST['draw']['id'] ?: 'new'));

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'woocommerce-lottery')]);
            }

            $data = $_POST['draw'];
            $id = absint($data['id'] ?? 0);
            $is_new = !$id;

            // Sanitize and prepare data
            $lottery_data = [
                'product_id' => absint($data['product_id'] ?? 0),
                'name' => sanitize_text_field($data['name'] ?? ''),
                'description' => wp_kses_post($data['description'] ?? ''),
                'prizes' => [],
                'start_date' => !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null,
                'end_date' => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
                'draw_type' => sanitize_text_field($data['draw_type'] ?? 'sold'),
                'lottery_status' => sanitize_text_field($data['lottery_status'] ?? 'draft'),
                'include_unsold' => isset($data['include_unsold']) && $data['include_unsold'] === '1' ? 1 : 0,
                'auto_draw' => isset($data['auto_draw']) && $data['auto_draw'] === '1' ? 1 : 0,
                'is_private' => isset($data['is_private']) && $data['is_private'] === '1' ? 1 : 0,
                'grid_settings' => [
                    'large' => [
                        'ticket_size' => absint($data['grid_settings']['large']['ticket_size'] ?? 35),
                        'columns' => absint($data['grid_settings']['large']['columns'] ?? 10)
                    ],
                    'medium' => [
                        'ticket_size' => absint($data['grid_settings']['medium']['ticket_size'] ?? 27),
                        'columns' => absint($data['grid_settings']['medium']['columns'] ?? 6)
                    ],
                    'small' => [
                        'ticket_size' => absint($data['grid_settings']['small']['ticket_size'] ?? 20),
                        'columns' => absint($data['grid_settings']['small']['columns'] ?? 3)
                    ],
                    'color_available' => sanitize_hex_color($data['grid_settings']['color_available'] ?? '#28a745'),
                    'color_sold' => sanitize_hex_color($data['grid_settings']['color_sold'] ?? '#dc3545'),
                    'color_selected' => sanitize_hex_color($data['grid_settings']['color_selected'] ?? '#007bff'),
                    'font_family' => sanitize_text_field($data['grid_settings']['font_family'] ?? 'Default'),
                    'font_size' => absint($data['grid_settings']['font_size'] ?? 16),
                    'font_color' => sanitize_hex_color($data['grid_settings']['font_color'] ?? '#fff'),
                    'border_width' => absint($data['grid_settings']['border_width'] ?? 1),
                    'border_color' => sanitize_hex_color($data['grid_settings']['border_color'] ?? '#ddd'),
                    'border_radius' => absint($data['grid_settings']['border_radius'] ?? 4),
                    'background_image' => esc_url_raw($data['grid_settings']['background_image'] ?? '')
                ],
                'cart_settings' => [
                    'width' => absint($data['cart_settings']['width'] ?? 250),
                    'columns' => absint($data['cart_settings']['columns'] ?? 3),
                    'position' => in_array($data['cart_settings']['position'] ?? 'right', ['left', 'right']) ? $data['cart_settings']['position'] : 'right',
                    'sticky' => isset($data['cart_settings']['sticky']) && $data['cart_settings']['sticky'] === '1' ? 1 : 0
                ],
                'ticket_count' => absint($data['ticket_count'] ?? 50),
                'ticket_price' => absint($data['ticket_price'] ?? 99),
                'ticket_limit_per_user' => absint($data['ticket_limit_per_user'] ?? 0),
                'settings' => [
                    'notify_winners' => isset($data['settings']['notify_winners']) && $data['settings']['notify_winners'] === '1' ? 1 : 0,
                    'notify_admin' => isset($data['settings']['notify_admin']) && $data['settings']['notify_admin'] === '1' ? 1 : 0,
                    'show_buyer_info' => isset($data['settings']['show_buyer_info']) && $data['settings']['show_buyer_info'] === '1' ? 1 : 0,
                    'social_buttons' => array_map('sanitize_text_field', $data['settings']['social_buttons'] ?? []),
                    'social_urls' => array_map('esc_url_raw', $data['settings']['social_urls'] ?? [])
                ]
            ];

            // Sanitize prizes
            if (!empty($data['prizes'])) {
                foreach ($data['prizes'] as $prize) {
                    $lottery_data['prizes'][] = [
                        'id' => sanitize_text_field($prize['id'] ?? uniqid()),
                        'name' => sanitize_text_field($prize['name'] ?? ''),
                        'description' => wp_kses_post($prize['description'] ?? ''),
                        'image' => esc_url_raw($prize['image'] ?? ''),
                        'product_id' => absint($prize['product_id'] ?? 0)
                    ];
                }
            }

            // Log received data for debugging
            error_log('WCLP: Saving form data, cart_settings: ' . print_r($lottery_data['cart_settings'], true));

            // Create new product if needed
            if ($is_new && (!isset($data['product_id_select']) || $data['product_id_select'] === 'create')) {
                $create = WCLP_Lottery_Manager::create_lottery_product($lottery_data['ticket_count'], $lottery_data['ticket_price'], $lottery_data['name']);
                if (is_wp_error($create)) {
                    error_log('WCLP: Save form failed: ' . $create->get_error_message());
                    wp_send_json_error(['message' => $create->get_error_message()]);
                }
                $lottery_data['product_id'] = $create['product_id'];
            }

            // Save lottery
            $result = WCLP_Lottery_Manager::save_lottery($lottery_data, $id);
            if (is_wp_error($result)) {
                error_log('WCLP: Save form failed: ' . $result->get_error_message());
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success(['message' => __('Lottery saved successfully', 'woocommerce-lottery')]);
        }
    }
}

add_action('wp_ajax_wclp_save_lottery', ['WCLP_Admin_Form', 'save_form']);