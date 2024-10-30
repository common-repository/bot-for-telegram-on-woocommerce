<?php
function bftow_enqueue_admin_ss($hook)
{

    wp_enqueue_style('bftow_style', BFTOW_URL . '/assets/css/styles.css', false, '1.0.1');
    wp_enqueue_script('bftow_script', BFTOW_URL . '/assets/js/scripts.js', array('jquery'), '1.0');

    $translation_array = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'ajax_nonce' => wp_create_nonce('ajax_nonce'),
        'activate_link' => esc_html__('REST API Link Activated', 'bot-for-telegram-on-woocommerce'),
        'deactivate_link' => esc_html__('REST API Link Deactivated', 'bot-for-telegram-on-woocommerce'),
    );

    wp_localize_script('bftow_script', 'bftow_localize', $translation_array);
}

add_action('admin_enqueue_scripts', 'bftow_enqueue_admin_ss');
