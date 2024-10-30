<?php

/**
 * @var $field
 * @var $field_id
 * @var $field_value
 * @var $field_label
 * @var $field_name
 * @var $section_name
 *
 */
$public_channel = bftow_get_option('bftow_notification_public_channel_id');
$chat_id = bftow_get_option('bftow_notification_channel_id');

?>

<bftow_channel_activation inline-template>
    <div class="wpcfto_generic_field wpcfto_generic_field_flex_input wpcfto_generic_field__text">
        <div class="wpcfto-field-aside">
            <label class="wpcfto-field-aside__label"><?php esc_html_e('Get Channel ID for Notifications', 'bot-for-telegram-on-woocommerce'); ?></label>
			<div class="wpcfto-field-description wpcfto-field-description__before description"><?php esc_html_e('To enable notification, you need to create a telegram channel, make it public, add your bot as an administrator to it. After that, specify the name of the channel and click "Get Channel ID". After that, you can make your channel private.', 'bot-for-telegram-on-woocommerce'); ?></div>
        </div>
        <div class="wpcfto-field-content">
            <div class="forminp" style="width: 100%">
                <input type="text" style="margin-bottom: 15px;" placeholder="<?php esc_attr_e('Public channel id. Example: @yourchannel', 'bot-for-telegram-on-woocommerce'); ?>" v-model="public_channel_id" />
                <input type="text" placeholder="Private channel id. Example: -1001000000000" id="bftow_notification_channel_id" v-model="private_channel_id"><br>
                <a href="#" class="button-primary" id="bftow_get_channel_id" @click.prevent="get_private_channel()"><?php esc_html_e('Get Channel ID', 'bot-for-telegram-on-woocommerce'); ?></a>
                <div class="wt-message"></div>
            </div>
        </div>
    </div>
</bftow_channel_activation>

<script>
    Vue.component('bftow_channel_activation', {
        data: function () {
            return {
                public_channel_id: '<?php echo esc_js($public_channel) ?>',
                private_channel_id: '<?php echo esc_js($chat_id) ?>',
            }
        },
        methods: {
            get_private_channel() {
                var _this = this;
                Vue.nextTick(function() {
                    var $ = jQuery;
                    var messageWrap = $('.wt-message');
                    $.ajax({
                        type: "POST",
                        url: bftow_localize.ajax_url,
                        dataType: 'json',
                        context: this,
                        data: 'action=bftow_pro_action_get_channel_id&public_channel=' + _this.public_channel_id + '&security=' + bftow_localize.ajax_nonce,
                        success: function (data) {
                            messageWrap.removeClass('success error');
                            messageWrap.text(data.message);
                            messageWrap.addClass(data.status);
                            if(typeof data.chat_id !== 'undefined'){
                                _this.private_channel_id = data.chat_id;
                            }
                        }
                    });
                })
            },
        },
    });
</script>
