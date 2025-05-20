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
    var modal = document.getElementById("wc-fb-warning-modal");
    var cancelButton = document.getElementById("wc-fb-warning-modal-cancel");
    var confirmButton = document.getElementById("wc-fb-warning-modal-confirm");
    var $statusElement = $('#wc-whatsapp-collect-consent-status');

    if (!facebook_for_woocommerce_whatsapp_consent.consent_collection_enabled) {
       // Change the status from "on-status" to "off-status" for the specific element.
       $statusElement.removeClass('on-status').addClass('off-status');
       // Update the text to "Off".
       $statusElement.text('Off');
       // Hide the original "Remove" button
       $('#wc-whatsapp-collect-consent-remove-container').addClass('fbwa-hidden-element');
       // Show the "Add" button
       $('#wc-whatsapp-collect-consent-add-container').removeClass('fbwa-hidden-element');
    }

    // On click of the remove button, show the warning modal
    $("#wc-whatsapp-collect-consent-remove").click(function(event) {
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
            // Send the AJAX request to disable WhatsApp consent collection
            $.post(facebook_for_woocommerce_whatsapp_consent_remove.ajax_url, {
                action: 'wc_facebook_whatsapp_consent_collection_disable',
                nonce: facebook_for_woocommerce_whatsapp_consent_remove.nonce
            }, function(response) {
                if (response.success) {
                    console.log( 'Whatsapp Consent Collection Disabled Successfully', response );
                    // Change the status from "on-status" to "off-status" for the specific element.
                    $statusElement.removeClass('on-status').addClass('off-status');
                    // Update the text to "Off".
                    $statusElement.text('Off');

                    // Hide the original "Remove" button
                    $('#wc-whatsapp-collect-consent-remove-container').addClass('fbwa-hidden-element');

                    // Show the "Add" button
                    $('#wc-whatsapp-collect-consent-add-container').removeClass('fbwa-hidden-element');
                } else {
                    console.log( 'Whatsapp Consent Collection Disabling Failed', response );
                }
            });

            // Close the modal
            modal.style.display = "none";
        };
    }

    // Add event listener to the "Add" button
    $('#wc-whatsapp-collect-consent-add').click(function() {
        // Send the AJAX request to enable WhatsApp consent collection
        $.post(facebook_for_woocommerce_whatsapp_consent.ajax_url, {
            action: 'wc_facebook_whatsapp_consent_collection_enable',
            nonce: facebook_for_woocommerce_whatsapp_consent.nonce
        }, function(response) {
            if (response.success) {
                console.log( 'Whatsapp Consent Collection Enabled Successfully', response );
                // Change the status from "off-status" to "on-status" for the specific element.
                $statusElement.removeClass('off-status').addClass('on-status');
                // Update the text to "On".
                $statusElement.text('On');

                // Hide the "Add" button
                $('#wc-whatsapp-collect-consent-add-container').addClass('fbwa-hidden-element');

                // Show the original "Remove" button
                $('#wc-whatsapp-collect-consent-remove-container').removeClass('fbwa-hidden-element');
            } else {
                console.log( 'Whatsapp Consent Collection Enabling Failed', response );
            }
        });
    });

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    };
});
