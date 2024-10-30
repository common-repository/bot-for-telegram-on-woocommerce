<?php

class BFTOW_Helpers
{
    private static $instance = [];

    protected function __construct()
    {
        add_filter('bftow_message_delimiter', 'bftow_delimiter');
    }

    static public function getInstance()
    {
        self::$instance = new self();

        return self::$instance;
    }

    public function bftow_log($logText, $chatId)
    {

        $send_data = [
            'text' => 'Log: ' . $logText,
            'chat_id' => $chatId,
        ];

        BFTOW_Api::getInstance()->send_message('sendMessage', $send_data);
    }

    public function bftow_write_to_file($fileName, $data)
    {
        if (BFTOW_DEBUG) {
            file_put_contents(BFTOW_USER_DIR . $fileName . '.txt', print_r($data, 1) . "\n", FILE_APPEND);
        }
    }

    public function bftow_emoji_conv($code)
    {
        return iconv('UCS-4LE', 'UTF-8', pack('V', $this->bftow_emoji_list($code)));
    }

    public function bftow_emoji_list($emojiCode)
    {
        $emoji = array(
            'U+1F601' => 0x1F601,
            'U+1F62D' => 0x1F62D,
            'U+1F4A9' => 0x1F4A9,
        );

        return $emoji[$emojiCode];
    }

    public function bftow_get_emoji($emojiName)
    {
        $emoji = array(
            'cart' => '🛒',
            'cross' => '❌',
            'checked' => '✅',
            'unchecked' => '☑️',
            'plus' => '➕',
            'minus' => '➖'
        );

        return $emoji[$emojiName];
    }

    public function bftow_delimiter($delim)
    {
        return $delim;
    }
}