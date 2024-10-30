<?php

function bftow_p($data)
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

function bftow_action_with_rest_url()
{
    wp_verify_nonce('ajax_nonce');

    $webHookAction = (!empty($_POST['btn_action']) && $_POST['btn_action'] == 'activate') ? 'setWebhook' : 'deleteWebhook';
    $url = get_site_url() . '/wp-json/woo-telegram/v1/main/';
    $api_url = bftow_get_option('bftow_proxy_server', 'https://api.telegram.org/bot');
    $result = wp_remote_get($api_url . BFTOW_Settings_Tab::bftow_get_token() . '/' . $webHookAction . '?url=' . $url,
        array(
            'headers' => array("Content-Type: application/json")
        )
    );

    wp_send_json(array('message' => json_decode($result['body'])));
}

add_action("wp_ajax_bftow_action_with_rest_url", 'bftow_action_with_rest_url');