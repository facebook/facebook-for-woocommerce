/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

jQuery( function( $ ) {

	/**
	 * WooCommerce Facebook Google Product Category Fields handler.
	 *
	 * @param {Object} categories
	 * @param {string} inputId
	 * @param {string} ajaxUrl
	 */
	window.WC_Facebook_Google_Product_Category_Fields = function( categories, inputId, ajaxUrl ) {

		console.log( 'FB DEBUG: Progressive JS constructor started' );
		console.log( 'FB DEBUG: Initial categories loaded:', Object.keys( categories ).length );
		console.log( 'FB DEBUG: Input ID:', inputId );
		console.log( 'FB DEBUG: AJAX URL:', ajaxUrl );
		
		/**
		 * The available categories - start with top-level only
		 *
		 * @type {Object}
		 */
		this.categories = categories;
		this.allCategories = {}; // Will be populated progressively
		
		/**
		 * AJAX URL for loading sub-categories
		 *
		 * @type {string}
		 */
		this.ajaxUrl = ajaxUrl;

		/**
		 * The input that should receive the selected category ID value.
		 *
		 * @type {Object}
		 */
		this.input = $( '#' + inputId );

		/**
		 * The container for our dynamic category fields
		 *
		 * @type {Object}
		 */
		this.container = $( '<div id="wc-facebook-google-product-category-fields"></div>' );

		// insert the container after the input
		this.input.after( this.container );

		// hide the original input
		this.input.hide();

		this.requestAttributesIfValid();
		this.addEventHandlers();

		console.log( 'FB DEBUG: Progressive JS constructor completed' );
	};

	window.WC_Facebook_Google_Product_Category_Fields.prototype = {

		/**
		 * Adds event handlers.
		 */
		addEventHandlers: function() {
			var handler = this;

			// on category select change, load children or set final value
			this.container.on( 'change', '.wc-facebook-google-product-category-select', function() {
				var $select     = $( this ),
					selectedId  = $select.val(),
					selectLevel = parseInt( $select.data( 'level' ) );

				console.log( 'FB DEBUG: Category selected:', selectedId, 'at level:', selectLevel );

				// clear any deeper level selects
				handler.clearDeeperLevels( selectLevel );

				if ( selectedId ) {
					// Load children for this category
					handler.loadChildren( selectedId, selectLevel );
				} else {
					// clear the input value
					handler.input.val( '' );
				}
			});
		},

		/**
		 * Loads children categories for the given parent ID
		 *
		 * @param {string} parentId
		 * @param {number} currentLevel
		 */
		loadChildren: function( parentId, currentLevel ) {
			var handler = this;
			
			console.log( 'FB DEBUG: Loading children for parent:', parentId );
			
			// Check if we already have children for this parent
			var children = this.getChildrenOf( parentId );
			
			if ( children.length > 0 ) {
				console.log( 'FB DEBUG: Children already loaded:', children.length );
				this.addChildrenSelect( children, currentLevel + 1 );
				return;
			}
			
			// Need to load via AJAX
			console.log( 'FB DEBUG: Making AJAX request for children of:', parentId );
			
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wc_facebook_load_category_children',
					parent_id: parentId,
					nonce: wc_facebook_google_product_category_fields_params.nonce
				},
				success: function( response ) {
					if ( response.success && response.data ) {
						console.log( 'FB DEBUG: AJAX success, loaded children:', Object.keys( response.data ).length );
						
						// Add to our categories cache
						$.extend( handler.allCategories, response.data );
						
						var children = handler.getChildrenOf( parentId );
						if ( children.length > 0 ) {
							handler.addChildrenSelect( children, currentLevel + 1 );
						} else {
							// This is a leaf category, set as final value
							handler.input.val( parentId );
							console.log( 'FB DEBUG: Leaf category selected:', parentId );
						}
					} else {
						console.log( 'FB DEBUG: AJAX failed or no children found for:', parentId );
						// This is a leaf category, set as final value
						handler.input.val( parentId );
					}
				},
				error: function( xhr, status, error ) {
					console.log( 'FB DEBUG: AJAX error:', error );
					// Fallback: treat as leaf category
					handler.input.val( parentId );
				}
			});
		},

		/**
		 * Gets children categories for a given parent ID
		 *
		 * @param {string} parentId
		 * @returns {Array}
		 */
		getChildrenOf: function( parentId ) {
			var children = [];
			var allCats = $.extend( {}, this.categories, this.allCategories );
			
			for ( var categoryId in allCats ) {
				if ( allCats[ categoryId ].parent === parentId ) {
					children.push({
						id: categoryId,
						name: allCats[ categoryId ].label
					});
				}
			}
			
			return children;
		},

		/**
		 * Adds a select field for children categories
		 *
		 * @param {Array} children
		 * @param {number} level
		 */
		addChildrenSelect: function( children, level ) {
			console.log( 'FB DEBUG: Adding select for level:', level, 'with children:', children.length );
			
			var $select = $( '<select class="wc-facebook-google-product-category-select"></select>' );
			$select.data( 'level', level );
			
			// Get appropriate placeholder text based on level and content
			var placeholder = '';
			if ( level === 0 ) {
				// Top level dropdown
				placeholder = facebook_for_woocommerce_google_product_category.i18n.top_level_dropdown_placeholder;
			} else if ( children.length === 0 ) {
				// Empty dropdown after selection
				placeholder = facebook_for_woocommerce_google_product_category.i18n.second_level_empty_dropdown_placeholder;
			} else {
				// General dropdown with options
				placeholder = facebook_for_woocommerce_google_product_category.i18n.general_dropdown_placeholder;
			}
			
			// Add empty option with proper placeholder
			$select.append( '<option value="">' + placeholder + '</option>' );
			
			// Add children options
			children.forEach( function( child ) {
				$select.append( '<option value="' + child.id + '">' + child.name + '</option>' );
			});
			
			// Wrap in container div
			var $container = $( '<div class="wc-facebook-google-product-category-field"></div>' );
			$container.append( $select );
			
			// Add to main container
			this.container.append( $container );
			
			// Initialize select2 with proper placeholder
			$select.select2({
				width: '100%',
				placeholder: placeholder,
				allowClear: true
			});
		},

		/**
		 * Clears select fields deeper than the given level
		 *
		 * @param {number} level
		 */
		clearDeeperLevels: function( level ) {
			this.container.find( '.wc-facebook-google-product-category-select' ).each( function() {
				var $select = $( this );
				var selectLevel = parseInt( $select.data( 'level' ) );
				
				if ( selectLevel > level ) {
					$select.closest( '.wc-facebook-google-product-category-field' ).remove();
				}
			});
		},

		/**
		 * Requests the product attributes, if valid.
		 */
		requestAttributesIfValid: function() {
			console.log( 'FB DEBUG: Setting up initial top-level select' );
			
			// Create initial top-level select
			var topLevelOptions = [];
			for ( var categoryId in this.categories ) {
				topLevelOptions.push({
					id: categoryId,
					name: this.categories[ categoryId ].label
				});
			}
			
			if ( topLevelOptions.length > 0 ) {
				this.addChildrenSelect( topLevelOptions, 0 );
			}
		}
	};
});
