/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // handle the whatsapp connect button click should open hosted ES flow
	$( '#woocommerce-whatsapp-connection' ).click( function( event ) {
        // dummy values for app id and config id, will be replaced in upcoming diffs
        const APP_ID = '18402284156271';
        const CONFIG_ID = '17502287264684';
        const HOSTED_ES_URL = `https://business.facebook.com/messaging/whatsapp/onboard/?app_id=${APP_ID}&config_id=${CONFIG_ID}`;
        window.open( HOSTED_ES_URL);
    });

} );
