/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
	var $consentCollectSuccess = $('#wc-fb-whatsapp-consent-collection-success');
  	var $consentCollectInprogress = $('#wc-fb-whatsapp-consent-collection-inprogress');
  	var $consentCollectNotstarted = $('#wc-fb-whatsapp-consent-collection-notstarted');
	if (facebook_for_woocommerce_whatsapp_consent.whatsapp_onboarding_complete) {
		if (facebook_for_woocommerce_whatsapp_consent.consent_collection_enabled) {
			$consentCollectSuccess.show();
			$consentCollectInprogress.hide();
			$consentCollectNotstarted.hide();
		} else {
			$consentCollectSuccess.hide();
			$consentCollectInprogress.show();
			$consentCollectNotstarted.hide();
		}
    } else {
			$consentCollectSuccess.hide();
			$consentCollectInprogress.hide();
			$consentCollectNotstarted.show();
    }

    // handle the whatsapp consent collect button click should save setting to wp_options table
	$( '#wc-whatsapp-collect-consent' ).click( function( event ) {

        $.post( facebook_for_woocommerce_whatsapp_consent.ajax_url, {
			action: 'wc_facebook_whatsapp_consent_collection_enable',
			nonce:  facebook_for_woocommerce_whatsapp_consent.nonce
		}, function ( response ) {
            if ( response.success ) {
				console.log( 'success', response );
				// update the progress for collect consent step
				$consentCollectSuccess.show();
				$consentCollectInprogress.hide();
				$consentCollectNotstarted.hide();
				// update the progress of billing step
				$('#wc-fb-whatsapp-billing-inprogress').show();
                $('#wc-fb-whatsapp-billing-notstarted').hide();

			}
		} );

    });

} );
