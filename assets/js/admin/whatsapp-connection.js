/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    var $connectSuccess = $('#wc-fb-whatsapp-connect-success');
    var $connectInProgress = $('#wc-fb-whatsapp-connect-inprogress');
    var $connectSubcontent = $('#wc-fb-whatsapp-onboarding-subcontent');
    var $connectButtonWrapper = $('#wc-fb-whatsapp-onboarding-button-wrapper');
    if (facebook_for_woocommerce_whatsapp_onboarding_progress.whatsapp_onboarding_complete) {
        $connectSuccess.show();
        $connectInProgress.hide();
        $connectSubcontent.hide();
        $connectButtonWrapper.hide();
    } else {
        $connectSuccess.hide();
        $connectInProgress.show();
    }

    // handle the whatsapp connect button click should open hosted ES flow
	$( '#woocommerce-whatsapp-connection' ).click( function( event ) {
        const APP_ID = '474166926521348'; // WOO_COMMERCE_APP_ID
        const CONFIG_ID = '1237758981048330'; // WOO_COMMERCE_WHATSAPP_CONFIG_ID
        const HOSTED_ES_URL = `https://business.facebook.com/messaging/whatsapp/onboard/?app_id=${APP_ID}&config_id=${CONFIG_ID}`;
        window.open( HOSTED_ES_URL);
        updateProgress(0,1800000); // retry for 30 minutes
    });

    function updateProgress(retryCount = 0, pollingTimeout = 1800000) {
        $.post( facebook_for_woocommerce_whatsapp_onboarding_progress.ajax_url, {
			action: 'wc_facebook_whatsapp_onboarding_progress_check',
			nonce:  facebook_for_woocommerce_whatsapp_onboarding_progress.nonce
		}, function ( response ) {

            // check if the response is success (i.e. onboarding is completed)
            if ( response.success ) {
				console.log( 'Whatsapp Connection is Complete', response );
                // update the progress for connect whatsapp step
                $connectInProgress.remove();
                $connectSuccess.show();
                 // collapse whatsapp onboarding step subcontect and button on success
                $connectSubcontent.hide();
                $connectButtonWrapper.hide();
                // update the progress for collect consent step and show button and subcontent
                $('#wc-fb-whatsapp-consent-collection-inprogress').show();
                $('#wc-fb-whatsapp-consent-collection-notstarted').hide();
                $('#wc-fb-whatsapp-consent-subcontent').show();
	            $('#wc-fb-whatsapp-consent-button-wrapper').show();

			} else {
                console.log('Whatsapp connection is not complete. Checking again in 5 seconds:', response, ', retry attempt:', retryCount, 'pollingTimeout', pollingTimeout);
                if(retryCount >= pollingTimeout) {
                    console.log('Max retries reached. Aborting.');
                    return;
                }
                setTimeout( function() { updateProgress(retryCount + 1, pollingTimeout); }, 5000 );
            }
		} );

    }

} );
