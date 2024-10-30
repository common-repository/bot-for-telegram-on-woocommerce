<?php

class BFTOW_Api
{
    private static $instance = [];
    private $BFTOW_Helper;

    protected function __construct()
    {
        $this->BFTOW_Helper = BFTOW_Helpers::getInstance();
    }

    static public function getInstance()
    {
        self::$instance = new self();

        return self::$instance;
    }

    public function send_message($method, $data, $headers = [])
    {
        $api_url = bftow_get_option('bftow_proxy_server', 'https://api.telegram.org/bot');
        $result = wp_remote_post($api_url . BFTOW_Settings_Tab::bftow_get_token() . '/' . $method,
            array(
                'body' => $data,
                'headers' => array_merge(array("Content-Type: application/json"), $headers)
            )
        );

        return (!is_array($result)) ? json_decode($result, 1) : $result;
    }

    public function send_photo($data)
    {
        $api_url = bftow_get_option('bftow_proxy_server', 'https://api.telegram.org/bot');
        $url = $api_url . BFTOW_Settings_Tab::bftow_get_token() . '/sendPhoto';
        $headers = array("Content-Type:multipart/form-data");

        $response = wp_remote_post($url,
            array(
                'method' => 'POST',
                'headers' => $headers,
                'httpversion' => '1.0',
                'sslverify' => false,
                'body' => $data
            )
        );

        $result = wp_remote_retrieve_body($response);

        return (!is_array($result)) ? json_decode($result, 1) : $result;
    }

    public function delete_message($data)
    {
        $result = $this->send_message('deleteMessage', $data);
        return (!is_array($result)) ? json_decode($result, 1) : $result;
    }

    public function bftow_send_message_to_user($user_id, $message = '', $photo = '', $image_size = 'full')
    {
        if(!empty($user_id) && !empty(get_user_meta($user_id, 'bftow_user_token', true))){
            $user = get_userdata($user_id);
            if(!empty($user)){
                $username = $user->user_login;
                if(!empty($photo)){
                    $image = wp_get_attachment_image_src($photo, $image_size);
                    if(!empty($image[0])){
                        $image_url = $image[0];
                        $send_data = [
                            'chat_id' => $username,
                            'photo' => $image_url,
                            'caption' => $message,
                            'parse_mode' => 'html',
                        ];
                        return $this->send_photo($send_data);
                    }
                }
                else {
                    $send_data = [
                        'chat_id' => $username,
                        'text' => $message,
                        'parse_mode' => 'html',
                    ];
                    return $this->send_message('sendMessage', $send_data);
                }
            }
        }
        return false;
    }
}
