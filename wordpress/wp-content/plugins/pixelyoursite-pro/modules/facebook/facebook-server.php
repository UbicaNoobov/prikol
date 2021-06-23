<?php
namespace PixelYourSite;
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
/*
 * @see https://github.com/facebook/facebook-php-business-sdk
 * This class use for sending facebook server events
 */
require_once PYS_PATH . '/modules/facebook/facebook-server-async-task.php';
use PYS_PRO_GLOBAL\FacebookAds\Api;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\EventRequest;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\Event;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\CustomData;
use PYS_PRO_GLOBAL\FacebookAds\Object\ServerSide\Content;

class FacebookServer {

    private static $_instance;
    private $isEnabled;
    private $hours = ['00-01', '01-02', '02-03', '03-04', '04-05', '05-06', '06-07', '07-08',
        '08-09', '09-10', '10-11', '11-12', '12-13', '13-14', '14-15', '15-16', '16-17',
        '17-18', '18-19', '19-20', '20-21', '21-22', '22-23', '23-24'
    ];
    private $access_token;
    private $testCode;
    private $isDebug;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }


    public function __construct() {

        $this->isEnabled = Facebook()->enabled() && Facebook()->isServerApiEnabled();
        $this->isDebug = PYS()->getOption( 'debug_enabled' );

        if($this->isEnabled) {
            add_action( 'wp_ajax_pys_api_event',array($this,"catchAjaxEvent"));
            add_action( 'wp_ajax_nopriv_pys_api_event', array($this,"catchAjaxEvent"));
            add_action( 'woocommerce_remove_cart_item', array($this, 'trackRemoveFromCartEvent'), 10, 2);
            add_action( 'woocommerce_add_to_cart', array($this, 'trackAddToCartEvent'), 40, 4);
            // initialize the s2s event async task
            new FacebookAsyncTask();
        }
    }

    function trackAddToCartEvent($cart_item_key, $product_id, $quantity, $variation_id) {
        if(EventsWoo()->isReadyForFire("woo_add_to_cart_on_button_click")
            && PYS()->getOption('woo_add_to_cart_catch_method') == "add_cart_js")
        {
            if( !empty($variation_id)
                && $variation_id > 0
                && ( !Facebook()->getOption( 'woo_variable_as_simple' )
                    || ( Facebook()->getSlug() == "facebook"
                        && !Facebook\Helpers\isDefaultWooContentIdLogic()
                    )
                )
            ) {
                $_product_id = $variation_id;
            } else {
                $_product_id = $product_id;
            }

            if(isset($_COOKIE["pys_fb_event_id"])) {
                $eventID = json_decode(stripslashes($_COOKIE["pys_fb_event_id"]))->AddToCart;
            } else {
                $eventID = (new EventIdGenerator())->guidv4();
            }


            $event = EventsWoo()->getEvent("woo_add_to_cart_on_button_click");
            $event->args = ['productId' => $_product_id,'quantity' => $quantity];
            $isSuccess = Facebook()->addParamsToEvent( $event );
            if ( !$isSuccess ) return;

            // prepare event data
            if(isset($_COOKIE['pys_landing_page']))
                $event->addParams(['landing_page'=>$_COOKIE['pys_landing_page']]);
            $eventData = $event->getData();
            $eventData = EventsManager::filterEventParams($eventData,"woo");


            $serverEvent = FacebookServer()->createEvent($eventID,$eventData['name'],$eventData['params']);
            $this->addAsyncEvents(array(array("pixelIds" => $eventData['pixelIds'], "event" => $serverEvent )));
        }

    }

    /**
     * @param String $cart_item_key
     * @param \WC_Cart $cart
     */

    function trackRemoveFromCartEvent ($cart_item_key,$cart) {
        $eventId = 'woo_remove_from_cart';

        $url = $_SERVER['HTTP_HOST'].strtok($_SERVER["REQUEST_URI"], '?');
        $postId = url_to_postid($url);
        $cart_id = wc_get_page_id( 'cart' );

        if(PYS()->getOption( 'woo_remove_from_cart_enabled') && $cart_id==$postId) {
            $event = new SingleEvent("woo_remove_from_cart",EventTypes::$STATIC);
            $eventData = Facebook()->getEventData($eventId,$cart->get_cart_item($cart_item_key));
            $event->addParams($eventData['data']);
            $event->addParams(getStandardParams());
            if(isset($_COOKIE['pys_landing_page'])){
                $event->addParams(['landing_page'=>$_COOKIE['pys_landing_page']]);
            }

            unset($eventData['data']);
            $event->addPayload($eventData);

            if(isset($_COOKIE["pys_fb_event_id"])) {
                $eventID = json_decode(stripslashes($_COOKIE["pys_fb_event_id"]))->RemoveFromCart;
            } else {
                $eventID = (new EventIdGenerator())->guidv4();
            }

            $data = $event->getData();

            $serverEvent = FacebookServer()->createEvent($eventID,$data['name'],$data['params']);
            $this->addAsyncEvents(array(array("pixelIds" => $data['pixelIds'], "event" => $serverEvent )));
        }
    }

    /**
     * Send this events by FacebookAsyncTask
     * @param array $event List of raw event data
     */
    public function addAsyncEvents($events) {
            do_action('pys_send_server_event', $events);
    }

    /*
     * If server message is blocked by gprg or it dynamic
     * we send data by ajax request from js and send the same data like browser event
     */
    function catchAjaxEvent() {

        $event = $_POST['event'];
        $data = isset($_POST['data']) ? $_POST['data'] : array();
        $ids = $_POST['ids'];
        $eventID = $_POST['eventID'];
        $wooOrder = isset($_POST['woo_order']) ? $_POST['woo_order'] : null;
        $eddOrder = isset($_POST['edd_order']) ? $_POST['edd_order'] : null;



       if($event == "hCR") $event="CompleteRegistration"; // de mask completer registration event if it was hidden


        if(isset($data['contents'])) {
            if(is_array($data['contents'])) {
                $contents = json_decode(json_encode($data['contents']));
            } else {
                $contents = json_decode(stripslashes($data['contents']));
            }

            $data['contents']=$contents;
        }

        $event = $this->createEvent($eventID,$event,$data,$wooOrder,$eddOrder);

        if($event) {
            if(isset($_POST['url'])) {
                if(PYS()->getOption('enable_remove_source_url_params')) {
                    $list = explode("?",$_POST['url']);
                    if(is_array($list) && count($list) > 0) {
                        $url = $list[0];
                    } else {
                        $url = $_POST['url'];
                    }
                } else {
                    $url = $_POST['url'];
                }
                $event->setEventSourceUrl($url);
            }

            $this->sendEvent($ids,array($event));
        }
        wp_die();
    }


    /**
     * We prepare data from event for browser and create Facebook server event object
     * @param String $name Event name
     * @param $data  //data for event
     * @return bool|\FacebookAds\Object\ServerSide\Event
     */
    function createEvent($eventID,$name, $data,$wooOrder = null,$eddOrder = null) {

        if(!$eventID) return false;

        // create Server event
        $event = ServerEventHelper::newEvent($name,$eventID,$wooOrder,$eddOrder);

        $event->setEventTime(time());
        $event->setActionSource("website");
        // prepare data
        if(isset($data['contents']) && is_array($data['contents'])) {
            $contents = array();
            foreach ($data['contents'] as $c) {
                $c = (object) $c;
                $content = array();
                $content['product_id'] = $c->id;
                $content['quantity'] = $c->quantity;
               // $content['item_price'] = $c->item_price;
                $contents[] = new Content($content);
            }
            $data['contents'] = $contents;
        } else {
            $data['contents'] = array();
        }

        // prepare custom data
        $customData = $this->getCustomData($name,$data);

        $event->setCustomData($customData);

        return $event;
    }

    function getCustomData($name,$data) {
        $customData = new CustomData($data);
        $customProperties = array();

        if(PYS()->getOption( 'track_traffic_source' ))
            $customProperties['traffic_source'] = getTrafficSource();


        if(PYS()->getOption( 'track_utms' )) {
            $customProperties = array_merge($customProperties,getUtms());
        }

        if(isset($data['category_name'])) {
            $customData->setContentCategory($data['category_name']);
        }

        $custom_values = ['event_action','download_type','download_name','download_url','target_url','text','trigger','traffic_source','plugin','user_role','event_url','page_title',"post_type",'post_id','categories','tags','video_type',
            'video_id','video_title','event_trigger','link_type','tag_text',"URL",
            'form_id','form_class','form_submit_label','transactions_count','average_order',
            'shipping_cost','tax','total','shipping','coupon_used','post_category','landing_page'];
        foreach ($custom_values as $val) {
            if(isset($data[$val])){
                $customProperties[$val] = $data[$val];
            }
        }

        $customData->setCustomProperties($customProperties);
        return $customData;
    }

    /**
     * Send event for each pixel id
     * @param array $pixel_Ids array of facebook ids
     * @param array $events One Facebook event object
     */
    function sendEvent($pixel_Ids, $events) {

        if (empty($events)|| apply_filters('pys_disable_server_event_filter',false)) {
            return;
        }

        if(!$this->access_token) {
            $this->access_token = Facebook()->getApiTokens();
            $this->testCode = Facebook()->getApiTestCode();
        }

        foreach($pixel_Ids  as $pixel_Id) {

            if(empty($this->access_token[$pixel_Id])) continue;

            $api = Api::init(null, null, $this->access_token[$pixel_Id],false);

            $request = (new EventRequest($pixel_Id))->setEvents($events);
            $request->setPartnerAgent("dvpixelyoursite");
            if(!empty($this->testCode[$pixel_Id]))
                $request->setTestEventCode($this->testCode[$pixel_Id]);

            if($this->isDebug) error_log("send fb api request ".print_r($request,true));

            try{
                $response = $request->execute();
            } catch (\Exception   $e) {
                error_log("error send Fb API request ".$e->getMessage());
            }

            if($this->isDebug && isset($response))  error_log("fb api response ".print_r($response,true));
        }
    }

}

/**
 * @return FacebookServer
 */
function FacebookServer() {
    return FacebookServer::instance();
}

FacebookServer();





