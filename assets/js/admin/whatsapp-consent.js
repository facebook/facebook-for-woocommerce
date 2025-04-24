/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
	var $connectSuccess = $('#wc-fb-whatsapp-consent-collection-success');
  	var $connectInprogress = $('#wc-fb-whatsapp-consent-collection-inprogress');
  	var $connectNotstarted = $('#wc-fb-whatsapp-consent-collection-notstarted');
	if (facebook_for_woocommerce_whatsapp_consent.whatsapp_onboarding_complete) {
		if (facebook_for_woocommerce_whatsapp_consent.consent_collection_enabled) {
			$connectSuccess.show();
			$connectInprogress.hide();
			$connectNotstarted.hide();
		} else {
			$connectSuccess.hide();
			$connectInprogress.show();
			$connectNotstarted.hide();
		}
    } else {
			$connectSuccess.hide();
			$connectInprogress.hide();
			$connectNotstarted.show();
    }

    // handle the whatsapp consent collect button click should save setting to wp_options table
	$( '#wc-whatsapp-collect-consent' ).click( function( event ) {

        $.post( facebook_for_woocommerce_whatsapp_consent.ajax_url, {
			action: 'wc_facebook_whatsapp_consent_collection_enable',
			nonce:  facebook_for_woocommerce_whatsapp_consent.nonce
		}, function ( response ) {
            if ( response.success ) {
				console.log( 'success', response );
				$connectSuccess.show();
				$connectInprogress.hide();
				$connectNotstarted.hide();
			}
		} );

    });

} );
