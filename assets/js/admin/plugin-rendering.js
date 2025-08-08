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
            modal.remove();
        });
    });

    /**
     * Banner dismissed callback
     */
    $(document).on('click','.plugin_updated_successfully .notice-dismiss', function (e) {
        e.preventDefault();
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_banner_post_update_close_action',
            nonce:  facebook_for_woocommerce_plugin_update.banner_close,
        }, function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if(data.success){
                // No success condition
            }   
        }).fail(function(xhr) {
            modal.remove();
        });
    });

    /**
     * Banner dismissed callback
     * but when master sync is off
     */
    $(document).on('click','#plugin_updated_successfully_but_master_sync_off .notice-dismiss', function (e) {
        e.preventDefault();
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_banner_post_update__master_sync_off_close_action',
            nonce:  facebook_for_woocommerce_plugin_update.banner_close,
        }, function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if(data.success){
                // No success condition
            }   
        }).fail(function(xhr) {
            modal.remove();
        });
    });

    /**
     * Banner dismissed callback
     */
       $(document).on('click','.plugin_updated_successfully .notice-dismiss', function (e) {
        e.preventDefault();
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_banner_post_update_close_action',
            nonce:  facebook_for_woocommerce_plugin_update.banner_close,
        }, function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if(data.success){
                // No success condition
            }   
        }).fail(function(xhr) {
            modal.remove();
        });
    });

    /**
     * Banner dismissed callback
     * but when master sync is off
     */
    $(document).on('click','#plugin_updated_successfully_but_master_sync_off .notice-dismiss', function (e) {
        e.preventDefault();
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_banner_post_update__master_sync_off_close_action',
            nonce:  facebook_for_woocommerce_plugin_update.banner_close,
        }, function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if(data.success){
                // No success condition
            }   
        }).fail(function(xhr) {
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

    // Sync all products 
    $('#sync_all_products').on('click',function(event) {
        event.preventDefault();
        let context = $(this);
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_facebook_sync_all_products',   
            nonce:  facebook_for_woocommerce_plugin_update.sync_back_in,
        } ,function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if( data.success ) {
                $('#plugin_updated_successfully_but_master_sync_off').hide();
                $('#plugin_updated_successfully_after_user_opts_in').show();
            }
        }).fail(function(xhr) {
            // No fail conditon
        });
    });

    // Sync all products 
    $('#sync_all_products').on('click',function(event) {
        event.preventDefault();
        let context = $(this);
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_facebook_sync_all_products',   
            nonce:  facebook_for_woocommerce_plugin_update.sync_back_in,
        } ,function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if( data.success ) {
                $('#plugin_updated_successfully_but_master_sync_off').hide();
                $('#plugin_updated_successfully_after_user_opts_in').show();
            }
        }).fail(function(xhr) {
            // No fail condition
        });
    });

	// Product set banner dismissed callback 
    $('.fb-product-set-banner').on('click', '.notice-dismiss', function(event) {
		event.preventDefault();
        $.post( facebook_for_woocommerce_plugin_update.ajax_url, {
            action: 'wc_facebook_product_set_banner_closed',
            nonce:  facebook_for_woocommerce_plugin_update.product_set_banner_closed_nonce,
        }, function (response){
            data = typeof response === "string" ? JSON.parse(response) : response;
            if(data.success){
                // No success condition
            }   
        }).fail(function(xhr) {
            // No fail conditon
        });
    });
});

