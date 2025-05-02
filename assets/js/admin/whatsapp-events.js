/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // update current view from utility settings to manage event when order confirmation button is clicked.
    $('#woocommerce-whatsapp-manage-order-confirmation').click(function (event) {
        let url = new URL(window.location.href);
        let params = new URLSearchParams(url.search);
        params.set('view', 'manage_event');
        url.search = params.toString();
        window.location.href = url.toString();
    });

    // call template library get API to show message template header, body and button text configured for the event.
    $("#library-template-content").load(facebook_for_woocommerce_whatsapp_events.ajax_url, function () {
        $.post(facebook_for_woocommerce_whatsapp_events.ajax_url, {
            action: 'wc_facebook_whatsapp_fetch_library_template_info',
            nonce: facebook_for_woocommerce_whatsapp_events.nonce
        }, function (response) {
            if (response.success) {
                const parsedData = JSON.parse(response.data);
                const apiResponseData = parsedData.data[0];
                // Parse template strings as HTML and extract text content to sanitize text
                const header = $.parseHTML(apiResponseData.header)[0].textContent;
                const body = $.parseHTML(apiResponseData.body)[0].textContent;
                const button = $.parseHTML(apiResponseData.buttons[0].text)[0].textContent;
                $('#library-template-content').html(`
                    <h3>Header</h3>
                    <p>${header}</p>
                    <h3>Body</h3>
                    <p>${body}</p>
                    <h3>Call to action</h3>
                    <p>${button}</p>
                `).show();
            }
        });
    });

    $('#woocommerce-whatsapp-save-order-confirmation').click(function (event) {
        var languageValue = $("#manage-event-language").val();
        var statusValue = $('input[name="template-status"]:checked').val();
        console.log('Save confirmation clicked: ', languageValue, statusValue);
        $.post(facebook_for_woocommerce_whatsapp_events.ajax_url, {
            action: 'wc_facebook_whatsapp_upsert_event_config',
            nonce: facebook_for_woocommerce_whatsapp_events.nonce,
            event: 'ORDER_PLACED',
            language: languageValue,
            status: statusValue
        }, function (response) {
            //TODO: Add Error Handling
            let url = new URL(window.location.href);
            let params = new URLSearchParams(url.search);
            params.set('view', 'utility_settings');
            url.search = params.toString();
            window.location.href = url.toString();
        });
    });
});