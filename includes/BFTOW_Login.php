<?php
new BFTOW_Login;

class BFTOW_Login
{
    public function __construct()
    {
        $this->init_authorize_buttons();
        if (isset($_GET['hash'], $_GET['auth_date'])) {
            add_action('init', [$this, 'login']);
        }

        add_shortcode('bftow_login', [$this, 'login_template']);
    }

    public function login()
    {
        $data = wp_unslash($_GET);
        $data = $this->filter_data($data);
        try {
            $this->validate_data($data);

            if(!empty($data['id'])) {
                $first_name = !empty($data['first_name']) ? sanitize_text_field($data['first_name']) : '';
                $last_name = !empty($data['last_name']) ? sanitize_text_field($data['last_name']) : '';
                $username = !empty($data['username']) ? sanitize_text_field($data['username']) : '';
                $photo_url = !empty($data['photo_url']) ? sanitize_text_field($data['photo_url']) : '';
                $display_name = !empty($last_name) ? $first_name . ' ' . $last_name : $first_name;
                $user = new BFTOW_User();
                $user_id = $user->bftow_create_user($data['id'], $display_name, $first_name, $last_name, $username, $photo_url);
                $user->login_user_by_id($user_id);
            }
        } catch (Exception $e) {
            wp_die($e->getMessage(), esc_html__('Error:', 'bot-for-telegram-on-woocommerce'), ['back_link' => true]);
        }

    }

    public function filter_data($data)
    {

        $desired_fields = [
            'auth_date' => '',
            'hash' => '',
            'id' => '',
            'first_name' => '',
            'last_name' => '',
            'username' => '',
            'photo_url' => '',
            'query_id' => '',
            'user' => '',
            'source' => '',
        ];

        return array_intersect_key($data, $desired_fields);
    }

    public function login_template($atts = [])
    {
        ob_start();
        $this->login_form($atts);
        return ob_get_clean();
    }

    public function login_form($atts = [])
    {
        if (empty(bftow_get_option('enable_tg_login', false)) || get_current_user_id()) {
            return '';
        }
        $atts = shortcode_atts($this->default_attributes(), $atts);
        require_once BFTOW_DIR . '/templates/login.php';
    }


    public function default_attributes()
    {
        $button_size = bftow_get_option('login_button_size', 'large');
        $login_show_photo = bftow_get_option('login_show_photo', false);
        $button_radius = bftow_get_option('login_corner_radius', 'default') === 'custom' ? bftow_get_option('login_custom_corner_radius', '20') : '';
        $redirect_to = bftow_get_option('login_redirect', 'current_page');
        $redirect_to = $redirect_to === 'custom' ? bftow_get_option('login_redirect_url', '') : $redirect_to;
        if ($redirect_to === 'current_page') {
            $redirect_to = '';
        } else if ($redirect_to === 'home_page') {
            $redirect_to = get_home_url();
        } else if ($redirect_to === 'account') {
            $redirect_to = get_permalink(get_option('woocommerce_myaccount_page_id'));
        }
        return [
            'button_size' => $button_size,
            'show_photo' => $login_show_photo,
            'button_radius' => $button_radius,
            'redirect_to' => $redirect_to,
        ];
    }

    public function validate_data($data)
    {

        $token = BFTOW_Settings_Tab::bftow_get_token();

        $incoming_hash = !empty($data['hash']) ? sanitize_text_field($data['hash']) : '';;
        $data_source = !empty($data['source']) ? sanitize_text_field($data['source']) : '';

        unset($data['hash'], $data['source']);

        $secret_key = self::get_secret_key($data_source, $token);

        $generated_hash = self::hash_auth_data($data, $secret_key);

        if (!hash_equals($generated_hash, $incoming_hash)) {
            throw new Exception(__('Unauthorized! Data is NOT from Telegram', 'bot-for-telegram-on-woocommerce'));
        }

        if ((time() - intval($data['auth_date'])) > DAY_IN_SECONDS) {
            throw new Exception(__('Invalid! The data is outdated', 'bot-for-telegram-on-woocommerce'));
        }
    }

    public static function get_secret_key($data_source, $bot_token)
    {
        $secret_key = '';

        switch ($data_source) {
            case 'WebAppData':

                $secret_key = hash_hmac('sha256', $bot_token, 'WebAppData', true);

                break;

            default:

                $secret_key = hash('sha256', $bot_token, true);

                break;
        }

        return $secret_key;
    }

    public static function hash_auth_data($auth_data, $secret_key)
    {

        $data_check_arr = [];

        foreach ($auth_data as $key => $value) {
            $data_check_arr[] = $key . '=' . $value;
        }

        sort($data_check_arr);

        $data_check_string = implode("\n", $data_check_arr);

        return bin2hex(hash_hmac('sha256', $data_check_string, $secret_key, true));
    }

    private function init_authorize_buttons()
    {
        $show_on_login = bftow_get_option('login_button_on_wp_login', false);
        $show_on_register_form = bftow_get_option('login_button_on_wp_register', false);
        $show_on_woocommerce_login_form = bftow_get_option('login_button_on_woo_login', false);
        $show_on_woocommerce_register_form = bftow_get_option('woocommerce_register_form', false);
        $show_on_woocommerce_after_checkout_registration_form = bftow_get_option('login_button_on_woo_register', false);
        if(!empty($show_on_login)){
            add_action('login_form', [$this, 'login_form'], 100);
        }
        if(!empty($show_on_register_form)){
            add_action('register_form', [$this, 'login_form'], 100);
        }
        if(!empty($show_on_woocommerce_login_form)){
            add_action('woocommerce_login_form', [$this, 'login_form'], 100);
        }
        if(!empty($show_on_woocommerce_register_form)){
            add_action('woocommerce_register_form', [$this, 'login_form'], 100);
        }
        if(!empty($show_on_woocommerce_after_checkout_registration_form)){
            add_action('woocommerce_after_checkout_registration_form', [$this, 'login_form'], 100);
        }
    }
}
