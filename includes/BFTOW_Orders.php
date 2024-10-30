<?php

class BFTOW_Orders
{
    private static $transientCartPrefix = 'bftow_temp_cart_';
    private $BFTOW_Products;
    private $BFTOW_Helper;
    private $BFTOW_User;

    public function __construct()
    {
        $this->BFTOW_Helper = BFTOW_Helpers::getInstance();
        $this->BFTOW_Products = new BFTOW_Products();
        $this->BFTOW_User = new BFTOW_User();
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'telegram_order_info']);
    }

    public function telegram_order_info($order)
    {
        $user_id = $order->get_customer_id();
        $telegram_name = get_user_meta($user_id, 'bftow_user_name', true);
        if (!empty($telegram_name)) {
            $telegram_phone = get_user_meta($user_id, 'bftow_phone', true);
            $location = get_user_meta($user_id, '_location', true);
            $address = get_user_meta($user_id, 'bftow_formatted_address', true);
            ob_start();
            ?>
			<div class="address">
				<h2><?php esc_html_e('Telegram user data', 'bot-for-telegram-on-woocommerce'); ?></h2>
				<p>
					<strong><?php esc_html_e('Name', 'bot-for-telegram-on-woocommerce'); ?></strong>
                    <?php echo esc_html($telegram_name); ?>
				</p>
                <?php if (!empty($address)): ?>
					<p>
						<strong><?php esc_html_e('Address', 'bot-for-telegram-on-woocommerce'); ?></strong>
                        <?php echo esc_html($address); ?>
					</p>
                <?php endif; ?>
                <?php if (!empty($telegram_phone)): ?>
					<p>
						<strong><?php esc_html_e('Phone', 'bot-for-telegram-on-woocommerce'); ?></strong>
                        <?php echo esc_html($telegram_phone); ?>
					</p>
                <?php endif; ?>
                <?php if (!empty($location)):
					$coordinates = $location['latitude'] . ',' . $location['longitude'];
					?>
					<p>
						<strong><?php esc_html_e('Location', 'bot-for-telegram-on-woocommerce'); ?></strong>
						<a href="https://www.google.com/maps/?ll=<?php echo esc_html($coordinates); ?>" target="_blank"><?php echo esc_html($coordinates); ?></a>
					</p>
                <?php endif; ?>
			</div>

            <?php
            echo ob_get_clean();
        }
    }

    public function bftow_add_to_cart_transient($tgUserId, $prodId, $quant, $variationId = 0)
    {
        $cartData = $this->bftow_get_cart_transient($tgUserId);

        if (empty($cartData[$prodId])) {
            $cartData[$prodId] = array(
                'product_id' => $prodId,
                'quantity' => $quant
            );

            if ($variationId != 0) {
                $cartData[$prodId]['variations'][$variationId] = array(
                    'product_id' => $variationId,
                    'quantity' => $quant,
                    'variation_id' => $variationId
                );
            }

        } else {
            $cartData[$prodId]['quantity'] = $cartData[$prodId]['quantity'] + $quant;

            if ($variationId != 0) {
                $cartData[$prodId]['variations'][$variationId] = array(
                    'product_id' => $variationId,
                    'quantity' => $quant,
                    'variation_id' => $variationId
                );
            }
        }

        return set_transient(self::$transientCartPrefix . $tgUserId, $cartData);
    }

    public static function bftow_get_cart_transient($tgUserId)
    {
        $cartData = get_transient(self::$transientCartPrefix . $tgUserId);

        $cartData = (!empty($cartData)) ? $cartData : array();

        return $cartData;
    }

    public function bftow_update_cart_transient($tgUserId, $prodId, $quant, $variationId = 0)
    {
        $tempCart = $this->bftow_get_cart_transient($tgUserId);

        if (!empty($tempCart)) {
            if ($variationId != 0) {
                $tempCart[$prodId]['variations'][$variationId] = array(
                    'product_id' => $variationId,
                    'quantity' => $quant,
                    'variation_id' => $variationId
                );
            } else {
                $tempCart[$prodId] = array(
                    'product_id' => $prodId,
                    'quantity' => $quant
                );
            }
        }

        set_transient(self::$transientCartPrefix . $tgUserId, $tempCart);

        return $this->bftow_get_answer_cart($tgUserId);
    }

    public function bftow_remove_from_cart_transient($tgUserId, $prodId, $variationId = 0)
    {
        $tempCart = $this->bftow_get_cart_transient($tgUserId);

        if (!empty($tempCart)) {
            if (empty($variationId)) {
                unset($tempCart[$prodId]);
            } else {
                if (count($tempCart[$prodId]['variations']) > 1) unset($tempCart[$prodId]['variations'][$variationId]);
                else unset($tempCart[$prodId]);
            }
        }

        set_transient(self::$transientCartPrefix . $tgUserId, $tempCart);

        return $this->bftow_get_answer_cart($tgUserId);
    }

    public static function delete_transient($tgUserId)
    {
        delete_transient(self::$transientCartPrefix . $tgUserId);
    }

    public function bftow_get_answer_cart($tgUserId, $edit = false)
    {

        $tempCart = $this->bftow_get_cart_transient($tgUserId);

        if (!empty($tempCart)) {
            $cartItems = array();
            $total = 0;
            foreach ($tempCart as $k => $item) {
                $quant = $item['quantity'];
                $prodId = $item['product_id'];

                if (!empty($item['variations'])) {
                    foreach ($item['variations'] as $vId => $varProd) {
                        $varId = $varProd['product_id'];
                        $quant = $varProd['quantity'];
                        $varRequest = '&chat_id=' . $tgUserId . '&variation_id=' . $varId;

                        $product = $this->BFTOW_Products->bftow_get_product($prodId, $varRequest);
                        if (empty($product)) continue;
                        $name = $product->name;
                        $product = $product->available_product_variations[0];
                        $price = apply_filters('bftow_product_price_in_cart', $product->price, $quant, false);
                        $priceFormated = BFTOW_Products::bftow_get_price_format($price);

                        $total += ($quant * $price);

                        $this->BFTOW_Helper->bftow_write_to_file('_variable', $name);

                        $cartItems[] = [
                            [
                                'text' => $name,
                                'callback_data' => json_encode([
                                    'action' => 'product_popup',
                                ]),
                            ]
                        ];

                        foreach (array_values((array)$product->variation) as $attr) {
                            $cartItems[] = [[
                                'text' => $this->BFTOW_Helper->bftow_get_emoji('checked') . ' ' . $attr,
                                'callback_data' => json_encode(array()),
                            ]];
                        }

                        $cartItems[] = [
                            [
                                'text' => $quant . ' x ' . $priceFormated . ' = ' . BFTOW_Products::bftow_get_price_format($quant * $price),
                                'callback_data' => json_encode(array()),
                            ]
                        ];

                        $cartItems[] = [
                            [
                                'text' => bftow_get_option('bftow_remove_btn_title', esc_html__('Remove', 'bot-for-telegram-on-woocommerce')),
                                'callback_data' => json_encode([
                                    'action' => 'r_f_c',
                                    'quant' => $quant,
                                    'prod_id' => $prodId,
                                    'var_id' => $varId
                                ]),
                            ],
                            [
                                'text' => '-',
                                'callback_data' => json_encode([
                                    'action' => 'r_q_f_c',
                                    'quant' => $quant,
                                    'prod_id' => $prodId,
                                    'var_id' => $varId
                                ]),
                            ],
                            [
                                'text' => '+',
                                'callback_data' => json_encode([
                                    'action' => 'p_q_f_c',
                                    'quant' => $quant,
                                    'prod_id' => $prodId,
                                    'var_id' => $varId
                                ]),
                            ],
                        ];

                        $cartItems[] = [
                            [
                                'text' => '------',
                                'callback_data' => json_encode(array()),
                            ]
                        ];
                    }
                } else {
                    $product = $this->BFTOW_Products->bftow_get_product($prodId);
                    $is_on_sale = !empty($product->is_on_sale) ? true : false;
                    $price = apply_filters('bftow_product_price_in_cart', $product->price, $quant, $is_on_sale);
                    $priceFormated = BFTOW_Products::bftow_get_price_format($price);
                    $name = $product->name;

                    $total += ($quant * $price);
                    $cartItems[] = [
                        [
                            'text' => $name,
                            'callback_data' => json_encode([
                                'action' => 'product_popup',
                            ]),
                        ]
                    ];

                    $cartItems[] = [
                        [
                            'text' => $quant . ' x ' . $priceFormated . ' = ' . BFTOW_Products::bftow_get_price_format($quant * $price),
                            'callback_data' => json_encode(array()),
                        ]
                    ];

                    $cartItems[] = [
                        [
                            'text' => bftow_get_option('bftow_remove_btn_title', esc_html__('Remove', 'bot-for-telegram-on-woocommerce')),
                            'callback_data' => json_encode([
                                'action' => 'r_f_c',
                                'quant' => $quant,
                                'prod_id' => $prodId
                            ]),
                        ],
                        [
                            'text' => '-',
                            'callback_data' => json_encode([
                                'action' => 'r_q_f_c',
                                'quant' => $quant,
                                'prod_id' => $prodId
                            ]),
                        ],
                        [
                            'text' => '+',
                            'callback_data' => json_encode([
                                'action' => 'p_q_f_c',
                                'quant' => $quant,
                                'prod_id' => $prodId
                            ]),
                        ],
                    ];

                    $cartItems[] = [
                        [
                            'text' => '------',
                            'callback_data' => json_encode(array()),
                        ]
                    ];
                }
            }

            $cartItems[] = [
                [
                    'text' => bftow_get_option('bftow_total_text', esc_html__('Total:', 'bot-for-telegram-on-woocommerce')) . ' ' . BFTOW_Products::bftow_get_price_format($total),
                    'callback_data' => json_encode(array()),
                ]
            ];

            $error = false;
            if ((BFTOW_Settings_Tab::bftow_request_ph_num() != false && empty($this->BFTOW_User->bftow_get_user_phone($tgUserId))) ||
                (bftow_get_option('bftow_request_location', false) && (empty($this->BFTOW_User->bftow_get_user_location($tgUserId))) || bftow_get_option('bftow_request_location_every_order', false))) {
                set_transient('bftow_last_action_' . $tgUserId, 'checkout');
                $error = true;
            }

            if ($error) {
                $cartItems[] = [
                    [
                        'text' => bftow_get_option('bftow_checkout_btn_title', esc_html__('Checkout', 'bot-for-telegram-on-woocommerce')),
                        'callback_data' => json_encode([
                            'action' => 'checkout',
                        ]),
                    ]
                ];
            } else {
                if (bftow_get_option('bftow_disable_site_checkout', '') !== true) {
                    $cartItems[] = [
                        [
                            'text' => bftow_get_option('bftow_checkout_btn_title', esc_html__('Checkout', 'bot-for-telegram-on-woocommerce')),
                            'url' => site_url() . '?action=bftow_create_order&bftow_token=' . $this->BFTOW_User->bftow_get_user_token($tgUserId),
                        ]
                    ];
                } else {
                    $cartItems[] = [
                        [
                            'text' => bftow_get_option('bftow_checkout_btn_title', esc_html__('Checkout', 'bot-for-telegram-on-woocommerce')),
                            'callback_data' => json_encode([
                                'action' => 'create_order',
                            ])
                        ]
                    ];
                }
            }

            $send_data = [
                'text' => bftow_get_option('bftow_your_cart', esc_html__('Your cart', 'bot-for-telegram-on-woocommerce')),
                'chat_id' => $tgUserId,
                'parse_mode' => 'html',
                'reply_markup' => json_encode([
                    'resize_keyboard' => true,
                    'inline_keyboard' => $cartItems
                ]),
            ];

            return $send_data;
        } else {
            return $send_data = [
                'cart_empty' => true,
                'text' => bftow_get_option('bftow_cart_empty', esc_html__('Cart empty', 'bot-for-telegram-on-woocommerce')),
                'chat_id' => $tgUserId,
            ];
        }
    }

    public static function get_order_data($order_id, $is_new = false)
    {
        $order = wc_get_order($order_id);
        $meta = get_post_meta($order_id);
        $user_id = !empty($meta['_customer_user'][0]) ? intval($meta['_customer_user'][0]) : '';
        $user_email = $meta['_billing_email'][0];
        $order_data = $order->get_data();
        $order_payment_method = $meta['_payment_method_title'][0];
        $order_date_created = $order_data['date_created']->date('Y-m-d H:i:s');
        $currency = $meta['_order_currency'][0];

        if ($is_new) {
            $message = esc_html__('New Order:', 'bot-for-telegram-on-woocommerce') . " <code>#{$order_id}</code>. \n";
        } else {
            $message = esc_html__('Order', 'bot-for-telegram-on-woocommerce') . " <code>#{$order_id}</code>. \n";
        }

        $message .= esc_html__('Payment Method:', 'bot-for-telegram-on-woocommerce') . " {$order_payment_method}. \n";
        $message .= esc_html__('Order Date:', 'bot-for-telegram-on-woocommerce') . " {$order_date_created}. \n";
        $message .= esc_html__('Order Total:', 'bot-for-telegram-on-woocommerce') . " <code>" . BFTOW_Products::bftow_get_price_format($order->get_total()) . "</code>\n";
        $message .= esc_html__('Order Details', 'bot-for-telegram-on-woocommerce') . "\n";

        foreach ($order->get_items() as $item_key => $item_values):

            $item_data = $item_values->get_data();
            $item_name = $item_values->get_name();
            if (!empty($item_data['variation_id'])) {
                $variation = new WC_Product_Variation($item_data['variation_id']);
                $variationName = implode(" / ", $variation->get_variation_attributes());
                $item_name .= ' : ' . $variationName;
            }
            $quantity = $item_data['quantity'];
            $line_total = $item_data['total'];
            $product = $item_values->get_product();
            if (empty($product)) continue;
            $product_cats = wp_get_post_terms($product->get_id(), 'product_cat');
            $cats = array();
            if (!empty($product_cats)) {
                foreach ($product_cats as $cat) {
                    $cats[] = $cat->name;
                }
            }
            $cats = implode(', ', $cats);
            if (!empty($cats)) {
                $cats = '( ' . $cats . ' )';
            } else {
                $cats = '';
            }
            $message .= "<strong>" . $quantity . "</strong> x " . $item_name . " " . $cats . " - " . BFTOW_Products::bftow_get_price_format($line_total) . "\n";
			if($order->has_downloadable_item() && $order->is_paid() && $order->is_download_permitted()) {
                $downloads = $item_values->get_item_downloads();
                if( ! empty( $downloads ) ) {
                    foreach( $downloads as $download_id => $download ) {
                        $message .= $download['name'] . ' <a href="' . $download['download_url'] . '">' . __("Download link", "bot-for-telegram-on-woocommerce") . '</a>' . "\n";
                    }
                }
            }
        endforeach;

        $phone = '';
        $formated_address = '';
        if (!empty($user_id)) {
            $phone = get_user_meta($user_id, '_phone', true);
            $formated_address = get_user_meta($user_id, 'bftow_formatted_address', true);
        }
        if (empty($phone) && !empty($meta['_billing_phone'][0])) {
            $phone = $meta['_billing_phone'][0];
        }

        if (!empty($meta['_billing_address_index'][0])) {
            $message .= esc_html__('Address:', 'bot-for-telegram-on-woocommerce') . " <code>{$meta['_billing_address_index'][0]}</code>\n";
        }
        if (!empty($formated_address)) {
            $message .= esc_html__('Address from location:', 'bot-for-telegram-on-woocommerce') . " {$formated_address}\n";
        }
        $message .= esc_html__('Customer Details', 'bot-for-telegram-on-woocommerce') . "\n";

        if (!empty($phone)) {
            $message .= esc_html__('Phone number:', 'bot-for-telegram-on-woocommerce') . " <code>{$phone}</code>\n";
        }
        if (!empty($user_email)) {
            $message .= esc_html__('User email:', 'bot-for-telegram-on-woocommerce') . " {$user_email}\n";
        }
        if (!empty($order_data['customer_note'])) {
            $message .= esc_html__('Customer provided note:', 'bot-for-telegram-on-woocommerce') . " {$order_data['customer_note']}\n";
        }


        return apply_filters('bftow_order_data_message', $message, $order_id, $order);
    }
}
