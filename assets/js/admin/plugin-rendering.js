/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */


jQuery( document ).ready( function( $ ) {
    //Setting up opt out modal
    let modal;

    $(document).on('click', '#modal_opt_out_button', function(e) {
        e.preventDefault();
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_facebook_opt_out_of_sync',
            nonce:  facebook_for_woocommerce_plugin_update.opt_out_of_sync,
        }, function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if(data.success){
                $('#opt_out_banner').hide();
                $('#opted_our_successfullly_banner').show();
                modal.remove();
            }   
        }).fail(function(xhr) {
            console.error("Error Code:", xhr.status);
            console.error("Error Message:", xhr.responseText);
            modal.remove();
        });
    });

    /**
     * Banner dismissed callback
     */
    $(document).on('click','#opt_out_banner .notice-dismiss, #opted_our_successfullly_banner .notice-dismiss', function (e) {
        e.preventDefault();
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_banner_close_action',
            nonce:  facebook_for_woocommerce_plugin_update.banner_close,
        }, function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if(data.success){
                // No success condition
            }   
        }).fail(function(xhr) {
            console.error("Error Code:", xhr.status);
            console.error("Error Message:", xhr.responseText);
            modal.remove();
        });
    });

    // Opt out sync controls
     $('.opt_out_of_sync_button').on('click', function(event) {
        event.preventDefault();
        modal = new $.WCBackboneModal.View({
            target: 'facebook-for-woocommerce-modal',
            string: {
                message: facebook_for_woocommerce_plugin_update.opt_out_confirmation_message,
                buttons: facebook_for_woocommerce_plugin_update.opt_out_confirmation_buttons
            }
        });
    })
});

