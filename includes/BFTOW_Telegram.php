<?php
new BFTOW_Telegram;

class BFTOW_Telegram
{
    private $chat_id, $displayName, $fName, $lName = '';
    private $msg_id = '';
    private $welcome_message = '';
    private $shop_button;
    private $cart_button;
    private $tg_data;
    private $inlineQueryId = '', $catId = '', $offset = 0, $prodId, $t_id, $a_id, $ak, $varId;
    private $BFTOW_User;
    private $BFTOW_Orders;
    private $BFTOW_Products;
    private $BFTOW_Helper;
    private $BFTOW_Api;
    private $fast_checkout = false;
    private $disable_quantity_input = false;
    private $buy_now_btn_text;
    private $show_description;
    private $info_updated;
    private $category_per_row;
    private $show_only_parent_categories;
    private $show_sku;
    private $sku_text;
    private $price_text;
    private $disable_site_checkout;
    private $checkout_button_text;
    private $request_location;
    private $update_location_button_text;
    private $out_of_stock_btn;
    private $hierarchy_categories;

    //Product
    private $quant = 1;

    public function __construct()
    {
        $this->BFTOW_Api = BFTOW_Api::getInstance();
        $this->BFTOW_Helper = BFTOW_Helpers::getInstance();
        $this->BFTOW_User = new BFTOW_User();
        $this->BFTOW_Orders = new BFTOW_Orders();
        $this->BFTOW_Products = new BFTOW_Products();


        $this->shop_button = bftow_get_option('bftow_shop_btn_title', esc_html__('Shop', 'bot-for-telegram-on-woocommerce'));
        $this->cart_button = bftow_get_option('bftow_cart_btn_title', esc_html__('Cart', 'bot-for-telegram-on-woocommerce'));
        $this->welcome_message = bftow_get_option('bftow_hello_title', esc_html__('Hello! Welcome to WooCommerce Telegram Store', 'bot-for-telegram-on-woocommerce'));
        $this->fast_checkout = bftow_get_option('bftow_enable_fast_checkout', false);
        $this->disable_quantity_input = bftow_get_option('bftow_disable_quantity_input', false);
        $this->show_description = bftow_get_option('bftow_show_excerpt', false);
        $this->buy_now_btn_text = bftow_get_option('bftow_buy_now_btn_text', esc_html__('Buy Now', 'bot-for-telegram-on-woocommerce'));
        $this->info_updated = bftow_get_option('bftow_info_updated', esc_html__('Information updated', 'bot-for-telegram-on-woocommerce'));
        $this->category_per_row = bftow_get_option('bftow_category_per_row', 2);
        $this->show_only_parent_categories = bftow_get_option('bftow_show_only_parent_categories', false);
        $this->show_sku = bftow_get_option('bftow_show_sku', false);
        $this->sku_text = bftow_get_option('bftow_sku_text', esc_html__('SKU', 'bot-for-telegram-on-woocommerce'));
        $this->price_text = bftow_get_option('bftow_price_text', esc_html__('Price:', 'bot-for-telegram-on-woocommerce'));
        $this->disable_site_checkout = bftow_get_option('bftow_disable_site_checkout', '');
        $this->checkout_button_text = bftow_get_option('bftow_proceed_checkout_btn_title', esc_html__('Proceed to Checkout', 'bot-for-telegram-on-woocommerce'));
        $this->update_location_button_text = bftow_get_option('bftow_update_location_button_text', esc_html__('Send Location', 'bot-for-telegram-on-woocommerce'));
        $this->out_of_stock_btn = bftow_get_option('bftow_out_of_stock_button', esc_html__('Out of Stock', 'bot-for-telegram-on-woocommerce'));
        $this->request_location = bftow_get_option('bftow_request_location', false);
        $this->hierarchy_categories = bftow_get_option('bftow_hierarchy_categories', false);

        add_action('rest_api_init', array($this, 'register_route'));
        add_action('bftow_order_created', array($this, 'bftow_send_apply_order_msg'), 10, 2);

        if (!empty($_GET['bftow_test'])) {
            echo '<pre>';
            var_dump(get_transient('bftow_test'));
            echo '<pre>';
            die;
        }
    }

    public function register_route()
    {
        register_rest_route('woo-telegram/v1', '/main/', array(
            'methods' => ['GET', 'POST'],
            'permission_callback' => '__return_true',
            'callback' => array($this, 'get_tg_data'),
        ));
    }

    public function get_tg_data()
    {
        $tg_data = file_get_contents('php://input');
        $this->tg_data = json_decode($tg_data, true, 100, JSON_UNESCAPED_UNICODE);
        do_action('bftow_get_tg_data', $this->tg_data);
        if (!empty($this->tg_data['message']['chat']['id'])) {
            $chat_id = $this->tg_data['message']['chat']['id'];
            set_transient('bftow_get_last_user_action_' . $chat_id, $this->tg_data, 600);
        }
        $userID = $this->BFTOW_User->bftow_get_user_system_id($chat_id);
        $is_user_blocked = get_user_meta($userID, 'bftow_user_blocked', true);
        if(!empty($is_user_blocked)) return false;

        set_transient('bftow_test', $this->tg_data, 600);
        $callbacks = array(
            '/start' => 'start_bot',
            trim(mb_strtolower($this->shop_button, 'utf-8')) => 'show_categories',
            trim(mb_strtolower($this->cart_button, 'utf-8')) => 'show_cart',
            trim(mb_strtolower($this->checkout_button_text, 'utf-8')) => 'checkout_action',
            'category' => 'show_products',
            'product' => 'show_product',
            'product_grouped' => 'show_grouped_product',
            'prd_vrbl' => 'show_variable_product',
            'clear_vrbl' => 'clear_selected_variable'
        );

        $queryType = '';

        if (!empty($this->tg_data['callback_query'])) {
            $data = $this->tg_data['callback_query'];
            $this->chat_id = $data['message']['chat']['id'];
            $this->msg_id = $data['message']['message_id'];
            $msg = $data;
            $queryType = 'callback_query';

            if (!empty($data['data'])) {
                $data = json_decode($data['data'], true);

                if (!empty($data['action'])) {
                    switch ($data['action']) {
                        case 'remove_quant':
                            $this->prodId = $data['prod_id'];
                            $this->quant = ($data['quant'] > 1) ? $data['quant'] - 1 : 1;
                            $this->update_product_buttons();
                            break;
                        case 'plus_quant':
                            $this->prodId = $data['prod_id'];
                            $this->quant = $data['quant'] + 1;
                            $this->update_product_buttons();
                            break;
                        case 'rmv_vrbl':
                            $this->prodId = $data['prod_id'];
                            $this->varId = $data['var_id'];
                            $this->quant = ($data['quant'] > 1) ? $data['quant'] - 1 : 1;
                            $product = $this->BFTOW_Products->bftow_get_product($this->prodId, '&chat_id=' . $this->chat_id . '&variation_id=' . $this->varId);
                            $this->update_variable_product_buttons($product);
                            break;
                        case 'pls_vrbl':
                            $this->prodId = $data['prod_id'];
                            $this->varId = $data['var_id'];
                            $this->quant = $data['quant'] + 1;
                            $product = $this->BFTOW_Products->bftow_get_product($this->prodId, '&chat_id=' . $this->chat_id . '&variation_id=' . $this->varId);
                            $this->update_variable_product_buttons($product);
                            break;
                        case 'r_q_f_c':
                            $this->prodId = $data['prod_id'];
                            $this->quant = ($data['quant'] > 1) ? $data['quant'] - 1 : 1;
                            $varId = (!empty($data['var_id'])) ? $data['var_id'] : 0;
                            $updateCart = $this->BFTOW_Orders->bftow_update_cart_transient($this->chat_id, $data['prod_id'], $this->quant, $varId);
                            $updateCart['message_id'] = $this->msg_id;
                            $this->BFTOW_Api->send_message('editMessageReplyMarkup', $updateCart);
                            break;
                        case 'p_q_f_c':
                            $this->prodId = $data['prod_id'];
                            $this->quant = $data['quant'] + 1;
                            $varId = (!empty($data['var_id'])) ? $data['var_id'] : 0;
                            $updateCart = $this->BFTOW_Orders->bftow_update_cart_transient($this->chat_id, $data['prod_id'], $this->quant, $varId);
                            $updateCart['message_id'] = $this->msg_id;
                            $this->BFTOW_Api->send_message('editMessageReplyMarkup', $updateCart);
                            break;
                        case 'atc':
                            $this->BFTOW_Api->delete_message(
                                array(
                                    'chat_id' => $this->chat_id,
                                    'message_id' => $this->msg_id
                                )
                            );

                            $varId = (!empty($data['var_id'])) ? $data['var_id'] : 0;

                            if ($this->BFTOW_Orders->bftow_add_to_cart_transient($this->chat_id, $data['prod_id'], $data['quant'], $varId)) {

                                if ($varId != 0) {
                                    BFTOW_Product_Variable::remove_variation($this->chat_id, $data['prod_id']);
                                }

                                $send_data = array(
                                    'text' => '"' . $this->BFTOW_Products->bftow_get_product_name($data['prod_id']) . '" ' . bftow_get_option('bftow_added_to_cart', esc_html__('added to cart', 'bot-for-telegram-on-woocommerce')),
                                    'chat_id' => $this->chat_id,
                                    'reply_markup' => json_encode([
                                        'resize_keyboard' => true,
                                        'keyboard' => $this->bftow_get_default_keyboard(),
                                    ])
                                );
                                $this->BFTOW_Api->send_message('sendMessage', $send_data);
                            } else {
                                $send_data = array(
                                    'text' => bftow_get_option('bftow_prod_not_added', esc_html__('Product not Added', 'bot-for-telegram-on-woocommerce')),
                                    'chat_id' => $this->chat_id,
                                    'reply_markup' => json_encode([
                                        'resize_keyboard' => true,
                                        'keyboard' => $this->bftow_get_default_keyboard(),
                                    ])
                                );
                                $this->BFTOW_Api->send_message('sendMessage', $send_data);
                            }
                            break;
                        case "r_f_c":
                            $this->prodId = $data['prod_id'];
                            $this->quant = $data['quant'] + 1;
                            $varId = (!empty($data['var_id'])) ? $data['var_id'] : 0;
                            $updateCart = $this->BFTOW_Orders->bftow_remove_from_cart_transient($this->chat_id, $data['prod_id'], $varId);
                            if (!isset($updateCart['cart_empty'])) {
                                $updateCart['message_id'] = $this->msg_id;
                                $this->BFTOW_Api->send_message('editMessageReplyMarkup', $updateCart);
                            } else {
                                unset($updateCart['cart_empty']);

                                $this->BFTOW_Api->delete_message(
                                    array(
                                        'chat_id' => $this->chat_id,
                                        'message_id' => $this->msg_id
                                    )
                                );

                                $updateCart ['reply_markup'] = json_encode([
                                    'resize_keyboard' => true,
                                    'keyboard' => $this->bftow_get_default_keyboard(),
                                ]);
                                $this->BFTOW_Api->send_message('sendMessage', $updateCart);
                            }
                            break;
                        case "checkout":
                            $this->checkout_action();
                            break;
                        case "product_simple":
                            $this->prodId = $data['prod_id'];
                            $callbackId = mb_strtolower('product_grouped', 'utf-8');
                            if (isset($callbacks[$callbackId])) self::{$callbacks[$callbackId]}();
                            break;
                        case "prd_vrbl":
                            $this->prodId = $data['prod_id'];
                            $this->ak = 'term_id';
                            $this->t_id = $data['t_id'];
                            $this->a_id = $data['a_id'];
                            $callbackId = mb_strtolower('prd_vrbl', 'utf-8');
                            if (isset($callbacks[$callbackId])) self::{$callbacks[$callbackId]}();
                            break;
                        case "clear_vrbl":
                            $this->prodId = $data['prod_id'];
                            $callbackId = mb_strtolower('clear_vrbl', 'utf-8');
                            if (isset($callbacks[$callbackId])) self::{$callbacks[$callbackId]}();
                            break;
                        case "child_cats":
                            $this->show_categories($data['term']);
                    }
                }
            }
            return;
        } elseif (!empty($this->tg_data['inline_query']) && !empty($this->tg_data['inline_query']['query'])) {
            $data = $this->tg_data['inline_query'];
            $this->inlineQueryId = $data['id'];
            $chat_id = $data['from']['id'];
            $msg = $data;
            $queryType = 'inline_query';
        } elseif (!empty($this->tg_data['message'])) {
            $data = $this->tg_data['message'];
            $msg = $data;
            $chat_id = $data['chat']['id'];
            $queryType = 'default';

            if (!empty($data['via_bot'])) {
                $this->msg_id = $data['message_id'];
                $this->prodId = $data['text'];
                $queryType = 'via_bot';
            } else if (!empty($data['contact'])) {
                $this->BFTOW_User->bftow_save_user_phone($chat_id, $data['contact']['phone_number']);
                $last_action = $this->get_last_action($chat_id);

                if (!empty($last_action) && $last_action === 'checkout') {
                    if(!empty($this->request_location)) {
                        $send_data = [
                            'text' => $this->update_location_button_text,
                            'chat_id' => $chat_id,
                            'reply_markup' => json_encode([
                                'resize_keyboard' => true,
                                'keyboard' => $this->bftow_request_location_keyboard(),
                            ])
                        ];
                    }
                    else {
                        $send_data = [
                            'text' => $this->info_updated,
                            'chat_id' => $chat_id,
                            'reply_markup' => json_encode([
                                'resize_keyboard' => true,
                                'inline_keyboard' => $this->bftow_get_proceed_checkout_keyboard($chat_id),
                            ])
                        ];
                        $this->set_last_action($chat_id, 'phone');
                    }
                    $this->BFTOW_Api->send_message('sendMessage', $send_data);

                } else {
                    $send_data = [
                        'text' => $this->info_updated,
                        'chat_id' => $chat_id,
                    ];
                    $this->BFTOW_Api->send_message('sendMessage', $send_data);
                }

            }
            else if(!empty($data['location'])) {
                $location = array(
                    'latitude' => $data['location']['latitude'],
                    'longitude' => $data['location']['longitude'],
                );
                $this->BFTOW_User->bftow_save_user_location($chat_id, $location);
                $last_action = $this->get_last_action($chat_id);

                if (!empty($last_action) && ($last_action === 'checkout' || $last_action === 'phone')) {
                    $send_data = [
                        'text' => $this->info_updated,
                        'chat_id' => $chat_id,
                        'reply_markup' => json_encode([
                            'resize_keyboard' => true,
                            'inline_keyboard' => $this->bftow_get_proceed_checkout_keyboard($chat_id),
                        ])
                    ];
                    $this->clear_last_action($chat_id);
                }
                else {
                    $send_data = [
                        'text' => $this->info_updated,
                        'chat_id' => $chat_id,
                        'keyboard' => $this->bftow_get_default_keyboard(),
                    ];
                }
                $this->BFTOW_Api->send_message('sendMessage', $send_data);
            }
        }

        if ($queryType == 'inline_query') {
            $parseData = $data['query'];
            $offset = !empty($data['offset']) ? $data['offset'] : 0;
            $this->offset = intval($offset);
            $callbackId = 'category';
            if (strpos($parseData, 'bftow_search_') === false) {
                $this->catId = ($parseData === 'all') ? 'all' : get_term_by('slug', $parseData, 'product_cat')->term_id;
            } else {
                $this->catId = $parseData;
            }
            if (isset($callbacks[$callbackId])) self::{$callbacks[$callbackId]}();
        } elseif ($queryType == 'via_bot') {
            $this->chat_id = $chat_id;
            $callbackId = mb_strtolower('product', 'utf-8');
            if (isset($callbacks[$callbackId])) self::{$callbacks[$callbackId]}();
        } elseif (!empty($queryType) && $queryType != 'inline_query') {
            $this->chat_id = $chat_id;
            $this->fName = !empty($data['chat']['first_name']) ? $data['chat']['first_name'] : '';
            $this->lName = !empty($data['chat']['last_name']) ? $data['chat']['last_name'] : '';
            $this->displayName = $this->fName;
            if (!empty($this->lName)) {
                $this->displayName .= ' ' . $this->lName;
            }

            $message = mb_strtolower(!empty($data['data']) ? $data['data'] : $data['text'], 'utf-8');

            $callbackId = mb_strtolower($message, 'utf-8');

            if (isset($callbacks[$callbackId])) self::{$callbacks[$callbackId]}();
        }

    }

    private function start_bot()
    {
        $chat_id = $this->chat_id;
        if (!empty($chat_id)) {

            $this->BFTOW_User->bftow_create_user($chat_id, $this->displayName, $this->fName, $this->lName);

            $send_data = [
                'text' => $this->welcome_message,
                'chat_id' => $chat_id,
                'reply_markup' => json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => $this->bftow_get_default_keyboard(),
                ])
            ];

            $this->BFTOW_Api->send_message('sendMessage', $send_data);
        }
    }

    private function show_categories($parent= '')
    {
        $categories = $this->get_categories($parent);
        $keyboard = [];
        $row = [];
        $i = 1;

        foreach ($categories as $id => $category) {
            $text = ($id === 'all') ? $category : $category->name;
            $slug = ($id === 'all') ? $id : $category->slug;
            $button = [
                'text' => $text,
                'switch_inline_query_current_chat' => urldecode($slug),
            ];
            if($id !== 'all' && $this->hierarchy_categories) {
                $term_id = $category->term_id;
                $children = get_terms([
                    'parent' => $term_id,
                    'hide_empty' => true,
                    'taxonomy' => 'product_cat'
                ]);
                if(!empty($children)) {
                    $button = [
                        'text' => $text,
                        'callback_data' => json_encode([
                            'action' => 'child_cats',
                            'term' => $term_id
                        ]),
                    ];
                }
            }
            $row[] = $button;

            if ($i % $this->category_per_row === 0 || $i === count($categories)) {
                $keyboard[] = $row;
                $row = [];
            }

            $i++;
        }

        if(empty($parent)) {
            $keyboard = apply_filters('bftow_categories_keyboard', $keyboard);
        }

        $send_data = [
            'text' => bftow_get_option('bftow_select_cat', esc_html__('Select category:', 'bot-for-telegram-on-woocommerce')),
            'chat_id' => $this->chat_id,
            'reply_markup' => json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => $keyboard,
            ])
        ];

        $this->BFTOW_Api->send_message('sendMessage', $send_data);
    }

    private function show_cart()
    {
        if ($this->BFTOW_Orders->bftow_get_cart_transient($this->chat_id)) {
            $send_data = $this->BFTOW_Orders->bftow_get_answer_cart($this->chat_id);
        } else {
            $send_data = [
                'text' => bftow_get_option('bftow_cart_empty', esc_html__('Cart empty', 'bot-for-telegram-on-woocommerce')),
                'chat_id' => $this->chat_id,
                'reply_markup' => json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => $this->bftow_get_default_keyboard(),
                ])
            ];
        }

        $this->BFTOW_Api->send_message('sendMessage', $send_data);
    }

    function get_categories($parent = '')
    {
        $args = [
            'taxonomy' => ['product_cat'],
            'post_type' => 'product',
            'hide_empty' => true,
            'fields' => 'all'
        ];

        if(!empty($parent)) {
            $args['parent'] = $parent;
        }
        if ($this->show_only_parent_categories === true && empty($parent)) {
            $args['parent'] = 0;
        }

        $args = apply_filters('bftow_get_categories_args', $args);
        $terms = get_terms($args);
        $categories = array();
        foreach ($terms as $term) {
            if (!empty($term->term_id)) {
                $categories[$term->term_id] = $term;
            }
        }
        $categories = apply_filters('bftow_get_product_categories', $categories);
        if(!empty($categories) && count($categories) === 1) $categories = array();
        return array_merge(['all' => bftow_get_option('bftow_all_product', esc_html__('All products', 'bot-for-telegram-on-woocommerce'))], $categories);
    }

    private function show_products()
    {
        $offset = $this->offset;
        $products = apply_filters('bftow_get_products_filter', array(), $this->catId, $offset);
        $post = array(
            "inline_query_id" => $this->inlineQueryId,
            "results" => json_encode($products),
            'cache_time' => 0,
            'next_offset' => strval($offset + 20)
        );

        $this->BFTOW_Api->send_message('answerInlineQuery', $post);
    }

    private function show_product()
    {
        $product = $this->BFTOW_Products->bftow_get_product($this->prodId, '&chat_id=' . $this->chat_id);

        if ($product->type == 'variable') {
            $this->show_variable_product($product);
            return;
        }

        $caption = $product->name . "\n";
        $caption .= $this->price_text . ' ' . $product->price_single_formatted;

        if ($this->show_sku === true && !empty($product->sku)) {
            $caption .= "\n" . $this->sku_text . ': ' . $product->sku;
        }
        if ($this->show_description === true) {
            $excerpt = get_the_excerpt($this->prodId);
            if (!empty($excerpt)) {
                $allowed_html = array(
                    'a' => array(
                        'href' => true,
                        'title' => true,
                    ),
                    'b' => array(),
                    'strong' => array(),
                    'em' => array(),
                    'i' => array(),
                    's' => array(),
                    'strike' => array(),
                    'del' => array(),
                    'pre' => array(
                        'language' => true
                    )
                );
                $excerpt = preg_replace('/<!--(.|s)*?-->/', '', wp_kses($excerpt, $allowed_html));
                $excerpt = substr($excerpt, 0, 960);
                $caption .= "\n\n" . $excerpt;
            }
        }

        $send_data = [
            'chat_id' => $this->chat_id,
            'photo' => $product->image_url,
            'caption' => $caption,
            'parse_mode' => 'html',
            'reply_markup' => $this->bftow_get_product_btns($product)
        ];

        $this->BFTOW_Api->delete_message(
            array(
                'chat_id' => $this->chat_id,
                'message_id' => $this->msg_id
            )
        );

        $this->BFTOW_Api->send_photo($send_data);
    }

    private function show_grouped_product()
    {
        $product = $this->BFTOW_Products->bftow_get_product($this->prodId);

        $priceMain = '';
        switch ($product->type) {
            case 'simple':
                $priceMain = $product->price_formatted;
                break;
            case 'grouped':
                $priceMain = $product->price_formatted;
                break;
            case 'external':
                $priceMain = $product->price_formatted;
                break;
        }
        $caption = $product->name . "\n" . $this->price_text . ' ' . $product->price_formatted;

        if ($this->show_sku === true) {
            $caption .= "\n" . $this->sku_text . ': ' . $product->sku;
        }

        if ($this->show_description === true) {
            $excerpt = get_the_excerpt($this->prodId);
            if (!empty($excerpt)) {
                $excerpt = substr($excerpt, 0, 960);
                $caption .= "\n\n" . $excerpt;
            }
        }
        $send_data = [
            'chat_id' => $this->chat_id,
            'photo' => $product->image_url,
            'caption' => $caption,
            'parse_mode' => 'html',
            'reply_markup' => $this->bftow_get_product_btns($product)
        ];

        $this->BFTOW_Api->send_photo($send_data);
    }

    private function show_variable_product($product = array())
    {
        if (empty($product)) {
            $product = $this->BFTOW_Products->bftow_get_product($this->prodId, '&chat_id=' . $this->chat_id . '&' . $this->t_id . '=' . $this->a_id);
        }

        if ($product->steps->current === $product->steps->total) {
            $selectedVariation = $product->available_product_variations[0];

            $caption = $product->name . "\n" . $this->price_text . ' ' . $selectedVariation->price_formatted;

            if ($this->show_sku === true) {
                $caption .= "\n" . $this->sku_text . ': ' . $product->sku;
            }

            if ($this->show_description === true) {
                $excerpt = get_the_excerpt($this->prodId);
                if (!empty($excerpt)) {
                    $excerpt = substr($excerpt, 0, 960);
                    $caption .= "\n\n" . $excerpt;
                }
            }

            $send_data = [
                'chat_id' => $this->chat_id,
                'photo' => $selectedVariation->image_url,
                'caption' => $caption,
                'parse_mode' => 'html',
                'reply_markup' => $this->bftow_get_product_btns($product)
            ];
        } else {
            $caption = $product->name . "\n" . $this->price_text . ' ' . $product->price_formatted;

            if ($this->show_sku === true) {
                $caption .= "\n" . $this->sku_text . ': ' . $product->sku;
            }

            $send_data = [
                'chat_id' => $this->chat_id,
                'photo' => $product->image_url,
                'caption' => $caption,
                'parse_mode' => 'html',
                'reply_markup' => $this->bftow_get_product_btns($product)
            ];
        }

        $this->BFTOW_Api->delete_message(
            array(
                'chat_id' => $this->chat_id,
                'message_id' => $this->msg_id
            )
        );

        $this->BFTOW_Api->send_photo($send_data);
    }

    private function clear_selected_variable()
    {
        $product = $this->BFTOW_Products->bftow_get_product($this->prodId, '&chat_id=' . $this->chat_id . '&clear=' . $this->chat_id);
        $this->show_variable_product($product);
    }

    private function update_product_buttons()
    {
        $send_data = [
            'chat_id' => $this->chat_id,
            'message_id' => $this->msg_id,
            'reply_markup' => $this->bftow_get_simple_prod_btns($this->prodId)
        ];

        $this->BFTOW_Api->send_message('editMessageReplyMarkup', $send_data);
    }

    private function update_variable_product_buttons($product)
    {
        $send_data = [
            'chat_id' => $this->chat_id,
            'message_id' => $this->msg_id,
            'reply_markup' => $this->bftow_get_variable_prod_btns($product)
        ];

        $this->BFTOW_Api->send_message('editMessageReplyMarkup', $send_data);
    }

    private function bftow_get_product_btns($product)
    {
        if($product->out_of_stock) return $this->get_out_off_stock_btns();

        switch ($product->type) {
            case 'simple':
                return $this->bftow_get_simple_prod_btns($product->id);
            case 'grouped':
                return $this->bftow_get_grouped_prod_btns($product);
            case 'external':
                return $this->bftow_get_external_prod_btn($product);
            case 'variable':
                return $this->bftow_get_variable_prod_btns($product);
            default:
                return apply_filters('bftow_get_product_btns_by_type', $product);
        }
    }

    private function get_out_off_stock_btns()
    {
        return json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => [
                [
                    [
                        'text' => $this->out_of_stock_btn,
                        'callback_data' => json_encode([]),
                    ]
                ]
            ],
        ]);
    }

    private function bftow_get_simple_prod_btns($prodId)
    {
//      bftow_disable_quantity_input
        $inline_keyboard = [];
        if ($this->disable_quantity_input === false) {
            $inline_keyboard[] = [
                [
                    'text' => $this->BFTOW_Helper->bftow_get_emoji('minus'),
                    'callback_data' => json_encode([
                        'action' => 'remove_quant',
                        'quant' => $this->quant,
                        'prod_id' => $prodId,
                    ]),
                ],
                [
                    'text' => $this->quant,
                    'callback_data' => json_encode(array()),
                ],
                [
                    'text' => $this->BFTOW_Helper->bftow_get_emoji('plus'),
                    'callback_data' => json_encode([
                        'action' => 'plus_quant',
                        'quant' => $this->quant,
                        'prod_id' => $prodId,
                    ]),
                ],
            ];
        }

        if ($this->fast_checkout === true) {
            if ($this->disable_site_checkout !== true) {
                $inline_keyboard[] = [
                    [
                        'text' => $this->buy_now_btn_text,
                        'url' => site_url() . '?bftow_product_id=' . $prodId . '&bftow_product_quantity=' . $this->quant . '&bftow_token=' . $this->BFTOW_User->bftow_get_user_token($this->chat_id),
                    ]
                ];
            }
            else {
                $inline_keyboard[] = [
                    [
                        'text' => $this->buy_now_btn_text,
                        'callback_data' => json_encode([
                            'action' => 'create_order',
                            'prd_id' => $prodId,
                            'qnt' => $this->quant,
                        ])
                    ]
                ];
            }

        } else {
            $inline_keyboard[] = [
                [
                    'text' => bftow_get_option('bftow_add_to_cart_btn_title', esc_html__('Add to Cart', 'bot-for-telegram-on-woocommerce')),
                    'callback_data' => json_encode([
                        'action' => 'atc',
                        'quant' => $this->quant,
                        'prod_id' => $prodId,
                    ]),
                ]
            ];
        }
        return json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => $inline_keyboard,
        ]);
    }

    private function bftow_get_grouped_prod_btns($products)
    {
        $btns = array();

        foreach ($products->products as $k => $product) {
            if ($product->type == 'external') {
                $btn = [[
                    'text' => $product->button_text,
                    'url' => $product->url,
                ]];
            } else {
                $btn = [[
                    'text' => $product->name . ' ' . $product->price_formatted,
                    'callback_data' => json_encode([
                        'action' => 'product_' . $product->type,
                        'prod_id' => $product->id,
                    ]),
                ]];
            }

            array_push($btns, $btn);
        }

        return json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => $btns,
        ]);
    }

    private function bftow_get_variable_prod_btns($product)
    {
        $btns = array();
        $btns[] = [[
            'text' => esc_html__('Select variation steps: ', 'bot-for-telegram-on-woocommerce') . $product->steps->current . '/' . $product->steps->total,
            'callback_data' => json_encode([])
        ]];
        if (!empty($product->selected_variations)) {
            foreach ($product->selected_variations as $k => $val) {
                $term = get_term_by('slug', $val, str_replace('attribute_', '', $k));
                $btns[] = [[
                    'text' => $this->BFTOW_Helper->bftow_get_emoji('checked') . ' ' . $term->name,
                    'callback_data' => json_encode([])
                ]];
            }
        }

        if (!empty($product->available_variations) && count($product->available_variations) > 0) {
            foreach ($product->available_variations as $tax => $variation) {
                if (array_key_exists($tax, $product->selected_variations)) continue;
                $availIds = $product->available_variations_ids->$tax;

                foreach ($availIds->attributes as $key => $term_id) {
                    $taxonomy = str_replace('attribute_', '', $tax);
                    $term = get_term($term_id, $taxonomy);
                    $btns[] = [[
                        'text' => $this->BFTOW_Helper->bftow_get_emoji('unchecked') . ' ' . $term->name,
                        'callback_data' => json_encode([
                            'action' => 'prd_vrbl',
                            't_id' => $availIds->id,
                            'a_id' => $term_id,
                            'prod_id' => $product->id,
                        ], JSON_UNESCAPED_UNICODE),
                    ]];
                }
            }
        } else {
            $inline_keyboard = [];
            if ($this->disable_quantity_input === false) {
                $inline_keyboard = [
                    [
                        'text' => $this->BFTOW_Helper->bftow_get_emoji('minus'),
                        'callback_data' => json_encode([
                            'action' => 'rmv_vrbl',
                            'quant' => $this->quant,
                            'prod_id' => $product->id,
                            'var_id' => $product->available_product_variations[0]->variation_id
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                    [
                        'text' => $this->quant,
                        'callback_data' => json_encode(array()),
                    ],
                ];
                if(!$product->available_product_variations[0]->is_manage_stock || $this->quant < $product->available_product_variations[0]->stock) {
                    $inline_keyboard[] = [
                        'text' => $this->BFTOW_Helper->bftow_get_emoji('plus'),
                        'callback_data' => json_encode([
                            'action' => 'pls_vrbl',
                            'quant' => $this->quant,
                            'prod_id' => $product->id,
                            'var_id' => $product->available_product_variations[0]->variation_id
                        ], JSON_UNESCAPED_UNICODE),
                    ];
                }
            }

            array_push($btns, $inline_keyboard);

            if(!$product->available_product_variations[0]->is_manage_stock || $this->quant < $product->available_product_variations[0]->stock) {
                if ($this->fast_checkout === true) {
                    if ($this->disable_site_checkout !== true) {
                        $inline_keyboard = [
                            [
                                'text' => $this->buy_now_btn_text,
                                'url' => site_url() . '?bftow_product_id=' . $product->id . '&bftow_product_quantity=' . $this->quant . '&bftow_token=' . $this->BFTOW_User->bftow_get_user_token($this->chat_id) . '&bftow_variation_id=' . $product->available_product_variations[0]->variation_id,
                            ]
                        ];
                    } else {
                        $inline_keyboard = [
                            [
                                'text' => $this->buy_now_btn_text,
                                'callback_data' => json_encode([
                                    'action' => 'create_order',
                                    'prd_id' => $product->available_product_variations[0]->variation_id,
                                    'qnt' => $this->quant,
                                ])
                            ]
                        ];
                    }
                } else {
                    $inline_keyboard = [
                        [
                            'text' => bftow_get_option('bftow_add_to_cart_btn_title', esc_html__('Add to Cart', 'bot-for-telegram-on-woocommerce')),
                            'callback_data' => json_encode([
                                'action' => 'atc',
                                'quant' => $this->quant,
                                'prod_id' => $product->id,
                                'var_id' => $product->available_product_variations[0]->variation_id
                            ]),
                        ]
                    ];
                }
                array_push($btns, $inline_keyboard);
            }
            else {
                $inline_keyboard = [
                    [
                        'text' => $this->out_of_stock_btn,
                        'callback_data' => json_encode([]),
                    ]
                ];
                array_push($btns, $inline_keyboard);
            }
        }

        if (!empty($product->selected_variations)) {
            $btns[] = [[
                'text' => $this->BFTOW_Helper->bftow_get_emoji('cross') . ' ' . esc_html__('Clear variations', 'bot-for-telegram-on-woocommerce'),
                'callback_data' => json_encode([
                    'action' => 'clear_vrbl',
                    'prod_id' => $product->id,
                ])
            ]];
        }

        return json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => $btns,
        ]);
    }

    private function bftow_get_external_prod_btn($product)
    {
        $btn = [[[
            'text' => $product->button_text,
            'url' => $product->url,
        ]]];

        return json_encode([
            'resize_keyboard' => true,
            'inline_keyboard' => $btn,
        ]);
    }

    public static function bftow_get_default_keyboard()
    {
        $first_row = [];
        if (empty(bftow_get_option('bftow_hide_shop_button', false))) {
            $first_row[] = [
                'text' => bftow_get_option('bftow_shop_btn_title', esc_html__('Shop', 'bot-for-telegram-on-woocommerce')),
            ];
        }
        if (empty(bftow_get_option('bftow_enable_fast_checkout', false)) && empty(bftow_get_option('bftow_hide_cart_button', false))) {
            $first_row[] = [
                'text' => bftow_get_option('bftow_cart_btn_title', esc_html__('Cart', 'bot-for-telegram-on-woocommerce')),
            ];
        }
        $keyboard = [
            $first_row
        ];
        if (bftow_get_option('bftow_show_checkout_button', false)) {
            $keyboard[] = [
                [
                    'text' => bftow_get_option('bftow_proceed_checkout_btn_title', esc_html__('Proceed to Checkout', 'bot-for-telegram-on-woocommerce'))
                ]
            ];
        }


        return apply_filters('bftow_default_keyboard', $keyboard);
    }

    private function bftow_get_with_user_phone_keyboard()
    {
        $default_keyboard = $this->bftow_get_default_keyboard();
        $phone_button = [
            [
                'text' => bftow_get_option('bftow_share_number', esc_html__('Share phone number', 'bot-for-telegram-on-woocommerce')),
                'request_contact' => true
            ]
        ];
        array_unshift($default_keyboard, $phone_button);
        return $default_keyboard;
    }

    private function bftow_request_location_keyboard()
    {
        $default_keyboard = $this->bftow_get_default_keyboard();
        $location_button = [
            [
                'text' => $this->update_location_button_text,
                'request_location' => true
            ]
        ];
        array_unshift($default_keyboard, $location_button);
        return $default_keyboard;
    }

    private function bftow_get_proceed_checkout_keyboard($chat_id)
    {
        if ($this->disable_site_checkout !== true) {
            return [
                [
                    [
                        'text' => $this->checkout_button_text,
                        'url' => site_url() . '?action=bftow_create_order&bftow_token=' . $this->BFTOW_User->bftow_get_user_token($chat_id),
                    ]
                ],
            ];
        } else {
            return [
                [
                    [
                        'text' => $this->checkout_button_text,
                        'callback_data' => json_encode([
                            'action' => 'create_order',
                        ])
                    ]
                ],
            ];
        }
    }

    public function bftow_send_apply_order_msg($msg, $chatId)
    {

        $send_data = [
            'text' => $msg,
            'chat_id' => $chatId,
            'parse_mode' => 'html'
        ];

        $this->BFTOW_Api->send_message('sendMessage', $send_data);
    }

    public function get_last_action($chatId)
    {
        if (!empty($chatId)) {
            return get_transient('bftow_last_action_' . $chatId);
        } else {
            return false;
        }
    }

    public function clear_last_action($chatId)
    {
        if (!empty($chatId)) {
            delete_transient('bftow_last_action_' . $chatId);
        }
    }

    public function set_last_action($chatId, $action)
    {
        if (!empty($chatId) && !empty($action)) {
            set_transient('bftow_last_action_' . $chatId, $action);
        }
    }

    function checkout_action()
    {
        $send_data = [];
        if (BFTOW_Settings_Tab::bftow_request_ph_num() != false && empty($this->BFTOW_User->bftow_get_user_phone($this->chat_id))) {
            $this->set_last_action($this->chat_id, 'checkout');
            $send_data = [
                'text' => bftow_get_option('bftow_share_number', esc_html__('Share phone number', 'bot-for-telegram-on-woocommerce')),
                'chat_id' => $this->chat_id,
                'reply_markup' => json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => $this->bftow_get_with_user_phone_keyboard(),
                ])
            ];
        }
        else if(bftow_get_option('bftow_request_location', false) && (empty($this->BFTOW_User->bftow_get_user_location($this->chat_id)) || bftow_get_option('bftow_request_location_every_order', false))) {
            $send_data = [
                'text' => $this->update_location_button_text,
                'chat_id' => $this->chat_id,
                'reply_markup' => json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => $this->bftow_request_location_keyboard(),
                ])
            ];
        }
        else {
            $send_data = [
                'text' => $this->checkout_button_text,
                'chat_id' => $this->chat_id,
                'reply_markup' => json_encode([
                    'resize_keyboard' => true,
                    'inline_keyboard' => $this->bftow_get_proceed_checkout_keyboard($this->chat_id),
                ])
            ];
        }
        $this->BFTOW_Api->send_message('sendMessage', $send_data);
    }
}
