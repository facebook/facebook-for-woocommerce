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

		/**
		 * The available categories - start with top-level only
		 *
		 * @type {Object}
		 */
			this.categories = categories;
		this.allCategories = $.extend( {}, categories ); // Cache for all loaded categories
		this.inputId = inputId;
		this.input = $( '#' + inputId );
		this.ajaxUrl = ajaxUrl;
		this.isLoadingAttributes = false; // Flag to prevent duplicate AJAX requests

		// Create the main container
		this.container = $( '<div id="wc-facebook-google-product-category-fields"></div>' );
		this.container.insertBefore( this.input );

		// Add event handlers
		this.addEventHandlers();

		// Initialize the interface
		this.initialize();
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

				// clear any deeper level selects
				handler.clearDeeperLevels( selectLevel );

				if ( selectedId ) {
					// Load children for this category
					handler.loadChildren( selectedId, selectLevel );
				} else {
					// clear the input value
					handler.input.val( '' );
					// Clear enhanced attributes when no category is selected
					handler.clearEnhancedAttributes();
				}
			});
		},

		/**
		 * Clears all enhanced catalog attribute elements
		 */
		clearEnhancedAttributes: function() {
			// Remove all enhanced attribute related elements
			$( '.wc-facebook-enhanced-catalog-attribute-row' ).remove();
			$( '.wc-facebook-enhanced-catalog-attribute-optional-row' ).remove();
			
			// Also remove any elements with the enhanced catalog attribute field ID
			var optionalSelectorID = this.globalsHolder().enhanced_attribute_optional_selector;
			if ( optionalSelectorID ) {
				$( '#' + optionalSelectorID ).closest( '.form-field, tr.form-field, p.form-field' ).remove();
			}
			
			// Reset the loading flag
			this.isLoadingAttributes = false;
		},

		/**
		 * Loads children categories for the given parent ID
		 *
		 * @param {string} parentId
		 * @param {number} currentLevel
		 */
		loadChildren: function( parentId, currentLevel ) {
			var handler = this;
			
			// Check if we already have children for this parent
			var children = this.getChildrenOf( parentId );
			
			if ( children.length > 0 ) {
				this.addChildrenSelect( children, currentLevel + 1 );
				return;
			}
			
			// Need to load via AJAX
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
						// Add to our categories cache
						$.extend( handler.allCategories, response.data );
						
						var children = handler.getChildrenOf( parentId );
						if ( children.length > 0 ) {
							handler.addChildrenSelect( children, currentLevel + 1 );
			} else {
							// This is a leaf category, set as final value
							handler.input.val( parentId );
							
							// Request enhanced catalog attributes for this final category
							handler.requestAttributesIfValid();
						}
					} else {
						// This is a leaf category, set as final value
						handler.input.val( parentId );
						
						
						// Request enhanced catalog attributes for this final category
						handler.requestAttributesIfValid();
				}
				},
				error: function( xhr, status, error ) {
					// Fallback: treat as leaf category
					handler.input.val( parentId );
					
					// Request enhanced catalog attributes for this final category
					handler.requestAttributesIfValid();
			}
			});
		},

		/**
		 * Gets children categories for a given parent ID
		 *
		 * @param {string} parentId The parent category ID
		 * @return {Array} Array of child category objects
		 */
		getChildrenOf: function( parentId ) {
			var children = [];
			
			// Search in allCategories cache
			for ( var categoryId in this.allCategories ) {
				var category = this.allCategories[ categoryId ];
				if ( category.parent === parentId ) {
					children.push({
						id: categoryId,
						name: category.label || category.name
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
			// Prevent duplicate requests
			if ( this.isLoadingAttributes ) {
				return;
			}
			
			// Check if we can show enhanced attributes on this page
			var canShowEnhancedAttributesID = 'wc_facebook_can_show_enhanced_catalog_attributes_id';
			if ( $( '#' + canShowEnhancedAttributesID ).val() !== 'true' ) {
				return;
			}

			// Clear any existing enhanced attribute elements first
			this.clearEnhancedAttributes();

			if ( this.isValid() ) {
				// Set loading flag to prevent duplicate requests
				this.isLoadingAttributes = true;
				
				var handler = this;
				var inputSelector = '#' + this.inputId;
				var $inputParent = $( inputSelector ).parents( 'div.form-field' );
				var optionalSelectorID = this.globalsHolder().enhanced_attribute_optional_selector;
				
				// Determine the correct parent element based on page type
				if ( this.getPageType() === this.globalsHolder().enhanced_attribute_page_type_edit_category ) {
					$inputParent = $( inputSelector ).parents( 'tr.form-field' );
				} else if ( this.getPageType() === this.globalsHolder().enhanced_attribute_page_type_edit_product ) {
					$inputParent = $( inputSelector ).parents( 'p.form-field' );
				}

				// Make AJAX request to load enhanced catalog attributes
			  $.get( this.globalsHolder().ajax_url, {
					action: 'wc_facebook_enhanced_catalog_attributes',
					security: '',
					selected_category: $( inputSelector ).val(),
					tag_id: parseInt( $( 'input[name=tag_ID]' ).val(), 10 ),
					taxonomy: $( 'input[name=taxonomy]' ).val(),
					item_id: parseInt( $( 'input[name=post_ID]' ).val(), 10 ),
					page_type: this.getPageType(),
				}, function( response ) {
					// Reset loading flag
					handler.isLoadingAttributes = false;
					
					var $response = $( response );

					// Set up "Show more attributes" checkbox event handler
					// Remove any existing event handlers first to prevent duplicates
					$( '#' + optionalSelectorID ).off( 'change.facebook-enhanced-attributes' );

					$( '#' + optionalSelectorID, $response ).on( 'change.facebook-enhanced-attributes', function() {
						$( '.wc-facebook-enhanced-catalog-attribute-optional-row' )
							.toggleClass( 'hidden', !$( this ).prop( 'checked' ) );
					});
					
					// Insert the enhanced attributes after the input parent
					$response.insertAfter( $inputParent );
					
					// Ensure tooltips work
					$( document.body ).trigger( 'init_tooltips' );
				}).fail( function() {
					// Reset loading flag on failure
					handler.isLoadingAttributes = false;
				});
				}
		},

		/**
		 * Returns true if there have been at least two levels of category selected
		 *
		 * @return {boolean}
		 */
		isValid: function() {
			var selectsWithValueCount = $( '.wc-facebook-google-product-category-select' )
				.filter( function( _i, el ) { 
					return $( el ).val() !== ""; 
				})
					.length;
			return selectsWithValueCount >= 2;
		},

		/**
		 * Gets the globals holder object
		 *
		 * @return {Object}
		 */
		globalsHolder: function() {
			if ( typeof( facebook_for_woocommerce_product_categories ) !== 'undefined' ) {
				return facebook_for_woocommerce_product_categories;
			} else if ( typeof( facebook_for_woocommerce_settings_sync ) !== 'undefined' ) {
				return facebook_for_woocommerce_settings_sync;
			} else {
				return facebook_for_woocommerce_products_admin;
			}
		},

		/**
		 * Gets the page type
		 *
		 * @return {string}
		 */
		getPageType: function() {
			if ( typeof( facebook_for_woocommerce_product_categories ) !== 'undefined' ) {
				if ( $( 'input[name=tag_ID]' ).length === 0 ) {
					return this.globalsHolder().enhanced_attribute_page_type_add_category;
				} else {
					return this.globalsHolder().enhanced_attribute_page_type_edit_category;
				}
			} else {
				return this.globalsHolder().enhanced_attribute_page_type_edit_product;
			}
		},

		/**
		 * Initializes the Google Product Category fields
		 */
		initialize: function() {
			// Check if there's an existing saved value
			var existingValue = this.input.val();
			
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
				
				// If there's an existing value, try to reconstruct the category path
				if ( existingValue ) {
					// Check if the existing value is a category ID (numeric) or category name/path (string)
					if ( /^\d+$/.test( existingValue ) ) {
						// It's a category ID, use existing logic
						this.loadCategoryPath( existingValue );
					} else {
						// It's a category name/path (from feed import), need to find the ID first
						this.findCategoryIdFromName( existingValue );
					}
				}
			}
		},

		/**
		 * Finds the category ID from a category name/path (used for feed-imported products)
		 *
		 * @param {string} categoryNamePath The category name or path like "Clothing & Accessories > Clothing > Shirts & Tops"
		 */
		findCategoryIdFromName: function( categoryNamePath ) {
			var handler = this;
			
			// Clean up the category path - decode HTML entities and normalize
			var cleanPath = this.cleanCategoryPath( categoryNamePath );
			
			// Make AJAX request to find the category ID from name/path
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wc_facebook_find_category_id_from_name',
					category_name_path: cleanPath,
					nonce: wc_facebook_google_product_category_fields_params.nonce
				},
				success: function( response ) {
					if ( response.success && response.data && response.data.category_id ) {
						// Update the hidden input with the correct ID
						handler.input.val( response.data.category_id );
						
						// Now reconstruct the path using the ID
						handler.loadCategoryPath( response.data.category_id );
			}
				},
				error: function( xhr, status, error ) {
					// Silent failure for missing categories
				}
			});
		},

		/**
		 * Cleans a category path by decoding HTML entities and normalizing
		 *
		 * @param {string} categoryPath The raw category path
		 * @return {string} The cleaned category path
		 */
		cleanCategoryPath: function( categoryPath ) {
			if ( ! categoryPath ) {
				return '';
			}

			// Create a temporary element to decode HTML entities
			var tempDiv = document.createElement( 'div' );
			tempDiv.innerHTML = categoryPath;
			var decoded = tempDiv.textContent || tempDiv.innerText || '';
			
			// Normalize the path separators - convert various forms to standard >
			var normalized = decoded
				.replace( /\s*&gt;\s*/g, ' > ' )  // &gt; to >
				.replace( /\s*>\s*/g, ' > ' )     // normalize spacing around >
				.replace( /\s+/g, ' ' )           // normalize multiple spaces
				.trim();
			
			return normalized;
		},

		/**
		 * Loads and reconstructs the category path for an existing saved category ID
		 *
		 * @param {string} categoryId The saved category ID to reconstruct
		 */
		loadCategoryPath: function( categoryId ) {
			var handler = this;
			
			// Make AJAX request to get the full category path
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wc_facebook_get_category_path',
					category_id: categoryId,
					nonce: wc_facebook_google_product_category_fields_params.nonce
				},
				success: function( response ) {
					if ( response.success && response.data && response.data.path ) {
						handler.reconstructCategoryPath( response.data.path, response.data.categories );
					}
				},
				error: function( xhr, status, error ) {
					// Silent failure for missing paths
				}
			});
		},

		/**
		 * Reconstructs the category dropdown chain from a category path
		 *
		 * @param {Array} path Array of category IDs from root to leaf
		 * @param {Object} categories Categories data from server
		 */
		reconstructCategoryPath: function( path, categories ) {
			var handler = this;
			
			// Add the loaded categories to our cache
			if ( categories ) {
				$.extend( handler.allCategories, categories );
			}
			
			// Set the first dropdown to the first category in path
			if ( path.length > 0 ) {
				var $firstSelect = this.container.find( '.wc-facebook-google-product-category-select' ).first();
				if ( $firstSelect.length ) {
					$firstSelect.val( path[0] ).trigger( 'change.select2' );
					
					// Recursively build the rest of the path
					this.buildPathRecursively( path, 1 );
				}
			}
		},

		/**
		 * Recursively builds the category path by loading children and setting values
		 *
		 * @param {Array} path Array of category IDs
		 * @param {number} currentIndex Current index in the path
		 */
		buildPathRecursively: function( path, currentIndex ) {
			var handler = this;
			
			if ( currentIndex >= path.length ) {
				// Path reconstruction complete
				// Set the final value and trigger enhanced attributes
				this.input.val( path[ path.length - 1 ] );
				this.requestAttributesIfValid();
				return;
			}
			
			var currentCategoryId = path[ currentIndex - 1 ];
			var nextCategoryId = path[ currentIndex ];
			
			// Get children for current category
			var children = this.getChildrenOf( currentCategoryId );
			
			if ( children.length > 0 ) {
				// Add the children dropdown
				this.addChildrenSelect( children, currentIndex );
				
				// Set the value for this dropdown
				var $newSelect = this.container.find( '.wc-facebook-google-product-category-select' ).eq( currentIndex );
				if ( $newSelect.length ) {
					$newSelect.val( nextCategoryId ).trigger( 'change.select2' );
				}
				
				// Continue with next level
				setTimeout( function() {
					handler.buildPathRecursively( path, currentIndex + 1 );
				}, 100 ); // Small delay to allow dropdown to render
			} else {
				// Need to load children via AJAX first
				$.ajax({
					url: this.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wc_facebook_load_category_children',
						parent_id: currentCategoryId,
						nonce: wc_facebook_google_product_category_fields_params.nonce
					},
					success: function( response ) {
						if ( response.success && response.data ) {
							// Add to our categories cache
							$.extend( handler.allCategories, response.data );
							
							// Now get children and continue
							var children = handler.getChildrenOf( currentCategoryId );
							if ( children.length > 0 ) {
								handler.addChildrenSelect( children, currentIndex );
								
								// Set the value for this dropdown
								var $newSelect = handler.container.find( '.wc-facebook-google-product-category-select' ).eq( currentIndex );
								if ( $newSelect.length ) {
									$newSelect.val( nextCategoryId ).trigger( 'change.select2' );
				}
								
								// Continue with next level
								setTimeout( function() {
									handler.buildPathRecursively( path, currentIndex + 1 );
								}, 100 );
							}
						}
					},
					error: function( xhr, status, error ) {
						// Silent failure for missing children
					}
				});
		}
		}
	};
});
