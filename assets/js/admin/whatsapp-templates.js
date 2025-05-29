/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // handle whatsapp view insights link click should open template insights in WhatsSpp Manager
	$( '#woocommerce-whatsapp-manager-insights' ).click( function( event ) {
        $.post( facebook_for_woocommerce_whatsapp_templates.ajax_url, {
			action: 'wc_facebook_whatsapp_fetch_url_info',
			nonce:  facebook_for_woocommerce_whatsapp_templates.nonce
		}, function ( response ) {
            console.log(response);
            if ( response.success ) {
                console.log( 'Whatsapp Template Insights Info was fetched successfully', response );
                var  business_id = response.data.business_id;
                var asset_id = response.data.waba_id;
                const MANAGE_TEMPLATES_URL = `https://business.facebook.com/latest/whatsapp_manager/message_templates?business_id=${business_id}&asset_id=${asset_id}`;                
                window.open(MANAGE_TEMPLATES_URL);
			}
            else {
                console.log( 'Whatsapp Template Insights Info fetch call failed', response );
            }
		} );
    });
} );
