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
                $('#opt_out_banner_update_available').hide();

                $('#opt_in_banner_update_available').show();
                $('#opt_in_banner').show();
                      
                modal.remove();
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

    $('.upgrade_plugin_button').on('click',function(event) {
        event.preventDefault();
        let context = $(this);
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_facebook_upgrade_plugin',   
            nonce:  facebook_for_woocommerce_plugin_update.upgrade_plugin,
        } ,function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if( data.success ) {
              location.reload();
            }
            else{
                context.text('Failed to update plugin !')
                context.css("color", "red");
                context.css('border', '2px solid red');
                context.prop('disabled', true);
            }
        }).fail(function(xhr) {
            console.error("Error Code:", xhr.status);
            console.error("Error Message:", xhr.responseText);
        });
    });

    $('#sync_all_products').on('click',function(event) {
        event.preventDefault();
        let context = $(this);
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_facebook_sync_all_products',   
            nonce:  facebook_for_woocommerce_plugin_update.sync_back_in,
        } ,function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if( data.success ) {
                $('#opted_out_banner_updated_plugin').hide();
                $('#opted_in_banner_updated_plugin').show();
            }
            else{
                context.text('Failed to enable sync !')
                context.css("color", "red");
                context.css('border', '2px solid red');
                context.prop('disabled', true);
            }
        }).fail(function(xhr) {
            console.error("Error Code:", xhr.status);
            console.error("Error Message:", xhr.responseText);
        });
    });

});

