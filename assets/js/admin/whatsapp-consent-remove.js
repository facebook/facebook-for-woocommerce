/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery(document).ready(function($) {
    // Handle the WhatsApp consent collect button click remove action.
    $('#wc-whatsapp-collect-consent-remove').click(function(event) {
        event.preventDefault(); // Prevent the default action of the link.

        var $button = $(this); // The clicked button
        var $statusElement = $button.closest('.event-config').find('.event-config-status');

        $.post(facebook_for_woocommerce_whatsapp_consent_remove.ajax_url, {
            action: 'wc_facebook_whatsapp_consent_collection_disable',
            nonce: facebook_for_woocommerce_whatsapp_consent_remove.nonce
        }, function(response) {
            if (response.success) {
                // Change the status from "on-status" to "off-status" for the specific element.
                $statusElement.removeClass('on-status').addClass('off-status');
                // Update the text to "Off".
                $statusElement.text('Off');
				// Update button text to "Add".
				$button.text('Add');
				console.log('success', response);
            }
        });
    });
});
