<?php

class BFTOW_WooCommerce
{
    private $token;
    private $user_id;
    private $bftow_tg_id;
    private $product_id;
    private $variation_id;
    private $quantity = 1;
    private $is_cart_url = false;

    public function __construct()
    {
        if (!empty($_GET['action']) && $_GET['action'] == 'bftow_create_order') {
            $this->token = sanitize_text_field($_GET['bftow_token']);
            add_action('init', array($this, 'create_cart'));
        }

        $this->is_cart_url = bftow_get_option('bftow_cart_on_site', false);

        if (!empty($_GET['bftow_product_id'])) {
            $this->product_id = intval($_GET['bftow_product_id']);
            $this->variation_id = !empty($_GET['bftow_variation_id']) ? intval($_GET['bftow_variation_id']) : 0;
            $this->token = sanitize_text_field($_GET['bftow_token']);
            $this->quantity = sanitize_text_field($_GET['bftow_product_quantity']);
            add_action('wp_loaded', array($this, 'fast_checkout'));
        }

        add_action( 'woocommerce_thankyou', array($this, 'redirect_to_bot'), 10, 1 );

        add_action('woocommerce_order_status_changed', array($this, 'status_changed'), 100, 4);

    }

    function create_cart()
    {
        $url = wc_get_checkout_url();
        if(!empty($this->is_cart_url)) {
            $url = wc_get_cart_url();
        }
        $this->set_user_from_token($this->token);

        /*Something went wrong, redirect to home page*/
        if(empty($this->bftow_tg_id)) {
            wp_redirect( home_url() );
            exit;
        }

        $buyer_cart = BFTOW_Orders::bftow_get_cart_transient($this->bftow_tg_id);

        WC()->cart->empty_cart();

        foreach($buyer_cart as $product) {
            if(!empty($product['variations'])) {
                foreach ($product['variations'] as $varId => $varData) {
                    WC()->cart->add_to_cart($varData['variation_id'], $varData['quantity'], $varData['variation_id']);
                }
            } else {
                WC()->cart->add_to_cart($product['product_id'], $product['quantity']);
            }
        }

        wp_safe_redirect( $url );

        exit();

    }

    function fast_checkout() {
        $this->set_user_from_token($this->token);
        $url = wc_get_checkout_url();
        if(!empty($this->is_cart_url)) {
            $url = wc_get_cart_url();
        }
        /*Something went wrong, redirect to home page*/
        if(empty($this->bftow_tg_id)) {
            wp_redirect( home_url() );
            exit;
        }

        WC()->cart->empty_cart();

        WC()->cart->add_to_cart( $this->product_id, $this->quantity, $this->variation_id );

        wp_safe_redirect( $url );

        exit();

    }

    static function find_user_by_token($token) {
        $args = array(
            'meta_key' => 'bftow_user_token',
            'meta_value' => $token,
            'number' => 1
        );

        $user_query = new WP_User_Query($args);

        return $user_query->get_results();
    }

    function set_user_from_token($token)
    {

        $buyer = self::find_user_by_token($token);

        if(empty($buyer)) return false;

        foreach ($buyer as $buyer_data) {

            $this->set_user_data($buyer_data);

        }
        $user = new BFTOW_User();

        $user->login_user_by_id($this->user_id);

    }

    function set_user_data($user) {

        if(!empty($user->ID)){
            $this->user_id = $user->ID;
        }

        if(!empty($user->data->user_login)){
            $this->bftow_tg_id = $user->data->user_login;
        }

    }


    function redirect_to_bot( $order_id ) {

        global $current_user;

        wp_get_current_user();

        $this->set_user_data($current_user);

        /*if we have cart from tg*/
        $buyer_cart = BFTOW_Orders::bftow_get_cart_transient($this->bftow_tg_id);

        if(!empty($buyer_cart)) {

            //do_action('bftow_order_created', self::get_order_details($order_id), $this->bftow_tg_id);

            BFTOW_Orders::delete_transient($this->bftow_tg_id);

            $bot_url = self::get_bot_url();

            if($bot_url) {
                wp_redirect( $bot_url );
                exit;
            }

        }

    }

    static function get_bot_url() {

        $bot_name = bftow_get_option('bftow_bot_name');

        if(empty($bot_name)) return false;

        return esc_url("t.me/{$bot_name}");

    }

    static function get_order_details($order_id, $order){

        $message = "";

        $is_sent = get_post_meta($order_id, 'bftow_is_sent_to_customer', true);

        if(!empty($is_sent)){
            $statuses = wc_get_order_statuses();
            $status = $order->post->post_status;
            $new_status = $statuses[$status];
            $message = sprintf(
                esc_html__('Order #%s status changed to %s.', 'bot-for-telegram-on-woocommerce'),
                $order_id,
                $new_status
            );
        }
        else {
            $message = BFTOW_Orders::get_order_data($order_id, true);
            update_post_meta($order_id, 'bftow_is_sent_to_customer', '1');
        }

        return $message;
    }

    static function strongify($string) {
        return "<strong>{$string}</strong>";
    }

    function status_changed($order_id, $status_from, $status_to, $order) {
        $user_id = get_post_meta($order_id, '_customer_user', true);
        $user = get_user_by( 'id', $user_id );
        $this->bftow_tg_id = $user->data->user_login;

        do_action('bftow_order_created', self::get_order_details($order_id, $order), $this->bftow_tg_id);

    }
}

new BFTOW_WooCommerce();
