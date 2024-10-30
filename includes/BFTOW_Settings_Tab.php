<?php

class BFTOW_Settings_Tab
{

    public static function bftow_get_token_e()
    {
        echo self::bftow_get_token();
    }

    public static function bftow_get_token()
    {
        return bftow_get_option('bftow_bot_api');
    }

    public static function bftow_request_ph_num()
    {
        return bftow_get_option('bftow_request_phone_number', false);
    }

}

new BFTOW_Settings_Tab;
