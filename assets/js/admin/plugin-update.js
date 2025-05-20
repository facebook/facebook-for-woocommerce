/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */


jQuery( document ).ready( function( $ ) {
    
    // Opt out sync controls
     $('#opt_out_of_sync_button').on('click', function(event) {
        event.preventDefault();
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_facebook_opt_out_of_sync',
            nonce:  facebook_for_woocommerce_plugin_update.opt_out_of_sync,
        }, function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if(data.success){
                $('#opt_out_banner').hide();
            }
        }).fail(function() {
            console.error("Error Code:", xhr.status);
            console.error("Error Message:", xhr.responseText);
        });
    })

    // Opt out sync controls
    $('#opt_in_to_automatic_sync_button').on('click', function(event) {
        event.preventDefault();
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_facebook_opt_back_in_for_sync',
            nonce:  facebook_for_woocommerce_plugin_update.opt_out_of_sync,
        }, function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if(data.success){
                $('#opt_in_banner').hide();
            }
        }).fail(function() {
            console.error("Error Code:", xhr.status);
            console.error("Error Message:", xhr.responseText);
        });
    })
});
