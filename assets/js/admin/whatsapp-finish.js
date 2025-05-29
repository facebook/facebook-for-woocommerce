/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    var doneBtn = $('#wc-whatsapp-onboarding-finish');

    // handle the whatsapp finish button click
	doneBtn.click( function( event ) {
        var spinnerState = $('#wc-whatsapp-onboarding-finish-loading-state');
        doneBtn.addClass('fbwa-button-disabled');
        spinnerState.show();
        // call the connect API to create configs and check payment
        $.post( facebook_for_woocommerce_whatsapp_finish.ajax_url, {
			action: 'wc_facebook_whatsapp_finish_onboarding',
			nonce:  facebook_for_woocommerce_whatsapp_finish.nonce
		}, function ( response ) {
            if ( response.success ) {
                // If success, redirect to utility settings page
                 let url = new URL(window.location.href);
                 let params = new URLSearchParams(url.search);
                 params.set('view', 'utility_settings');
                 url.search = params.toString();
                 window.location.href = url.toString();
                 console.log( 'Whatsapp Connect Success', response );
			} else {
                var message;
                const error = response.data;
                console.log( 'Whatsapp Connect Failure', response );

                switch (error) {
                    case "Incorrect payment setup":
                        message = facebook_for_woocommerce_whatsapp_finish.i18n.payment_setup_error;
                        break;
                    case "Onboarding is not complete or has failed.":
                        message = facebook_for_woocommerce_whatsapp_finish.i18n.onboarding_incomplete_error;
                        break;
                    default:
                        message = facebook_for_woocommerce_whatsapp_finish.i18n.generic_error;
                }


                const errorNoticeHtml = `
                      <div class="notice-error">
                        <p>${message}</p>
                      </div>
                    `;
                $( '#payment-method-error-notice' ).html( errorNoticeHtml ).show();
                spinnerState.hide();
                doneBtn.removeClass('fbwa-button-disabled');
            }
		} );
    });

} );
