<?php
namespace PixelYourSite;


class EventsWoo extends EventsFactory {

    private $events = array(
        "woo_frequent_shopper",
        "woo_vip_client",
        "woo_big_whale",
        "woo_view_content",
        "woo_view_content_for_category",
        "woo_view_category",
        "woo_view_item_list",
        "woo_view_item_list_single",
        "woo_view_item_list_search",
        "woo_view_item_list_shop",
        "woo_view_item_list_tag",
        "woo_add_to_cart_on_cart_page",
        "woo_add_to_cart_on_cart_page_category",
        "woo_add_to_cart_on_checkout_page",
        "woo_add_to_cart_on_checkout_page_category",
        "woo_initiate_checkout",
        "woo_initiate_checkout_category",
        "woo_purchase",
        "woo_initiate_set_checkout_option",
        "woo_initiate_checkout_progress_f",
        "woo_initiate_checkout_progress_l",
        "woo_initiate_checkout_progress_e",
        "woo_initiate_checkout_progress_o",
        "woo_remove_from_cart",
        "woo_add_to_cart_on_button_click",
        "woo_affiliate",
        "woo_paypal",
        "woo_select_content_category",
        "woo_select_content_single",
        "woo_select_content_search",
        "woo_select_content_shop",
        "woo_select_content_tag",
    );
    public $doingAMP = false;


    private static $_instance;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }

    private function __construct() {

    }

    static function getSlug() {
        return "woo";
    }

    function getCount()
    {
        $size = 0;
        if(!$this->isEnabled()) {
            return 0;
        }
        foreach ($this->events as $event) {
            if($this->isActive($event)){
                $size++;
            }
        }
       return $size;
    }

    function isEnabled()
    {
        return isWooCommerceActive() && PYS()->getOption( 'woo_enabled' );
    }

    function getOptions() {

        if($this->isEnabled()) {
            global $post;
            $data = array(
                'enabled'                       => true,
                'addToCartOnButtonEnabled'      => PYS()->getOption( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_button_click' ),
                'addToCartOnButtonValueEnabled' => PYS()->getOption( 'woo_add_to_cart_value_enabled' ),
                'addToCartOnButtonValueOption'  => PYS()->getOption( 'woo_add_to_cart_value_option' ),
                'woo_purchase_on_transaction'   => PYS()->getOption( 'woo_purchase_on_transaction' ) ,
                'singleProductId'               => isWooCommerceActive() && is_singular( 'product' ) ? $post->ID : null,
                'affiliateEnabled'              => PYS()->getOption( 'woo_affiliate_enabled' ),
                'removeFromCartSelector'        => isWooCommerceVersionGte( '3.0.0' )
                    ? 'form.woocommerce-cart-form .remove'
                    : '.cart .product-remove .remove',
                'addToCartCatchMethod'  => PYS()->getOption('woo_add_to_cart_catch_method')
            );
            $woo_affiliate_custom_event_type = PYS()->getOption( 'woo_affiliate_custom_event_type' );
            if ( PYS()->getOption( 'woo_affiliate_event_type' ) == 'custom' && ! empty( $woo_affiliate_custom_event_type ) ) {
                $data['affiliateEventName'] = sanitizeKey( PYS()->getOption( 'woo_affiliate_custom_event_type' ) );
            } else {
                $data['affiliateEventName'] = PYS()->getOption( 'woo_affiliate_event_type' );
            }
            return $data;
        } else {
            return array(
                'enabled' => false,
            );
        }

    }

    function isReadyForFire($event)
    {
        switch ($event) {
            case 'woo_affiliate': {
                return PYS()->getOption( 'woo_affiliate_enabled' );
            }
            case 'woo_add_to_cart_on_button_click': {

                return PYS()->getOption( 'woo_add_to_cart_enabled' )
                    && PYS()->getOption( 'woo_add_to_cart_on_button_click' )
                    && PYS()->getOption('woo_add_to_cart_catch_method') == "add_cart_js"; // or use in hook
            }
            case 'woo_select_content_category': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) && !$this->doingAMP && is_tax( 'product_cat' );
            }
            case 'woo_select_content_single': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) && !$this->doingAMP && is_product();
            }
            case 'woo_select_content_search': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) && !$this->doingAMP && is_search();
            }
            case 'woo_select_content_shop': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) && !$this->doingAMP && is_shop()&& !is_search();
            }
            case 'woo_select_content_tag': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) && !$this->doingAMP && is_product_tag();
            }
            case 'woo_paypal': {
                return PYS()->getOption( 'woo_paypal_enabled' ) && is_checkout() && ! is_wc_endpoint_url();
            }
            case 'woo_remove_from_cart': {
                return PYS()->getOption( 'woo_remove_from_cart_enabled') && is_cart();
            }
            case "woo_initiate_checkout_progress_f":
            case "woo_initiate_checkout_progress_l":
            case "woo_initiate_checkout_progress_e":
            case "woo_initiate_checkout_progress_o": {
                return PYS()->getOption( "woo_checkout_steps_enabled" ) && is_checkout() ;
            }
            case 'woo_initiate_set_checkout_option': {
                return PYS()->getOption( "woo_checkout_steps_enabled" )  && is_checkout() && ! is_wc_endpoint_url();
            }
            case 'woo_purchase' : {
                return $this->checkPurchase();
            }
            case 'woo_frequent_shopper': {
                if(is_order_received_page() && PYS()->getOption( 'woo_frequent_shopper_enabled' ) &&
                    wooIsRequestContainOrderId()) {
                    $customerTotals = $this->getWooCustomerTotals();
                    $orders_count = (int) PYS()->getOption( 'woo_frequent_shopper_transactions' );
                    return  $customerTotals['orders_count'] >= $orders_count;
                }
                return false;
            }
            case 'woo_vip_client': {
                if(is_order_received_page() && PYS()->getOption( 'woo_vip_client_enabled' )&&
                    wooIsRequestContainOrderId()) {
                    $customerTotals = $this->getWooCustomerTotals();
                    $orders_count = (int) PYS()->getOption( 'woo_vip_client_transactions' );
                    $avg = (int) PYS()->getOption( 'woo_vip_client_average_value' );
                    return $customerTotals['orders_count'] >= $orders_count &&
                        $customerTotals['avg_order_value'] >= $avg;
                }
                return false;
            }
            case 'woo_big_whale': {
                if(is_order_received_page() && PYS()->getOption( 'woo_big_whale_enabled' )&&
                    wooIsRequestContainOrderId()) {
                    $customerTotals = $this->getWooCustomerTotals();
                    $ltv = (int) PYS()->getOption( 'woo_big_whale_ltv' );
                    return $customerTotals['ltv'] >= $ltv;
                }
                return false;
            }
            case 'woo_view_content_for_category':
            case 'woo_view_content' : {
                return PYS()->getOption( 'woo_view_content_enabled' ) && is_product();
            }
            case 'woo_view_category': {
                return PYS()->getOption( 'woo_view_category_enabled' ) &&  is_tax( 'product_cat' );
            }
            case 'woo_view_item_list': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) &&  is_tax( 'product_cat' );
            }
            case 'woo_view_item_list_single': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) &&  is_product();
            }
            case 'woo_view_item_list_search': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) &&  is_search();
            }
            case 'woo_view_item_list_shop': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) &&  is_shop() && !is_search();
            }
            case 'woo_view_item_list_tag': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) &&  is_product_tag();
            }
            case 'woo_add_to_cart_on_cart_page_category': {
                return PYS()->getOption( 'woo_add_to_cart_enabled' ) &&
                    PYS()->getOption( 'woo_add_to_cart_on_cart_page' ) &&
                    is_cart() &&
                    Facebook()->enabled() &&
                    count(Facebook()->getCategoryPixelIDs()) > 0;
            }
            case 'woo_add_to_cart_on_cart_page': {
                return PYS()->getOption( 'woo_add_to_cart_enabled' ) &&
                    PYS()->getOption( 'woo_add_to_cart_on_cart_page' ) &&
                    is_cart();
            }
            case 'woo_add_to_cart_on_checkout_page': {
                return PYS()->getOption( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_checkout_page' )
                    && is_checkout() && ! is_wc_endpoint_url();
            }
            case 'woo_add_to_cart_on_checkout_page_category': {
                return PYS()->getOption( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_checkout_page' )
                    && is_checkout() && ! is_wc_endpoint_url() &&
                    Facebook()->enabled() &&
                    count(Facebook()->getCategoryPixelIDs()) > 0;
            }
            case 'woo_initiate_checkout': {
                return PYS()->getOption( 'woo_initiate_checkout_enabled' ) && is_checkout() && ! is_wc_endpoint_url();
            }
            case 'woo_initiate_checkout_category': {
                return PYS()->getOption( 'woo_initiate_checkout_enabled' ) && is_checkout() &&
                    !is_wc_endpoint_url() &&
                    Facebook()->enabled() &&
                    count(Facebook()->getCategoryPixelIDs()) > 0;
            }
        }
        return false;
    }

    function getEvent($event)
    {
        switch ($event) {
            case 'woo_remove_from_cart':
            case 'woo_select_content_search':
            case 'woo_select_content_shop':
            case 'woo_select_content_tag':
            case 'woo_select_content_single':
            case 'woo_select_content_category': {
                $event = new GroupedEvent($event,EventTypes::$DYNAMIC);
                return $event;
            }
            case 'woo_initiate_set_checkout_option':
            case 'woo_initiate_checkout':
            case 'woo_add_to_cart_on_checkout_page':
            case 'woo_add_to_cart_on_cart_page':
            case 'woo_view_item_list_tag':
            case 'woo_view_item_list_shop':
            case 'woo_view_item_list_search':
            case 'woo_view_item_list_single':
            case 'woo_view_category':
            case 'woo_view_item_list':
            case 'woo_view_content_for_category':
            case 'woo_view_content':
            case 'woo_big_whale':
            case 'woo_vip_client':
            case 'woo_frequent_shopper':
                return new SingleEvent($event,EventTypes::$STATIC);
            case 'woo_paypal':
            case 'woo_add_to_cart_on_button_click':
            case 'woo_affiliate':
                return new SingleEvent($event,EventTypes::$DYNAMIC);
            case 'woo_initiate_checkout_category':
            case 'woo_add_to_cart_on_checkout_page_category':
            case 'woo_add_to_cart_on_cart_page_category': {
                $categoryEvent = new GroupedEvent($event,EventTypes::$STATIC);
                $activeCatIds = Facebook()->getCategoryPixelIDs();
                $catIds = $this->getWooCartActiveCategories($activeCatIds);
                foreach ($catIds as $key){
                    $categoryEvent->addEvent(new SingleEvent($key,EventTypes::$STATIC));
                }
                return $categoryEvent;
            }
            case 'woo_purchase' : {
                $events = array();
                $event = new SingleEvent($event,EventTypes::$STATIC);
                $order_id = wooGetOrderIdFromRequest();
                $event->addPayload(['woo_order' => $order_id]);
                $events[] = $event;
                // add child event complete_registration
                if(PYS()->getOption( 'woo_complete_registration_enabled' )) {
                    $event = new SingleEvent('woo_complete_registration',EventTypes::$STATIC);
                    $events[] = $event;
                }

                // add child event for separate category
                $categoryEvent = new GroupedEvent("woo_purchase_category",EventTypes::$STATIC);
                $events[] = $categoryEvent;

                if(Facebook()->enabled()) {
                    $activeCatIds = Facebook()->getCategoryPixelIDs();
                    if(count($activeCatIds) > 0) {

                        $catIds = $this->getWooOrderActiveCategories($order_id,$activeCatIds);
                        foreach ($catIds as $key){
                            $single = new SingleEvent($key,EventTypes::$STATIC);
                            $single->addPayload(['woo_order' => $order_id]);
                            $categoryEvent->addEvent($single);
                        }
                    }
                }
                return $events;
            }
            case "woo_initiate_checkout_progress_f":
            case "woo_initiate_checkout_progress_l":
            case "woo_initiate_checkout_progress_e":
            case "woo_initiate_checkout_progress_o":{
                $single =  new SingleEvent($event,EventTypes::$DYNAMIC);
                return $single;
            }
        }

        return null;
    }

    private function isActive($event)
    {
        switch ($event) {
            case 'woo_affiliate': {
                return PYS()->getOption( 'woo_affiliate_enabled' );
            }
            case 'woo_add_to_cart_on_button_click': {
                return PYS()->getOption( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_button_click' );
            }
            case 'woo_paypal': {
                return PYS()->getOption( 'woo_paypal_enabled' ) ;
            }
            case 'woo_remove_from_cart': {
                return PYS()->getOption( 'woo_remove_from_cart_enabled') ;
            }
           /* case "woo_initiate_checkout_progress_f":
            case "woo_initiate_checkout_progress_l":
            case "woo_initiate_checkout_progress_e":
            case "woo_initiate_checkout_progress_o": {
                return PYS()->getOption( "woo_checkout_steps_enabled" ) ;
            }*/
            case 'woo_initiate_set_checkout_option': {
                return PYS()->getOption( "woo_checkout_steps_enabled" );
            }
            case 'woo_purchase' : {
                return PYS()->getOption( 'woo_purchase_enabled' );
            }
            case 'woo_frequent_shopper': {
                return PYS()->getOption( 'woo_frequent_shopper_enabled' );
            }
            case 'woo_vip_client': {
                return PYS()->getOption( 'woo_vip_client_enabled' );
            }
            case 'woo_big_whale': {
                return PYS()->getOption( 'woo_big_whale_enabled' );
            }

            case 'woo_view_content' : {
                return PYS()->getOption( 'woo_view_content_enabled' ) ;
            }
            case 'woo_view_category': {
                return PYS()->getOption( 'woo_view_category_enabled' ) ;
            }

            case 'woo_select_content_category':
            case 'woo_view_item_list': {
                return PYS()->getOption( 'woo_view_item_list_enabled' ) ;
            }

            case 'woo_initiate_checkout': {
                return PYS()->getOption( 'woo_initiate_checkout_enabled' );
            }

        }
        return false;
    }
    private function getWooCartActiveCategories($activeIds) {
        $fireForCategory = array();
        foreach (WC()->cart->cart_contents as $cart_item_key => $cart_item) {
            $_product =  wc_get_product( $cart_item['product_id'] );
            if(!$_product) continue;
            $productCat = $_product->get_category_ids();
            foreach ($activeIds as $key => $value) {
                if(in_array($key,$productCat)) {
                    $fireForCategory[] = $key;
                }
            }
        }
        return array_unique($fireForCategory);
    }

    private function getWooOrderActiveCategories($orderId,$activeIds) {
        $order = new \WC_Order( $orderId );

        $fireForCategory = array();
        foreach ($order->get_items() as $item) {
            $_product =  wc_get_product( $item->get_product_id() );
            if(!$_product) continue;
            $productCat = $_product->get_category_ids();
            foreach ($activeIds as $key => $value) {
                if(in_array($key,$productCat)) { // fire initiate_checkout for all category pixel
                    $fireForCategory[] = $key;
                }
            }
        }
        return array_unique($fireForCategory);
    }

    function getWooCustomerTotals(){
        return PYS()->getEventsManager()->getWooCustomerTotals();
    }

    function getEvents() {
       return $this->events;
    }



    private function checkPurchase() {
        if(PYS()->getOption( 'woo_purchase_enabled' ) &&
            is_order_received_page() &&
            wooIsRequestContainOrderId()
        ) {
            $order_id = wooGetOrderIdFromRequest();

            $order = wc_get_order($order_id);
            if(!$order) return false;
            $status = "wc-".$order->get_status("edit");

            $disabledStatuses = (array)PYS()->getOption("woo_order_purchase_disabled_status");

            if( in_array($status,$disabledStatuses)) {
                return false;
            }

            if (PYS()->getOption( 'woo_purchase_on_transaction' ) &&
                get_post_meta( $order_id, '_pys_purchase_event_fired', true ) ) {
                return false;  // skip woo_purchase if this transaction was fired
            }
            update_post_meta( $order_id, '_pys_purchase_event_fired', true );

            return true;
        }
        return false;
    }
}

/**
 * @return EventsWoo
 */
function EventsWoo() {
    return EventsWoo::instance();
}

EventsWoo();
