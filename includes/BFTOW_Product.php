<?php

class BFTOW_Product
{
    private $show_out_of_stock;

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_route'));

        add_filter('bftow_get_grouped_product', array($this, 'grouped_product'), 10, 2);
        add_filter('bftow_get_external_product', array($this, 'external_product'), 10, 2);

        $this->show_out_of_stock = bftow_get_option('bftow_show_out_of_stock', false);
    }

    public function register_route()
    {
        register_rest_route('woo-telegram/v1', '/product/', array(
            'methods' => ['GET'],
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'validate_callback' => function ($param, $request, $key) {
                        return is_numeric($param);
                    }
                )
            ),
            'callback' => array($this, 'bftow_get_product'),
        ));
    }

    public static function get_product_url($id)
    {
        return add_query_arg('id', $id, get_site_url() . "/wp-json/woo-telegram/v1/product/");
    }

    public function bftow_get_product($request)
    {

        $product_id = $request->get_param('id');

        return self::get_product_data($product_id);

    }

    static function get_product_data($product_id)
    {

        $product = wc_get_product($product_id);

        if(empty($product)) return false;

        $data = array();
        $data['id'] = (int)$product_id;
        $data['name'] = $product->get_name();
        $data['type'] = $product->get_type();
        $data['sku'] = $product->get_sku();
        $data['is_on_sale'] = $product->is_on_sale();

        $data['out_of_stock'] = false;

        $is_manage_stock = $product->managing_stock();
        if(!empty($is_manage_stock)) {
            if($product->get_stock_status() !== 'instock') {
                $data['out_of_stock'] = true;
            }
        }

        $data['price'] = (float)$product->get_price();
        if($product->is_on_sale() && $data['type'] !== 'grouped' && $data['type'] !== 'variable'){
            $data['price_single_formatted'] = '<del>' . BFTOW_Products::bftow_get_price_format($product->get_regular_price()) . '</del> ' . BFTOW_Products::bftow_get_price_format($product->get_sale_price());
        }
        else {
            $data['price_single_formatted'] = BFTOW_Products::bftow_get_price_format($product->get_price());
        }

        $data['price_formatted'] = BFTOW_Products::bftow_get_price_format($product->get_price());
        $data['image_path'] = get_attached_file(get_post_thumbnail_id($product_id));
        $data['image_url'] = get_the_post_thumbnail_url($product_id, 'large');
        if(empty($data['image_url'])){
            $data['image_url'] = wc_placeholder_img_src('large');
        }

        return apply_filters("bftow_get_{$data['type']}_product", $data, $product);

    }

    function grouped_product($data, $product)
    {

        $data['products'] = array();

        $types = apply_filters('bftow_product_types', array('simple', 'grouped', 'external', 'variable'));

        foreach ($product->get_children() as $child_id) {
            $child_data = self::get_product_data($child_id);
            if (!in_array($child_data['type'], $types)) continue;
            $data['products'][] = $child_data;
        }

        $prices = wp_list_pluck($data['products'], 'price');

        $data['price'] = array(
            'min' => (float)min($prices),
            'max' => (float)max($prices),
        );
        $data['price_formatted'] = BFTOW_Products::bftow_get_price_format($data['price']['min']) .
            ' - ' .
            BFTOW_Products::bftow_get_price_format($data['price']['max']);

        return $data;
    }

    function external_product($data, $product)
    {

        $data['url'] = $product->get_product_url();
        $data['button_text'] = $product->get_button_text();

        return $data;
    }
}

new BFTOW_Product();