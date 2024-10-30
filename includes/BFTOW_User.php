<?php
class BFTOW_User {
    public function __construct() {}

    public function bftow_create_user($tgUserId, $displayName, $fName, $lName, $username = '', $photo_url = '') {

        $userId = $this->bftow_get_user_system_id($tgUserId);
        $is_exists = $userId;
        $parse_url = parse_url(get_bloginfo('url'));
        $email = $tgUserId . '@' . $parse_url['host'];
        $userId = (!$userId) ? wp_create_user( $tgUserId, wp_generate_password(), $email ) : $userId;

        if($userId) {
            $userdata = array(
                'ID'           => $userId,
                'display_name' => $displayName,
                'first_name' => $fName,
                'last_name' => $lName,
            );

            $current_user_data = get_userdata($userId);
            if(empty($current_user_data->user_email)){
                $userdata['user_email'] = $email;
            }

            wp_update_user( $userdata );

            $client = !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'telegram';
            if(!empty($fName)) {
                update_user_meta($userId, 'billing_first_name', $fName);
            }
            if(!empty($lName)) {
                update_user_meta($userId, 'billing_last_name', $lName);
            }
            if(!empty($username)){
                update_user_meta($userId, 'bftow_username', $username);
            }
            if(!empty($photo_url)){
                update_user_meta($userId, 'bftow_photo_url', $photo_url);
            }
            update_user_meta($userId, 'bftow_user_name', $fName);
            update_user_meta($userId, 'bftow_telegram_chat_id', $tgUserId);
            update_user_meta($userId, 'bftow_user_system_id', $userId);
            update_user_meta($userId, 'bftow_user_token', md5( $client . time() ) );

            do_action('bftow_update_user', $is_exists, $userId, $displayName);
        }
        return $userId;
    }

    public function bftow_get_user_system_id ($tgId) {
        $users = get_users([
            'meta_key' => 'bftow_telegram_chat_id',
            'meta_value' => $tgId,
            'fields' => ['ID'],
            'number' => 1
        ]);
        $ids = wp_list_pluck($users, 'ID');
        if(!empty($ids[0])){
            return $ids[0];
        }
        $user = get_user_by('login', $tgId);
        if($user)
        {
            update_user_meta($user->ID, 'bftow_telegram_chat_id', $tgId);
            return $user->ID;
        }

        return false;
    }

    public static function bftow_get_user_tg_chat_id ($user_id) {
        $user = get_user_by('id', $user_id);
        if($user)
        {
            return $user->user_login;
        }

        return false;
    }

    public function bftow_save_user_phone ($tgId, $phone) {
        $userId = $this->bftow_get_user_system_id($tgId);

        if($userId) {
            update_user_meta($userId, 'bftow_phone', $phone);
            update_user_meta($userId, '_phone', $phone);
            update_user_meta($userId, 'billing_phone', $phone);
            do_action('bftow_phone_updated', $userId, $tgId, $phone);
            return true;
        }

        return false;
    }

    public function bftow_save_user_location ($tgId, $location) {
        $userId = $this->bftow_get_user_system_id($tgId);

        if($userId) {
            update_user_meta($userId, '_location', $location);
            do_action('bftow_location_saved', $userId, $location);
            return true;
        }

        return false;
    }

    public function bftow_get_user_phone ( $tgId ) {
        $userId = $this->bftow_get_user_system_id($tgId);

        if($userId) {
            return get_user_meta($userId, '_phone', true);
        }
        return false;
    }

    public function bftow_get_user_location ( $tgId ) {
        $userId = $this->bftow_get_user_system_id($tgId);

        if($userId) {
            return get_user_meta($userId, '_location', true);
        }
        return false;
    }

    public function bftow_get_user_token ( $tgId ) {
        $userId = $this->bftow_get_user_system_id($tgId);

        if($userId) {
            return get_user_meta($userId, 'bftow_user_token', true);
        }
        return false;
    }

    public function bftow_reset_user_data($tgId)
    {
        $userId = $this->bftow_get_user_system_id($tgId);
        delete_user_meta($userId, '_phone', '');
        delete_user_meta($userId, '_location', '');
        delete_user_meta($userId, 'bftow_user_name', '');
        delete_user_meta($userId, 'bftow_user_system_id', '');
        delete_user_meta($userId, 'bftow_user_token', '');
    }

    function login_user_by_id($user_id) {
        clean_user_cache($user_id);
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, false);

        $user = get_user_by('id', $user_id);
        update_user_caches($user);
    }
}
