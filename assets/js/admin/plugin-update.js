/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */


(function( $ ) {

    console.log("✅ JavaScript loaded inside the Marketing tab!");

    if ($("#woocommerce-marketing").length) {
       
    }

    
    // Opt out sync controls
    const optOutOfSyncButton = $('#opt_out_of_sync_button');
    optOutOfSyncButton.click(function(event) {
        console.log("Somethign");
        event.preventDefault();
        $.post( facebook_for_woocommerce_settings_sync.ajax_url, {
            action: 'wc_facebook_opt_out_of_sync',
            nonce:  facebook_for_woocommerce_settings_sync.opt_out_of_sync,
        }, function (response){
            console.log(response);
                data = typeof response === "string" ? JSON.parse(response) : response;
                console.log("Success:", data); 
        }).fail(function() {
            console.error("Error Code:", xhr.status);
            console.error("Error Message:", xhr.responseText);
        });
    })
})( jQuery );
