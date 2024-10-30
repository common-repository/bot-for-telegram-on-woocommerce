<?php
class BFTOW_Products {

    private $BFTOW_Helper;
    private $show_sku;
    private $show_out_of_stock;
    private $sku_text;

	public function __construct() {
        $this->BFTOW_Helper = BFTOW_Helpers::getInstance();
        $this->show_sku = bftow_get_option('bftow_show_sku', false);
        $this->show_out_of_stock = bftow_get_option('bftow_show_out_of_stock', false);
        $this->sku_text = bftow_get_option('bftow_sku_text', esc_html__('SKU', 'bot-for-telegram-on-woocommerce'));
	    add_action('init', function() {
	        add_filter('bftow_get_products_filter', array($this, 'bftow_get_products'), 10, 3);
        });
    }

	public function bftow_get_products($products, $catId, $offset){

		$products = [];

		$args = [
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => 20,
            'orderby' => 'date title',
            'order' => 'DESC',
			'offset' => $offset,
		];

        $args['tax_query'][] = array(
            'taxonomy'  => 'product_type',
            'field'     => 'name',
            'terms'     => apply_filters('bftow_product_types', array('simple', 'grouped', 'external', 'variable')),
        );

        $args['meta_query'] = [
            'relation' => 'AND',
            array(
                'relation' => 'OR',
                array(
                    'key' => 'bftow_hide_product',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => 'bftow_hide_product',
                    'value' => 'on',
                    'compare' => '!='
                )
            )
        ];

        if(empty($this->show_out_of_stock)) {
            $args['meta_query'][] = array(
                'key' => '_stock_status',
                'value' => 'instock'
            );
        }

        if(!empty($catId) && $catId !== 'all' && strpos($catId, 'bftow_search_') === false) {
            $args['tax_query'][] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => intval($catId)
            );
        }
        if(strpos($catId, 'bftow_search_') !== false) {
            $search = str_replace('bftow_search_', '', $catId);
            if(!empty($search)){
                $args['s'] = $search;
            }
        }

        $args = apply_filters('bftow_get_products_args', $args);

		$q = new WP_Query($args);

		if ($q->have_posts()) {
            $total = $q->found_posts;
			while ($q->have_posts()) {
				$q->the_post();

                $prodId = get_the_ID();
                $prodType = get_the_terms( $prodId,'product_type');

                $product = wc_get_product($prodId);
                $sku = $product->get_sku();
                $salePrice = get_post_meta($prodId, '_sale_price', true);
                $regularPrice = get_post_meta($prodId, '_regular_price', true);
                if(!empty($salePrice)){
                    $prodPrice = self::bftow_get_price_format($salePrice);
                }
                else {
                    $prodPrice = self::bftow_get_price_format($regularPrice);
                }


                if($prodType[0]->slug == 'grouped' || $prodType[0]->slug == 'variable') {
                    $prodPriceGrouped = get_post_meta($prodId, '_price');

                    $prodPrice = '';
                    if(!empty($prodPriceGrouped) && count($prodPriceGrouped) > 1) {
                        $prodPrice = self::bftow_get_price_format($prodPriceGrouped[0]) . ' - ' . self::bftow_get_price_format($prodPriceGrouped[count($prodPriceGrouped) - 1]);
                    }
                    else {
                        $prodPrice = self::bftow_get_price_format($product->get_price());
                    }
                }

                $thumbnail_url = get_the_post_thumbnail_url();
                if(empty($thumbnail_url)){
                    $thumbnail_url = wc_placeholder_img_src();
                }

                if($this->show_sku === true && !empty($sku)) {
                    $prodPrice .= "\n" . $this->sku_text . ': ' . $sku;
                }

				$products[] = array(
					"type" => "article",
					"id" => $prodId,
					"title" => get_the_title(),
					"description" => $prodPrice,
					"input_message_content" => array(
						"message_text" => $prodId
					),
					'thumb_url' => $thumbnail_url,
				);
			}
		}

		wp_reset_postdata();

		return $products;
	}

	public function bftow_get_product($prodId, $variation = '') {

        $url = get_site_url() . '/wp-json/woo-telegram/v1/product/?id=' . $prodId . $variation;
        $response = wp_remote_get($url, array('sslverify' => false));
        $response = wp_remote_retrieve_body($response);
        $response = str_replace("\xEF\xBB\xBF", '', $response);

		return json_decode($response);
	}

	public function bftow_get_product_name($prod_id) {
	    $product = $this->bftow_get_product($prod_id);

	    return $product->name;
    }

	public static function bftow_get_price_format($price) {
		$symbol = self::bftow_get_currency_symbol(get_option( 'woocommerce_currency', 'GBP' ));
		$separator = get_option( 'woocommerce_price_decimal_sep', '.' );
		$decimal_separator = stripslashes( $separator );
		$thousand_separator = stripslashes( get_option( 'woocommerce_price_thousand_sep' ) );
		$decimals = absint( get_option( 'woocommerce_price_num_decimals', 2 ) );
		$currency_pos = get_option( 'woocommerce_currency_pos', 'left' );
		$format       = '%1$s%2$s';

		$negative          = $price < 0;
		$price             = floatval( $negative ? $price * -1 : $price );
		$price             = number_format( $price, $decimals, $decimal_separator, $thousand_separator );

		switch ( $currency_pos ) {
			case 'left':
				$format = '%1$s%2$s';
				break;
			case 'right':
				$format = '%2$s%1$s';
				break;
			case 'left_space':
				$format = '%1$s %2$s';
				break;
			case 'right_space':
				$format = '%2$s %1$s';
				break;
		}

		$formatted_price = ( $negative ? '-' : '' ) . sprintf( $format, $symbol, $price );

		return $formatted_price;
	}

	private static function bftow_get_currency_symbol ($symbol) {
		$symbolArr = array(
			'AED' => '&#x62f;.&#x625;',
			'AFN' => '&#x60b;',
			'ALL' => 'L',
			'AMD' => 'AMD',
			'ANG' => '&fnof;',
			'AOA' => 'Kz',
			'ARS' => '&#36;',
			'AUD' => '&#36;',
			'AWG' => 'Afl.',
			'AZN' => 'AZN',
			'BAM' => 'KM',
			'BBD' => '&#36;',
			'BDT' => '&#2547;&nbsp;',
			'BGN' => '&#1083;&#1074;.',
			'BHD' => '.&#x62f;.&#x628;',
			'BIF' => 'Fr',
			'BMD' => '&#36;',
			'BND' => '&#36;',
			'BOB' => 'Bs.',
			'BRL' => '&#82;&#36;',
			'BSD' => '&#36;',
			'BTC' => '&#3647;',
			'BTN' => 'Nu.',
			'BWP' => 'P',
			'BYR' => 'Br',
			'BYN' => 'Br',
			'BZD' => '&#36;',
			'CAD' => '&#36;',
			'CDF' => 'Fr',
			'CHF' => '&#67;&#72;&#70;',
			'CLP' => '&#36;',
			'CNY' => '&yen;',
			'COP' => '&#36;',
			'CRC' => '&#x20a1;',
			'CUC' => '&#36;',
			'CUP' => '&#36;',
			'CVE' => '&#36;',
			'CZK' => '&#75;&#269;',
			'DJF' => 'Fr',
			'DKK' => 'DKK',
			'DOP' => 'RD&#36;',
			'DZD' => '&#x62f;.&#x62c;',
			'EGP' => 'EGP',
			'ERN' => 'Nfk',
			'ETB' => 'Br',
			'EUR' => '&euro;',
			'FJD' => '&#36;',
			'FKP' => '&pound;',
			'GBP' => '&pound;',
			'GEL' => '&#x20be;',
			'GGP' => '&pound;',
			'GHS' => '&#x20b5;',
			'GIP' => '&pound;',
			'GMD' => 'D',
			'GNF' => 'Fr',
			'GTQ' => 'Q',
			'GYD' => '&#36;',
			'HKD' => '&#36;',
			'HNL' => 'L',
			'HRK' => 'kn',
			'HTG' => 'G',
			'HUF' => '&#70;&#116;',
			'IDR' => 'Rp',
			'ILS' => '&#8362;',
			'IMP' => '&pound;',
			'INR' => '&#8377;',
			'IQD' => '&#x639;.&#x62f;',
			'IRR' => '&#xfdfc;',
			'IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;',
			'ISK' => 'kr.',
			'JEP' => '&pound;',
			'JMD' => '&#36;',
			'JOD' => '&#x62f;.&#x627;',
			'JPY' => '&yen;',
			'KES' => 'KSh',
			'KGS' => '&#x441;&#x43e;&#x43c;',
			'KHR' => '&#x17db;',
			'KMF' => 'Fr',
			'KPW' => '&#x20a9;',
			'KRW' => '&#8361;',
			'KWD' => '&#x62f;.&#x643;',
			'KYD' => '&#36;',
			'KZT' => '&#8376;',
			'LAK' => '&#8365;',
			'LBP' => '&#x644;.&#x644;',
			'LKR' => '&#xdbb;&#xdd4;',
			'LRD' => '&#36;',
			'LSL' => 'L',
			'LYD' => '&#x644;.&#x62f;',
			'MAD' => '&#x62f;.&#x645;.',
			'MDL' => 'MDL',
			'MGA' => 'Ar',
			'MKD' => '&#x434;&#x435;&#x43d;',
			'MMK' => 'Ks',
			'MNT' => '&#x20ae;',
			'MOP' => 'P',
			'MRU' => 'UM',
			'MUR' => '&#x20a8;',
			'MVR' => '.&#x783;',
			'MWK' => 'MK',
			'MXN' => '&#36;',
			'MYR' => '&#82;&#77;',
			'MZN' => 'MT',
			'NAD' => 'N&#36;',
			'NGN' => '&#8358;',
			'NIO' => 'C&#36;',
			'NOK' => '&#107;&#114;',
			'NPR' => '&#8360;',
			'NZD' => '&#36;',
			'OMR' => '&#x631;.&#x639;.',
			'PAB' => 'B/.',
			'PEN' => 'S/',
			'PGK' => 'K',
			'PHP' => '&#8369;',
			'PKR' => '&#8360;',
			'PLN' => '&#122;&#322;',
			'PRB' => '&#x440;.',
			'PYG' => '&#8370;',
			'QAR' => '&#x631;.&#x642;',
			'RMB' => '&yen;',
			'RON' => 'lei',
			'RSD' => '&#1088;&#1089;&#1076;',
			'RUB' => '&#8381;',
			'RWF' => 'Fr',
			'SAR' => '&#x631;.&#x633;',
			'SBD' => '&#36;',
			'SCR' => '&#x20a8;',
			'SDG' => '&#x62c;.&#x633;.',
			'SEK' => '&#107;&#114;',
			'SGD' => '&#36;',
			'SHP' => '&pound;',
			'SLL' => 'Le',
			'SOS' => 'Sh',
			'SRD' => '&#36;',
			'SSP' => '&pound;',
			'STN' => 'Db',
			'SYP' => '&#x644;.&#x633;',
			'SZL' => 'L',
			'THB' => '&#3647;',
			'TJS' => '&#x405;&#x41c;',
			'TMT' => 'm',
			'TND' => '&#x62f;.&#x62a;',
			'TOP' => 'T&#36;',
			'TRY' => '&#8378;',
			'TTD' => '&#36;',
			'TWD' => '&#78;&#84;&#36;',
			'TZS' => 'Sh',
			'UAH' => '&#8372;',
			'UGX' => 'UGX',
			'USD' => '&#36;',
			'UYU' => '&#36;',
			'UZS' => 'UZS',
			'VEF' => 'Bs F',
			'VES' => 'Bs.S',
			'VND' => '&#8363;',
			'VUV' => 'Vt',
			'WST' => 'T',
			'XAF' => 'CFA',
			'XCD' => '&#36;',
			'XOF' => 'CFA',
			'XPF' => 'Fr',
			'YER' => '&#xfdfc;',
			'ZAR' => '&#82;',
			'ZMW' => 'ZK',
		);

		return html_entity_decode($symbolArr[$symbol]);
	}
}
