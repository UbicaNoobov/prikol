<?php
use PixelYourSite\GA\Helpers;

function getSearchEventDataV4() {

    if ( ! PixelYourSite\GA()->getOption( 'search_event_enabled' ) ) {
        return false;
    }
    $params = array();
    $params['search'] = empty( $_GET['s'] ) ? null : $_GET['s'];

    return array(
        'name'  => 'search',
        'data'  => $params,
    );

}

function getCompleteRegistrationEventParamsV4() {

    return array(
        'name' => 'sign_up',
        'data' => array(
            'content_name'    => get_the_title(),
            'event_url'       => \PixelYourSite\getCurrentPageUrl(true),
            'method'          => \PixelYourSite\getUserRoles(),
            'non_interaction' => PixelYourSite\GA()->getOption( 'complete_registration_event_non_interactive' ),
        ),
    );
}