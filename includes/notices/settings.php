<?php

function bftow_admin_settings_url()
{
    return admin_url('admin.php?page=bftow_settings');
}

function bftow_docs_url()
{
    return "https://wp-guruteam.com/woocommerce-telegram/#!/how-it-works";
}

function bftow_plugin_settings_link($links)
{
    $settings_link = '<a href="' . bftow_admin_settings_url() . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

$plugin = plugin_basename(BFTOW_FILE);
add_filter("plugin_action_links_$plugin", 'bftow_plugin_settings_link');

add_action('admin_notices', function () {
    $token = BFTOW_Settings_Tab::bftow_get_token();
    if (empty($token)) { ?>
        <style>
            #bftow_notice {
                background: rgb(146, 184, 247) !important;
                background: linear-gradient(222deg, rgba(146, 184, 247, 1) 0%, rgba(181, 109, 222, 1) 100%) !important;
                border: none !important;
                color: #fff !important;
                padding: 15px 30px !important;
                border-radius: 10px !important;
            }

            #bftow_notice p {
                margin-bottom: 20px !important;
                font-size: 18px !important;
            }
        </style>
        <div class="notice notice-warning" id="bftow_notice">
            <p>
                <?php esc_html_e('You have not configured the Bot for Telegram on WooCommerce plugin yet', 'bot-for-telegram-on-woocommerce'); ?>
            </p>
            <div style="margin-bottom: 20px;">
                <a href="<?php echo esc_url(bftow_admin_settings_url()) ?>"
                   class="button button-primary"><?php esc_html_e('Take me to settings', 'bot-for-telegram-on-woocommerce'); ?></a>
                <a href="<?php echo esc_url(bftow_docs_url()) ?>"
                   class="button button-primary"
                   target="_blank"
                   style="margin-left: 10px;">
                    <?php esc_html_e('Read docs', 'bot-for-telegram-on-woocommerce'); ?>
                </a>
                <a href="<?php echo esc_url('https://www.youtube.com/watch?v=exBcurPpbNs') ?>"
                   class="button button-primary"
                   target="_blank"
                   style="margin-left: 10px; background-color: #e62117; border-color: #e62117;">
                    <?php esc_html_e('YouTube tutorial', 'bot-for-telegram-on-woocommerce'); ?>
                </a>
            </div>

        </div>
    <?php }
});