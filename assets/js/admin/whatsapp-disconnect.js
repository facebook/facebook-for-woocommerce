/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // handle whatsapp disconnect widget edit link click should open business manager with whatsapp asset selected
	$( '#wc-whatsapp-disconnect-edit' ).click( function( event ) {
        $.post( facebook_for_woocommerce_whatsapp_disconnect.ajax_url, {
			action: 'wc_facebook_whatsapp_fetch_url_info',
			nonce:  facebook_for_woocommerce_whatsapp_disconnect.nonce
		}, function ( response ) {
            console.log(response);
            if ( response.success ) {
                var  business_id = response.data.business_id;
                var asset_id = response.data.waba_id;
                const WHATSAPP_MANAGER_URL = `https://business.facebook.com/latest/whatsapp_manager/phone_numbers/?asset_id=${asset_id}&business_id=${business_id}`;
                window.open(WHATSAPP_MANAGER_URL);
			}
		} );
    });

    // handle whatsapp disconnect button click should disconnect whatsapp from woocommerce
    $( '#wc-whatsapp-disconnect-button' ).click( function( event ) {
        $.post( facebook_for_woocommerce_whatsapp_disconnect.ajax_url, {
			action: 'wc_facebook_disconnect_whatsapp',
			nonce:  facebook_for_woocommerce_whatsapp_disconnect.nonce
		}, function ( response ) {
            if ( response.success ) {
                // TODO: redirect to onboarding view
                console.log("Success!!!",response);
			} else {
                console.log("Disconnect Failure!!!",response);
            }
		} );
    });
} );
