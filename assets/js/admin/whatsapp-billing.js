/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
  	var $billingStepInProgress = $('#wc-fb-whatsapp-billing-inprogress');
  	var $billingStepNotStarted = $('#wc-fb-whatsapp-billing-notstarted');
	var $billingStepSuccess = $('#wc-fb-whatsapp-billing-success');
	var $billingSubcontent = $('#wc-fb-whatsapp-billing-subcontent');
	var $billingButtonWrapper = $('#wc-fb-whatsapp-billing-button-wrapper');
	var $whatsappOnboardingDoneButton = $('#whatsapp-onboarding-done-button');
	if (facebook_for_woocommerce_whatsapp_billing.consent_collection_enabled) {
		facebook_for_woocommerce_whatsapp_billing.is_payment_setup ? $billingStepSuccess.show() : $billingStepInProgress.show();
		$whatsappOnboardingDoneButton.show();
		$billingStepNotStarted.hide();
	} else {
		$billingStepInProgress.hide();
		$billingStepNotStarted.show();
		$billingSubcontent.hide();
		$whatsappOnboardingDoneButton.hide();
		$billingButtonWrapper.hide()
	}

    // handle the whatsapp add payment button click should open billing flow in Meta
    $('#wc-whatsapp-add-payment').click(function(event) {

        $.post( facebook_for_woocommerce_whatsapp_billing.ajax_url, {
			action: 'wc_facebook_whatsapp_fetch_url_info',
			nonce:  facebook_for_woocommerce_whatsapp_billing.nonce
		}, function ( response ) {
            if ( response.success ) {
				console.log( 'Whatsapp Billing Url Info Fetched Successfully', response );
                var  business_id = response.data.business_id;
                var asset_id = response.data.waba_id;
				const BILLING_URL = `https://business.facebook.com/billing_hub/accounts/details/?business_id=${business_id}&asset_id=${asset_id}&account_type=whatsapp-business-account`;
                window.open( BILLING_URL);
			} else {
				console.log( 'Whatsapp Billing Url Info Fetch Failure', response );
			}
		} );


    });

} );
