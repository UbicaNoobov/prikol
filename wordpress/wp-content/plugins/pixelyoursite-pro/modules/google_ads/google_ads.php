<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** @noinspection PhpIncludeInspection */
require_once PYS_PATH . '/modules/google_analytics/function-helpers.php';
/** @noinspection PhpIncludeInspection */
require_once PYS_PATH . '/modules/google_ads/function-helpers.php';

use PixelYourSite\Ads\Helpers;

class GoogleAds extends Settings implements Pixel {

	private static $_instance;

	private $configured;

	/** @var array $wooOrderParams Cached WooCommerce Purchase and AM events params */
	private $wooOrderParams = array();

	private $googleBusinessVertical;

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

	public function __construct() {

		parent::__construct( 'google_ads' );

		$this->locateOptions(
			PYS_PATH . '/modules/google_ads/options_fields.json',
			PYS_PATH . '/modules/google_ads/options_defaults.json'
		);

		add_action( 'pys_register_pixels', function( $core ) {
			/** @var PYS $core */
			$core->registerPixel( $this );
		} );

		// cache value
		$this->googleBusinessVertical = PYS()->getOption( 'google_retargeting_logic' ) == 'ecomm' ? 'retail' : 'custom';

        add_filter('pys_google_ads_settings_sanitize_ads_ids_field', 'PixelYourSite\Ads\Helpers\sanitizeTagIDs');
        add_action( 'add_meta_boxes', array( $this, 'registerProductMetaBox' ) );
        add_action( 'save_post_product', array( $this, 'saveProductMetaBox' ), 10, 3 );
        add_action( 'wp_head', array( $this, 'output_meta_tag' ) );
	}

	public function enabled() {
		return $this->getOption( 'enabled' );
	}

	public function configured() {

        $license_status = PYS()->getOption( 'license_status' );
        $ads_ids = $this->getPixelIDs();

        $this->configured = $this->enabled()
                            && ! empty( $license_status ) // license was activated before
                            && count( $ads_ids ) > 0
                            && !empty($ads_ids[0])
                            && ! apply_filters( 'pys_pixel_disabled', false, $this->getSlug() );

		return $this->configured;

	}

	public function getPixelIDs() {

		$ids = (array) $this->getOption( 'ads_ids' );

		if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
			return apply_filters("pys_google_ads_ids",$ids);
		} else {
			return apply_filters("pys_google_ads_ids",(array) reset( $ids )); // return first id only
		}

	}

	public function getPixelOptions() {

		return array(
			'conversion_ids'      => $this->getPixelIDs(),
            'wooVariableAsSimple' => $this->getOption( 'woo_variable_as_simple' ),
		);

	}
    public function addParamsToEvent(&$event) {
        if ( ! $this->configured() ) {
            return false;
        }
        $isActive = false;
        switch ($event->getId()) {
            case 'init_event':{
                $eventData = $this->getPageViewEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            case "signal_user_signup":
            case "signal_page_scroll":
            case "signal_time_on_page":
            case "signal_tel":
            case "signal_email":
            case "signal_form":
            case "signal_download":
            case "signal_comment":
            case "signal_watch_video": {
                $isActive = $this->getOption('signal_events_enabled');
            }  break;
            case "signal_adsense": break;

            case 'woo_view_content':{
                $eventData = $this->getWooViewContentEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            } break;
            case 'woo_add_to_cart_on_cart_page':
            case 'woo_add_to_cart_on_checkout_page': {
                $eventData = $this->getWooAddToCartOnCartEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'woo_view_category':{
                $eventData = $this->getWooViewCategoryEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'woo_purchase':{
                $eventData = $this->getWooPurchaseEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'edd_view_content':{
                $eventData = $this->getEddViewContentEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'edd_add_to_cart_on_checkout_page':{
                $eventData = $this->getEddCartEventParams( 'add_to_cart' );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'edd_view_category':{
                $eventData = $this->getEddViewCategoryEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'edd_purchase':{
                $eventData = $this->getEddCartEventParams( 'purchase' );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'custom_event':{
                $eventData =  $this->getCustomEventData( $event->args );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'woo_add_to_cart_on_button_click': {
                if (  $this->getOption( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_button_click' ) ) {
                    $isActive = true;
                    if(isset($event->args['productId'])) {
                        $productId = $event->args['productId'];
                        $quantity = $event->args['quantity'];
                        $eventData =  $this->getWooAddToCartOnButtonClickEventParams( $productId,$quantity );

                        if($eventData) {
                            $event->addParams($eventData["params"]);
                            unset($eventData["params"]);
                            $event->addPayload($eventData);
                        }
                    }


                    $event->addPayload(array(
                        'name'=>"add_to_cart"
                    ));
                }
            }break;

            case 'woo_affiliate': {
                if (  $this->getOption( 'woo_affiliate_enabled' ) ) {
                    $isActive = true;
                    if(isset($event->args['productId'])) {
                        $productId = $event->args['productId'];
                        $quantity = $event->args['quantity'];
                        $eventData = $this->getWooAffiliateEventParams( $productId,$quantity );
                        if($eventData) {
                            $event->addParams($eventData["params"]);
                            unset($eventData["params"]);
                            $event->addPayload($eventData);
                        }
                    }
                }
            }break;

            case 'edd_add_to_cart_on_button_click': {
                if (  $this->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
                    $isActive = true;
                    $event->addPayload(array(
                        'name'=>"add_to_cart"
                    ));
                }
            }break;

        }

        return $isActive;
    }

    private function addDataToEvent($eventData,&$event) {
        $params = $eventData["data"];
        unset($eventData["data"]);
        //unset($eventData["name"]);
        $event->addParams($params);
        $event->addPayload($eventData);
    }

	public function getEventData( $eventType, $args = null ) {

        if ( ! $this->configured() ) {
            return false;
        }

        switch ( $eventType ) {

            case 'edd_add_to_cart_on_button_click':
                return $this->getEddAddToCartOnButtonClickEventParams( $args );

            default:
                return false;   // event does not supported
        }


    }

    public function outputNoScriptEvents() {

	    /* dont send google ads no script events to google analytics
        if ( ! $this->configured() ) {
            return;
        }

        $eventsManager = PYS()->getEventsManager();

        foreach ( $eventsManager->getStaticEvents( 'google_ads' ) as $eventName => $events ) {
            foreach ( $events as $event ) {
                foreach ( $this->getPixelIDs() as $pixelID ) {

                    $args = array(
                        'v'   => 1,
                        'tid' => $pixelID,
                        't'   => 'event',
                    );

                    //@see: https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#ec
                    if ( isset( $event['params']['event_category'] ) ) {
                        $args['ec'] = urlencode( $event['params']['event_category'] );
                    }

                    if ( isset( $event['params']['event_action'] ) ) {
                        $args['ea'] = urlencode( $event['params']['event_action'] );
                    }

                    if ( isset( $event['params']['event_label'] ) ) {
                        $args['el'] = urlencode( $event['params']['event_label'] );
                    }

                    if ( isset( $event['params']['value'] ) ) {
                        $args['ev'] = urlencode( $event['params']['value'] );
                    }

                    if ( isset( $event['params']['items'] ) ) {

                        foreach ( $event['params']['items'] as $key => $item ) {

                            @$args["pr{$key}id" ] = urlencode( $item['id'] );
                            @$args["pr{$key}nm"] = urlencode( $item['name'] );
                            @$args["pr{$key}ca"] = urlencode( $item['category'] );
                            //@$args["pr{$key}va"] = urlencode( $item['id'] ); // variant
                            @$args["pr{$key}pr"] = urlencode( $item['price'] );
                            @$args["pr{$key}qt"] = urlencode( $item['quantity'] );

                        }

                        //@todo: not tested
                        //https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#pa
                        $args["pa"] = 'detail'; // required

                    }

                    // ALT tag used to pass ADA compliance
                    printf( '<noscript><img height="1" width="1" style="display: none;" src="%s" alt="google_analytics"></noscript>',
                        add_query_arg( $args, 'https://www.google-analytics.com/collect' ) );

                    echo "\r\n";

                }
            }
        }
        */
    }

    private function getPageViewEventParams() {
        global $post;
        $cpt = get_post_type();
        $params = array();
        $items = array();

        if((!isWooCommerceActive() || ($cpt != "product" && !is_checkout() && !is_cart() && !is_order_received_page() && !is_tax('product_cat'))) &&
            (!isEddActive() || ($cpt != "download" && !edd_is_checkout() && !edd_is_success_page() && !is_tax('download_category')))
            ) {

            if (!$this->getOption("page_view_post_enabled") && $cpt == "post") return false;
            if (!$this->getOption("page_view_page_enabled") && $cpt == "page") return false;

            if ($cpt != "post" && $cpt != "page") {
                $enabledCustom = (array)$this->getOption("page_view_custom_post_enabled");
                if (!in_array("index_" . $cpt, $enabledCustom)) return false;
            }

            if(is_category() ) {
                global $posts;
                if($posts) {
                    foreach ($posts as $p) {
                        $items[] = array(
                            "id"=> $p->ID,
                            "google_business_vertical" => $this->getOption("page_view_business_vertical")
                        );
                    }
                }
            } else {
                if($post) {
                    $items[] = array(
                        "id"=> $post->ID,
                        "google_business_vertical" => $this->getOption("page_view_business_vertical")
                    );
                }

            }
        }

        if ( PYS()->getEventsManager()->doingAMP ) {
            return array(
                'name' => 'PageView',
                'data' => array(),
            );
        }

        $params['items'] = $items;

        return array(
            'name'  => 'page_view',
            'data'  => $params,

        );

    }

    private function getSearchEventData() {

        if ( ! $this->getOption( 'search_event_enabled' ) ) {
            return false;
        }
        $params = array();
        $params['search'] = empty( $_GET['s'] ) ? null : $_GET['s'];

        return array(
            'name'  => 'view_search_results',
            'data'  => $params,
        );

    }

    /**
     * @param CustomEvent $event
     *
     * @return array|bool
     */
    private function getCustomEventData( $event ) {

        $ads_action = $event->getGoogleAdsAction();

        if ( ! $event->isGoogleAdsEnabled() || empty( $ads_action ) ) {
            return false;
        }

        $params = array(
            'event_category'  => $event->google_ads_event_category,
            'event_label'     => $event->google_ads_event_label,
            'value'           => $event->google_ads_event_value,
        );

	    // add custom params
	    foreach ( $event->getGoogleAdsCustomParams() as $custom_param ) {
		    $params[ $custom_param['name'] ] = $custom_param['value'];
	    }

        // SuperPack Dynamic Params feature
        $params = apply_filters( 'pys_superpack_dynamic_params', $params, 'google_ads' );

	    // ids
	    $ids = array();

	    $conversion_label = $event->google_ads_conversion_label;
	    $conversion_id = $event->google_ads_conversion_id;

	    if ( $conversion_id == '_all' ) {
		    $ids = $this->getPixelIDs();
	    } else {
	    	$ids[] = $conversion_id;
	    }

	    // AW-12345678 => AW-12345678/da324asDvas
	    if ( ! empty( $conversion_label ) ) {
		    foreach ( $ids as $key => $value ) {
			    $ids[ $key ] = $value . '/' . $conversion_label;
		    }
	    }

	    return array(
		    'name'  => $ads_action,
		    'data'  => $params,
		    'delay' => $event->getDelay(),
		    'conversion_labels'   => $ids,
	    );

    }

    private function getWooViewCategoryEventParams() {
        global $posts;

        if ( ! $this->getOption( 'woo_view_category_enabled' ) ) {
            return false;
        }

        $term = get_term_by( 'slug', get_query_var( 'term' ), 'product_cat' );
        if(!is_a($term,"WP_Term"))
            return false;
        $parent_ids = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );

        $product_categories = array();
        $product_categories[] = $term->name;

        foreach ( $parent_ids as $term_id ) {
            $parent_term = get_term_by( 'id', $term_id, 'product_cat' );
            $product_categories[] = $parent_term->name;
        }

        $list_name = implode( '/', array_reverse( $product_categories ) );

        $items = array();
        $total_value = 0;

        for ( $i = 0; $i < count( $posts ); $i ++ ) {

            if ( $posts[ $i ]->post_type !== 'product' ) {
                continue;
            }

            $item = array(
                'id'            => Helpers\getWooFullItemId( $posts[ $i ]->ID ),
                'google_business_vertical' => $this->googleBusinessVertical,
            );

            $items[] = $item;
            $total_value += getWooProductPriceToDisplay( $posts[ $i ]->ID );

        }

        $params = array(
            'event_category' => 'ecommerce',
            'event_label'    => $list_name,
            'value'          => $total_value,
            'items'          => $items,
        );

        return array(
            'name'  => 'view_item_list',
            'ids' => Helpers\getConversionIDs( 'woo_view_category' ),
            'data'  => $params,
        );

    }

    private function getWooViewContentEventParams() {
        global $post;

        if ( ! $this->getOption( 'woo_view_content_enabled' ) ) {
            return false;
        }
        $id = Helpers\getWooFullItemId( $post->ID );
        $params = array(
            'ecomm_prodid'=> $id,
            'ecomm_pagetype'=> 'product',
            'event_category'  => 'ecommerce',
            'value' => getWooProductPriceToDisplay( $post->ID ),
            'items'           => array(
                array(
                    'id'       => $id,
                    'google_business_vertical' => $this->googleBusinessVertical,
                ),
            ),
        );


        return array(
            'name'  => 'view_item',
            'data'  => $params,
            'ids'   => Helpers\getConversionIDs( 'woo_view_content' ),
            'delay' => (int) PYS()->getOption( 'woo_view_content_delay' ),
        );

    }

    private function getWooAddToCartOnButtonClickEventParams( $product_id,$quantity ) {

        $product = wc_get_product( $product_id );
        if(!$product) return false;
        $price = getWooProductPriceToDisplay( $product_id, $quantity );
        $contentId = Helpers\getWooFullItemId( $product_id );


        if(is_home() || is_front_page()) {
            $ecomm_pagetype = "home";
        }elseif(is_shop()) {
            $ecomm_pagetype = "shop";
        }elseif(is_cart()) {
            $ecomm_pagetype = "cart";
        }elseif(is_single()) {
            $ecomm_pagetype = "product";
        }elseif(is_category()) {
            $ecomm_pagetype = "category";
        } else {
            $ecomm_pagetype = get_post_type();
        }
        $params = array(
            'ecomm_prodid' => $contentId,
            'ecomm_pagetype'=> $ecomm_pagetype,
            'event_category'  => 'ecommerce',
            'value' => $price,
        );

        $product_ids = array();
        $items = array();

        $isGrouped = $product->get_type() == "grouped";
        if($isGrouped) {
            $product_ids = $product->get_children();
        } else {
            $product_ids[] = $product_id;
        }

        foreach ($product_ids as $child_id) {
            $childProduct = wc_get_product($child_id);
            if($childProduct->get_type() == "variable" && $isGrouped) {
                continue;
            }
            $childContentId = Helpers\getWooFullItemId( $child_id );
            $items[] = array(
                'id'       => $childContentId,
                'google_business_vertical' => $this->googleBusinessVertical,
            );
        }
        $params['items'] = $items;

        $data = array(
            'ids' => Helpers\getConversionIDs( 'woo_add_to_cart' ),
            'params'  => $params,
        );

        $product = wc_get_product($product_id);
        if($product->get_type() == 'grouped') {
            $grouped = array();
            foreach ($product->get_children() as $childId) {
                $grouped[$childId] = array(
                    'content_id' => Helpers\getWooFullItemId( $childId ),
                    'price' => getWooProductPriceToDisplay( $childId )
                );
            }
            $data['grouped'] = $grouped;
        }

        return $data;

    }

    private function getWooAddToCartOnCartEventParams() {

        if ( ! $this->getOption( 'woo_add_to_cart_enabled' ) ) {
            return false;
        }

        $params = $this->getWooCartParams("AddToCartOnCart");

        if(is_home() || is_front_page()) {
            $ecomm_pagetype = "home";
        }elseif(is_shop()) {
            $ecomm_pagetype = "shop";
        }elseif(is_cart()) {
            $ecomm_pagetype = "cart";
        }elseif(is_single()) {
            $ecomm_pagetype = "product";
        }elseif(is_category()) {
            $ecomm_pagetype = "category";
        } else {
            $ecomm_pagetype = get_post_type();
        }
        $contentId = array();
        foreach ($params['items'] as $item){
            $contentId[] = $item["id"];
        }

        $params['ecomm_prodid'] = $contentId;
        $params['ecomm_pagetype'] = $ecomm_pagetype;
        $params['event_category']  = 'ecommerce';

        return array(
            'name' => 'add_to_cart',
            'ids' => Helpers\getConversionIDs( 'woo_add_to_cart' ),
            'data' => $params
        );

    }

    private function getWooRemoveFromCartParams( $cart_item ) {

        if ( ! $this->getOption( 'woo_remove_from_cart_enabled' ) ) {
            return false;
        }
        $product_id = Helpers\getWooCartItemId( $cart_item );
        $content_id = Helpers\getWooFullItemId( $product_id );


        $product = get_post( $product_id );

        if ( ! empty( $cart_item['variation_id'] ) ) {
            $variation = wc_get_product( (int) $cart_item['variation_id'] );
            if(is_a($variation, 'WC_Product_Variation')) {
                $parentId = $variation->get_parent_id();
                $name = $variation->get_title();
                $category = implode( '/', getObjectTerms( 'product_cat', $parentId ) );
                $variation_name = implode("/", $variation->get_variation_attributes());
            } else {
                $name = $product->post_title;
                $variation_name = null;
                $category = implode( '/', getObjectTerms( 'product_cat', $product_id ) );
            }

        } else {
            $name = $product->post_title;
            $variation_name = null;
            $category = implode( '/', getObjectTerms( 'product_cat', $product_id ) );
        }

        $_product = wc_get_product($product_id);
        if(!$_product) return false;
        if($_product->get_type() == "bundle") {
            $price = getWooBundleProductCartPrice($cart_item);
        } else {
            $price = getWooProductPriceToDisplay( $product_id, $cart_item['quantity'] );
        }



        return array(
            'name'=>"remove_from_cart",
            'data' => array(
                'event_category'  => 'ecommerce',
                'currency'        => get_woocommerce_currency(),
                'value' => $price,
                'items'           => array(
                    array(
                        'id'       => $content_id,
                        'google_business_vertical' => $this->googleBusinessVertical,
                    ),
                ),
            ),
        );

    }

    private function getWooInitiateCheckoutEventParams() {

        if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) ) {
            return false;
        }

        $params = $this->getWooCartParams( 'checkout' );

        return array(
            'name'  => 'begin_checkout',
            'ids' => Helpers\getConversionIDs( 'woo_initiate_checkout' ),
            'data'  => $params
        );

    }

    private function getWooAffiliateEventParams( $product_id,$quantity ) {

        if ( ! $this->getOption( 'woo_affiliate_enabled' ) ) {
            return false;
        }

        $product = get_post( $product_id );

        $params = array(
            'event_category'  => 'ecommerce',
            'items'           => array(
                array(
                    'id'       => Helpers\getWooFullItemId( $product_id ),
                    'name'     => $product->post_title,
                    'category' => implode( '/', getObjectTerms( 'product_cat', $product_id ) ),
                    'quantity' => $quantity,
                    'price'    => getWooProductPriceToDisplay( $product_id, $quantity ),
                ),
            ),
        );

        return array(
            'params'  => $params,
        );

    }

    private function getWooPayPalEventParams() {

        if ( ! $this->getOption( 'woo_paypal_enabled' ) ) {
            return false;
        }

        $params = $this->getWooCartParams( 'paypal' );
        unset( $params['coupon'] );

        return array(
            'name' => getWooPayPalEventName(),
            'data' => $params,
        );

    }

    private function getWooPurchaseEventParams() {

        if ( ! $this->getOption( 'woo_purchase_enabled' ) ) {
            return false;
        }
        $order_id = wooGetOrderIdFromRequest();

        $order = new \WC_Order( $order_id );
        $items = array();
        $total_value = 0;

        foreach ( $order->get_items( 'line_item' ) as $line_item ) {

            $product_id = Helpers\getWooCartItemId( $line_item );
            $content_id = Helpers\getWooFullItemId( $product_id );

            $product = wc_get_product( $product_id );
            if(!$product) continue;

            /**
             * Discounted price used instead of price as is on Purchase event only to avoid wrong numbers in
             * Analytic's Product Performance report.
             */
            if ( isWooCommerceVersionGte( '3.0' ) ) {
                $price = $line_item['total'] + $line_item['total_tax'];
            } else {
                $price = $line_item['line_total'] + $line_item['line_tax'];
            }

            $qty   = $line_item['qty'];
            $price = $price / $qty;

            if ( isWooCommerceVersionGte( '3.0' ) ) {

                if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) ) {
                    $price = wc_get_price_including_tax( $product, array( 'qty' => 1, 'price' => $price ) );
                } else {
                    $price = wc_get_price_excluding_tax( $product, array( 'qty' => 1, 'price' => $price ) );
                }

            } else {

                if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) ) {
                    $price = $product->get_price_including_tax( 1, $price );
                } else {
                    $price = $product->get_price_excluding_tax( 1, $price );
                }

            }

            $item = array(
                'id'       => $content_id,
                'google_business_vertical' => $this->googleBusinessVertical,
            );

            $items[] = $item;
            $total_value   += $price;

        }

        // calculate value
        if ( PYS()->getOption( 'woo_event_value' ) == 'custom' ) {
            $value = getWooOrderTotal( $order );
        } else {
            $value = $order->get_total();
        }

        if ( isWooCommerceVersionGte( '2.7' ) ) {
            $tax      = (float) $order->get_total_tax( 'edit' );
            $shipping = (float) $order->get_shipping_total( 'edit' );
        } else {
            $tax      = $order->get_total_tax();
            $shipping = $order->get_total_shipping();
        }

        // coupons
        if ( $coupons = $order->get_items( 'coupon' ) ) {
            $coupon = reset( $coupons );
            $coupon = $coupon['name'];
        } else {
            $coupon = null;
        }
        $ids = array();
        foreach ($items as $i) {
            $ids[] = $i['id'];
        }
        $params = array(
            'ecomm_prodid'=> $ids,
            'ecomm_pagetype'=> "purchase confirmation",
            'ecomm_totalvalue'=> $value,
            'event_category'  => 'ecommerce',
            'transaction_id'  => $order_id,
            'value'           => $value,
            'currency'        => get_woocommerce_currency(),
            'items'           => $items,
            'tax'             => $tax,
            'shipping'        => $shipping,
            'coupon'          => $coupon,
        );

        return array(
            'name' => 'purchase',
            'ids' => Helpers\getConversionIDs( 'woo_purchase' ),
            'data' => $params
        );

    }

    private function getWooAdvancedMarketingEventParams( $eventType ) {

        if ( ! $this->getOption( $eventType . '_enabled' ) ) {
            return false;
        }

        $customer_params = PYS()->getEventsManager()->getWooCustomerTotals();
        $params = array(
            "plugin" => "PixelYourSite"
        );


        switch ( $eventType ) {
            case 'woo_frequent_shopper':
                $eventName = 'FrequentShopper';
                $params['transactions_count'] = $customer_params['orders_count'];
                break;

            case 'woo_vip_client':
                $eventName = 'VipClient';
                $params['average_order'] = $customer_params['avg_order_value'];
                $params['transactions_count'] = $customer_params['orders_count'];
                break;

            default:
                $params['predicted_ltv'] = $customer_params['ltv'];
                $eventName = 'BigWhale';
        }

        return array(
            'name'  => $eventName,
            'data'  => $params,
        );

    }

    private function getWooCartParams( $context = 'cart' ) {

        $items = array();
        $total_value = 0;

        foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {

            $product_id = Helpers\getWooCartItemId( $cart_item );
            if(!$product_id) continue;
            $content_id = Helpers\getWooFullItemId( $product_id );
            $price = getWooProductPriceToDisplay( $product_id );
            $item = array(
                'id'       => $content_id,
                'google_business_vertical' => $this->googleBusinessVertical,
            );

            $items[] = $item;
            $total_value += $price * $cart_item['quantity'];

        }
        $coupons =  WC()->cart->get_applied_coupons();
        if ( count($coupons) > 0 ) {
            $coupon = $coupons[0];
        } else {
            $coupon = null;
        }

        $params = array(
            'event_category' => 'ecommerce',
            'value' => $total_value,
            'items' => $items,
            'coupon' => $coupon
        );

        return $params;

    }

    private function getWooOrderParams() {

        if ( ! empty( $this->wooOrderParams ) ) {
            return $this->wooOrderParams;
        }
        $order_id = wooGetOrderIdFromRequest();

        $order = new \WC_Order( $order_id );
        $items = array();

        foreach ( $order->get_items( 'line_item' ) as $line_item ) {

            $product_id = Helpers\getWooCartItemId( $line_item );
            $content_id = Helpers\getWooFullItemId( $product_id );

            $post = get_post( $product_id );

            if ( $line_item['variation_id'] ) {
                $variation = wc_get_product( (int) $line_item['variation_id'] );
                if(is_a($variation, 'WC_Product_Variation')) {
                    $name = $variation->get_title();
                    $category = implode( '/', getObjectTerms( 'product_cat', $variation->get_parent_id() ) );
                    $variation_name = implode("/", $variation->get_variation_attributes());
                } else {
                    $name = $post->post_title;
                    $variation_name = null;
                    $category = implode( '/', getObjectTerms( 'product_cat', $product_id ) );
                }

            } else {
                $name = $post->post_title;
                $variation_name = null;
                $category = implode( '/', getObjectTerms( 'product_cat', $product_id ) );
            }

            $item = array(
                'id'       => $content_id,
                'name'     => $name,
                'category' => $category,
                'quantity' => $line_item['qty'],
                'price'    => getWooProductPriceToDisplay( $product_id ),
                'variant'  => $variation_name,
            );

            $items[] = $item;

        }

        // calculate value
        if ( PYS()->getOption( 'woo_event_value' ) == 'custom' ) {
            $value = getWooOrderTotal( $order );
        } else {
            $value = $order->get_total();
        }

        if ( isWooCommerceVersionGte( '2.7' ) ) {
            $tax = (float) $order->get_total_tax( 'edit' );
            $shipping = (float) $order->get_shipping_total( 'edit' );
        } else {
            $tax = $order->get_total_tax();
            $shipping = $order->get_total_shipping();
        }

        $this->wooOrderParams = array(
            'event_category' => 'ecommerce',
            'transaction_id' => $order_id,
            'value'          => $value,
            'currency'       => get_woocommerce_currency(),
            'items'          => $items,
            'tax'            => $tax,
            'shipping'       => $shipping
        );

        return $this->wooOrderParams;

    }

    private function getEddViewContentEventParams() {
        global $post;

        if ( ! $this->getOption( 'edd_view_content_enabled' ) ) {
            return false;
        }

        $price = getEddDownloadPriceToDisplay( $post->ID );
        $id = Helpers\getEddDownloadContentId($post->ID);
        $params = array(
            'ecomm_prodid'=> $id,
            'ecomm_pagetype'=> 'product',
            'event_category'  => 'ecommerce',
            'value' => $price,
            'items'           => array(
                array(
                    'id'       => $id,
                    'google_business_vertical' => $this->googleBusinessVertical,
                ),
            ),
        );

        return array(
            'name'  => 'view_item',
            'ids' => Helpers\getConversionIDs( 'edd_view_content' ),
            'data'  => $params,
            'delay' => (int) PYS()->getOption( 'edd_view_content_delay' ),
        );

    }

    private function getEddAddToCartOnButtonClickEventParams( $download_id ) {

        if ( ! $this->getOption( 'edd_add_to_cart_enabled' ) || ! PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
            return false;
        }

        // maybe extract download price id
        if ( strpos( $download_id, '_') !== false ) {
            list( $download_id, $price_index ) = explode( '_', $download_id );
        } else {
            $price_index = null;
        }

        $price = getEddDownloadPriceToDisplay( $download_id, $price_index );

        if(is_home()) {
            $ecomm_pagetype = "home";
        }elseif(is_category()) {
            $ecomm_pagetype = "category";
        } else {
            $ecomm_pagetype = get_post_type();
        }
        $contentId = Helpers\getEddDownloadContentId($download_id);
        $params = array(
            'ecomm_prodid' => $contentId,
            'ecomm_pagetype'=> $ecomm_pagetype,
            'event_category'  => 'ecommerce',
            'value' => $price,
            'items'           => array(
                array(
                    'id'       => $contentId,
                    'google_business_vertical' => $this->googleBusinessVertical,
                ),
            ),
        );

        return array(
            'ids' => Helpers\getConversionIDs( 'edd_add_to_cart' ),
            'params' => $params,
        );

    }

    private function getEddCartEventParams( $context = 'add_to_cart' ) {

        if ( $context == 'add_to_cart' && ! $this->getOption( 'edd_add_to_cart_enabled' ) ) {
            return false;
        } elseif ( $context == 'begin_checkout' && ! $this->getOption( 'edd_initiate_checkout_enabled' ) ) {
            return false;
        } elseif ( $context == 'purchase' && ! $this->getOption( 'edd_purchase_enabled' ) ) {
            return false;
        } else {
            // AM events allowance checked by themselves
        }

        if ( $context == 'add_to_cart' || $context == 'begin_checkout' ) {
            $cart = edd_get_cart_contents();
        } else {
            $cart = edd_get_payment_meta_cart_details( edd_get_purchase_id_by_key( getEddPaymentKey() ), true );
        }

        $items = array();
        $total_value = 0;

        foreach ( $cart as $cart_item_key => $cart_item ) {

            $download_id   = (int) $cart_item['id'];

            if ( in_array( $context, array( 'purchase', 'FrequentShopper', 'VipClient', 'BigWhale' ) ) ) {
                $item_options = $cart_item['item_number']['options'];
            } else {
                $item_options = $cart_item['options'];
            }

            if ( ! empty( $item_options ) && $item_options['price_id'] !== 0 ) {
                $price_index = $item_options['price_id'];
            } else {
                $price_index = null;
            }

            /**
             * Price as is used for all events except Purchase to avoid wrong values in Product Performance report.
             */
            if ( $context == 'purchase' ) {

                $include_tax = PYS()->getOption( 'edd_tax_option' ) == 'included' ? true : false;

                $price = $cart_item['item_price'] - $cart_item['discount'];

                if ( $include_tax == false && edd_prices_include_tax() ) {
                    $price -= $cart_item['tax'];
                } elseif ( $include_tax == true && edd_prices_include_tax() == false ) {
                    $price += $cart_item['tax'];
                }

            } else {
                $price = getEddDownloadPriceToDisplay( $download_id, $price_index );
            }

            $item = array(
                'id'       => Helpers\getEddDownloadContentId($download_id),
                'google_business_vertical' => $this->googleBusinessVertical,
//				'variant'  => $variation_name,
            );

            $items[] = $item;
            $total_value += $price;

        }
        $params = array(
            'ecomm_pagetype'=> "purchase confirmation",
            'event_category' => 'ecommerce',
            'value' => $total_value,
            'items' => $items,
        );





        if ( $context == 'purchase' ) {

            $payment_key = getEddPaymentKey();
            $payment_id = (int) edd_get_purchase_id_by_key( $payment_key );
            $user = edd_get_payment_meta_user_info( $payment_id );

            // coupons
            $coupons = isset( $user['discount'] ) && $user['discount'] != 'none' ? $user['discount'] : null;

            if ( ! empty( $coupons ) ) {
                $coupons = explode( ', ', $coupons );
                $params['coupon'] = $coupons[0];
            }

            $params['transaction_id'] = $payment_id;
            $params['currency'] = edd_get_currency();

            // calculate value
            if ( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
                $params['value'] = getEddOrderTotal( $payment_id );
            } else {
                $params['value'] = edd_get_payment_amount( $payment_id );
            }

            if ( edd_use_taxes() ) {
                $params['tax'] = edd_get_payment_tax( $payment_id );
            } else {
                $params['tax'] = 0;
            }

            $ids = array();
            foreach ($items as $i) {
                $ids[] = $i['id'];
            }
            $params['ecomm_prodid'] = $ids;
            $params['ecomm_totalvalue'] = $total_value;

        }
        $ids = array();
        switch ($context) {
            case 'add_to_cart':
                $ids = Helpers\getConversionIDs( 'edd_add_to_cart' );
                break;

            case 'begin_checkout':
                $ids = Helpers\getConversionIDs( 'edd_initiate_checkout' );
                break;

            case 'purchase':
                $ids = Helpers\getConversionIDs( 'edd_purchase' );
                break;
        }

        return array(
            'name' => $context,
            'ids' => $ids,
            'data' => $params,
        );

    }

    private function getEddRemoveFromCartParams( $cart_item ) {

        if ( ! $this->getOption( 'edd_remove_from_cart_enabled' ) ) {
            return false;
        }

        $download_id = $cart_item['id'];

        $price_index = ! empty( $cart_item['options'] ) ? $cart_item['options']['price_id'] : null;
        $price = getEddDownloadPriceToDisplay( $download_id, $price_index );

        return array(
            'name'=>'remove_from_cart',
            'data' => array(
                'event_category'  => 'ecommerce',
                'currency'        => edd_get_currency(),
                'value' => $price,
                'items'           => array(
                    array(
                        'id'       => Helpers\getEddDownloadContentId($download_id),
                        'google_business_vertical' => $this->googleBusinessVertical,
//						'variant'  => $variation_name,
                    ),
                ),
            ),
        );

    }

    private function getEddViewCategoryEventParams() {
        global $posts;

        if ( ! $this->getOption( 'edd_view_category_enabled' ) ) {
            return false;
        }

        $term = get_term_by( 'slug', get_query_var( 'term' ), 'download_category' );
        $parent_ids = get_ancestors( $term->term_id, 'download_category', 'taxonomy' );

        $download_categories = array();
        $download_categories[] = $term->name;

        foreach ( $parent_ids as $term_id ) {
            $parent_term = get_term_by( 'id', $term_id, 'download_category' );
            $download_categories[] = $parent_term->name;
        }

        $list_name = implode( '/', array_reverse( $download_categories ) );

        $items = array();
        $total_value = 0;

        for ( $i = 0; $i < count( $posts ); $i ++ ) {

            $item = array(
                'id'            => Helpers\getEddDownloadContentId($posts[ $i ]->ID),
                'google_business_vertical' => $this->googleBusinessVertical,
            );

            $items[] = $item;
            $total_value += getEddDownloadPriceToDisplay( $posts[ $i ]->ID );

        }

        $params = array(
            'event_category' => 'ecommerce',
            'event_label'    => $list_name,
            'value'          => $total_value,
            'items'          => $items,
        );

        return array(
            'name'  => 'view_item_list',
            'ids' => Helpers\getConversionIDs( 'edd_view_category' ),
            'data'  => $params,
        );

    }

    private function getEddAdvancedMarketingEventParams( $eventType ) {

        if ( ! $this->getOption( $eventType . '_enabled' ) ) {
            return false;
        }

        switch ( $eventType ) {
            case 'edd_frequent_shopper':
                $eventName = 'FrequentShopper';
                break;

            case 'edd_vip_client':
                $eventName = 'VipClient';
                break;

            default:
                $eventName = 'BigWhale';
        }

        $params = $this->getEddCartEventParams( $eventName );

        return array(
            'name' => $eventName,
            'data' => $params['data'],
        );

    }

    function registerProductMetaBox () {

        if ( current_user_can( 'manage_pys' ) ) {
            add_meta_box( 'pys-gads-box', 'PYS Google Ads',
                array( $this, 'render_meta_box' ),
                "product","side" );

        }
    }

    function render_meta_box () {
        wp_nonce_field( 'pys_save_meta_box', '_pys_nonce' );
        include 'views/html-meta-box.php';
    }


    /**
     * @param $post_id
     * @param \WP_Post $post
     * @param $update
     */
    function saveProductMetaBox($post_id, $post, $update) {
        if ( ! isset( $_POST['_pys_nonce'] ) || ! wp_verify_nonce( $_POST['_pys_nonce'], 'pys_save_meta_box' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $data = $_POST['pys_ads_conversion_label'];

        $meta = array(
            'enable' => isset( $data['enable'] ),
            'label'  => isset( $data['label'] ) ? trim( $data['label'] ) : '',
            'id'     => isset( $data['id'] ) ? trim( $data['id'] ) : '',
        );

        update_post_meta($post_id,"_pys_conversion_label_settings",$meta);
    }
    function output_meta_tag() {
        $metaTags = (array) $this->getOption( 'verify_meta_tag' );
        foreach ($metaTags as $tag) {
            echo $tag;
        }
    }
}

/**
 * @return GoogleAds
 */
function Ads() {
	return GoogleAds::instance();
}

Ads();
