/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
    // Set Event Status for Order Placed
    var orderPlacedActiveStatus = $('#order-placed-active-status');
    var orderPlacedInactiveStatus = $('#order-placed-inactive-status');
    if(facebook_for_woocommerce_whatsapp_events.order_placed_enabled){
        orderPlacedInactiveStatus.hide();
        orderPlacedActiveStatus.show();
    }
    else {
        orderPlacedActiveStatus.hide();
        orderPlacedInactiveStatus.show();
    }

    // Set Event Status for Order FulFilled
    var orderFulfilledActiveStatus = $('#order-fulfilled-active-status');
    var orderFulfilledInactiveStatus = $('#order-fulfilled-inactive-status');
    if(facebook_for_woocommerce_whatsapp_events.order_fulfilled_enabled){
        orderFulfilledInactiveStatus.hide();
        orderFulfilledActiveStatus.show();
    }
    else {
        orderFulfilledActiveStatus.hide();
        orderFulfilledInactiveStatus.show();
    }

    // Set Event Status for Order Refunded
    var orderRefundedActiveStatus = $('#order-refunded-active-status');
    var orderRefundedInactiveStatus = $('#order-refunded-inactive-status');
    if(facebook_for_woocommerce_whatsapp_events.order_refunded_enabled){
        orderRefundedInactiveStatus.hide();
        orderRefundedActiveStatus.show();
    }
    else {
        orderRefundedActiveStatus.hide();
        orderRefundedInactiveStatus.show();
    }

    var eventConfiglanguage = getEventLanguage(facebook_for_woocommerce_whatsapp_events.event);
    $("#manage-event-language").val(eventConfiglanguage);

    $('#woocommerce-whatsapp-manage-order-placed, #woocommerce-whatsapp-manage-order-fulfilled, #woocommerce-whatsapp-manage-order-refunded').click(function (event) {
        var clickedButtonId = $(event.target).attr("id");
        let view=clickedButtonId.replace("woocommerce-whatsapp-", "");
        view = view.replaceAll("-", "_");
        let url = new URL(window.location.href);
        let params = new URLSearchParams(url.search);
        params.set('view', view);
        url.search = params.toString();
        window.location.href = url.toString();
    });

    // call template library get API to show message template header, body and button text configured for the event.
    $("#library-template-content").load(facebook_for_woocommerce_whatsapp_events.ajax_url, function () {
        $.post(facebook_for_woocommerce_whatsapp_events.ajax_url, {
            action: 'wc_facebook_whatsapp_fetch_library_template_info',
            nonce: facebook_for_woocommerce_whatsapp_events.nonce,
            event: facebook_for_woocommerce_whatsapp_events.event,
        }, function (response) {
            if (response.success) {
                const parsedData = JSON.parse(response.data);
                const apiResponseData = parsedData.data[0];
                // Parse template strings as HTML and extract text content to sanitize text
                const header = $.parseHTML(apiResponseData.header)[0].textContent;
                const body = $.parseHTML(apiResponseData.body)[0].textContent;
                if (facebook_for_woocommerce_whatsapp_events.event === "ORDER_REFUNDED") {
                    $('#library-template-content').html(`
                        <h3>Header</h3>
                        <p>${header}</p>
                        <h3>Body</h3>
                        <p>${body}</p>
                    `).show();
                }
                else {
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
            event: facebook_for_woocommerce_whatsapp_events.event,
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

    function getEventLanguage(event) {
        switch (event) {
            case "ORDER_PLACED":
                return facebook_for_woocommerce_whatsapp_events.order_placed_language;
            case "ORDER_FULFILLED":
                return facebook_for_woocommerce_whatsapp_events.order_fulfilled_language;
            case "ORDER_REFUNDED":
                return facebook_for_woocommerce_whatsapp_events.order_refunded_language;
            default:
                return null;
        }
    }
});