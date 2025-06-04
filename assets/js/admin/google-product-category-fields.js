/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

console.log('FB DEBUG: google-product-category-fields.js loading...');

jQuery( document ).ready( ( $ ) => {

	'use strict';

	console.log('FB DEBUG: jQuery ready, initializing Google Product Category Fields class');

	/**
	 * Google product category field handler.
	 *
	 * @since 2.1.0
	 *
	 * @type {WC_Facebook_Google_Product_Category_Fields} object
	 */
	window.WC_Facebook_Google_Product_Category_Fields = class WC_Facebook_Google_Product_Category_Fields {
		/**
		 * Handler constructor.
		 *
		 * @since 2.1.0
		 *
		 * @param {Object[]} categories The full categories list, indexed by the category ID
		 * @param {string} categories[].label The category label
		 * @param {string[]} categories[].options The category's child categories' IDs
		 * @param {string} categories[].parent The category's parent category ID
		 * @param {string} input_id The element that should receive the latest concrete category ID
		 */
		constructor(categories, input_id) {

			console.log('FB DEBUG: Constructor called with categories:', categories ? Object.keys(categories).length : 'null', 'input_id:', input_id);

			this.categories = categories;
			this.input_id = input_id;

			var $input = $( '#' + this.input_id );

			$( '<div id="wc-facebook-google-product-category-fields"></div>' )
				.insertBefore( $input )
				.on( 'change', 'select.wc-facebook-google-product-category-select', ( event ) => {
					this.onChange( $( event.target ) );
				} );

			this.addInitialSelects( $input.val() );
		}

		/**
		 * Adds the initial select fields for the previously selected values.
		 *
		 * If there is no previously selected value, it adds two selected fields with no selected option.
		 *
		 * @param {string} categoryId the selected google product category
		 */
		addInitialSelects( categoryId ) {

			console.log('FB DEBUG: addInitialSelects called with:', categoryId);

			if ( categoryId ) {

				// If categoryId is a string (like "Clothing & Accessories > Clothing > Shirts & Tops"), 
				// convert it to a numeric ID
				if ( isNaN( categoryId ) ) {
					console.log('FB DEBUG: Converting category string to ID:', categoryId);
					categoryId = this.getCategoryId( categoryId );
					console.log('FB DEBUG: Converted to ID:', categoryId);
				}

				if ( categoryId ) {
					this.getSelectedCategoryIds( categoryId ).forEach( ( pair ) => {
						this.addSelect( this.getOptions( pair[1] ), pair[0] );
					} );

					var options = this.getOptions( categoryId );

					if ( Object.keys( options ).length ) {
						this.addSelect( options );
					}
				} else {
					console.log('FB DEBUG: Could not find category ID, showing top-level categories');
					this.addSelect( this.getOptions() );
					this.addSelect( {} );
				}

			} else {

				console.log('FB DEBUG: No existing category, showing top-level categories');
				this.addSelect( this.getOptions() );
				this.addSelect( {} );
			}
		}

		/**
		 * Updates the subsequent selects whenever one of the selects changes.
		 *
		 * @since 2.1.0
		 */
		onChange(element) {

			// remove following select fields if their options depended on the value of the current select field
			if ( element.hasClass( 'locked' ) ) {
				element.closest( '.wc-facebook-google-product-category-field' ).nextAll().remove();
			}

			var categoryId = element.val();

			if ( categoryId ) {

				var options = this.getOptions( categoryId );

				if ( Object.keys( options ).length ) {
					this.addSelect( options );
				}

			} else {

				// use category ID from the last select field that has a selected value
				categoryId = element.closest( '#wc-facebook-google-product-category-fields' )
					.find( '.wc-facebook-google-product-category-select' )
						.not( element )
							.last()
								.val();

				if ( ! categoryId ) {
					this.addSelect( {} );
				}
			}

			$( '#' + this.input_id ).val( categoryId );
		}

		/**
		 * Gets an array of selected category IDs up to the category with the given ID.
		 *
		 * @since 2.1.0
		 *
		 * @param {string} categoryId the concrete category ID
		 *
		 * @return {Array} an array of category ID pairs (selected option, parent)
		 */
		getSelectedCategoryIds( categoryId ) {

			let ids = [];
			let selectedCategory = this.categories[ categoryId ];

			if ( selectedCategory ) {

				ids.push( [ categoryId, selectedCategory.parent ] );

				if ( selectedCategory.parent ) {
					ids = this.getSelectedCategoryIds( selectedCategory.parent ).concat( ids );
				}
			}

			return ids;
		}

		/**
		 * Adds a select field.
		 *
		 * @since 2.1.0
		 *
		 * @param {Object.<string, string>} options an object with option IDs as keys and option labels as values
		 * @param {string} selected the selected option ID
		 */
		addSelect( options, selected ) {

			var $container = $( '#wc-facebook-google-product-category-fields' );
			var $otherSelects = $container.find( '.wc-facebook-google-product-category-select' );
			var $select = $( '<select class="wc-facebook-google-product-category-select"></select>' );

			$otherSelects.addClass( 'locked' );

			$container.append( $( '<div class="wc-facebook-google-product-category-field" style="margin-bottom: 16px">' ).append( $select ) );

			$select.attr( 'data-placeholder', this.getSelectPlaceholder( $otherSelects, options ) ).append( $( '<option value=""></option>' ) );

			Object.keys( options ).forEach( ( key ) => {
				$select.append( $( '<option value="' + key + '">' + options[ key ] + '</option>' ) );
			} );

			$select.val( selected ).select2( { allowClear: true } );
		}

		/**
		 * Gets an array of category options.
		 *
		 * @since 2.1.0
		 *
		 * @param {string} parent_id the parent category ID
		 *
		 * @return {Object.<string, string>} an object with option IDs as keys and option labels as values
		 */
		getOptions( parent_id ) {

			let options = {};

			for ( const key in this.categories ) {

				if ( this.categories.hasOwnProperty( key ) ) {

					const category = this.categories[ key ];

					// Handle the case where top-level categories have parent: '' but parent_id is undefined
					const categoryParent = category.parent;
					const targetParent = parent_id || '';

					if ( categoryParent === targetParent ) {
						options[ key ] = category.label;
					}
				}
			}

			return options;
		}

		/**
		 * Gets the placeholder text for a select field.
		 *
		 * @since 2.1.0
		 *
		 * @param {jQuery} $otherSelects other select fields
		 * @param {Object.<string, string>} options the select field options
		 *
		 * @return {string} the placeholder text
		 */
		getSelectPlaceholder( $otherSelects, options ) {

			if ( $otherSelects.length === 0 ) {
				return facebook_for_woocommerce_google_product_category.i18n.top_level_dropdown_placeholder;
			} else if ( Object.keys( options ).length === 0 ) {
				return facebook_for_woocommerce_google_product_category.i18n.second_level_empty_dropdown_placeholder;
			} else {
				return facebook_for_woocommerce_google_product_category.i18n.general_dropdown_placeholder;
			}
		}

		/**
		 * Gets a concrete category ID from a category string.
		 *
		 * @since 2.1.0
		 *
		 * @param {string} categoryString the category string
		 *
		 * @return {string} the concrete category ID
		 */
		getCategoryId( categoryString ) {

			console.log('FB DEBUG: getCategoryId searching for:', categoryString);

			for ( const key in this.categories ) {

				if ( this.categories.hasOwnProperty( key ) ) {

					const category = this.categories[ key ];
					
					// Try exact match first
					if ( category.label === categoryString ) {
						console.log('FB DEBUG: Found exact match for category ID:', key);
						return key;
					}
				}
			}

			console.log('FB DEBUG: No exact match found for category string:', categoryString);
			
			// If no exact match, try to find the last part of the category string
			// For "Clothing & Accessories > Clothing > Shirts & Tops", look for "Shirts & Tops"
			if ( categoryString.includes( ' > ' ) ) {
				const lastPart = categoryString.split( ' > ' ).pop();
				console.log('FB DEBUG: Trying to match last part:', lastPart);
				
				for ( const key in this.categories ) {
					if ( this.categories.hasOwnProperty( key ) ) {
						const category = this.categories[ key ];
						if ( category.label === lastPart ) {
							console.log('FB DEBUG: Found match for last part, category ID:', key);
							return key;
						}
					}
				}
			}

			console.log('FB DEBUG: No match found for category string');
			return '';
		}
	};
} );
