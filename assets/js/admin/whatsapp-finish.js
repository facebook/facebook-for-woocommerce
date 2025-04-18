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
        // TODO: call the connect API to create configs and check payment
        // If error, show banner
        // If success, redirect to utility settings page
        let url = window.location.href;
            if (url.indexOf('?') > -1){
               url += '&view=utility_settings'
            } else {
               url += '?view=utility_settings'
            }
            window.location.href = url;
        });

} );
