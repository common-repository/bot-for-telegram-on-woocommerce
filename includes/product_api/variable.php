<?php

new BFTOW_Product_Variable();

class BFTOW_Product_Variable
{
    public function __construct()
    {

        add_filter('bftow_get_variable_product', array($this, 'variable_product'), 10, 2);
    }

    function variable_product($data, $product)
    {

        global $wpdb;

        $chat_id = (!empty($_GET['chat_id'])) ? intval($_GET['chat_id']) : 0;
        $transient_name = "bftow_{$data['id']}_{$chat_id}";

        $variations_data = $product->get_available_variations();

        $prices = wp_list_pluck($variations_data, 'display_price');

        $data['price'] = array(
            'min' => (float)min($prices),
            'max' => (float)max($prices),
        );
        $data['price_formatted'] = BFTOW_Products::bftow_get_price_format($data['price']['min']) .
            ' - ' .
            BFTOW_Products::bftow_get_price_format($data['price']['max']);

        $variations = array();
        $variations_ids = array();
        $variations_ids_bot = array();

        //return $variations_data;

        foreach ($variations_data as $variation) {
            foreach ($variation['attributes'] as $attribute_key => $attribute_value) {
                if(empty($attribute_value)) continue;
                if (empty($variations[$attribute_key])) $variations[$attribute_key] = array();

                $attribute = str_replace('attribute_pa_', '', $attribute_key);
                $query = "select attribute_id from {$wpdb->prefix}woocommerce_attribute_taxonomies where attribute_name='{$attribute}'";
                $attribute_id = $wpdb->get_var($query);
                if (empty($variations_ids[$attribute_id])) $variations_ids[$attribute_id] = array(
                    'type' => $attribute_key,
                    'attributes' => array()
                );

                if (empty($variations_ids_bot[$attribute_key])) $variations_ids_bot[$attribute_key] = array(
                    'id' => (int) $attribute_id,
                    'attributes' => array()
                );

                $variations[$attribute_key][] = $attribute_value;

                $term = get_term_by('slug', $attribute_value, str_replace('attribute_', '', $attribute_key));

                $variations_ids[$attribute_id]['attributes'][$term->term_id] = $attribute_value;
                $variations_ids_bot[$attribute_key]['attributes'][$attribute_value] = $term->term_id;

                $variations[$attribute_key] = array_filter(array_unique($variations[$attribute_key]));

            }
        }

        $available_variations = array();

        /*Get selected only variations*/
        $saved_variations = get_transient($transient_name);
        $selected_variations = ($saved_variations !== false) ? $saved_variations : array();

        if (!empty($_GET)) {
            foreach ($_GET as $get_key => $get_value) {
                if (empty($variations_ids[$get_key])) continue;
                $type = $variations_ids[$get_key];
                $get_key = $type['type'];
                $get_value = $type['attributes'][$get_value];
                $selected_variations[sanitize_text_field($get_key)] = sanitize_text_field($get_value);
            }
        }

        if(!empty($_GET['clear'])) {
            self::remove_variation($chat_id, $data['id']);
            $selected_variations = array();
        }
        /*Save current variation for future request*/
        set_transient($transient_name, $selected_variations);

        $data['selected_variations'] = $selected_variations;

        $products = array();

        foreach ($variations_data as $variation) {

            if(!$this->filter_variation($variation, $selected_variations, $variations)) continue;

            $thumbnail_url = get_the_post_thumbnail_url($data['id']);

            if(empty($thumbnail_url)){
                $thumbnail_url = wc_placeholder_img_src();
            }
            $stock = 100000;
            $manage_stock = false;
            $product_variation = new WC_Product_Variation($variation['variation_id']);
            if(!empty($product_variation)) {
                $stock = $product_variation->get_stock_quantity();
                $manage_stock = $product_variation->get_manage_stock();
            }
            $products[] = array(
                'id' => (int)$data['id'],
                'variation_id' => (int)$variation['variation_id'],
                'price' => (float)$variation['display_price'],
                'price_formatted' => BFTOW_Products::bftow_get_price_format($variation['display_price']),
                'image_path' => get_attached_file($variation['image_id']),
                'image_url' => $thumbnail_url,
                'variation' => $variation['attributes'],
                'stock' => $stock,
                'is_manage_stock' => $manage_stock
            );

            foreach($variation['attributes'] as $attribute => $variation_value) {
                if(!isset($available_variations[$attribute])) $available_variations[$attribute] = array();
                $available_variations[$attribute][] = $variation_value;
            }
        }

        $available_variations = self::get_available_variations($available_variations, $variations);
        $data['available_variations_ids'] = $variations_ids_bot;
        $data['available_variations'] = self::get_stepped_available_variations($available_variations, $selected_variations);
        $data['available_product_variations'] = $products;
        $data['steps'] = array(
            'current' => count($data['selected_variations']),
            'total' => count($variations),
        );

        return $data;
    }

    function filter_variation($variation, $selected_variations, $variations) {

        if(!empty($_GET['variation_id'])) {
            $variation_id = intval($_GET['variation_id']);

            return ($variation['variation_id'] === $variation_id);
        }

        if(empty($selected_variations)) return true;

        $is_available_variation = true;
        foreach ($selected_variations as $selected_attribute_key => $selected_attribute_value) {


            $variation_atts = $variation['attributes'];

            /*We have empty attr, so we need all of them*/
            if(empty($variation_atts[$selected_attribute_key])) continue;

            /*We have exact attr so we need only this one*/
            if(in_array($selected_attribute_value, $variation_atts)) continue;

            /*And if we here, we have unavailable attribute*/
            $is_available_variation = false;
        }



        return $is_available_variation;

    }


    static function get_available_variations($current_variations, $variations_data) {

        $available_variations = array();

        foreach ($current_variations as $attr => $variations) {

            if(!isset($available_variations[$attr])) $available_variations[$attr] = array();

            foreach($variations as $variation) {

                if($variation === '') {
                    $available_variations[$attr] = array_merge($available_variations[$attr], $variations_data[$attr]);
                }

                if(!in_array($variation, $available_variations[$attr])) $available_variations[$attr][] = $variation;


            }

            $available_variations[$attr] = array_values(array_unique(array_filter($available_variations[$attr])));

        }

        return $available_variations;
    }


    static function get_stepped_available_variations($available_variations, $selected_variations) {

        $available_variation = array();
        foreach ($available_variations as $attr => $variation) {
            if(!empty($selected_variations[$attr])) continue;
            $available_variation[$attr] = $variation;
            break;
        }

        return $available_variation;
    }

    static function remove_variation($chat_id, $product_id) {
        delete_transient("bftow_{$product_id}_{$chat_id}");
    }

}
