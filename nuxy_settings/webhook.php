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
?>

<bftow_webhook_activation inline-template>
    <div class="wpcfto_generic_field wpcfto_generic_field_flex_input wpcfto_generic_field__text">
        <div class="wpcfto-field-aside">
            <label class="wpcfto-field-aside__label"><?php esc_html_e('Activate API URL', 'bot-for-telegram-on-woocommerce'); ?></label>
            <div class="wpcfto-field-description wpcfto-field-description__before description"><?php esc_html_e('Save Bot token first', 'bot-for-telegram-on-woocommerce'); ?></div>
        </div>
        <div class="wpcfto-field-content">
            <div class="forminp">
                <a href="#" class="button-primary" id="bftow_activate_tg_api_url" @click.prevent="activate()">
                    <?php esc_html_e('Activate REST URL', 'bot-for-telegram-on-woocommerce'); ?>
                </a>
                <a href="#" class="button-secondary" id="bftow_deactivate_tg_api_url" @click.prevent="deactivate()">
                    <?php esc_html_e('Deactivate REST URL', 'bot-for-telegram-on-woocommerce'); ?>
                </a>
                <div id="wt-info-line"></div>
            </div>
        </div>
    </div>
</bftow_webhook_activation>

<script>
    Vue.component('bftow_webhook_activation', {
        data: function () {
            return {

            }
        },
        methods: {
            activate() {
                Vue.nextTick(function() {
                    var $ = jQuery;
                    $.ajax({
                        type: "POST",
                        url: bftow_localize.ajax_url,
                        dataType: 'json',
                        context: this,
                        data: 'action=bftow_action_with_rest_url&btn_action=activate&security=' + bftow_localize.ajax_nonce,
                        beforeSend: function () {
                            $('#wt-info-line').addClass('loading');
                        },
                        success: function (data) {
                            var msg = data.message;
                            $('#wt-info-line').removeClass('loading').text(msg.description);
                        }
                    });
                })
            },
            deactivate() {
                Vue.nextTick(function() {
                    var $ = jQuery;
                    $.ajax({
                        type: "POST",
                        url: bftow_localize.ajax_url,
                        dataType: 'json',
                        context: this,
                        data: 'action=bftow_action_with_rest_url&btn_action=deactivate&security=' + bftow_localize.ajax_nonce,
                        beforeSend: function () {
                            $('#wt-info-line').addClass('loading');
                        },
                        success: function (data) {
                            var msg = data.message;

                            $('#wt-info-line').removeClass('loading').text(msg.description);
                        }
                    });
                });
            }
        },
    });
</script>