<?php
/**
 * Class WCLP_Lottery_Manager
 * Handles contest CRUD operations and WooCommerce synchronization
 * WooCommerce Lottery Plugin 2.2.2, Generated: July 30, 2025
 */

if (!class_exists('WCLP_Lottery_Manager')) {
    class WCLP_Lottery_Manager {
        /**
         * Save or update a contest
         * @param array $data Contest data
         * @param int $id Contest ID for update, 0 for create
         * @return int|WP_Error Contest ID or error
         */
        public static function save_lottery($data, $id = 0) {
            global $wpdb;

            $existing = $id ? self::get_lottery($id) : [];
            $is_active = !empty($existing['lottery_status']) && in_array($existing['lottery_status'], ['active', 'ready_to_draw', 'completed']);

            // Default values, preserving existing data
            $defaults = [
                'product_id' => $existing['product_id'] ?? 0,
                'name' => $existing['name'] ?? '',
                'description' => $existing['description'] ?? '',
                'prizes' => $existing['prizes'] ?? [],
                'start_date' => $existing['start_date'] ?? null,
                'end_date' => $existing['end_date'] ?? null,
                'draw_type' => $existing['draw_type'] ?? 'sold',
                'lottery_status' => $existing['lottery_status'] ?? 'draft',
                'include_unsold' => $existing['include_unsold'] ?? 0,
                'auto_draw' => $existing['auto_draw'] ?? 0,
                'is_private' => $existing['is_private'] ?? 0,
                'grid_settings' => $existing['grid_settings'] ?? [
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
                'cart_settings' => $existing['cart_settings'] ?? [
                    'width' => 250,
                    'columns' => 3,
                    'position' => 'right',
                    'sticky' => 0
                ],
                'ticket_count' => $existing['ticket_count'] ?? 50,
                'ticket_price' => $existing['ticket_price'] ?? 99,
                'ticket_limit_per_user' => $existing['ticket_limit_per_user'] ?? 0,
                'settings' => $existing['settings'] ?? [
                    'notify_winners' => 0,
                    'notify_admin' => 0,
                    'show_buyer_info' => 1,
                    'social_buttons' => [],
                    'social_urls' => []
                ],
                'last_draw_id' => $existing['last_draw_id'] ?? null
            ];

            // Merge data with defaults
            $data = wp_parse_args($data, $defaults);

            // Limit ticket_count to 999
            $data['ticket_count'] = min(absint($data['ticket_count']), 999);

            // Use existing values for restricted fields if active
            if ($is_active) {
                $data['product_id'] = $existing['product_id'];
                $data['name'] = $existing['name'];
                $data['ticket_count'] = $existing['ticket_count'];
                $data['ticket_price'] = $existing['ticket_price'];
                $data['start_date'] = $existing['start_date'];
                $data['end_date'] = $existing['end_date'];
                $data['draw_type'] = $existing['draw_type'];
                $data['prizes'] = array_map(function($prize, $index) use ($existing) {
                    $existing_prize = $existing['prizes'][$index] ?? [];
                    return [
                        'id' => sanitize_text_field($prize['id'] ?? uniqid()),
                        'name' => $existing_prize['name'] ?? sanitize_text_field($prize['name'] ?? ''),
                        'description' => wp_kses_post($prize['description'] ?? ''),
                        'image' => esc_url_raw($prize['image'] ?? ''),
                        'product_id' => $existing_prize['product_id'] ?? absint($prize['product_id'] ?? 0)
                    ];
                }, (array)$data['prizes'], array_keys((array)$data['prizes']));
            } else {
                // Validation for non-active contests
                if (empty($data['product_id']) && $data['lottery_status'] !== 'draft') {
                    return new WP_Error('invalid_product', __('Product is required for non-draft contests', 'woocommerce-lottery'));
                }
                if (strlen($data['name']) < 3 || strlen($data['name']) > 255) {
                    return new WP_Error('invalid_name', __('Contest name must be 3-255 characters', 'woocommerce-lottery'));
                }
                if ($data['draw_type'] === 'date' && $data['start_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $data['start_date'])) {
                    return new WP_Error('invalid_date', __('Invalid start date format', 'woocommerce-lottery'));
                }
                if ($data['draw_type'] === 'date' && $data['end_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $data['end_date'])) {
                    return new WP_Error('invalid_date', __('Invalid end date format', 'woocommerce-lottery'));
                }
            }

            // Validate prizes
            $prizes = array_map(function($prize) {
                return [
                    'id' => sanitize_text_field($prize['id'] ?? uniqid()),
                    'name' => sanitize_text_field($prize['name'] ?? ''),
                    'description' => wp_kses_post($prize['description'] ?? ''),
                    'image' => esc_url_raw($prize['image'] ?? ''),
                    'product_id' => absint($prize['product_id'] ?? 0)
                ];
            }, (array)$data['prizes']);
            $data['winners_count'] = count(array_filter($prizes, function($prize) { return !empty($prize['name']); }));
            $max_prizes = floor($data['ticket_count'] * 0.15);
            if ($data['winners_count'] > $max_prizes) {
                return new WP_Error('invalid_winners', __('Winners count must not exceed 15% of tickets', 'woocommerce-lottery'));
            }

            // Validate WooCommerce product
            if ($data['product_id']) {
                $product = wc_get_product($data['product_id']);
                if (!$product || $product->get_type() !== 'variable') {
                    return new WP_Error('invalid_product', __('Invalid variable product', 'woocommerce-lottery'));
                }
            }

            // Format data, handling checkboxes explicitly
            $data['description'] = wp_kses_post($data['description']);
            $data['prizes'] = wp_json_encode($prizes, JSON_UNESCAPED_SLASHES);
            $data['grid_settings'] = wp_json_encode([
                'large' => [
                    'ticket_size' => absint($data['grid_settings']['large']['ticket_size'] ?? ($existing['grid_settings']['large']['ticket_size'] ?? 35)),
                    'columns' => absint($data['grid_settings']['large']['columns'] ?? ($existing['grid_settings']['large']['columns'] ?? 10))
                ],
                'medium' => [
                    'ticket_size' => absint($data['grid_settings']['medium']['ticket_size'] ?? ($existing['grid_settings']['medium']['ticket_size'] ?? 27)),
                    'columns' => absint($data['grid_settings']['medium']['columns'] ?? ($existing['grid_settings']['medium']['columns'] ?? 6))
                ],
                'small' => [
                    'ticket_size' => absint($data['grid_settings']['small']['ticket_size'] ?? ($existing['grid_settings']['small']['ticket_size'] ?? 20)),
                    'columns' => absint($data['grid_settings']['small']['columns'] ?? ($existing['grid_settings']['small']['columns'] ?? 3))
                ],
                'color_available' => sanitize_hex_color($data['grid_settings']['color_available'] ?? ($existing['grid_settings']['color_available'] ?? '#28a745')),
                'color_sold' => sanitize_hex_color($data['grid_settings']['color_sold'] ?? ($existing['grid_settings']['color_sold'] ?? '#dc3545')),
                'color_selected' => sanitize_hex_color($data['grid_settings']['color_selected'] ?? ($existing['grid_settings']['color_selected'] ?? '#007bff')),
                'font_family' => sanitize_text_field($data['grid_settings']['font_family'] ?? ($existing['grid_settings']['font_family'] ?? 'Default')),
                'font_size' => absint($data['grid_settings']['font_size'] ?? ($existing['grid_settings']['font_size'] ?? 16)),
                'font_color' => sanitize_hex_color($data['grid_settings']['font_color'] ?? ($existing['grid_settings']['font_color'] ?? '#fff')),
                'border_width' => absint($data['grid_settings']['border_width'] ?? ($existing['grid_settings']['border_width'] ?? 1)),
                'border_color' => sanitize_hex_color($data['grid_settings']['border_color'] ?? ($existing['grid_settings']['border_color'] ?? '#ddd')),
                'border_radius' => absint($data['grid_settings']['border_radius'] ?? ($existing['grid_settings']['border_radius'] ?? 4)),
                'background_image' => esc_url_raw($data['grid_settings']['background_image'] ?? ($existing['grid_settings']['background_image'] ?? ''))
            ], JSON_UNESCAPED_SLASHES);
            $data['cart_settings'] = wp_json_encode([
                'width' => absint($data['cart_settings']['width'] ?? ($existing['cart_settings']['width'] ?? 250)),
                'columns' => absint($data['cart_settings']['columns'] ?? ($existing['cart_settings']['columns'] ?? 3)),
                'position' => in_array($data['cart_settings']['position'] ?? ($existing['cart_settings']['position'] ?? 'right'), ['left', 'right']) ? $data['cart_settings']['position'] : ($existing['cart_settings']['position'] ?? 'right'),
                'sticky' => isset($data['cart_settings']['sticky']) ? (int)$data['cart_settings']['sticky'] : 0
            ], JSON_UNESCAPED_SLASHES);
            $data['settings'] = wp_json_encode([
                'notify_winners' => isset($data['settings']['notify_winners']) ? (int)$data['settings']['notify_winners'] : 0,
                'notify_admin' => isset($data['settings']['notify_admin']) ? (int)$data['settings']['notify_admin'] : 0,
                'show_buyer_info' => isset($data['settings']['show_buyer_info']) ? (int)$data['settings']['show_buyer_info'] : 0,
                'social_buttons' => array_map('sanitize_text_field', $data['settings']['social_buttons'] ?? ($existing['settings']['social_buttons'] ?? [])),
                'social_urls' => array_map('esc_url_raw', $data['settings']['social_urls'] ?? ($existing['settings']['social_urls'] ?? []))
            ], JSON_UNESCAPED_SLASHES);
            $data['created_at'] = $existing['created_at'] ?? current_time('mysql');
            $data['updated_at'] = current_time('mysql');
            $data['last_draw_id'] = isset($data['last_draw_id']) ? absint($data['last_draw_id']) : ($existing['last_draw_id'] ?? null);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('json_error', __('Failed to encode settings', 'woocommerce-lottery'));
            }

            // Prepare data for DB
            $table = $wpdb->prefix . 'wclp_lotteries';
            $format = [
                '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s'
            ];
            $data_to_save = [
                'product_id' => $data['product_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'winners_count' => $data['winners_count'],
                'prizes' => $data['prizes'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'draw_type' => $data['draw_type'],
                'lottery_status' => $existing['lottery_status'] ?? 'draft', // Preserve existing status
                'include_unsold' => $data['include_unsold'],
                'auto_draw' => $data['auto_draw'],
                'is_private' => $data['is_private'],
                'grid_settings' => $data['grid_settings'],
                'cart_settings' => $data['cart_settings'],
                'ticket_count' => $data['ticket_count'],
                'ticket_price' => $data['ticket_price'],
                'ticket_limit_per_user' => $data['ticket_limit_per_user'],
                'settings' => $data['settings'],
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at']
                ];
            if ($data['last_draw_id'] !== null) {
                $data_to_save['last_draw_id'] = $data['last_draw_id'];
                $format[] = '%d';
            }

            // Save to DB
            try {
                if ($id) {
                    $result = $wpdb->update($table, $data_to_save, ['id' => $id], $format, ['%d']);
                    if ($result === false) {
                        wc_get_logger()->error('WCLP: Failed to update contest ID ' . $id . ': ' . $wpdb->last_error, ['source' => 'wclp']);
                        return new WP_Error('db_error', __('Failed to update contest', 'woocommerce-lottery'));
                    }
                    $lottery_id = $id;
                } else {
                    $result = $wpdb->insert($table, $data_to_save, $format);
                    if ($result === false) {
                        wc_get_logger()->error('WCLP: Failed to create contest: ' . $wpdb->last_error, ['source' => 'wclp']);
                        return new WP_Error('db_error', __('Failed to create contest', 'woocommerce-lottery'));
                    }
                    $lottery_id = $wpdb->insert_id;
                }

                // Sync WooCommerce variations (only for non-active contests)
                if (!$is_active) {
                    self::sync_variations($lottery_id, $data['ticket_count'], $data['ticket_price']);
                }

                return $lottery_id;
            } catch (Exception $e) {
                wc_get_logger()->error('WCLP: Save contest failed: ' . $e->getMessage(), ['source' => 'wclp']);
                return new WP_Error('save_lottery_failed', __('Error saving contest: ', 'woocommerce-lottery') . $e->getMessage());
            }
        }

        /**
         * Activate a contest
         * @param int $id Contest ID
         * @return bool|WP_Error Success or error
         */
        public static function activate_lottery($id) {
            global $wpdb;
            $lottery = self::get_lottery($id);
            if (!$lottery) {
                return new WP_Error('invalid_lottery', __('Invalid contest', 'woocommerce-lottery'));
            }

            if ($lottery['lottery_status'] !== 'draft') {
                return new WP_Error('invalid_status', __('Contest is not in draft status', 'woocommerce-lottery'));
            }

            if (empty($lottery['product_id']) || $lottery['ticket_count'] <= 0) {
                return new WP_Error('invalid_product', __('Valid product and ticket count are required', 'woocommerce-lottery'));
            }

            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wclp_lotteries WHERE product_id = %d AND lottery_status IN ('active', 'ready_to_draw') AND id != %d",
                $lottery['product_id'], $id
            ));
            if ($existing) {
                return new WP_Error('duplicate_lottery', __('Active contest for this product already exists', 'woocommerce-lottery'));
            }

            $result = $wpdb->update(
                $wpdb->prefix . 'wclp_lotteries',
                ['lottery_status' => 'active', 'updated_at' => current_time('mysql')],
                ['id' => $id, 'lottery_status' => 'draft'],
                ['%s', '%s'],
                ['%d', '%s']
            );

            if ($result === false || $result === 0) {
                wc_get_logger()->error('WCLP: Failed to activate contest ID ' . $id . ': ' . $wpdb->last_error, ['source' => 'wclp']);
                return new WP_Error('db_error', __('Failed to activate contest', 'woocommerce-lottery'));
            }

            return true;
        }

        /**
         * Delete a contest
         * @param int $id Contest ID
         * @return bool Success status
         */
        public static function delete_lottery($id) {
            global $wpdb;
            $result = $wpdb->delete($wpdb->prefix . 'wclp_lotteries', ['id' => $id], ['%d']);
            if ($result === false) {
                wc_get_logger()->error('WCLP: Delete contest failed: Database error for ID ' . $id . ': ' . $wpdb->last_error, ['source' => 'wclp']);
            }
            return $result !== false;
        }

        /**
         * Retrieve a contest by ID
         * @param int $id Contest ID
         * @return array|bool Contest data or false
         */
        public static function get_lottery($id) {
            global $wpdb;
            $lottery = wp_cache_get('wclp_lottery_' . $id);
            if ($lottery !== false) {
                return $lottery;
            }

            if (!is_numeric($id) || $id <= 0) {
                wp_cache_set('wclp_lottery_' . $id, [], '', 300);
                return [];
            }
            $lottery = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wclp_lotteries WHERE id = %d", $id), ARRAY_A);
            if ($lottery) {
                $lottery['prizes'] = json_decode($lottery['prizes'] ?: '[]', true) ?: [];
                $lottery['grid_settings'] = json_decode($lottery['grid_settings'] ?: '{}', true) ?: [];
                $lottery['cart_settings'] = json_decode($lottery['cart_settings'] ?: '{}', true) ?: [
                    'width' => 250,
                    'columns' => 3,
                    'position' => 'right',
                    'sticky' => 0
                ];
                $lottery['settings'] = json_decode($lottery['settings'] ?: '{}', true) ?: [];
                $lottery['start_date'] = $lottery['start_date'] ?: '';
                $lottery['end_date'] = $lottery['end_date'] ?: '';
                $lottery['last_draw_id'] = $lottery['last_draw_id'] ? absint($lottery['last_draw_id']) : null;
                $lottery['lottery_status'] = $lottery['lottery_status'] ?: 'draft';
                wp_cache_set('wclp_lottery_' . $id, $lottery, '', 300);
            } else {
                wp_cache_set('wclp_lottery_' . $id, [], '', 300);
            }
            return $lottery ?: [];
        }

        /**
         * Sync contest with WooCommerce product variations
         * @param int $lottery_id Contest ID
         * @param int $ticket_count Number of tickets
         * @param float $ticket_price Price per ticket
         */
        public static function sync_variations($lottery_id, $ticket_count, $ticket_price) {
            global $wpdb;
            $lottery = self::get_lottery($lottery_id);
            if (!$lottery) {
                return;
            }

            $product = wc_get_product($lottery['product_id']);
            if (!$product || $product->get_type() !== 'variable') {
                return;
            }

            $ticket_count = min($ticket_count, 999);

            // Ensure nomer attribute exists
            $attributes = $product->get_attributes();
            if (!isset($attributes['nomer'])) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_name('nomer');
                $attribute->set_options(range(1, $ticket_count));
                $attribute->set_visible(true);
                $attribute->set_variation(true);
                $attributes['nomer'] = $attribute;
                $product->set_attributes($attributes);
                $product->save();
            } else {
                $attribute = $attributes['nomer'];
                $attribute->set_options(range(1, $ticket_count));
                $attributes['nomer'] = $attribute;
                $product->set_attributes($attributes);
                $product->save();
            }

            // Get existing variations
            $existing_variations = $product->get_children();
            $existing_nomers = [];
            foreach ($existing_variations as $vid) {
                $variation = wc_get_product($vid);
                if (!$variation) continue;
                $nomer = $variation->get_attribute('nomer');
                if ($nomer) {
                    $existing_nomers[$vid] = $nomer;
                } else {
                    wp_delete_post($vid, true);
                }
            }

            // Delete excess variations
            foreach ($existing_nomers as $vid => $nomer) {
                if ((int)$nomer > $ticket_count) {
                    wp_delete_post($vid, true);
                    unset($existing_nomers[$vid]);
                }
            }

            // Create or update variations
            $batch_size = 25;
            $new_nomers = [];
            for ($i = 1; $i <= $ticket_count; $i++) {
                $nomer = (string)$i;
                if (!in_array($nomer, $existing_nomers)) {
                    $new_nomers[] = $nomer;
                }
            }

            foreach ($new_nomers as $nomer) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product->get_id());
                $variation->set_attributes(['nomer' => $nomer]);
                $variation->set_regular_price($ticket_price);
                $variation->set_price($ticket_price);
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity(1);
                $variation->set_status('publish');
                $variation->save();
            }

            // Update existing variations
            $reserved_nomers = $wpdb->get_col($wpdb->prepare(
                "SELECT nomer FROM {$wpdb->prefix}wclp_reservations WHERE lottery_id = %d AND order_id IS NOT NULL",
                $lottery_id
            ));

            foreach ($existing_nomers as $vid => $nomer) {
                if ((int)$nomer <= $ticket_count) {
                    $variation = wc_get_product($vid);
                    if ($variation) {
                        $variation->set_regular_price($ticket_price);
                        $variation->set_price($ticket_price);
                        $stock = in_array($nomer, $reserved_nomers) ? 0 : 1;
                        $variation->set_stock_quantity($stock);
                        $variation->set_manage_stock(true);
                        $variation->save();
                    }
                }
            }

            // Verify variations
            $final_variations = $product->get_children();
            $final_nomers = [];
            foreach ($final_variations as $vid) {
                $variation = wc_get_product($vid);
                if ($variation) {
                    $nomer = $variation->get_attribute('nomer');
                    if ($nomer) {
                        $final_nomers[] = $nomer;
                    }
                }
            }

            // Update product meta
            update_post_meta($product->get_id(), '_lottery_id', $lottery_id);
            update_post_meta($product->get_id(), '_ticket_count', $ticket_count);
            update_post_meta($product->get_id(), '_ticket_price', $ticket_price);
        }

        /**
         * Get available products for contest
         * @param int $current_lottery_id Optional ID of the current contest being edited
         * @return array List of eligible products
         */
        public static function get_available_products($current_lottery_id = 0) {
            global $wpdb;
            $cache_key = 'wclp_available_products_' . $current_lottery_id;
            $products = wp_cache_get($cache_key);
            if ($products !== false) {
                return $products;
            }

            $exclude_ids = [];
            $current_product_id = 0;

            if ($current_lottery_id) {
                $current_lottery = self::get_lottery($current_lottery_id);
                $current_product_id = $current_lottery ? $current_lottery['product_id'] : 0;
                $exclude_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT product_id FROM {$wpdb->prefix}wclp_lotteries WHERE lottery_status IN ('active', 'ready_to_draw', 'draft') AND id != %d",
                    $current_lottery_id
                ));
            } else {
                $exclude_ids = $wpdb->get_col("SELECT product_id FROM {$wpdb->prefix}wclp_lotteries WHERE lottery_status IN ('active', 'ready_to_draw', 'draft')");
            }

            $products = wc_get_products([
                'type' => 'variable',
                'status' => 'publish',
                'limit' => -1,
                'exclude' => $exclude_ids
            ]);
            $filtered = array_filter($products, function($product) use ($current_product_id) {
                $attributes = $product->get_attributes();
                return isset($attributes['nomer']) && !empty($product->get_children()) || ($current_product_id && $product->get_id() == $current_product_id);
            });
            wp_cache_set($cache_key, $filtered, '', 3600);
            return $filtered;
        }
    }
}