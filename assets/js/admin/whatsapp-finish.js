/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // handle the whatsapp finish button click
	$( '#wc-whatsapp-onboarding-finish' ).click( function( event ) {
        // call the connect API to create configs and check payment
        $.post( facebook_for_woocommerce_whatsapp_finish.ajax_url, {
			action: 'wc_facebook_whatsapp_finish_onboarding',
			nonce:  facebook_for_woocommerce_whatsapp_finish.nonce
		}, function ( response ) {
            if ( response.success ) {
                // If success, redirect to utility settings page
                 let url = new URL(window.location.href);
                 let params = new URLSearchParams(url.search);
                 params.set('view', 'utility_settings');
                 url.search = params.toString();
                 window.location.href = url.toString();
			} else {
                // TODO: Handle error show error banner in UI
                console.log(response);
            }
		} );
    });

} );
