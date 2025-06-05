/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

(function( $ ) {
	
	/**
	 * Determines if the current modal is blocked.
	 *
	 * @returns {boolean}
	 */
	window.isModalBlocked = function() {

		let $modal = $( '.wc-backbone-modal-content' );

		return $modal.is( '.processing' ) || $modal.parents( '.processing' ).length;
	}


	/**
	 * Blocks the current modal.
	 */
	window.blockModal = function() {

		if ( ! isModalBlocked() ) {
			return $( '.wc-backbone-modal-content' ).addClass( 'processing' ).block( {
				message:    null,
				overlayCSS: {
					background: '#fff',
					opacity:    0.6
				}
			} );
		}
	}


	/**
	 * Unblocks the current modal.
	 */
	window.unBlockModal = function() {

		$( '.wc-backbone-modal-content' ).removeClass( 'processing' ).unblock();
	}


	/**
	 * Closes the current modal.
	 */
	window.closeExistingModal = function() {

		$( '#wc-backbone-modal-dialog .modal-close' ).trigger( 'click' );
	}

})( jQuery );
