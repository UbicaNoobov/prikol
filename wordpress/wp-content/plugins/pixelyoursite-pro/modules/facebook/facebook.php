<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/** @noinspection PhpIncludeInspection */
require_once PYS_PATH . '/modules/facebook/function-helpers.php';
require_once PYS_PATH . '/modules/facebook/FDPEvent.php';
require_once PYS_PATH . '/modules/facebook/PYSServerEventHelper.php';
require_once PYS_PATH . '/modules/facebook/function-helpers-woo-category.php';
use PixelYourSite\Facebook\Helpers;
use PixelYourSite\Facebook\HelpersCategory;
use function PixelYourSite\Facebook\Helpers\getFacebookWooProductContentId;


class Facebook extends Settings implements Pixel {

	private static $_instance;

	private $configured;

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;

	}

    public function __construct() {

        parent::__construct( 'facebook' );

        $this->locateOptions(
            PYS_PATH . '/modules/facebook/options_fields.json',
            PYS_PATH . '/modules/facebook/options_defaults.json'
        );

	    add_action( 'pys_register_pixels', function( $core ) {
	    	/** @var PYS $core */
	    	$core->registerPixel( $this );
	    } );
        add_action( 'wp_head', array( $this, 'output_meta_tag' ) );
    }

    public function enabled() {
	    return $this->getOption( 'enabled' );
    }

	public function configured() {

        $license_status = PYS()->getOption( 'license_status' );
        $pixel_id = $this->getPixelIDs();

        $this->configured = $this->enabled()
                            && ! empty( $license_status ) // license was activated before
                            && count( $pixel_id ) > 0
                            && !empty($pixel_id[0])
                            && ! apply_filters( 'pys_pixel_disabled', false, $this->getSlug() );

		return $this->configured;
	}

	public function getPixelIDs() {

		$ids = (array) $this->getOption( 'pixel_id' );

		if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
			return apply_filters("pys_facebook_ids",$ids);
		} else {
			return apply_filters("pys_facebook_ids",(array) reset( $ids )); // return first id only
		}
	}



    public function getDPAPixelID() {
	    if($this->getOption( 'fdp_use_own_pixel_id' )) {
	        return $this->getOption( 'fdp_pixel_id' );
        } else {
	        return "";
        }
    }

    public function getDPAPixelServerID() {
        if($this->getOption( 'fdp_use_own_pixel_id' )) {
            return $this->getOption( 'fdp_pixel_server_id' );
        } else {
            return "";
        }
    }

    public function getDPAPixelServerTestCode() {
        if($this->getOption( 'fdp_use_own_pixel_id' )) {
            return $this->getOption( 'fdp_pixel_server_test_code' );
        } else {
            return "";
        }
    }

    public function getCategoryPixelIDs() {
	    return (array)$this->getOption("category_pixel_ids");
    }

    public function getCategoryPixelServerIDs() {
        return (array)$this->getOption("category_pixel_server_ids");
    }

    public function getCategoryPixelServerTextCode() {
        return (array)$this->getOption("category_pixel_server_test_code");
    }


    public function getEddCategoryPixelIDs() {
        return (array)$this->getOption("edd_category_pixel_ids");
    }

    public function getEddCategoryPixelServerIDs() {
        return (array)$this->getOption("edd_category_pixel_server_ids");
    }

    public function getEddCategoryPixelServerTextCode() {
        return (array)$this->getOption("edd_category_pixel_server_test_code");
    }

	public function getPixelOptions() {

        if($this->getOption( 'advanced_matching_enabled' )) {
            $advancedMatching = Helpers\getAdvancedMatchingParams();
        } else {
            $advancedMatching = array();
        }


		return array(
			'pixelIds'            => $this->getPixelIDs(),
			'defoultPixelIds'     => $this->getPixelIDs(),
			'advancedMatching'    => $advancedMatching,
			'removeMetadata'      => $this->getOption( 'remove_metadata' ),
			'wooVariableAsSimple' => $this->getOption( 'woo_variable_as_simple' ),
            'serverApiEnabled'    => $this->isServerApiEnabled() && count($this->getApiTokens()) > 0,
            "ajaxForServerEvent"  => $this->getOption( "server_event_use_ajax" ),
            'wooCRSendFromServer' => $this->getOption("woo_complete_registration_send_from_server") &&
                                        $this->getOption("woo_complete_registration_fire_every_time"),
            'send_external_id'    => $this->getOption('send_external_id')
        );

	}

    public function updateOptions( $values = null ) {
	    if(isPixelCogActive() &&
            isset($_POST['pys'][ $this->getSlug() ]['woo_complete_registration_custom_value'])
        ) {
	        $val = $_POST['pys'][ $this->getSlug() ]['woo_complete_registration_custom_value'];
	        $currentVal = $this->getOption('woo_complete_registration_custom_value');
	        if($val != 'cog') {
                $_POST['pys'][ $this->getSlug() ]['woo_complete_registration_custom_value_old'] = $val;
            } elseif ( $currentVal != 'cog' ) {
                $_POST['pys'][ $this->getSlug() ]['woo_complete_registration_custom_value_old'] = $currentVal;
            }
        }
        parent::updateOptions($values);
    }

    public function addParamsToEvent(&$event) {
        if ( ! $this->configured() ) {
            return false;
        }
        $isActive = false;

        switch ($event->getId()) {
            case 'init_event':{
                    $eventData = $this->getPageViewEventParams();
                    if($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData,$event);
                    }
            } break;

            //Signal events
            case "signal_user_signup":
            case "signal_adsense":
            case "signal_page_scroll":
            case "signal_time_on_page":
            case "signal_tel":
            case "signal_email":
            case "signal_form":
            case "signal_download":
            case "signal_comment":
            case "signal_watch_video":
            case "signal_click" : {
                $isActive = $this->getOption('signal_events_enabled');
            }break;

            case 'woo_complete_registration': {
                if( $this->getOption("woo_complete_registration_fire_every_time") ||
                    get_user_meta( get_current_user_id(), 'pys_complete_registration', true )
                ) {
                    $eventData = $this->getWooCompleteRegistrationEventParams();
                    if($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData,$event);
                    }
                }
            }break;
            case 'woo_frequent_shopper':
            case 'woo_vip_client':
            case 'woo_big_whale': {
                $eventData =  $this->getWooAdvancedMarketingEventParams( $event->getId() );
                if($eventData) {
                    $isActive = true;
                    $event->addParams($eventData["data"]);
                    $event->addPayload($eventData["payload"]);
                }
            }break;

            case 'woo_view_content': {
                $eventData =  $this->getWooViewContentEventParams();
                if($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData,$event);
                }
            }break;
            case 'woo_view_content_for_category': {
                $eventData =  $this->getWooViewContentEventParamsForCategory();
                if($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData,$event);
                }
            }break;
            case 'woo_view_category':{
                $eventData =  $this->getWooViewCategoryEventParams();
                if($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData,$event);
                }
            }break;
            case 'woo_add_to_cart_on_cart_page':
            case 'woo_add_to_cart_on_checkout_page': {
                $eventData =  $this->getWooAddToCartOnCartEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'woo_add_to_cart_on_cart_page_category':
            case 'woo_add_to_cart_on_checkout_page_category': {
                foreach ($event->getEvents() as $child){
                    $eventData = $this->getWooAddToCartOnCartEventParamsFroCategory($child->getId());
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $child);
                    }
                }

            }break;

            case 'woo_initiate_checkout': {
                $eventData =  $this->getWooInitiateCheckoutEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'woo_initiate_checkout_category': {
                if(is_a($event,GroupedEvent::class)) {
                    foreach ($event->getEvents() as $child) {
                        $eventData = $this->getWooInitiateCheckoutCategoryEventParams($child->getId());
                        if ($eventData) {
                            $isActive = true;
                            $this->addDataToEvent($eventData, $child);
                        }
                    }
                }
            }break;

            case 'woo_purchase':{
                $eventData =  $this->getWooPurchaseEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            case 'woo_purchase_category':{
                if(is_a($event,GroupedEvent::class)) {
                    foreach ($event->getEvents() as $child){
                        $eventData = $this->getWooPurchaseCategoryEventParams($child->getId());
                        if ($eventData) {
                            $isActive = true;
                            $this->addDataToEvent($eventData, $child);
                        }
                    }
                }

            }break;

            case 'woo_remove_from_cart':{
                if(is_a($event,GroupedEvent::class)) {
                    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                        $eventData =  $this->getWooRemoveFromCartParams( $cart_item );
                        if ($eventData) {
                            $child = new SingleEvent($cart_item_key,EventTypes::$DYNAMIC);
                            $isActive = true;
                            $this->addDataToEvent($eventData, $child);
                            $event->addEvent($child);
                        }
                    }
                }
            }break;

            case 'woo_paypal': {
                $eventData =  $this->getWooPayPalEventParams();
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
            } break;

            case 'edd_add_to_cart_on_checkout_page': {
                    $eventData = $this->getEddCartEventParams('AddToCart');
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $event);
                    }
                }break;

            case 'edd_remove_from_cart': {
                if(is_a($event,GroupedEvent::class)) {
                    foreach ( edd_get_cart_contents() as $cart_item_key => $cart_item ) {
                        $eventData =  $this->getEddRemoveFromCartParams( $cart_item );
                        if ($eventData) {
                            $child = new SingleEvent($cart_item_key,EventTypes::$DYNAMIC);
                            $isActive = true;
                            $this->addDataToEvent($eventData, $child);
                            $event->addEvent($child);
                        }
                    }
                }
            }break;

            case 'edd_view_category': {
                    $eventData = $this->getEddViewCategoryEventParams();
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $event);
                    }
                }break;

            case 'edd_initiate_checkout': {
                    $eventData = $this->getEddCartEventParams('InitiateCheckout');
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $event);
                    }
                }break;

            case 'edd_initiate_checkout_category': {
                if(is_a($event,GroupedEvent::class)) {
                    foreach ($event->getEvents() as $child) {
                        $eventData = $this->getEddCartEventParams('InitiateCheckout',$child->getId());
                        if ($eventData) {
                            $isActive = true;
                            $this->addDataToEvent($eventData, $child);
                        }
                    }
                }
                }break;

            case 'edd_purchase': {

                    $eventData = $this->getEddCartEventParams('Purchase');
                    if ($eventData) {
                        $isActive = true;
                        $this->addDataToEvent($eventData, $event);
                    }
                }break;
            case 'edd_purchase_category': {
                if(is_a($event,GroupedEvent::class)) {
                    foreach ($event->getEvents() as $child) {
                        $eventData = $this->getEddCartEventParams('Purchase',$child->getId());
                        if ($eventData) {
                            $isActive = true;
                            $this->addDataToEvent($eventData, $child);
                        }
                    }
                }
                }break;

            case 'edd_frequent_shopper':
            case 'edd_vip_client':
            case 'edd_big_whale': {
                $eventData = $this->getEddAdvancedMarketingEventParams($event->getId());
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;
            case 'search_event':{
                $eventData =  $this->getSearchEventParams();
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'fdp_view_content':{
                if($this->getOption("fdp_view_content_enabled")){
                    $params = Helpers\getFDPViewContentEventParams();
                    $params["content_type"] = $this->getOption("fdp_content_type");
                    $payload = array(
                        'name' => "ViewContent",
                        'pixelIds' => $this->getPixelsForDpaEvent()
                    );
                    $event->addParams($params);
                    $event->addPayload($payload);
                    $isActive = true;
                }
            }break;
            case 'fdp_view_category':{
                if($this->getOption("fdp_view_category_enabled")){
                    $params = Helpers\getFDPViewCategoryEventParams();
                    $params["content_type"] = $this->getOption("fdp_content_type");
                    $payload = array(
                        'name' => "ViewCategory",
                        'pixelIds' => $this->getPixelsForDpaEvent()
                    );
                    $event->addParams($params);
                    $event->addPayload($payload);
                    $isActive = true;
                }
            }break;

            case 'fdp_add_to_cart':{
                if($this->getOption("fdp_add_to_cart_enabled")){
                    $params = Helpers\getFDPAddToCartEventParams();
                    $params["content_type"] = $this->getOption("fdp_content_type");
                    $params["value"] = $this->getOption("fdp_add_to_cart_value");
                    $params["currency"] = $this->getOption("fdp_currency");
                    $trigger_type = $this->getOption("fdp_add_to_cart_event_fire");
                    $trigger_value = $trigger_type == "scroll_pos" ?
                        $this->getOption("fdp_add_to_cart_event_fire_scroll") :
                        $this->getOption("fdp_add_to_cart_event_fire_css") ;
                    $payload = array(
                        'name' => "AddToCart",
                        'pixelIds' => $this->getPixelsForDpaEvent(),
                        'trigger_type' => $trigger_type,
                        'trigger_value' => $trigger_value
                    );
                    $event->addParams($params);
                    $event->addPayload($payload);
                    $isActive = true;
                }
            }break;

            case 'fdp_purchase':{
                if($this->getOption("fdp_view_category_enabled")){
                    $params = Helpers\getFDPPurchaseEventParams();
                    $params["content_type"] = $this->getOption("fdp_content_type");
                    $params["value"] = $this->getOption("fdp_purchase_value");
                    $params["currency"] = $this->getOption("fdp_currency");
                    $trigger_type = $this->getOption("fdp_purchase_event_fire");
                    $trigger_value = $trigger_type == "scroll_pos" ?
                        $this->getOption("fdp_purchase_event_fire_scroll") :
                        $this->getOption("fdp_purchase_event_fire_css");
                    $payload = array(
                        'name' => "Purchase",
                        'pixelIds' => $this->getPixelsForDpaEvent(),
                        'trigger_type' => $trigger_type,
                        'trigger_value' => $trigger_value
                    );
                    $event->addParams($params);
                    $event->addPayload($payload);
                    $isActive = true;
                }
            }break;

            case 'custom_event':{
                $eventData =  $this->getCustomEventParams( $event->args );
                if ($eventData) {
                    $isActive = true;
                    $this->addDataToEvent($eventData, $event);
                }
            }break;

            case 'woo_add_to_cart_on_button_click':{
                if (  $this->getOption( 'woo_add_to_cart_enabled' )
                    && PYS()->getOption( 'woo_add_to_cart_on_button_click' ) )
                {
                    $isActive = true;
                    if(isset($event->args['productId'])) {
                        $productId = $event->args['productId'];
                        $quantity = $event->args['quantity'];
                        $eventData =  $this->getWooAddToCartOnButtonClickEventParams( $productId,$quantity );
                        $event->addParams($eventData["params"]);
                    }
                    $event->addPayload(array(
                        'name'=>"AddToCart",
                        'pixelIds' => isset($eventData["pixelIds"]) ? $eventData["pixelIds"] : null,
                    ));
                }
            }break;

            case 'woo_affiliate':{
                if($this->getOption( 'woo_affiliate_enabled' )){
                    $isActive = true;
                    if(isset($event->args['productId'])) {
                        $productId = $event->args['productId'];
                        $quantity = $event->args['quantity'];
                        $eventData =  $this->getWooAffiliateEventParams( $productId,$quantity );
                        $event->addParams($eventData["params"]);
                    }
                }
            }break;

            case 'edd_add_to_cart_on_button_click':{
                if (  $this->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
                    $isActive = true;
                    $event->addPayload(array(
                        'name'=>"AddToCart"
                    ));
                }
            }break;
        }

        if($isActive) {
            if(is_a($event,GroupedEvent::class) ) {
                $events = $event->getEvents();
            } else {
                $events = [$event];
            }
            foreach ($events as $child) {
                if( !isset($child->payload['pixelIds']) )
                    $child->payload['pixelIds'] = $this->getPixelIDs();

                if($this->isServerApiEnabled()) {
                    $child->payload['eventID'] = EventIdGenerator::guidv4();
                }
            }


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
                $eventData =  $this->getEddAddToCartOnButtonClickEventParams( $args );break;
            case 'woo_remove_from_cart':
                $eventData =  $this->getWooRemoveFromCartParams( $args );break;

			default: $eventData = false;   // event does not supported
		}
        if($eventData) {
            if( !isset($eventData['pixelIds']) )
                $eventData['pixelIds'] = $this->getPixelIDs();
        }

        return $eventData;

	}

	public function outputNoScriptEvents() {

		if ( ! $this->configured() ) {
			return;
		}

		$eventsManager = PYS()->getEventsManager();

		foreach ( $eventsManager->getStaticEvents( 'facebook' ) as $eventId => $events ) {

			foreach ( $events as $event ) {
                if($event['name']== "hCR") continue;
				foreach ( $this->getPixelIDs() as $pixelID ) {

					$args = array(
						'id'       => $pixelID,
						'ev'       => urlencode( $event['name'] ),
						'noscript' => 1,
					);
					if(isset($event['eventID'])) {
                        $args['eid'] = $event['eventID'];
                    }

					foreach ( $event['params'] as $param => $value ) {
					    if(is_array($value))
                            $value = json_encode($value);
						@$args[ 'cd[' . $param . ']' ] = urlencode( $value );
					}

					// ALT tag used to pass ADA compliance
					printf( '<noscript><img height="1" width="1" style="display: none;" src="%s" alt="facebook_pixel"></noscript>',
						add_query_arg( $args, 'https://www.facebook.com/tr' ) );

					echo "\r\n";

				}
			}
		}

	}

	private function getPageViewEventParams() {
        global $post;

        $pixelIds = $this->getPixelIDs();
        $pixelIds = HelpersCategory\getWooCategoryPixelIdsForPageView($pixelIds);
        $pixelIds = HelpersCategory\getEddCategoryPixelIdsForPageView($pixelIds);

        $cpt = get_post_type();
        $params = array();

        if(isWooCommerceActive() && $cpt == 'product') {
            $params['categories'] = implode( ', ', getObjectTerms( 'product_cat', $post->ID ) );
            $params['tags']       = implode( ', ', getObjectTerms( 'product_tag', $post->ID ) );
        } elseif (isEddActive() && $cpt == 'download') {
            $params['categories'] = implode( ', ', getObjectTerms( 'download_category', $post->ID ) );
            $params['tags']       = implode( ', ', getObjectTerms( 'download_tag', $post->ID ) );
        } elseif ($post instanceof \WP_Post) {
            $params['tags'] = implode( ', ', getObjectTerms( 'post_tag', $post->ID ) );
            if ( ! empty( $taxonomies ) && $terms = getObjectTerms( $taxonomies[0], $post->ID ) ) {
                $params['categories'] = implode( ', ', $terms );
            }
        }

        $data = array(
            'name'  => 'PageView',
            'data'  => $params,
            'pixelIds' => $pixelIds
        );

        return $data;
    }

    function getPixelsForDpaEvent(){
        $dpaPixel = $this->getDPAPixelID();
        $pixelIds = array();
        if($dpaPixel) {
            $pixelIds[] = $dpaPixel;
        } else {
            $pixelIds = $this->getPixelIDs();
        }
        return $pixelIds;
    }


	private function getSearchEventParams() {

		if ( ! $this->getOption( 'search_event_enabled' ) ) {
			return false;
		}
        $params = array();
		$params['search'] = empty( $_GET['s'] ) ? null : $_GET['s'];

		return array(
			'name'  => 'Search',
			'data'  => $params,
		);

	}

	private function getWooViewContentEventParamsForCategory() {
        $pixelIds = (array)Facebook()->getOption("category_pixel_ids");
        if(count($pixelIds) == 0) return false;

        global $post;
        $terms = get_the_terms( $post->ID, 'product_cat' );
        if(!$terms)return false;
        $pixelList = array();
        foreach ($terms as $term) {
            if(isset($pixelIds[$term->term_id])) {
                if(!in_array($pixelIds[$term->term_id],$pixelList))
                    $pixelList[] = $pixelIds[$term->term_id];
            }
        }
        if(count($pixelList) == 0) return false;

        $data = $this->getWooViewContentEventParams();
        $data['pixelIds'] = $pixelList;
        return $data;
    }

	private function getWooViewContentEventParams() {
		global $post;

		if ( ! $this->getOption( 'woo_view_content_enabled' ) ) {
			return false;
		}

		$params = array();

		$product = wc_get_product( $post->ID );
        if(!$product) return false;
		$content_id = Helpers\getFacebookWooProductContentId( $post->ID );
		$params['content_ids']  =  $content_id ;

		if ( wooProductIsType( $product, 'variable' ) && ! $this->getOption( 'woo_variable_as_simple' ) ) {
			$params['content_type'] = 'product_group';
		} else {
			$params['content_type'] = 'product';
		}

		// Facebook for WooCommerce plugin integration
		if ( ! Helpers\isDefaultWooContentIdLogic() && wooProductIsType( $product, 'variable' ) ) {
			$params['content_type'] = 'product_group';
		}

		// content_name, category_name, tags
        $tagsList = getObjectTerms( 'product_tag', $post->ID );
		if(count($tagsList)) {
            $params['tags'] = implode( ', ', $tagsList );
        }

		$params = array_merge( $params, Helpers\getWooCustomAudiencesOptimizationParams( $post->ID ) );

		// currency, value
		if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {

			$value_option   = PYS()->getOption( 'woo_view_content_value_option' );
			$global_value   = PYS()->getOption( 'woo_view_content_value_global', 0 );
			$percents_value = PYS()->getOption( 'woo_view_content_value_percent', 100 );

			$params['value']    = getWooEventValue( $value_option, $global_value, $percents_value, $post->ID,1 );
            $params['currency'] = get_woocommerce_currency();

		}

		// contents
		if ( Helpers\isDefaultWooContentIdLogic() ) {

			// Facebook for WooCommerce plugin does not support new Dynamic Ads parameters
			$params['contents'] =  array(
				array(
					'id'         => (string) reset( $content_id ),
					'quantity'   => 1,
					//'item_price' => getWooProductPriceToDisplay( $post->ID ),
				)
			) ;

		}

		$params['product_price'] = getWooProductPriceToDisplay( $post->ID );

		return array(
			'name'  => 'ViewContent',
			'data'  => $params,
			'delay' => (int) PYS()->getOption( 'woo_view_content_delay' ),
		);

	}

	private function getWooAddToCartOnButtonClickEventParams( $product_id,$quantity ) {

		$params = Helpers\getWooSingleAddToCartParams( $product_id, $quantity, false );
		$data = array(
            'params' => $params,
        );
        $categoryPixels = (array)$this->getOption("category_pixel_ids");
        if(count($categoryPixels) > 0) {
            $_product_id = $product_id;
            $post = get_post($product_id);
            if ( $post->post_type == 'product_variation' ) {
                $_product_id = $post->post_parent; // get terms from parent
            }
            $product = wc_get_product($_product_id);
            $productCatIds = $product->get_category_ids();
            $keys = array_keys($categoryPixels);
            $catIds = array_intersect($productCatIds,$keys);
            if(count($catIds) > 0){
                $data['pixelIds'] = $this->getPixelIDs();
                foreach ($catIds as $id) { // Add Category pixels
                    if(!in_array($categoryPixels[$id],$data['pixelIds']))
                        $data['pixelIds'][] = $categoryPixels[$id];
                }
            }
        }

        $product = wc_get_product($product_id);
        if($product->get_type() == 'grouped') {
            $grouped = array();
            foreach ($product->get_children() as $childId) {
                $conId = getFacebookWooProductContentId( $childId );
                $grouped[$childId] = array(
                    'content_id' => (string) reset($conId),
                    'price' => getWooProductPriceToDisplay( $childId )
                );
            }
            $data['grouped'] = $grouped; // used for add to cart
        }



		return $data;

	}

    private function   getWooAddToCartOnCartEventParamsFroCategory($catId) {
        if ( ! $this->getOption( 'woo_add_to_cart_enabled' ) ) {
            return false;
        }
        $categoryPixels = $this->getCategoryPixelIDs();
        $params = Helpers\getWooCartParams('cart',array("category"=>$catId));

        return array(
            'name' => 'AddToCart',
            'data' => $params,
            'pixelIds' => array($categoryPixels[$catId])
        );

    }

	private function   getWooAddToCartOnCartEventParams() {

		if ( ! $this->getOption( 'woo_add_to_cart_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooCartParams();

		return array(
			'name' => 'AddToCart',
			'data' => $params,
		);

	}

	private function getWooRemoveFromCartParams( $cart_item ) {

		if ( ! $this->getOption( 'woo_remove_from_cart_enabled' ) ) {
			return false;
		}

		$product_id = Helpers\getFacebookWooCartItemId( $cart_item );
        $product = wc_get_product($product_id);
        if(!$product) return false;
		$content_id = Helpers\getFacebookWooProductContentId( $product_id );

		$params['content_type'] = 'product';
		$params['content_ids']  =  $content_id ;

		// content_name, category_name, tags
        $tagsList = getObjectTerms( 'product_tag', $product_id );
        if(count($tagsList)) {
            $params['tags'] = implode( ', ', $tagsList );
        }
		$params = array_merge( $params, Helpers\getWooCustomAudiencesOptimizationParams( $product_id ) );

		$params['num_items'] = $cart_item['quantity'];
		$params['product_price'] = getWooProductPriceToDisplay( $product_id );



		$params['contents'] =  array(
			array(
				'id'         => (string) reset( $content_id ),
				'quantity'   => $cart_item['quantity'],
				//'item_price' => getWooProductPriceToDisplay( $product_id ),
			)
		) ;

		return array( 'name' => "RemoveFromCart",
            'data' => $params );

	}

	private function getWooViewCategoryEventParams() {
		global $posts;

		if ( ! $this->getOption( 'woo_view_category_enabled' ) ) {
			return false;
		}

		if ( Helpers\isDefaultWooContentIdLogic() ) {
			$params['content_type'] = 'product';
		} else {
			$params['content_type'] = 'product_group';
		}

        $params['content_category'] = array();
		$term = get_term_by( 'slug', get_query_var( 'term' ), 'product_cat' );

		if ( $term ) {

            $params['content_name'] = $term->name;

            $parent_ids = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );

            foreach ( $parent_ids as $term_id ) {
                $term = get_term_by( 'id', $term_id, 'product_cat' );
                $params['content_category'][] = $term->name;
            }

        }

		$params['content_category'] = implode( ', ', $params['content_category'] );

		$content_ids = array();
		$limit       = min( count( $posts ), 5 );

		for ( $i = 0; $i < $limit; $i ++ ) {
			$content_ids = array_merge( Helpers\getFacebookWooProductContentId( $posts[ $i ]->ID ), $content_ids );
		}

		$params['content_ids']  =  $content_ids ;

        $pixelIds =  $this->getPixelIDs();

        $categoryPixels = (array)$this->getOption("category_pixel_ids");
        if(count($categoryPixels) > 0) {
            $currentCatId = get_queried_object_id();
            if(isset($categoryPixels[$currentCatId]))
                $pixelIds[] = $categoryPixels[$currentCatId];
        }

		return array(
			'name' => 'ViewCategory',
			'data' => $params,
            'pixelIds' => $pixelIds
		);

	}

	private function getWooInitiateCheckoutEventParams() {

		if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooCartParams( 'InitiateCheckout' );

		return array(
			'name' => 'InitiateCheckout',
			'data' => $params,
		);

	}

    private function getWooInitiateCheckoutCategoryEventParams($categoryId) {

        if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) ) {
            return false;
        }

        $params = Helpers\getWooCartParams( 'InitiateCheckout',array("category"=>$categoryId) );
        $categoryPixels = Facebook()->getCategoryPixelIDs();
        return array(
            'name' => 'InitiateCheckout',
            'data' => $params,
            'pixelIds' => array($categoryPixels[$categoryId])
        );

    }

	private function getWooPurchaseEventParams() {

		if ( ! $this->getOption( 'woo_purchase_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooPurchaseParams( 'woo_purchase' );
        $order_id = wooGetOrderIdFromRequest();
		return array(
			'name' => 'Purchase',
			'data' => $params,
            'woo_order' => $order_id
		);

	}

	private function getWooPurchaseCategoryEventParams($categoryId) {
        if ( ! $this->getOption( 'woo_purchase_enabled' ) ) {
            return false;
        }
        $order_id = wooGetOrderIdFromRequest();
        $params = Helpers\getWooPurchaseParams( 'woo_purchase',array("category"=>$categoryId) );
        $categoryPixels = Facebook()->getCategoryPixelIDs();
        return array(
            'name' => 'Purchase',
            'data' => $params,
            'pixelIds' => array($categoryPixels[$categoryId]),
            'woo_order' => $order_id
        );
    }

	private function getWooAffiliateEventParams( $product_id,$quantity ) {

		if ( ! $this->getOption( 'woo_affiliate_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooSingleAddToCartParams( $product_id, $quantity, true );

		return array(
			'params' => $params,
		);

	}

	private function getWooPayPalEventParams() {

		if ( ! $this->getOption( 'woo_paypal_enabled' ) ) {
			return false;
		}

		// we're using Cart date as of Order not exists yet
		$params = Helpers\getWooCartParams( 'PayPal' );

		return array(
			'name' => getWooPayPalEventName(),
			'data' => $params,
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
			'payload' => array('name' => $eventName),
			'data' => $params,
		);

	}

	/**
	 * @param CustomEvent $customEvent
	 *
	 * @return array|bool
	 */
	private function getCustomEventParams( $customEvent ) {

		$event_type = $customEvent->getFacebookEventType();

		if ( ! $customEvent->isFacebookEnabled() || empty( $event_type ) ) {
			return false;
		}

		$params = array();

		// add pixel params
		if ( $customEvent->isFacebookParamsEnabled() ) {

			$params = $customEvent->getFacebookParams();

			// use custom currency if any
			if ( ! empty( $params['custom_currency'] ) ) {
				$params['currency'] = $params['custom_currency'];
				unset( $params['custom_currency'] );
			}

			// add custom params
			foreach ( $customEvent->getFacebookCustomParams() as $custom_param ) {
				$params[ $custom_param['name'] ] = $custom_param['value'];
			}

		}

		// SuperPack Dynamic Params feature
		$params = apply_filters( 'pys_superpack_dynamic_params', $params, 'facebook' );

		return array(
			'name'  => $customEvent->getFacebookEventType(),
			'data'  => $params,
			'delay' => $customEvent->getDelay(),
		);

	}

	private function getWooCompleteRegistrationEventParams() {

        if( !wooIsRequestContainOrderId()) return false;


        $eventName =  'CompleteRegistration';
        $pixels = $this->getPixelIDs();

        $order_id = wooGetOrderIdFromRequest();
        $order = new \WC_Order( $order_id );
        if($this->getOption('woo_complete_registration_use_custom_value')) {
            $params = Helpers\getCompleteRegistrationOrderParams($order);
        } else {
            $params = array();
        }


        $catPixelIds = $this->getCategoryPixelIDs();
        if(count($catPixelIds) > 0) {
            $catPixelIdsKey = array_keys($catPixelIds);
            foreach ($order->get_items() as $item) {
                $product = wc_get_product($item->get_product_id());
                if(!$product) continue;
                $catIds = array_intersect($product->get_category_ids(),$catPixelIdsKey);
                if(count($catIds) == 0) continue;
                foreach ($catIds as $id) {
                    if(!in_array($catPixelIds[$id],$pixels))
                        $pixels[]=$catPixelIds[$id];
                }
            }

        }

		return  array(
            'name'      => $eventName,
            'data'      => $params,
            'pixelIds'  => $pixels
        );

	}


	private function getEddViewContentEventParams() {
		global $post;

		if ( ! $this->getOption( 'edd_view_content_enabled' ) ) {
			return false;
		}

        $pixelIds = $this->getPixelIDs();
        $pixelIds = HelpersCategory\getEddCategoryPixelIdsForProduct($post->ID,$pixelIds);

		$params = array(
			'content_type' => 'product',
			'content_ids'  =>  Helpers\getFacebookEddDownloadContentId( $post->ID ) ,
		);

		// content_name, category_name
        $tagsList = getObjectTerms( 'download_tag', $post->ID );
        if(count($tagsList)) {
            $params['tags'] = implode( ', ', $tagsList );
        }

		$params = array_merge( $params, Helpers\getEddCustomAudiencesOptimizationParams( $post->ID ) );

		// currency, value
		if ( PYS()->getOption( 'edd_view_content_value_enabled' ) ) {

			if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$amount = getEddDownloadPrice( $post->ID );
			} else {
				$amount = getEddDownloadPriceToDisplay( $post->ID );
			}

			$value_option   = PYS()->getOption( 'edd_view_content_value_option' );
			$global_value   = PYS()->getOption( 'edd_view_content_value_global', 0 );
			$percents_value = PYS()->getOption( 'edd_view_content_value_percent', 100 );

			$params['value'] = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
            $params['currency'] = edd_get_currency();

		}

		// contents
		$params['contents'] =  array(
			array(
				'id'         => (string) $post->ID,
				'quantity'   => 1,
				//'item_price' => getEddDownloadPriceToDisplay( $post->ID ),
			)
		) ;

		return array(
			'name'      => 'ViewContent',
			'data'      => $params,
			'delay'     => (int) PYS()->getOption( 'edd_view_content_delay' ),
            'pixelIds'  => $pixelIds
		);

	}

	private function getEddAddToCartOnButtonClickEventParams( $download_id ) {
		global $post;

		if ( ! $this->getOption( 'edd_add_to_cart_enabled' ) || ! PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
			return false;
		}

        $pixelIds = $this->getPixelIDs();
        $pixelIds = HelpersCategory\getEddCategoryPixelIdsForProduct($post->ID,$pixelIds);

		// maybe extract download price id
		if ( strpos( $download_id, '_') !== false ) {
			list( $download_id, $price_index ) = explode( '_', $download_id );
		} else {
			$price_index = null;
		}

		$params = array(
			'content_type' => 'product',
			'content_ids'  =>  Helpers\getFacebookEddDownloadContentId( $post->ID ) ,
		);

		// content_name, category_name
        $tagsList = getObjectTerms( 'download_tag', $post->ID );
        if(count($tagsList)) {
            $params['tags'] = implode( ', ', $tagsList );
        }

		$params = array_merge( $params, Helpers\getEddCustomAudiencesOptimizationParams( $post->ID ) );

		// currency, value
		if ( PYS()->getOption( 'edd_add_to_cart_value_enabled' ) ) {

			if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$amount = getEddDownloadPrice( $post->ID, $price_index );
			} else {
				$amount = getEddDownloadPriceToDisplay( $post->ID, $price_index );
			}

			$value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
			$percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
			$global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );
            $params['currency'] = edd_get_currency();
			$params['value'] = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
		}


		$license = getEddDownloadLicenseData( $download_id );
		$params  = array_merge( $params, $license );

		// contents
		$params['contents'] =  array(
			array(
				'id'         => (string) $download_id,
				'quantity'   => 1,
				//'item_price' => getEddDownloadPriceToDisplay( $download_id ),
			)
		);

		return array(
			'params' => $params,
            'pixelIds' => $pixelIds
		);

	}

	private function getEddCartEventParams( $context = 'AddToCart',$filter = null ) {

		if ( $context == 'AddToCart' && ! $this->getOption( 'edd_add_to_cart_enabled' ) ) {
			return false;
		} elseif ( $context == 'InitiateCheckout' && ! $this->getOption( 'edd_initiate_checkout_enabled' ) ) {
			return false;
		} elseif ( $context == 'Purchase' && ! $this->getOption( 'edd_purchase_enabled' ) ) {
			return false;
		} else {
			// AM events allowance checked by themselves
		}

        $data = array(
            'name' => $context
        );


		if ( $context == 'AddToCart' ) {
			$value_enabled  = PYS()->getOption( 'edd_add_to_cart_value_enabled' );
			$value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
			$percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
			$global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );
		} elseif ( $context == 'InitiateCheckout' ) {
			$value_enabled  = PYS()->getOption( 'edd_initiate_checkout_value_enabled' );
			$value_option   = PYS()->getOption( 'edd_initiate_checkout_value_option' );
			$percents_value = PYS()->getOption( 'edd_initiate_checkout_value_percent', 100 );
			$global_value   = PYS()->getOption( 'edd_initiate_checkout_global', 0 );
		} else {
			$value_enabled  = PYS()->getOption( 'edd_purchase_value_enabled' );
			$value_option   = PYS()->getOption( 'edd_purchase_value_option' );
			$percents_value = PYS()->getOption( 'edd_purchase_value_percent', 100 );
			$global_value   = PYS()->getOption( 'edd_purchase_value_global', 0 );
		}

		$params = array(
			'content_type' => 'product'
		);

		$content_ids        = array();
		$content_names      = array();
		$content_categories = array();
		$tags               = array();
		$contents           = array();

		$num_items   = 0;
		$total       = 0;
		$total_as_is = 0;

		$licenses = array(
			'transaction_type'   => null,
			'license_site_limit' => null,
			'license_time_limit' => null,
			'license_version'    => null
		);

		if ( $context == 'AddToCart' || $context == 'InitiateCheckout' ) {
			$cart = edd_get_cart_contents();
		} else {
			$cart = edd_get_payment_meta_cart_details( edd_get_purchase_id_by_key( getEddPaymentKey() ), true );
		}

		foreach ( $cart as $cart_item_key => $cart_item ) {

			$download_id   = (int) $cart_item['id'];
           if($filter !=null && !has_term($filter,"download_category",$download_id)) {
                continue;
            }

			$content_ids[] = Helpers\getFacebookEddDownloadContentId( $download_id );

			// content_name, category_name
			$custom_audiences = Helpers\getEddCustomAudiencesOptimizationParams( $download_id );

			$content_names[]      = $custom_audiences['content_name'];
			$content_categories[] = $custom_audiences['category_name'];

			$tags = array_merge( $tags, getObjectTerms( 'download_tag', $download_id ) );

			$num_items += $cart_item['quantity'];

			if ( in_array( $context, array( 'Purchase', 'FrequentShopper', 'VipClient', 'BigWhale' ) ) ) {
				$item_options = $cart_item['item_number']['options'];
			} else {
				$item_options = $cart_item['options'];
			}

			if ( ! empty( $item_options ) && $item_options['price_id'] !== 0 ) {
				$price_index = $item_options['price_id'];
			} else {
				$price_index = null;
			}

			// calculate cart items total
			if ( $value_enabled ) {

				if ( $context == 'Purchase' ) {

					if ( PYS()->getOption( 'edd_tax_option' ) == 'included' ) {
						$total += $cart_item['subtotal'] + $cart_item['tax'] - $cart_item['discount'];
					} else {
						$total += $cart_item['subtotal'] - $cart_item['discount'];
					}

					$total_as_is += $cart_item['price'];

				} else {

					$total += getEddDownloadPrice( $download_id, $price_index ) * $cart_item['quantity'];
					$total_as_is += edd_get_cart_item_final_price( $cart_item_key );

				}

			}

			// get download license data
			array_walk( $licenses, function( &$value, $key, $license ) {

				if ( ! isset( $license[ $key ] ) ) {
					return;
				}

				if ( $value ) {
					$value = $value . ', ' . $license[ $key ];
				} else {
					$value = $license[ $key ];
				}

			}, getEddDownloadLicenseData( $download_id ) );

			// contents
			$contents[] = array(
				'id'         => (string) $download_id,
				'quantity'   => $cart_item['quantity'],
				//'item_price' => getEddDownloadPriceToDisplay( $download_id, $price_index ),
			);

		}

		$params['content_ids']   =  $content_ids ;
		$params['content_name']  = implode( ', ', $content_names );
		$params['category_name'] = implode( ', ', $content_categories );
		$params['contents']      =  $contents ;

		$tags           = array_slice( array_unique( $tags ), 0, 100 );
		if(count($tags))
		    $params['tags'] = implode( ', ', $tags );

		$params['num_items'] = $num_items;

		// currency, value
		if ( $value_enabled ) {

			if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$amount = $total;
			} else {
				$amount = $total_as_is;
			}
            $params['currency'] = edd_get_currency();
			$params['value']    = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
		}


		$params = array_merge( $params, $licenses );

		if ( $context == 'Purchase' ) {

			$payment_key = getEddPaymentKey();
			$payment_id = (int) edd_get_purchase_id_by_key( $payment_key );

			$user = edd_get_payment_meta_user_info( $payment_id );
			$meta = edd_get_payment_meta( $payment_id );

			// coupons
			$coupons = isset( $user['discount'] ) && $user['discount'] != 'none' ? $user['discount'] : null;

			if ( ! empty( $coupons ) ) {
				$coupons = explode( ', ', $coupons );
				$params['coupon'] = $coupons[0];
			}

			// calculate value
			if ( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$params['value'] = getEddOrderTotal( $payment_id );
			} else {
				$params['value'] = edd_get_payment_amount( $payment_id );
			}
            $params['currency'] = edd_get_currency();
			if ( edd_use_taxes() ) {
				$params['tax'] = edd_get_payment_tax( $payment_id );
			} else {
				$params['tax'] = 0;
			}
            $data['edd_order'] = $payment_id;
		}



		if($filter != null) {
            $categoryPixels = Facebook()->getEddCategoryPixelIDs();
            $data['pixelIds'] = array($categoryPixels[$filter]);
        }
        $data['data'] = $params;


		return $data;

	}

	private function getEddRemoveFromCartParams( $cart_item ) {

		if ( ! $this->getOption( 'edd_remove_from_cart_enabled' ) ) {
			return false;
		}

		$download_id = $cart_item['id'];
		$price_index = ! empty( $cart_item['options'] ) ? $cart_item['options']['price_id'] : null;

		$params = array(
			'content_type' => 'product',
			'content_ids' => Helpers\getFacebookEddDownloadContentId( $download_id )
		);

		// content_name, category_name, tags
        $tagsList = getObjectTerms( 'download_tag', $download_id );
        if(count($tagsList)) {
            $params['tags'] = implode( ', ', $tagsList );
        }

		$params = array_merge( $params, Helpers\getEddCustomAudiencesOptimizationParams( $download_id ) );

		$params['num_items'] = $cart_item['quantity'];

		$params['contents'] =  array(
			array(
				'id'         => (string) $download_id,
				'quantity'   => $cart_item['quantity'],
				'item_price' => getEddDownloadPriceToDisplay( $download_id, $price_index ),
			)
		) ;

		return array(
		    'name' => 'RemoveFromCart',
		    'data' => $params );

	}

	private function getEddViewCategoryEventParams() {
		global $posts;

		if ( ! $this->getOption( 'edd_view_category_enabled' ) ) {
			return false;
		}



		$params['content_type'] = 'product';

		$term = get_term_by( 'slug', get_query_var( 'term' ), 'download_category' );
		$params['content_name'] = $term->name;

		$parent_ids = get_ancestors( $term->term_id, 'download_category', 'taxonomy' );
		$params['content_category'] = array();

		foreach ( $parent_ids as $term_id ) {
			$parentTerm = get_term_by( 'id', $term_id, 'download_category' );
			$params['content_category'][] = $parentTerm->name;
		}

		$params['content_category'] = implode( ', ', $params['content_category'] );

		$content_ids = array();
		$limit       = min( count( $posts ), 5 );

		for ( $i = 0; $i < $limit; $i ++ ) {
			$content_ids = array_merge( array( Helpers\getFacebookEddDownloadContentId( $posts[ $i ]->ID ) ),
				$content_ids );
		}

		$params['content_ids']  =  $content_ids ;

        $pixelIds = $this->getPixelIDs();
        $pixelIds = HelpersCategory\getEddCategoryPixelIdsForCategory($term->term_id,$pixelIds);

		return array(
			'name' => 'ViewCategory',
			'data' => $params,
            'pixelIds' => $pixelIds
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

		$params['data']['product_names'] = $params['data']['content_name'];
		$params['data']['product_ids'] = $params['data']['content_ids'];

		unset( $params['data']['content_name'] );
		unset( $params['data']['content_ids'] );
		unset( $params['data']['content_type'] );
		unset( $params['data']['contents'] );

		return array(
			'name' => $eventName,
			'data' => $params['data'],
		);

	}

    /**
     * @return []
     */
    public function getApiTokens() {

        $tokens = array();
        $pixelids = (array) $this->getOption( 'pixel_id' );
        $serverids = (array) $this->getOption( 'server_access_api_token' );

        if(count($pixelids) == 0) return array();

        if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
            foreach ($pixelids as $key => $val) {
                if(isset($serverids[$key]))
                    $tokens[$val] = $serverids[$key];
            }
        } else {
            $tokens[$pixelids[0]] =  reset( $serverids ); // return first id only
        }

        // add woo category pixel ids
        $catIds = $this->getCategoryPixelIDs();
        $catServerIds = $this->getCategoryPixelServerIDs();
        foreach ($catIds as $key => $val) {
            if(isset($catServerIds[$key]))
                $tokens[$val] = $catServerIds[$key];
        }
        // add edd category pixel ids
        $eddCatIds = $this->getEddCategoryPixelIDs();
        $eddCatServerIds = $this->getEddCategoryPixelServerIDs();
        foreach ($eddCatIds as $key => $val) {
            if(isset($eddCatServerIds[$key]))
                $tokens[$val] = $eddCatServerIds[$key];
        }

        return $tokens;
    }

    /**
     * @return []
     */
    public function getApiTestCode() {
        $testCode = array();
        $pixelids = (array) $this->getOption( 'pixel_id' );
        $serverTestCode = (array) $this->getOption( 'test_api_event_code' );
        if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
            foreach ($pixelids as $key => $val) {
                if(isset($serverTestCode[$key]))
                    $testCode[$val] = $serverTestCode[$key];
            }
        } else {
            $testCode[$pixelids[0]] = reset( $serverTestCode ); // return first id only
        }
        // add woo category pixel ids
        $catIds = $this->getCategoryPixelIDs();
        $catTestCode = $this->getCategoryPixelServerTextCode();
        foreach ($catIds as $key => $val) {
            if(isset($catTestCode[$key]))
                $testCode[$val] = $catTestCode[$key];
        }
        // add edd category pixel ids
        $eddCatIds = $this->getEddCategoryPixelIDs();
        $eddCatTestCode = $this->getEddCategoryPixelServerTextCode();
        foreach ($eddCatIds as $key => $val) {
            if(isset($eddCatTestCode[$key]))
                $testCode[$val] = $eddCatTestCode[$key];
        }

        return $testCode;
    }

    function output_meta_tag() {
        $metaTags = (array) Facebook()->getOption( 'verify_meta_tag' );
        foreach ($metaTags as $tag) {
            echo $tag;
        }
    }



    /**
     * @return bool
     */
    public function isServerApiEnabled() {
        return $this->getOption("use_server_api");
    }
}

/**
 * @return Facebook
 */
function Facebook() {
	return Facebook::instance();
}

Facebook();
