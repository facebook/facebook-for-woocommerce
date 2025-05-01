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
        $.post( facebook_for_woocommerce_whatsapp_templates.ajax_url, {
			action: 'wc_facebook_whatsapp_fetch_url_info',
			nonce:  facebook_for_woocommerce_whatsapp_templates.nonce
		}, function ( response ) {
            console.log(response);
            if ( response.success ) {
                var  business_id = response.data.business_id;
                var asset_id = response.data.waba_id;
                const EDIT_CONNECTION_URL = `https://business.facebook.com/latest/settings/whatsapp_account?business_id=${business_id}&selected_asset_id=${asset_id}&selected_asset_type=whatsapp-business-account&detail_view_tab=PARTNERS`;
                window.open(EDIT_CONNECTION_URL);
			}
		} );
    });
} );
