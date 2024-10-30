<?php
/**
 * @var $atts | from BFTOW_Login->login_template
 */
$params = 'data-telegram-login="' . bftow_get_option('bftow_bot_name', '') . '" ';
$params .= 'data-size="' . esc_attr($atts['button_size']) . '"';
$params .= ' data-auth-url="' . esc_attr($atts['redirect_to']) .  '"';
if ( !empty($atts['button_radius']) ) {
    $params .= ' data-radius="' . esc_attr($atts['button_radius']) . '"';
}
if ( empty( $atts['show_photo'] ) ) {
    $params .= ' data-userpic="false"';
}

echo '<div class="bftow_login" style="margin: 10px 0;"><script async src="https://telegram.org/js/telegram-widget.js?21"  data-request-access="write" ' . $params . '></script></div>';
