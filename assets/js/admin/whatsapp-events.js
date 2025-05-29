/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery(document).ready(function ($) {
    // Set Event Status for Order Placed
    var orderPlacedActiveStatus = $('#order-placed-active-status');
    var orderPlacedInactiveStatus = $('#order-placed-inactive-status');
    if (facebook_for_woocommerce_whatsapp_events.order_placed_enabled) {
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
    if (facebook_for_woocommerce_whatsapp_events.order_fulfilled_enabled) {
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
    if (facebook_for_woocommerce_whatsapp_events.order_refunded_enabled) {
        orderRefundedInactiveStatus.hide();
        orderRefundedActiveStatus.show();
    }
    else {
        orderRefundedActiveStatus.hide();
        orderRefundedInactiveStatus.show();
    }

    var saveEventBtn = $('#woocommerce-whatsapp-save-order-confirmation');
    var cancelEventBtn = $('#woocommerce-whatsapp-cancel-order-confirmation');

    $('#woocommerce-whatsapp-manage-order-placed, #woocommerce-whatsapp-manage-order-fulfilled, #woocommerce-whatsapp-manage-order-refunded').click(function (event) {
        var clickedButtonId = $(event.target).attr("id");
        let view = clickedButtonId.replace("woocommerce-whatsapp-", "");
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
                const event = facebook_for_woocommerce_whatsapp_events.event;
                const headerReplacements = {
                    "ORDER_REFUNDED": {
                        "{{1}}": "{{$amount}}"
                    }
                };
                const bodyReplacements = {
                    "ORDER_PLACED": {
                        "{{1}}": "{{first_name}}",
                        "{{2}}": "#{{order_number}}"
                    },
                    "ORDER_FULFILLED": {
                        "{{1}}": "{{first_name}}",
                        "{{2}}": "#{{order_number}}"
                    },
                    "ORDER_REFUNDED": {
                        "{{1}}": "{{first_name}}",
                        "{{2}}": "{{$amount}}",
                        "{{3}}": "#{{order_number}}"
                    }
                };
                const parsedData = JSON.parse(response.data);
                const apiResponseData = parsedData.data[0];
                // Parse template strings as HTML and extract text content to sanitize text
                var header = $.parseHTML(apiResponseData.header)[0].textContent;
                header = header.replace(/{{\d+}}/g, function (match) {
                    return headerReplacements[event][match];
                });
                var body = $.parseHTML(apiResponseData.body)[0].textContent;
                body = body.replace(/{{\d+}}/g, function(match) {
                    return bodyReplacements[event][match];
                  });
                // Body content has line breaks that need to be rendered in html
                body = body.replace(/\n/g, '<br>');
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
                console.log('Whatsapp Library Template call succeeded', response);
            }
            else {
                console.log('Whatsapp Library Template call failed', response);
                const message = facebook_for_woocommerce_whatsapp_finish.i18n.generic_error;
                const errorNoticeHtml = `
                <div class="notice-error">
                  <p>${message}</p>
                </div>
              `;
                $('#events-error-notice').html(errorNoticeHtml).show();
            }
        });
    });

    saveEventBtn.click(function (event) {
        var languageValue = $("#manage-event-language").val();
        var statusValue = $('input[name="template-status"]:checked').val();
        var spinnerState = $('#woocommerce-whatsapp-save-loading-state');
        saveEventBtn.addClass('fbwa-button-disabled');
        cancelEventBtn.addClass('fbwa-button-disabled');
        spinnerState.show();
        $.post(facebook_for_woocommerce_whatsapp_events.ajax_url, {
            action: 'wc_facebook_whatsapp_upsert_event_config',
            nonce: facebook_for_woocommerce_whatsapp_events.nonce,
            event: facebook_for_woocommerce_whatsapp_events.event,
            language: languageValue,
            status: statusValue
        }, function (response) {
            if (response.success) {
                let url = new URL(window.location.href);
                let params = new URLSearchParams(url.search);
                params.set('view', 'utility_settings');
                url.search = params.toString();
                window.location.href = url.toString();
                console.log('Whatsapp Event Config has been updated', response);
            }
            else {
                spinnerState.hide();
                saveEventBtn.removeClass('fbwa-button-disabled');
                cancelEventBtn.removeClass('fbwa-button-disabled');
                console.log('Whatsapp Event Config Update failure', response);
                const message = facebook_for_woocommerce_whatsapp_finish.i18n.generic_error;
                const errorNoticeHtml = `
                <div class="notice-error">
                  <p>${message}</p>
                </div>
              `;
                $('#events-error-notice').html(errorNoticeHtml).show();
            }
        });
    });

    $("#manage-event-language").load(facebook_for_woocommerce_whatsapp_events.ajax_url, function () {
        $.post(facebook_for_woocommerce_whatsapp_events.ajax_url, {
            action: 'wc_facebook_whatsapp_fetch_supported_languages',
            nonce: facebook_for_woocommerce_whatsapp_events.nonce,
        }, function (response) {
            if (response.success) {
                const parsedData = JSON.parse(response.data);
                const supportedLanguages = parsedData.supported_languages;
                $.each(supportedLanguages, function (index, languageObj) {
                    var displayValue = $.parseHTML(languageObj.display_value)[0].textContent;
                    var locale = $.parseHTML(languageObj.locale)[0].textContent;
                    $("#manage-event-language").append($("<option></option>").text(displayValue).val(locale));
                });
                var eventConfiglanguage = getEventLanguage(facebook_for_woocommerce_whatsapp_events.event);
                $("#manage-event-language").val(eventConfiglanguage);
                console.log('Fetch supported language call succeeded');
            }
            else {
                console.log('Fetch supported language call failed', response);
                const message = facebook_for_woocommerce_whatsapp_finish.i18n.generic_error;
                const errorNoticeHtml = `
                <div class="notice-error">
                  <p>${message}</p>
                </div>
              `;
                $('#events-error-notice').html(errorNoticeHtml).show();
            }
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