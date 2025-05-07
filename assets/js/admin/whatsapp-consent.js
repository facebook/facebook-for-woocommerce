/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( function( $ ) {
	var $consentCollectSuccess = $('#wc-fb-whatsapp-consent-collection-success');
  	var $consentCollectInProgress = $('#wc-fb-whatsapp-consent-collection-inprogress');
  	var $consentCollectNotStarted = $('#wc-fb-whatsapp-consent-collection-notstarted');
	var $consentSubcontent = $('#wc-fb-whatsapp-consent-subcontent');
	var $consentButtonWrapper = $('#wc-fb-whatsapp-consent-button-wrapper');
	if (facebook_for_woocommerce_whatsapp_consent.whatsapp_onboarding_complete) {
		if (facebook_for_woocommerce_whatsapp_consent.consent_collection_enabled) {
			showConsentCollectionProgressIcon(true, false, false);
			$consentSubcontent.hide();
			$consentButtonWrapper.hide();
		} else {
			showConsentCollectionProgressIcon(false, true, false);
		}
    } else {
		showConsentCollectionProgressIcon(false, false, true);
		$consentSubcontent.hide();
		$consentButtonWrapper.hide();
    }

    // handle the whatsapp consent collect button click should save setting to wp_options table
	$( '#wc-whatsapp-collect-consent' ).click( function( event ) {

        $.post( facebook_for_woocommerce_whatsapp_consent.ajax_url, {
			action: 'wc_facebook_whatsapp_consent_collection_enable',
			nonce:  facebook_for_woocommerce_whatsapp_consent.nonce
		}, function ( response ) {
            if ( response.success ) {
				console.log( 'Whatsapp Consent Collection is Enabled in Checkout Flow', response );
				// update the progress for collect consent step and hide the button and subcontent
				showConsentCollectionProgressIcon(true, false, false);
				$consentSubcontent.hide();
				$consentButtonWrapper.hide();
				// update the progress of billing step and show the button and subcontent
				if(response.data['is_payment_setup'] === true) {
                    $('#wc-fb-whatsapp-billing-inprogress').hide();
                    $('#wc-fb-whatsapp-billing-notstarted').hide();
                    $('#wc-fb-whatsapp-billing-success').show();
                } else {
					$('#wc-fb-whatsapp-billing-inprogress').show();
               		$('#wc-fb-whatsapp-billing-notstarted').hide();

				}
				$('#wc-fb-whatsapp-billing-subcontent').show();
				$('#wc-fb-whatsapp-billing-button-wrapper').show();
				$('#whatsapp-onboarding-done-button').show();
			} else {
				console.log( 'Whatsapp Consent Collection Enabling has Failed', response );
			}
		} );

    });

	function showConsentCollectionProgressIcon(success, inProgress, notStarted) {
		if (success) {
		  $consentCollectSuccess.show();
		} else {
		  $consentCollectSuccess.hide();
		}

		if (inProgress) {
		  $consentCollectInProgress.show();
		} else {
		  $consentCollectInProgress.hide();
		}

		if (notStarted) {
		  $consentCollectNotStarted.show();
		} else {
		  $consentCollectNotStarted.hide();
		}
	  }

} );
