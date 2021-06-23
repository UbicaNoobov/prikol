<?php
namespace PixelYourSite;

class EnrichOrder {
    private static $_instance;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function init() {
        //woo
        if(PYS()->getOption("woo_enabled_save_data_to_orders")) {
            add_filter( 'woocommerce_checkout_fields' , array($this,'woo_add_checkout_fields') );
            add_filter( 'woocommerce_form_field_hidden' , array($this,'woocommerce_form_field_hidden'),10,4 );
            add_action( 'woocommerce_checkout_update_order_meta',array($this,'woo_save_checkout_fields'),10, 2);

            add_action( 'woocommerce_analytics_update_order_stats',array($this,'woo_update_analytics'));

            if(PYS()->getOption("woo_add_enrich_to_order_details")) {
                add_action( 'add_meta_boxes', array($this,'woo_add_order_meta_boxes') );
            }

            if(PYS()->getOption("woo_add_enrich_to_admin_email")) {
                add_action( 'woocommerce_email_customer_details', array($this,'woo_add_enrich_to_admin_email'),80,4 );
            }

        }

        // edd
        if(PYS()->getOption("edd_enabled_save_data_to_orders")) {

            add_action('edd_checkout_form_top', array($this, 'add_edd_checkout_fields'));
            add_filter('edd_payment_meta', array($this, 'edd_save_checkout_fields'),10,2);

            if(PYS()->getOption("woo_add_enrich_to_order_details")) {
                add_action('edd_view_order_details_main_after', array($this, 'add_edd_order_details'));
            }
        }
    }

    function woocommerce_form_field_hidden( $field, $key, $args, $value ) {
        if(isset($args["class"]) && count($args["class"]) > 0 && $args["class"][0] == "pys_enrich_data" ) {
             return str_replace("data-priority","style='display:none'",$field);
        }
        return $field;
    }

    function woo_add_checkout_fields( $fields ) {
        $fields['billing']['pys_empty']['type'] = 'hidden'; // plugin Checkout Manager for WooCommerce duplicate first hidden filed
        $fields['billing']['pys_empty']['class'] = array('pys_enrich_data');

        $fields['billing']['pys_landing']['type'] = 'hidden';
        $fields['billing']['pys_landing']['class'] = array('pys_enrich_data');

        $fields['billing']['pys_source']['type'] = 'hidden';
        $fields['billing']['pys_source']['class'] = array('pys_enrich_data');

        $fields['billing']['pys_utm']['type'] = 'hidden';
        $fields['billing']['pys_utm']['class'] = array('pys_enrich_data');

        $fields['billing']['pys_browser_time']['type'] = 'hidden';
        $fields['billing']['pys_browser_time']['class'] = array('pys_enrich_data');
        return $fields;
    }

    function woo_update_analytics($orderId) {
        if(!metadata_exists( 'post', $orderId, 'pys_enrich_data_analytics' )) {
            $totals = getWooUserStat($orderId);
            if($totals['orders_count'] == 0) {
                $totals = array(
                    'orders_count' => 'Guest order',
                    'avg_order_value' => 'Guest order',
                    'ltv' => 'Guest order',
                );
            }
            update_post_meta($orderId,"pys_enrich_data_analytics",$totals);
        }

    }

    function woo_save_checkout_fields($order_id, $data) {

        $pysData = [];
        $pysData['pys_landing'] = isset($data['pys_landing']) ? sanitize_text_field($data['pys_landing']) : "";
        $pysData['pys_source'] = isset($data['pys_source']) ? sanitize_text_field($data['pys_source']) : "";
        $pysData['pys_utm'] = isset($data['pys_utm']) ? sanitize_text_field($data['pys_utm']) : "";
        $pysData['pys_browser_time'] = isset($data['pys_browser_time']) ? sanitize_text_field($data['pys_browser_time']) : "";

        update_post_meta($order_id,"pys_enrich_data",$pysData);
    }

    function woo_add_order_meta_boxes () {
        add_meta_box( 'pys_enrich_fields_woo', __('PixelYourSite Pro','pixelyoursite'),
            array($this,"woo_render_order_fields"), 'shop_order');
    }

    /**
     * @param \WC_Order$order
     * @param $sent_to_admin
     * @param $plain_text
     * @param $email
     */

    function woo_add_enrich_to_admin_email($order, $sent_to_admin, $plain_text, $email) {
        if($sent_to_admin) {
            $orderId = $order->get_id();
            echo "<h2>". __('PixelYourSite Professional','pixelyoursite')."</h2>";
            include 'views/html-order-meta-box.php';
            echo "Your clients don't see this information, we only send it in the New Order email that you get. You can remove it by opening the PixelYourSite plugin's main page. Look for \"Add enriched order's data to WooCommerce's default \"New Order\" email.\" turn it OFF, and Save.</br>";
        }

    }

    function woo_render_order_fields() {
        global  $post;
        $orderId = $post->ID;
        include 'views/html-order-meta-box.php';
    }


    function add_edd_checkout_fields() {
        $fields = ['pys_landing','pys_source','pys_utm','pys_browser_time'];
        foreach ($fields as $field) : ?>
            <div id="<?=$field?>_field">
                <input type="hidden" name="<?=$field?>" />
            </div>
        <?php endforeach;
    }

    function edd_save_checkout_fields( $payment_meta ,$init_payment_data) {

        if ( 0 !== did_action('edd_pre_process_purchase') ) {
            $pysData = [];

            if(get_current_user_id()) {
                $totals = getEddCustomerTotals();
            } else {
                $totals = getEddCustomerTotalsByEmail($payment_meta['email']);
                if($totals['orders_count'] == 0) {
                    $totals = array(
                        'orders_count' => 'Guest order',
                        'avg_order_value' => 'Guest order',
                        'ltv' => 'Guest order',
                    );
                }
            }


            $pysData['pys_landing'] = isset($_POST['pys_landing']) ? sanitize_text_field($_POST['pys_landing']) : "";
            $pysData['pys_source'] = isset($_POST['pys_source']) ? sanitize_text_field($_POST['pys_source']) : "";
            $pysData['pys_utm'] = isset($_POST['pys_utm']) ? sanitize_text_field($_POST['pys_utm']) : "";
            $pysData['pys_browser_time'] = isset($_POST['pys_browser_time']) ? sanitize_text_field($_POST['pys_browser_time']) : "";

            $pysData = array_merge($pysData,$totals);
            $payment_meta['pys_enrich_data'] = $pysData;
        }
        return $payment_meta;
    }


    function add_edd_order_details($payment_id) {
        include 'views/html-edd-order-box.php';
    }
}

/**
 * @return EnrichOrder
 */
function EnrichOrder() {
    return EnrichOrder::instance();
}

EnrichOrder();

