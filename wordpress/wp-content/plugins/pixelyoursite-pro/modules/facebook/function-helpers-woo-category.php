<?php

namespace PixelYourSite\Facebook\HelpersCategory;
use PixelYourSite;
use function PixelYourSite\wooGetOrderIdFromRequest;
use function PixelYourSite\wooIsRequestContainOrderId;


function getWooCategoryPixelIdsForPageView($pixelIds) {

    if(!PixelYourSite\isWooCommerceActive()) {
        return $pixelIds;
    }

    $categoryPixels = (array)PixelYourSite\Facebook()->getCategoryPixelIDs();
    $keys = array_keys($categoryPixels);

    if(is_product_category($keys)) {
        $currentCatId = get_queried_object_id();
        if(isset($categoryPixels[$currentCatId]))
            $pixelIds[] = $categoryPixels[$currentCatId];
    } elseif (is_product()) {
        /* @var \WC_Product $product*/
        $categoryPixels = (array)PixelYourSite\Facebook()->getOption("category_pixel_ids");
        $product = wc_get_product();
        $keys = array_keys($categoryPixels);
        $productIds = $product->get_category_ids();
        $catIds = array_intersect($productIds,$keys);
        if(count($catIds) > 0){
            foreach ($catIds as $id) {
                if(!in_array($categoryPixels[$id],$pixelIds))
                    $pixelIds[] = $categoryPixels[$id];
            }
        }
    } elseif (is_order_received_page() && wooIsRequestContainOrderId()) {
        $categoryPixels = (array)PixelYourSite\Facebook()->getOption("category_pixel_ids");
        $order_id = wooGetOrderIdFromRequest();
        $order = new \WC_Order( $order_id );

        foreach ( $order->get_items( 'line_item' ) as $line_item ) {
            $product_id  = $line_item['product_id'];
            $_product = wc_get_product($product_id);
            if(!$_product) continue;

            $keys = array_keys($categoryPixels);
            $productIds = $_product->get_category_ids();
            $catIds = array_intersect($productIds,$keys);

            foreach ($catIds as $id) {
                if(!in_array($categoryPixels[$id],$pixelIds)) // disable duplicate pixel_id
                    $pixelIds[] = $categoryPixels[$id];
            }
        }
    } elseif (is_checkout() && ! is_wc_endpoint_url()) {
        $categoryPixels = (array)PixelYourSite\Facebook()->getOption("category_pixel_ids");
        foreach ( WC()->cart->cart_contents as $cart_item_key => $line_item ) {
            $product_id  = $line_item['product_id'];
            $_product = wc_get_product($product_id);
            if(!$_product) continue;

            $keys = array_keys($categoryPixels);
            $productIds = $_product->get_category_ids();
            $catIds = array_intersect($productIds,$keys);

            foreach ($catIds as $id) {
                if(!in_array($categoryPixels[$id],$pixelIds)) // disable duplicate pixel_id
                    $pixelIds[] = $categoryPixels[$id];
            }
        }
    }
    return $pixelIds;
}

/**
 * Add category piselId for event
 * @param array $pixelIds
 * @return array
 */
function getEddCategoryPixelIdsForPageView($pixelIds) {
    if(!PixelYourSite\isEddActive()) {
        return $pixelIds;
    }
    $categoryPixels = (array)PixelYourSite\Facebook()->getEddCategoryPixelIDs();
    $keys = array_keys($categoryPixels);

    if(is_tax( 'download_category', $keys )) {
        $currentCatId = get_queried_object_id();
        if(isset($categoryPixels[$currentCatId]))
            $pixelIds[] = $categoryPixels[$currentCatId];
    } elseif (is_singular( 'download' ) ) {
        global $post;
        $catIds = getIntersectEddProduct($post->ID,$keys);
        if(count($catIds) > 0){
            foreach ($catIds as $id) {
                if(!in_array($categoryPixels[$id],$pixelIds))
                    $pixelIds[] = $categoryPixels[$id];
            }
        }
    } elseif (edd_is_checkout()) {
        $cart = edd_get_cart_contents();

        foreach ( $cart as $cart_item_key => $cart_item ) {
            $download_id   = (int) $cart_item['id'];
            $catIds = getIntersectEddProduct($download_id,$keys);
            foreach ($catIds as $id) {
                if(!in_array($categoryPixels[$id],$pixelIds)) // disable duplicate pixel_id
                    $pixelIds[] = $categoryPixels[$id];
            }
        }
    } elseif (edd_is_success_page()) {
        $cart = edd_get_payment_meta_cart_details( edd_get_purchase_id_by_key( PixelYourSite\getEddPaymentKey() ), true );
        foreach ( $cart as $cart_item_key => $cart_item ) {
            $download_id   = (int) $cart_item['id'];
            $catIds = getIntersectEddProduct($download_id,$keys);
            foreach ($catIds as $id) {
                if(!in_array($categoryPixels[$id],$pixelIds)) // disable duplicate pixel_id
                    $pixelIds[] = $categoryPixels[$id];
            }
        }
    }
    return $pixelIds;
}

function getEddCategoryPixelIdsForProduct($postId,$pixelIds) {
    $categoryPixels = (array)PixelYourSite\Facebook()->getEddCategoryPixelIDs();
    if(count($categoryPixels) == 0) return $pixelIds;

    $keys = array_keys($categoryPixels);
    $catIds = getIntersectEddProduct($postId,$keys);
    if(count($catIds) > 0){
        foreach ($catIds as $id) {
            if(!in_array($categoryPixels[$id],$pixelIds))
                $pixelIds[] = $categoryPixels[$id];
        }
    }
    return $pixelIds;
}

function getEddCategoryPixelIdsForCategory($catId,$pixelIds) {
    $categoryPixels = (array)PixelYourSite\Facebook()->getEddCategoryPixelIDs();
    if(count($categoryPixels) == 0) return $pixelIds;

    $pixelIds[] = $categoryPixels[$catId];
    return $pixelIds;
}


function getEddCategoryPixelIdsForPurchase() {
    $pixelIds = array();

    $categoryPixels = (array)PixelYourSite\Facebook()->getEddCategoryPixelIDs();
    if(count($categoryPixels) == 0) return $pixelIds;

    $keys = array_keys($categoryPixels);
    $cart = edd_get_payment_meta_cart_details( edd_get_purchase_id_by_key( PixelYourSite\getEddPaymentKey() ), true );
    foreach ( $cart as $cart_item_key => $cart_item ) {
        $download_id   = (int) $cart_item['id'];
        $catIds = getIntersectEddProduct($download_id,$keys);
        foreach ($catIds as $id) {
            if(!in_array($categoryPixels[$id],$pixelIds)) // disable duplicate pixel_id
                $pixelIds[] = $categoryPixels[$id];
        }
    }
    return $pixelIds;
}


function getIntersectEddProduct($productId,$keys) {
    $terms = get_the_terms( $productId , 'download_category' );
    if(!$terms) return array();
    $productIds = array();

    foreach ($terms as $term) {
        $productIds[]=$term->term_id;
    }

    return array_intersect($productIds,$keys);
}