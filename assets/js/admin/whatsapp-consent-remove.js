/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // handle the whatsapp consent collect button click remove action.
	$( '#wc-whatsapp-collect-consent-remove' ).click( function( event ) {

        $.post( facebook_for_woocommerce_whatsapp_consent.ajax_url, {
			action: 'wc_facebook_whatsapp_consent_collection_disable',
			nonce:  facebook_for_woocommerce_whatsapp_consent_disable.nonce
		}, function ( response ) {

            if ( response.success ) {
				console.log( 'success', response ); 
			}
		} );

    });

} );
