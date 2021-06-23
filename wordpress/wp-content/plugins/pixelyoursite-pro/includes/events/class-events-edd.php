<?php

namespace PixelYourSite;

class EventsEdd extends EventsFactory {
    private $events = array(
        'edd_frequent_shopper',
        'edd_vip_client',
        'edd_big_whale',
        'edd_view_content',
        'edd_view_category',
        'edd_add_to_cart_on_checkout_page',
        'edd_remove_from_cart',
        'edd_initiate_checkout',
        'edd_purchase',
        'edd_add_to_cart_on_button_click'
    );

    private $eddCustomerTotals = array();
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
        return "edd";
    }

    function getEvents() {
        return $this->events;
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
        return isEddActive() && PYS()->getOption( 'edd_enabled' );
    }

    function getOptions()
    {
        if($this->isEnabled()) {
            return array(
                'enabled'                       => true,
                'addToCartOnButtonEnabled'      => isEventEnabled( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' ),
                'addToCartOnButtonValueEnabled' => PYS()->getOption( 'edd_add_to_cart_value_enabled' ),
                'addToCartOnButtonValueOption'  => PYS()->getOption( 'edd_add_to_cart_value_option' ),
                'edd_purchase_on_transaction'   => PYS()->getOption( 'edd_purchase_on_transaction' )
            );
        } else {
            return array(
                'enabled'                       => false
            );
        }
    }

    function isReadyForFire($event)
    {
        switch ($event) {
            case 'edd_add_to_cart_on_button_click': {
                return PYS()->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' );
            }
            case 'edd_purchase': {
                return $this->checkPurchase();
            }
            case 'edd_initiate_checkout': {
                return  PYS()->getOption( 'edd_initiate_checkout_enabled' ) && edd_is_checkout();
            }
            case 'edd_remove_from_cart': {
                return PYS()->getOption( 'edd_remove_from_cart_enabled') && edd_is_checkout();
            }
            case 'edd_add_to_cart_on_checkout_page' : {
                return PYS()->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_checkout_page' )
                    && edd_is_checkout();
            }
            case 'edd_view_category': {
                return PYS()->getOption( 'edd_view_category_enabled' ) && is_tax( 'download_category' );
            }
            case 'edd_view_content' : {
                return PYS()->getOption( 'edd_view_content_enabled' ) && is_singular( 'download' );
            }
            case 'edd_vip_client': {
                $customerTotals = $this->getEddCustomerTotals();
                if(edd_is_success_page() && PYS()->getOption( 'edd_vip_client_enabled' )) {
                    $orders_count = (int) PYS()->getOption( 'edd_vip_client_transactions' );
                    $avg = (int) PYS()->getOption( 'edd_vip_client_average_value' );
                    return $customerTotals['orders_count'] >= $orders_count && $customerTotals['avg_order_value'] >= $avg;
                }
                return false;
            }
            case 'edd_big_whale': {
                $customerTotals = $this->getEddCustomerTotals();
                if(edd_is_success_page() && PYS()->getOption( 'edd_big_whale_enabled' )) {
                    $ltv = (int) PYS()->getOption( 'edd_big_whale_ltv' );
                    return $customerTotals['ltv'] >= $ltv;
                }
                return false;
            }
            case 'edd_frequent_shopper': {
                $customerTotals = $this->getEddCustomerTotals();
                if(edd_is_success_page() && PYS()->getOption( 'edd_frequent_shopper_enabled' )) {
                    $orders_count = (int) PYS()->getOption( 'edd_frequent_shopper_transactions' );
                    return $customerTotals['orders_count'] >= $orders_count;
                }
                return false;
            }

        }
        return false;
    }

    function getEvent($event)
    {
        switch ($event) {
            case 'edd_add_to_cart_on_checkout_page' :
            case 'edd_view_category': //@todo: +7.1.0+  maybe fire on Shop page as well? review GA 'list' param then
            case 'edd_view_content':
            case 'edd_vip_client':
            case 'edd_big_whale':
            case 'edd_frequent_shopper': {
                return new SingleEvent($event,EventTypes::$STATIC);
            }
            case 'edd_remove_from_cart': {
                return new GroupedEvent($event,EventTypes::$DYNAMIC);
            }
            case 'edd_add_to_cart_on_button_click': {

                return new SingleEvent($event,EventTypes::$DYNAMIC);
            }

            case 'edd_initiate_checkout': {
                $events = array();
                $events[] = new SingleEvent($event,EventTypes::$STATIC);
                if(Facebook()->enabled()) {
                    $categoryPixels = (array)Facebook()->getEddCategoryPixelIDs();
                    if(count($categoryPixels) > 0) {
                        $catIds = $this->getEddCartActiveCategories($categoryPixels);
                        if(count($catIds) > 0) {
                            $groupEvent = new GroupedEvent('edd_initiate_checkout_category',EventTypes::$STATIC);
                            foreach ($catIds as $key){
                                $groupEvent->addEvent(new SingleEvent($key,EventTypes::$STATIC));
                            }
                            $events[] = $groupEvent;
                        }
                    }
                }

                return $events;
            }
            case 'edd_purchase': {
                $events = array();
                $payment_key = getEddPaymentKey();
                $order_id = (int) edd_get_purchase_id_by_key( $payment_key );

                $event = new SingleEvent($event,EventTypes::$STATIC);
                $event->addPayload(['edd_order'=>$order_id]);
                $events[] = $event;
                if(Facebook()->enabled()) {
                    $categoryPixels = (array)Facebook()->getEddCategoryPixelIDs();
                    if(count($categoryPixels) > 0) {
                        $catIds = $this->getEddCartActiveCategories($categoryPixels);
                        if(count($catIds) > 0) {
                            $groupEvent = new GroupedEvent('edd_purchase_category',EventTypes::$STATIC);
                            foreach ($catIds as $key){
                                $child = new SingleEvent($key,EventTypes::$STATIC);
                                $child->addPayload(['edd_order'=>$order_id]);
                                $groupEvent->addEvent($child);
                            }
                            $events[] = $groupEvent;
                        }
                    }
                }
                return $events;
            }
        }
    }

    private function isActive($event)
    {
        switch ($event) {
            case 'edd_add_to_cart_on_button_click': {
                return PYS()->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' );
            }
            case 'edd_purchase': {
                return PYS()->getOption( 'edd_purchase_enabled' );
            }
            case 'edd_initiate_checkout': {
                return  PYS()->getOption( 'edd_initiate_checkout_enabled' ) ;
            }
            case 'edd_remove_from_cart': {
                return PYS()->getOption( 'edd_remove_from_cart_enabled');
            }
            case 'edd_add_to_cart_on_checkout_page' : {
                return PYS()->getOption( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_checkout_page' );
            }
            case 'edd_view_category': {
                return PYS()->getOption( 'edd_view_category_enabled' ) ;
            }
            case 'edd_view_content' : {
                return PYS()->getOption( 'edd_view_content_enabled' ) ;
            }
            case 'edd_vip_client': {
                return PYS()->getOption( 'edd_vip_client_enabled' );
            }
            case 'edd_big_whale': {
                return PYS()->getOption( 'edd_big_whale_enabled' );
            }
            case 'edd_frequent_shopper': {
                return PYS()->getOption( 'edd_frequent_shopper_enabled' );
            }
        }
        return false;
    }

    private function getEddCartActiveCategories($categoryPixels){
        $catIds = array();
        $keys = array_keys($categoryPixels);
        $cart = edd_get_cart_contents();
        foreach ( $cart as $cart_item_key => $cart_item ) {
            $download_id   = (int) $cart_item['id'];
            $productCatIds = Facebook\HelpersCategory\getIntersectEddProduct($download_id,$keys);
            foreach ($productCatIds as $id) {
                if(!in_array($categoryPixels[$id],$catIds)) // disable duplicate pixel_id
                    $catIds[]=$id;
            }
        }
        return array_unique($catIds);
    }

    public function getEddCustomerTotals() {
        return PYS()->getEventsManager()->getEddCustomerTotals();
    }

    private function checkPurchase() {
        if(PYS()->getOption( 'edd_purchase_enabled' ) && edd_is_success_page()) {
            /**
             * When a payment gateway used, user lands to Payment Confirmation page first, which does automatic
             * redirect to Purchase Confirmation page. We filter Payment Confirmation to avoid double Purchase event.
             */
            if ( isset( $_GET['payment-confirmation'] ) ) {
                //@fixme: some users will not reach success page and event will not be fired
                //return;
            }
            $payment_key = getEddPaymentKey();
            $order_id = (int) edd_get_purchase_id_by_key( $payment_key );
            $status = edd_get_payment_status( $order_id );

            // pending payment status used because we can't fire event on IPN
            if ( strtolower( $status ) != 'publish' && strtolower( $status ) != 'pending' ) {
                return false;
            }


            if ( PYS()->getOption( 'edd_purchase_on_transaction' ) &&
                get_post_meta( $order_id, '_pys_purchase_event_fired', true ) ) {
                return false; // skip woo_purchase if this transaction was fired
            }
            update_post_meta( $order_id, '_pys_purchase_event_fired', true );

            return true;
        }
        return false;
    }
}

/**
 * @return EventsEdd
 */
function EventsEdd() {
    return EventsEdd::instance();
}

EventsEdd();