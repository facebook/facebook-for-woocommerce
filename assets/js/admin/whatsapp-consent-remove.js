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
    var modal = document.getElementById("warning-modal");
    var closeBtn = modal.querySelector(".close");
    var cancelBtn = document.getElementById("modal-cancel");
    var confirmBtn = document.getElementById("warning-modal-confirm");

    // Handle the whatsapp consent collect button click remove action
    $("#wc-whatsapp-collect-consent-remove").click(function(event) {
        // Show the modal
        modal.style.display = "block";

        // Prevent default action
        event.preventDefault();
    });

    // Close modal when clicking the X
    closeBtn.onclick = function() {
        modal.style.display = "none";
    };

    // Close modal when clicking the Cancel button
    cancelBtn.onclick = function() {
        modal.style.display = "none";
    };

    // Handle confirm action
    confirmBtn.onclick = function() {
        // Send the AJAX request to disable WhatsApp consent collection
        $.post(facebook_for_woocommerce_whatsapp_consent_remove.ajax_url, {
            action: 'wc_facebook_whatsapp_consent_collection_disable',
            nonce: facebook_for_woocommerce_whatsapp_consent_remove.nonce
        }, function(response) {
            if (response.success) {
                console.log('success', response);
                // You can add a redirect or page refresh here if needed
            }
        });

        // Close the modal
        modal.style.display = "none";
    };

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    };

} );
