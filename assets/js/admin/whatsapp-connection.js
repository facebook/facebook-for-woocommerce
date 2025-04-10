/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // handle the whatsapp connect button click should open hosted ES flow
	$( '#woocommerce-whatsapp-connection' ).click( function( event ) {
        const APP_ID = '474166926521348'; // WOO_COMMERCE_APP_ID
        const CONFIG_ID = '1237758981048330'; // WOO_COMMERCE_WHATSAPP_CONFIG_ID
        const HOSTED_ES_URL = `https://business.facebook.com/messaging/whatsapp/onboard/?app_id=${APP_ID}&config_id=${CONFIG_ID}`;
        window.open( HOSTED_ES_URL);
        updateProgress(0,1800000); // retry for 30 minutes
    });

    function updateProgress(retryCount = 0, maxRetries = 1800000) {
        $.post( facebook_for_woocommerce_whatsapp_onboarding_progress.ajax_url, {
			action: 'wc_facebook_whatsapp_onboarding_progress_check',
			nonce:  facebook_for_woocommerce_whatsapp_onboarding_progress.nonce
		}, function ( response ) {

            // check if the response is success (i.e. onboarding is completed)
            if ( response.success ) {
                // TODO: if success, update the UI with the onboarding succeeded
				console.log( 'success', response );
			} else {
                console.log('Failure. Checking again in 1 second:', response, ', retry attempt:', retryCount, 'maxRetries', maxRetries);
                if(retryCount >= maxRetries) {
                    console.log('Max retries reached. Aborting.');
                    return;
                }
                setTimeout( updateProgress(retryCount+1, maxRetries), 1000 );
            }
		} );

    }

} );
