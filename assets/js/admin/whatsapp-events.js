/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // handle the manage event button click for order confirmation
    $('#woocommerce-whatsapp-manage-order-confirmation').click(function(event) {
        $.post( facebook_for_woocommerce_whatsapp_events.ajax_url, {
			action: 'wc_facebook_whatsapp_fetch_library_template_info',
			nonce:  facebook_for_woocommerce_whatsapp_events.nonce
		}, function ( response ) {
            // TODO: update UI components to prefill library template content
            console.log(response);
		} );
    });
} );