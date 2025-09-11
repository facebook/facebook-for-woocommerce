/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( document ).ready( ( $ ) => {

	let $form = $( 'form[id="edittag"]' );
	let $defaultCategoryField = $( '#wc_facebook_google_product_category_id' );
	let originalDefaultCategoryId = $defaultCategoryField.val();

	$form.on( 'submit', function( event ) {

		if ( $form.data( 'allow-submit' ) || $defaultCategoryField.val() === originalDefaultCategoryId ) {
			return;
		}

		event.preventDefault();

		$( '#wc-backbone-modal-dialog .modal-close' ).trigger( 'click' );

		new $.WCBackboneModal.View( {
			target: 'facebook-for-woocommerce-modal',
			string: {
				message: facebook_for_woocommerce_product_categories.default_google_product_category_modal_message,
				buttons: facebook_for_woocommerce_product_categories.default_google_product_category_modal_buttons
			}
		} );

		$( document.body )
			.off( 'wc_backbone_modal_response.facebook_for_commerce' )
			.on( 'wc_backbone_modal_response.facebook_for_commerce', function() {
				$form.data( 'allow-submit', true ).find( ':submit' ).trigger( 'click' );
			} );
	} );

	// Add new category button handler to clear Google Product Category selections when clicked
  $('#submit').on('click', function(e) {
    // Only proceed if this is the "Add new category" button
    if ($(this).val() === 'Add new category') {
      // Check if the Google Product Category Fields handler exists
      if (typeof window.wc_facebook_google_product_category_fields !== 'undefined') {

        // Clear selections
        $('#' + window.wc_facebook_google_product_category_fields.input_id).val('');
        $('#wc-facebook-google-product-category-fields').empty();
        $('.wc-facebook-enhanced-catalog-attribute-row').remove();

        // Recreate initial empty selectors
        window.wc_facebook_google_product_category_fields.addInitialSelects('');

        // Set the last dropdown's margin bottom
        $('.wc-facebook-google-product-category-field').last().attr('style', 'margin-bottom: 24px');
      }
    }
  });
} );
