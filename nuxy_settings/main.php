<?php

/**
 * Initiating Stylemix NUXY settings framework
 */

new BFTOW_Nuxy_Settings();

class BFTOW_Nuxy_Settings
{

    /**
     * @throws Exception
     */
    public function __construct()
    {
        add_filter('stm_wpcfto_boxes', array($this, 'product_metabox'));
        add_filter('stm_wpcfto_fields', array($this, 'product_settings'));
        add_filter('wpcfto_options_page_setup', array($this, 'telegram_settings'));
        add_filter('wpcfto_check_is_pro_field', function () {
            return defined('BFTOW_PRO_DIR');
        });
        add_filter('wpcfto_field_bftow_webhook_activation', function ($path) {
            return BFTOW_DIR . '/nuxy_settings/webhook.php';
        });
        add_filter('wpcfto_field_bftow_notification_channel_id', function ($path) {
            return BFTOW_DIR . '/nuxy_settings/channel_activation.php';
        });
        add_filter('bftow_nuxy_messages_settings', array($this, 'pro_messages'));
        add_action('stm_wpcfto_single_field_before_start', function ($classes, $field_name, $field, $is_pro, $pro_url) { ?>
            <?php if ($is_pro === 'is_pro'): ?>
                <div class="pro_notice">
                    <div>
                        <span><?php echo sprintf('%s available in PRO version.', $field['label']) ?></span>
                        <?php echo apply_filters('bftow_call_to_action_button',
                            sprintf('<a href="https://1.envato.market/9WeZJ0" target="_blank">%s</a>',
                                esc_html__('Preview Pro Version', 'bftow'))
                            );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php }, 10, 5);

        add_action('admin_init', function () {
            $options_moved = get_option('bftow_patch_settings');
            if ($options_moved !== '1.0.0') {
                $settings = [];
                $request_body = $this->telegram_settings([])[0]['fields'];
                if (!empty($request_body)) {
                    foreach ($request_body as $section_name => $section) {
                        foreach ($section['fields'] as $field_name => $field) {
                            $default_value = (isset($field['value'])) ? $field['value'] : '';
                            /*GET STORED OLD VALUE*/
                            $old_value = get_option($field_name);
                            if ($old_value === false) continue;
                            if ($old_value === 'yes') $old_value = true;
                            if ($old_value === 'no') $old_value = false;
                            $settings[$field_name] = (isset($old_value)) ? $old_value : $default_value;

                        }
                    }
                }

                update_option('bftow_settings', $settings);
                update_option('bftow_patch_settings', '1.0.0');
            }
        });
    }

    function telegram_settings($setups)
    {
        $setups[] = array(
            'option_name' => 'bftow_settings',
            'title' => esc_html__('Telegram Bot Settings', 'bot-for-telegram-on-woocommerce'),
            'sub_title' => esc_html__('by Guru Team', 'bot-for-telegram-on-woocommerce'),
            'logo' => BFTOW_URL . '/assets/images/icon.png',
            'page' => array(
                'page_title' => 'Telegram Bot Settings',
                'menu_title' => 'Telegram Bot Settings',
                'menu_slug' => 'bftow_settings',
                'icon' => BFTOW_URL . '/assets/images/icon.png',
                'position' => 40,
            ),
            'fields' => array(
                'bot_settings' => array(
                    'name' => esc_html__('BOT API Settings', 'bot-for-telegram-on-woocommerce'),
                    'fields' => array(
                        'bftow_bot_api' => array(
                            'label' => esc_html__('Telegram Bot Token', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                        ),
                        'bftow_bot_name' => array(
                            'label' => esc_html__('Telegram Bot Name', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'description' => esc_html__('Set if you want user to get back to Telegram after successful checkout. (Without "@")', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_buttons' => array(
                            'label' => esc_html__('Activate API URL', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'bftow_webhook_activation',
                            'description' => esc_html__('Save BOT Token first', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_proxy_server' => array(
                            'label' => esc_html__('Proxy server', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => 'https://api.telegram.org/bot'
                        ),
                        'bftow_google_maps_api_key' => array(
                            'label' => esc_html__('Google Maps API key', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'pro' => true,
                            'description' => __('<a href="https://developers.google.com/maps/documentation/geocoding/overview">Provide Google Maps API key</a> with enabled geocoding API and configured billing account. If you leave this field empty, the location will be taken via openstreetmap', 'bot-for-telegram-on-woocommerce'),
                        ),
                    )
                ),
                'interface_settings' => array(
                    'name' => esc_html__('Interface', 'bot-for-telegram-on-woocommerce'),
                    'fields' => apply_filters('bftow_nuxy_interface_settings', array(
                        'bftow_request_phone_number' => array(
                            'label' => esc_html__('Request phone number', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                        ),
                        'bftow_request_location' => array(
                            'label' => esc_html__('Request location', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'value' => false,
                            'pro' => true,
                        ),
                        'bftow_request_location_every_order' => array(
                            'label' => esc_html__('Request a location for every order', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'value' => false,
                            'pro' => true,
                        ),
                        'bftow_enable_fast_checkout' => array(
                            'label' => esc_html__('Enable fast checkout', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                        ),
                        'bftow_cart_on_site' => array(
                            'label' => esc_html__('Cart page instead checkout page', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'value' => false,
                            'description' => esc_html__('if enabled and the checkout occurs on the site, when clicking on "checkout" button the user will be redirected to the cart page', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_hide_cart_button' => array(
                            'label' => esc_html__('Hide cart button', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'value' => false,
                        ),
                        'bftow_hide_account_button' => array(
                            'label' => esc_html__('Hide my account button', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'value' => false,
                            'pro' => true,
                        ),
                        'bftow_hide_shop_button' => array(
                            'label' => esc_html__('Hide shop button', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'value' => false,
                            'pro' => true,
                        ),
                        'bftow_show_checkout_button' => array(
                            'label' => esc_html__('Show checkout button', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'value' => false,
                        ),
                        'bftow_disable_quantity_input' => array(
                            'label' => esc_html__('Disable quantity input', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                        ),
                        'bftow_show_excerpt' => array(
                            'label' => esc_html__('Show product short description', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                        ),
                        'bftow_show_out_of_stock' => array(
                            'label' => esc_html__('Show out of stock products', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'pro' => true
                        ),
                        'bftow_show_sku' => array(
                            'label' => esc_html__('Show SKU', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                        ),
                        'bftow_show_only_parent_categories' => array(
                            'label' => esc_html__('Show only parent categories', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                        ),
                        'bftow_hierarchy_categories' => array(
                            'label' => esc_html__('Show categories in hierarchy', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'pro' => true,
                            'description' => esc_html__('When you select a category, the child categories will be displayed', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_category_per_row' => array(
                            'label' => esc_html__('Categories per row', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'select',
                            'options' => [
                                1 => 1,
                                2 => 2,
                                3 => 3
                            ],
                            'value' => 2,
                        ),
                        'bftow_user_created_notification' => array(
                            'pro' => true,
                            'label' => esc_html__('Notify when user is created', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'value' => true,
                        ),
                        'bftow_disable_site_checkout' => array(
                            'pro' => true,
                            'label' => esc_html__('Disable checkout', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'description' => esc_html__('Disable checkout on the site, the order will be created automatically, and the administrator will receive a notification about it', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_product_category_buttons' => array(
                            'pro' => true,
                            'label' => esc_html__('Category row', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'repeater',
                            'fields' => array(
                                'category_row' => array(
                                    'type' => 'repeater',
                                    'label' => esc_html__('Category', 'bot-for-telegram-on-woocommerce'),
                                    'fields' => array(
                                        'category' => array(
                                            'type' => 'select',
                                            'options' => $this->get_categories(),
                                            'label' => esc_html__('Select category', 'bot-for-telegram-on-woocommerce'),
                                        )
                                    )
                                )
                            )
                        ),
                        'bftow_enable_search' => array(
                            'pro' => true,
                            'label' => esc_html__('Enable Search', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'value' => esc_html__('Enable Search', 'bot-for-telegram-on-woocommerce'),
                        ),
                    ))
                ),
                'messages_settings' => array(
                    'name' => esc_html__('Messages', 'bot-for-telegram-on-woocommerce'),
                    'fields' => apply_filters('bftow_nuxy_messages_settings', array(
                        'bftow_share_number' => array(
                            'label' => esc_html__('Share number Message', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Share phone number', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_hello_title' => array(
                            'label' => esc_html__('Hello Message', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Hello! Welcome to WooCommerce Telegram Store', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_select_cat' => array(
                            'label' => esc_html__('Select category text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Select category:', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_all_product' => array(
                            'label' => esc_html__('All products text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('All products', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_prod_not_added' => array(
                            'label' => esc_html__('Product not added text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Product not added', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_out_of_stock_button' => array(
                            'label' => esc_html__('Out of stock button text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Out of stock', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_your_cart' => array(
                            'label' => esc_html__('Your cart text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Your cart', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_added_to_cart' => array(
                            'label' => esc_html__('Added to cart text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('added to cart', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_cart_empty' => array(
                            'label' => esc_html__('Cart empty Message', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Cart empty', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_add_to_cart_btn_title' => array(
                            'label' => esc_html__('Add to cart button text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Add to Cart', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_buy_now_btn_text' => array(
                            'label' => esc_html__('Buy Now button text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Buy Now', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_shop_btn_title' => array(
                            'label' => esc_html__('Title Button Shop', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Shop', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_cart_btn_title' => array(
                            'label' => esc_html__('Title Button Cart', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Cart', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_checkout_btn_title' => array(
                            'label' => esc_html__('Title Button Checkout', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Checkout', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_total_text' => array(
                            'label' => esc_html__('Total button text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Total:', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_price_text' => array(
                            'label' => esc_html__('"Price:" text', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Price:', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_proceed_checkout_btn_title' => array(
                            'label' => esc_html__('Title Button Proceed To Checkout', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Proceed to Checkout', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_remove_btn_title' => array(
                            'label' => esc_html__('Title Button Remove', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Remove', 'bot-for-telegram-on-woocommerce')
                        ),
                        'bftow_info_updated' => array(
                            'label' => esc_html__('Information updated message', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('Information updated', 'bot-for-telegram-on-woocommerce'),
                        ),
                        'bftow_sku_text' => array(
                            'label' => esc_html__('SKU Message', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => esc_html__('SKU', 'bot-for-telegram-on-woocommerce'),
                        ),
                    ))
                ),
                'notification_settings' => array(
                    'name' => esc_html__('Notification Settings', 'bot-for-telegram-on-woocommerce'),
                    'fields' => array(
                        'bftow_notification_channel_id' => array(
                            'label' => esc_html__('Get Private Channel ID for Notifications', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'bftow_notification_channel_id',
                            'pro' => true
                        ),
                    )
                ),
                'keyboard' => array(
                    'name' => esc_html__('Keyboard', 'bot-for-telegram-on-woocommerce'),
                    'fields' => array(
                        'keyboard' => array(
                            'pro' => true,
                            'type' => 'repeater',
                            'label' => esc_html__('Custom Button', 'bot-for-telegram-on-woocommerce'),
                            'fields' => array(
                                'button_text' => array(
                                    'type' => 'text',
                                    'label' => esc_html__('Button text', 'bot-for-telegram-on-woocommerce'),
                                ),
                                'command' => array(
                                    'type' => 'text',
                                    'label' => esc_html__('Command', 'bot-for-telegram-on-woocommerce'),
                                    'description' => esc_html__('Optional. Enter telegram command. e.g. /faq', 'bot-for-telegram-on-woocommerce'),
                                ),
                                'message_text' => array(
                                    'type' => 'editor',
                                    'label' => esc_html__('Message', 'bot-for-telegram-on-woocommerce'),
                                ),
                                'message_image' => array(
                                    'type' => 'image',
                                    'label' => esc_html__('Message Image', 'bot-for-telegram-on-woocommerce'),
                                ),
                                'web_app_url' => array(
                                    'type' => 'text',
                                    'label' => esc_html__('Web App URL', 'bot-for-telegram-on-woocommerce'),
                                ),
                            )
                        ),
                    )
                ),
				'tg_login' => array(
                    'name' => esc_html__('Telegram Login Settings', 'bot-for-telegram-on-woocommerce'),
					'fields' => array(
                        'enable_tg_login' => array(
                            'label' => esc_html__('Enable Telegram Login', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'description' => wp_kses_post(sprintf(__('Send the /setdomain command to <a href="https://t.me/BotFather" target="_blank">@Botfather</a> choose your bot, after that send %s', 'bot-for-telegram-on-woocommerce'), $this->get_site_domain()))
                        ),
						'login_button_size' => array(
                            'label' => esc_html__('Button size', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'radio',
                            'dependency_mode' => 'disabled',
                            'options' => [
                                'large' => esc_html__('Large', 'bot-for-telegram-on-woocommerce'),
                                'medium' => esc_html__('Medium', 'bot-for-telegram-on-woocommerce'),
                                'small' => esc_html__('Small', 'bot-for-telegram-on-woocommerce'),
                            ],
                            'value' => 'large',
                            'dependency' => array(
                                'key' => 'enable_tg_login',
                                'value' => 'empty'
                            )
						),
                        'login_show_photo' => array(
                            'label' => esc_html__('Show User Photo', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'dependency_mode' => 'disabled',
                            'dependency' => array(
                                'key' => 'enable_tg_login',
                                'value' => 'empty'
                            )
                        ),
                        'login_corner_radius' => array(
                            'label' => esc_html__('Corner Radius', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'radio',
                            'dependency_mode' => 'disabled',
                            'options' => [
                                'default' => esc_html__('Default', 'bot-for-telegram-on-woocommerce'),
                                'custom' => esc_html__('Custom', 'bot-for-telegram-on-woocommerce'),
                            ],
                            'value' => 'default',
                            'dependency' => array(
                                'key' => 'enable_tg_login',
                                'value' => 'empty'
                            )
                        ),
                        'login_custom_corner_radius' => array(
                            'label' => esc_html__('Corner Radius', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'number',
                            'value' => '20',
                            'dependency' => array(
                                'key' => 'login_corner_radius',
                                'value' => 'custom'
                            )
                        ),
                        'login_redirect' => array(
                            'label' => esc_html__('Redirect page after login', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'radio',
                            'dependency_mode' => 'disabled',
                            'options' => [
                                'current_page' => esc_html__('Current Page', 'bot-for-telegram-on-woocommerce'),
                                'home_page' => esc_html__('Home Page', 'bot-for-telegram-on-woocommerce'),
                                'account' => esc_html__('WooCommerce Account Page', 'bot-for-telegram-on-woocommerce'),
                                'custom' => esc_html__('Custom URL', 'bot-for-telegram-on-woocommerce'),
                            ],
                            'value' => 'current_page',
                            'dependency' => array(
                                'key' => 'enable_tg_login',
                                'value' => 'empty'
                            )
                        ),
                        'login_redirect_url' => array(
                            'label' => esc_html__('Redirect URL', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'text',
                            'value' => get_home_url(),
                            'dependency' => array(
                                'key' => 'login_redirect',
                                'value' => 'custom'
                            )
                        ),
                        'login_button_on_wp_login' => array(
                            'label' => esc_html__('Add Login button on WordPress Login Form', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'dependency_mode' => 'disabled',
                            'dependency' => array(
                                'key' => 'enable_tg_login',
                                'value' => 'empty'
                            )
                        ),
                        'login_button_on_wp_register' => array(
                            'label' => esc_html__('Add Login button on WordPress Register Form', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'dependency_mode' => 'disabled',
                            'dependency' => array(
                                'key' => 'enable_tg_login',
                                'value' => 'empty'
                            )
                        ),
                        'login_button_on_woo_login' => array(
                            'label' => esc_html__('Add Login button on WooCommerce Login Form', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'dependency_mode' => 'disabled',
                            'dependency' => array(
                                'key' => 'enable_tg_login',
                                'value' => 'empty'
                            )
                        ),
                        'login_button_on_woo_register' => array(
                            'label' => esc_html__('Add Login button on WooCommerce Register Form', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'dependency_mode' => 'disabled',
                            'dependency' => array(
                                'key' => 'enable_tg_login',
                                'value' => 'empty'
                            )
                        ),
                        'login_button_on_woo_checkout' => array(
                            'label' => esc_html__('Add Login button on WooCommerce Checkout Form', 'bot-for-telegram-on-woocommerce'),
                            'description' => esc_html__('You have to enable "Allow customers to create an account during checkout" option', 'bot-for-telegram-on-woocommerce'),
                            'type' => 'checkbox',
                            'dependency_mode' => 'disabled',
                            'dependency' => array(
                                'key' => 'enable_tg_login',
                                'value' => 'empty'
                            )
                        ),
					)
				)
            )
        );

        return $setups;
    }

    function get_categories()
    {
        $args = [
            'taxonomy' => ['product_cat'],
            'post_type' => 'product',
            'hide_empty' => true,
            'fields' => 'all'
        ];
        $terms = get_terms($args);
        $all_products_button = bftow_get_option('bftow_all_product', esc_html__('All products', 'bot-for-telegram-on-woocommerce'));
        $categories = array(
            'all;' . $all_products_button => $all_products_button
        );
        foreach ($terms as $term) {
            if (!empty($term->slug)) {
                $categories[$term->slug . ';' . $term->name] = $term->name;
            }
        }
        return $categories;
    }

    function pro_messages($settings)
    {
        $settings['bftow_account_button_text'] = array(
            'label' => esc_html__('My account button text', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('My account', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_orders_button_text'] = array(
            'label' => esc_html__('Order Button text', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('My orders', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_update_phone_button_text'] = array(
            'label' => esc_html__('Update phone button text', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('Update phone', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_update_location_button_text'] = array(
            'label' => esc_html__('Send location button text', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('Send location', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_main_menu_button_text'] = array(
            'label' => esc_html__('Main menu button text', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('Main menu', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_no_orders_found'] = array(
            'label' => esc_html__('No orders found message', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('No orders found', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_your_orders'] = array(
            'label' => esc_html__('Your orders message', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('Your orders', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_account_message'] = array(
            'label' => esc_html__('My account message', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('My account:', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_search_button_text'] = array(
            'label' => esc_html__('Search Button Text', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('Search', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_text_after_search'] = array(
            'label' => esc_html__('Message after search button', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('Enter a search word', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_not_found_text'] = array(
            'label' => esc_html__('Not found message', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('Products not found', 'bot-for-telegram-on-woocommerce'),
        );
        $settings['bftow_search_result_button_text'] = array(
            'label' => esc_html__('Search result button text', 'bot-for-telegram-on-woocommerce'),
            'type' => 'text',
            'pro' => true,
            'value' => esc_html__('Show results', 'bot-for-telegram-on-woocommerce'),
        );
        return $settings;
    }

    function product_metabox($boxes)
    {
        $boxes['bftow_product_metabox'] = [
            'post_type' => ['product'],
            'label' => esc_html__('Telegram product settings', 'bot-for-telegram-on-woocommerce'),
        ];
        return $boxes;
    }

    function product_settings($fields)
    {
        $fields['bftow_product_metabox'] = [
            'tab_1' => [
                'name' => esc_html__('Product settings', 'bot-for-telegram-on-woocommerce'),
                'fields' => [
                    'bftow_hide_product' => [
                        'type' => 'checkbox',
                        'label' => esc_html__('Hide the product in the telegram', 'bot-for-telegram-on-woocommerce'),
                    ],
                ]
            ],
        ];
        return $fields;
    }

	function get_site_domain()
    {
        $home_url = get_home_url();
        $parse = parse_url($home_url);
        return $parse['host'];
    }
}

function bftow_get_option($option_name, $default = '')
{
    $settings = get_option('bftow_settings', []);
    if ($default === 'yes') $default = true;
    if ($default === 'no') $default = false;
    if (empty($settings)) return $default;
    return (isset($settings[$option_name])) ? $settings[$option_name] : $default;
}
