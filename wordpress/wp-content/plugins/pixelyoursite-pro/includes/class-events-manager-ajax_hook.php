<?php
namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


class AjaxHookEventManager {

    public static $pendingEvents = array();
    public static $DIV_ID_FOR_AJAX_EVENTS = "pys_ajax_events";
    private static $_instance;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    public function __construct() {

    }

    public function addHooks() {


        if(EventsWoo()->isEnabled()) {
            if ( isEventEnabled('woo_add_to_cart_enabled')
                && PYS()->getOption('woo_add_to_cart_on_button_click')
                && PYS()->getOption('woo_add_to_cart_catch_method') == "add_cart_hook" )
            {
                add_action( 'wp_footer', array( __CLASS__, 'addDivForAjaxPixelEvent')  );
                add_action( 'woocommerce_add_to_cart',array(__CLASS__, 'trackWooAddToCartEvent'),40, 6);
            }
        }
    }


static function trackWooAddToCartEvent($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        if(isset($cart_item_data['woosb_parent_id'])) return; // fix for WPC Product Bundles for WooCommerce (Premium) product

        $is_ajax_request = wp_doing_ajax();
        if( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'yith_wacp_add_item_cart') {
            $is_ajax_request = true;
        }
        $standardParams = getStandardParams();

        foreach ( PYS()->getRegisteredPixels() as $pixel ) {

            if( !empty($variation_id)
                && $variation_id > 0
                && ( !$pixel->getOption( 'woo_variable_as_simple' )
                    || ( $pixel->getSlug() == "facebook"
                        && !Facebook\Helpers\isDefaultWooContentIdLogic()
                    )
                )
            ) {
                $_product_id = $variation_id;
            } else {
                $_product_id = $product_id;
            }


            $event = new SingleEvent('woo_add_to_cart_on_button_click',EventTypes::$STATIC);
            $event->args = ['productId' => $_product_id,'quantity' => $quantity];
            $isSuccess = $pixel->addParamsToEvent( $event );
            if ( !$isSuccess ) {
                continue; // event is disabled or not supported for the pixel
            }

            if(count($event->params) == 0) {
                // add product params
                // use for not update bing and pinterest, need remove in next updates
                $eventData = $pixel->getEventData('woo_add_to_cart_on_button_click',$product_id);
                if($eventData) {
                    $event->addParams($eventData['params']);
                    unset($eventData['params']);
                    $event->addPayload($eventData);
                }
            }



            // add standard params
            if($pixel->getSlug() != "ga" || $pixel->isUse4Version()) {
                $event->addParams($standardParams);
            }

            // prepare event data
            $eventData = $event->getData();
            $eventData = EventsManager::filterEventParams($eventData,"woo");

            AjaxHookEventManager::$pendingEvents["woo_add_to_cart_on_button_click"][ $pixel->getSlug() ] = $eventData;

            if($pixel->getSlug() == "facebook" && Facebook()->isServerApiEnabled()) {
                $name = $eventData['name'];
                $data = $eventData['params'];
                $eventID = isset($eventData['eventID']) ? $eventData['eventID'] : false;
                $ids = (array)$event->payload["pixelIds"];
                $event = FacebookServer()->createEvent($eventID,$name,$data);

                if($is_ajax_request) {
                    FacebookServer()->sendEvent($ids,array($event));
                } else {
                    FacebookServer()->addAsyncEvents(array(array("pixelIds" => $ids, "event" => $event )));
                }
            }
        }

        if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
            add_filter('wc_add_to_cart_message_html',array(__CLASS__, 'addPixelCodeToAddToCarMessage'),90,3);
        } elseif ($is_ajax_request) {
            add_filter('woocommerce_add_to_cart_fragments', array(__CLASS__, 'addPixelCodeToAddToCartFragment'));
        } else {
            add_action("wp_footer",array(__CLASS__, 'printEvent'));
           // self::printEvent();
        }

    }

    public static function printEvent() {
        $pixelsEventData = self::$pendingEvents["woo_add_to_cart_on_button_click"];
        if( !is_null($pixelsEventData) ) {
            echo "<div id='pys_late_event' style='display:none' dir='".json_encode($pixelsEventData)."'></div>";
            unset(self::$pendingEvents["woo_add_to_cart_on_button_click"]);
        }
    }

    public  static function addDivForAjaxPixelEvent(){
        echo self::getDivForAjaxPixelEvent();
        ?>
        <script>
            var node = document.getElementsByClassName('woocommerce-message')[0];
            if(node && document.getElementById('pys_late_event')) {
                var messageText = node.textContent.trim();
                if(!messageText) {
                    node.style.display = 'none';
                }
            }
        </script>
            <?php
    }

    public  static function getDivForAjaxPixelEvent($content = ''){
        return "<div id='".self::$DIV_ID_FOR_AJAX_EVENTS."'>" . $content . "</div>";
    }

    public static function addPixelCodeToAddToCarMessage($message, $products, $show_qty) {
        $pixelsEventData = self::$pendingEvents["woo_add_to_cart_on_button_click"];
        if( !is_null($pixelsEventData) ){
            $message .= "<div id='pys_late_event' dir='".json_encode($pixelsEventData)."'></div>";
            unset(self::$pendingEvents["woo_add_to_cart_on_button_click"]);
        }
        return $message;
    }

    public static function addPixelCodeToAddToCartFragment($fragments) {


        $pixelsEventData = self::$pendingEvents["woo_add_to_cart_on_button_click"];
        if( !is_null($pixelsEventData) ){

            $pixel_code = self::generatePixelCode($pixelsEventData);
            $fragments['#'.self::$DIV_ID_FOR_AJAX_EVENTS] =
                self::getDivForAjaxPixelEvent($pixel_code);
            unset(self::$pendingEvents["woo_add_to_cart_on_button_click"]);
        }

        return $fragments;
    }

    public static function generatePixelCode($pixelsEventData){

        ob_start();
        //$cartHashKey = apply_filters( 'woocommerce_cart_hash_key', 'wc_cart_hash_' . md5( get_current_blog_id() . '_' . get_site_url( get_current_blog_id(), '/' ) . get_template() ) );
       ?>
        <script>
            function pys_getCookie(name) {
                var v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
                return v ? v[2] : null;
            }
            function pys_setCookie(name, value, days) {
                var d = new Date;
                d.setTime(d.getTime() + 24*60*60*1000*days);
                document.cookie = name + "=" + value + ";path=/;expires=" + d.toGMTString();
            }
            var name = 'pysAddToCartFragmentId';
            var cartHash = "<?=WC()->cart->get_cart_hash()?>";

            if(pys_getCookie(name) != cartHash) { // prevent re send event if user update page
                <?php foreach ($pixelsEventData as $slug => $eventData) : ?>

                    var pixel = getPixelBySlag('<?=$slug?>');
                    var event = <?=json_encode($eventData)?>;
                    pixel.fireEvent(event.name, event);

                <?php  endforeach; ?>
                pys_setCookie(name,cartHash,90)
            }
            </script>
        <?php

        $code = ob_get_clean();
        return $code;
    }




}
