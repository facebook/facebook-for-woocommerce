/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // Get the modal and related elements
    var modal = document.getElementById("wc-fb-disconnect-warning-modal");
    var cancelButton = document.getElementById("wc-fb-disconnect-warning-modal-cancel");
    var confirmButton = document.getElementById("wc-fb-disconnect-warning-modal-confirm");

    // On click of the remove button, show the warning modal
    $("#wc-whatsapp-disconnect-button").click(function(event) {
        // Show the modal
        modal.style.display = "block";

        // Prevent default action
        event.preventDefault();
    });

    if (cancelButton) {
        // Close modal when clicking the Cancel button
        cancelButton.onclick = function() {
            modal.style.display = "none";
        };
    }

    if (confirmButton) {
        // Handle confirm action
        confirmButton.onclick = function() {
            $.post( facebook_for_woocommerce_whatsapp_disconnect.ajax_url, {
                action: 'wc_facebook_disconnect_whatsapp',
                nonce:  facebook_for_woocommerce_whatsapp_disconnect.nonce
            }, function ( response ) {
                if ( response.success ) {
                    let url = new URL(window.location.href);
                    let params = new URLSearchParams(url.search);
                    params.delete('view');
                    url.search = params.toString();
                    window.location.href = url.toString();
                    console.log( 'Whatsapp Disconnect Success', response );
                } else {
                    console.log("Whatsapp Disconnect Failure!!!",response);
                }
            } );

            // Close the modal
            modal.style.display = "none";
        };
    }

    // handle whatsapp disconnect widget edit link click should open business manager with whatsapp asset selected
	$( '#wc-whatsapp-disconnect-edit' ).click( function( event ) {
        $.post( facebook_for_woocommerce_whatsapp_disconnect.ajax_url, {
			action: 'wc_facebook_whatsapp_fetch_url_info',
			nonce:  facebook_for_woocommerce_whatsapp_disconnect.nonce
		}, function ( response ) {

            if ( response.success ) {
                console.log( 'Whatsapp Edit Url Info Fetched Successfully', response );
                var  business_id = response.data.business_id;
                var asset_id = response.data.waba_id;
                const WHATSAPP_MANAGER_URL = `https://business.facebook.com/latest/whatsapp_manager/phone_numbers/?asset_id=${asset_id}&business_id=${business_id}`;
                window.open(WHATSAPP_MANAGER_URL);
			} else {
                console.log( 'Whatsapp Edit Url Info Fetch Failure', response );
            }
		} );
    });

} );
