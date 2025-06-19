<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 *
 * Note: If you encounter issues with form submission, check the error logs.
 * Form processing happens in the process_form_submission() method.
 */

namespace WooCommerce\Facebook\Admin\Settings_Screens;

defined( 'ABSPATH' ) || exit;

/**
 * Note on attribute normalization:
 * While the ProductAttributeMapper class contains normalization functions for fields like condition, gender, and age_group,
 * this admin interface enforces the exact values required by Facebook's API through dropdown menus.
 * The normalization functions are still useful for handling product data that comes from other sources
 * (imports, bulk edits, API calls, etc.) but aren't needed for this UI since we're restricting input to valid values.
 */

use WooCommerce\Facebook\Admin\Abstract_Settings_Screen;
use WooCommerce\Facebook\ProductAttributeMapper;

/**
 * The product attributes settings screen.
 *
 * @since 3.5.4
 */
class Product_Attributes extends Abstract_Settings_Screen {

	/** @var string screen ID */
	const ID = 'product-attributes';

	/** @var string the option name for custom attribute mappings */
	const OPTION_CUSTOM_ATTRIBUTE_MAPPINGS = 'wc_facebook_custom_attribute_mappings';


	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'initHook' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_admin_field_attribute_mapping_table', array( $this, 'render_attribute_mapping_table_field' ) );
		add_action( 'woocommerce_admin_field_info_note', array( $this, 'render_info_note_field' ) );

		// Add hooks to process form submissions and display notices
		add_action( 'admin_init', array( $this, 'process_form_submission' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );

		// Add AJAX handler for notice dismissal
		add_action( 'wp_ajax_fb_dismiss_attribute_notice', array( $this, 'ajax_dismiss_notice' ) );
	}


	/**
	 * Initializes this settings page's properties.
	 */
	public function initHook() {
		$this->id    = self::ID;
		$this->label = __( 'Attribute Mapping', 'facebook-for-woocommerce' );
		$this->title = __( 'Attribute Mapping', 'facebook-for-woocommerce' );
	}


	/**
	 * Enqueues the assets.
	 *
	 * @internal
	 *
	 * @since 3.5.4
	 */
	public function enqueue_assets() {
		// Only load on our settings page
		if ( ! $this->is_current_screen_page() ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );

		wp_enqueue_script(
			'facebook-for-woocommerce-product-attributes',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/product-attributes.js',
			array( 'jquery', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION,
			true // Load in footer
		);

		// Add dismissible notice handlers
		wp_add_inline_script(
			'facebook-for-woocommerce-product-attributes',
			"
			jQuery(document).ready(function($) {
				// Make notices dismissible
				$(document).on('click', '.fb-attributes-notice .notice-dismiss', function() {
					var noticeEl = $(this).closest('.fb-attributes-notice');
					var noticeId = noticeEl.data('notice-id');
					
					// Hide the notice with animation
					noticeEl.slideUp('fast');
					
					// Send AJAX request to mark this notice as dismissed
					$.post(ajaxurl, {
						action: 'fb_dismiss_attribute_notice',
						notice_id: noticeId,
						security: '" . wp_create_nonce( 'fb_dismiss_attribute_notice' ) . "'
					});
				});
			});
			"
		);

		// Add custom CSS for the attribute mapping page
		wp_enqueue_style(
			'facebook-for-woocommerce-product-attributes',
			facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/facebook-for-woocommerce-product-attributes.css',
			[],
			\WC_Facebookcommerce::PLUGIN_VERSION
		);
	}


	/**
	 * Gets the screen's settings.
	 *
	 * @since 3.5.4
	 *
	 * @return array
	 */
	public function get_settings(): array {
		// The settings array is empty because we'll be rendering the content directly
		return array();
	}


	/**
	 * Custom rendering for the attribute mapping page.
	 *
	 * @since 3.5.4
	 */
	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Auto-cleanup orphaned mappings on page load
		$cleaned_count = $this->cleanup_orphaned_mappings();

		// Show cleanup notification if any mappings were removed
		if ( $cleaned_count > 0 ) {
			$message = sprintf(
				/* translators: %d: number of orphaned mappings cleaned up */
				_n(
					'Cleaned up %d obsolete attribute mapping for an attribute that no longer exists.',
					'Cleaned up %d obsolete attribute mappings for attributes that no longer exist.',
					$cleaned_count,
					'facebook-for-woocommerce'
				),
				$cleaned_count
			);
			?>
			<div class="notice notice-info is-dismissible">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
			<?php
		}

		$product_attributes = $this->get_product_attributes();
		$facebook_fields    = $this->get_facebook_fields();
		$current_mappings   = $this->get_saved_mappings();

		$edit_mode = isset( $_GET['edit'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'facebook_product_attributes_edit' );

		// Check for success message from redirect
		$show_success = isset( $_GET['success'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'facebook_product_attributes_success' ) && '1' === $_GET['success'];

		?>
		<div class="wrap woocommerce">
			<h2><?php esc_html_e( 'Attribute Mapping', 'facebook-for-woocommerce' ); ?></h2>
			<p><?php esc_html_e( 'Map your custom attributes in WooCommerce to Meta equivalents. This helps to sync and display your products with the right information in your Meta ads.', 'facebook-for-woocommerce' ); ?> <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=product_attributes' ) ); ?>"><?php esc_html_e( 'Manage WooCommerce attributes', 'facebook-for-woocommerce' ); ?></a>.</p>
			
			<?php if ( $show_success ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Product attribute mappings saved successfully.', 'facebook-for-woocommerce' ); ?></p>
			</div>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Remove success parameter from URL to prevent showing the message on page refresh
					if (window.history && window.history.replaceState) {
						var url = window.location.href;
						url = url.replace(/[?&]success=1/, '');
						window.history.replaceState({}, document.title, url);
					}
				});
			</script>
			<?php endif; ?>
			
			<?php if ( $edit_mode ) : ?>
				<!-- Edit Mode -->
				<form method="post" id="attribute-mapping-form" action="">
					<?php wp_nonce_field( 'wc_facebook_save_attribute_mappings', 'save_attribute_mappings_nonce' ); ?>
					
					<table class="widefat" id="facebook-attribute-mapping-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'WooCommerce Attribute', 'facebook-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Meta Attribute', 'facebook-for-woocommerce' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $current_mappings as $wc_attribute => $fb_field ) : ?>
								<tr class="fb-attribute-row">
									<td>
										<select name="wc_facebook_attribute_mapping[<?php echo esc_attr( $wc_attribute ); ?>]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e( 'Select attribute', 'facebook-for-woocommerce' ); ?>">
											<option value=""><?php esc_html_e( 'Select attribute', 'facebook-for-woocommerce' ); ?></option>
											<?php foreach ( $product_attributes as $attribute_id => $attribute_label ) : ?>
												<option value="<?php echo esc_attr( $attribute_id ); ?>" <?php selected( $attribute_id, $wc_attribute ); ?>><?php echo esc_html( $attribute_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<select name="wc_facebook_field_mapping[<?php echo esc_attr( $wc_attribute ); ?>]" class="fb-field-search" data-placeholder="<?php esc_attr_e( 'Select attribute', 'facebook-for-woocommerce' ); ?>">
											<option value=""><?php esc_html_e( 'Select attribute', 'facebook-for-woocommerce' ); ?></option>
											<?php foreach ( $facebook_fields as $field_id => $field_label ) : ?>
												<option value="<?php echo esc_attr( $field_id ); ?>" <?php selected( $field_id, $fb_field ); ?>><?php echo esc_html( $field_label ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<a href="#" class="remove-mapping" title="<?php esc_attr_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>">
											<?php esc_html_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
							
							<!-- Always have one empty row for new mappings -->
							<?php $unique_index = time(); ?>
							<tr class="fb-attribute-row">
								<td>
									<select name="wc_facebook_attribute_mapping[<?php echo esc_attr( $unique_index ); ?>]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e( 'Select attribute', 'facebook-for-woocommerce' ); ?>">
										<option value=""><?php esc_html_e( 'Select attribute', 'facebook-for-woocommerce' ); ?></option>
										<?php foreach ( $product_attributes as $attribute_id => $attribute_label ) : ?>
											<option value="<?php echo esc_attr( $attribute_id ); ?>"><?php echo esc_html( $attribute_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<select name="wc_facebook_field_mapping[<?php echo esc_attr( $unique_index ); ?>]" class="fb-field-search" data-placeholder="<?php esc_attr_e( 'Select attribute', 'facebook-for-woocommerce' ); ?>">
										<option value=""><?php esc_html_e( 'Select attribute', 'facebook-for-woocommerce' ); ?></option>
										<?php foreach ( $facebook_fields as $field_id => $field_label ) : ?>
											<option value="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<a href="#" class="remove-mapping" title="<?php esc_attr_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>">
										<?php esc_html_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>
									</a>
								</td>
							</tr>
							
							<!-- Add new mapping button row -->
							<tr class="add-mapping-row">
								<td colspan="3" style="text-align: left; padding: 12px;">
									<button type="button" class="button add-new-mapping"><?php esc_html_e( 'Add new mapping', 'facebook-for-woocommerce' ); ?></button>
								</td>
							</tr>
						</tbody>
					</table>
					
					<p class="submit">
						<button type="submit" name="save_attribute_mappings" class="button button-primary"><?php esc_html_e( 'Save Changes', 'facebook-for-woocommerce' ); ?></button>
						<a href="<?php echo esc_url( remove_query_arg( 'edit' ) ); ?>" class="button" style="margin-left: 10px;"><?php esc_html_e( 'Cancel', 'facebook-for-woocommerce' ); ?></a>
					</p>
				</form>
			<?php else : ?>
				<!-- View Mode -->
				<div id="attribute-mappings-view">
					<table class="widefat" id="facebook-attribute-mapping-table-view">
						<thead>
							<tr>
								<th><?php esc_html_e( 'WooCommerce Attribute', 'facebook-for-woocommerce' ); ?></th>
								<th><?php esc_html_e( 'Meta Attribute', 'facebook-for-woocommerce' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $current_mappings ) ) : ?>
								<tr>
									<td colspan="2" style="text-align: center; padding: 20px; color: #666; font-style: italic;">
										<?php esc_html_e( 'No attribute mappings configured.', 'facebook-for-woocommerce' ); ?>
									</td>
								</tr>
							<?php else : ?>
								<?php
								foreach ( $current_mappings as $wc_attribute => $fb_field ) :
									$wc_attribute_label = isset( $product_attributes[ $wc_attribute ] ) ? $product_attributes[ $wc_attribute ] : $wc_attribute;
									$fb_field_label     = isset( $facebook_fields[ $fb_field ] ) ? $facebook_fields[ $fb_field ] : $fb_field;
									?>
									<tr>
										<td><?php echo esc_html( $wc_attribute_label ); ?></td>
										<td><?php echo esc_html( $fb_field_label ); ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					
					<p style="margin-top: 15px;">
						<?php if ( empty( $current_mappings ) ) : ?>
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'edit', '1' ), 'facebook_product_attributes_edit', '_wpnonce' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Add new mapping', 'facebook-for-woocommerce' ); ?></a>
						<?php else : ?>
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'edit', '1' ), 'facebook_product_attributes_edit', '_wpnonce' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Edit Mappings', 'facebook-for-woocommerce' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
			<?php endif; ?>
			
		</div>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Initialize enhanced select boxes
				function initializeSelects() {
					if ($.fn.select2) {
						$('.wc-attribute-search, .fb-field-search').select2({
							width: '100%',
							placeholder: function() {
								return $(this).data('placeholder');
							}
						});
					}
				}
				
				// Initialize on page load
				initializeSelects();
				
				// Update the template row with a unique index based on position
				function updateEmptyRowIndices() {
					// Find all rows with placeholder indices (999)
					$('#facebook-attribute-mapping-table tbody tr').each(function(index) {
						var $row = $(this);
						
						// Check if this row has the placeholder index [999]
						var $wcAttrField = $row.find('.wc-attribute-search');
						var nameAttr = $wcAttrField.attr('name') || '';
						
						if (nameAttr.indexOf('[999]') > -1) {
							// Update all field names in this row with the current index
							$row.find('.wc-attribute-search').attr('name', 'wc_facebook_attribute_mapping[' + index + ']');
							$row.find('.fb-field-search').attr('name', 'wc_facebook_field_mapping[' + index + ']');
						}
					});
				}
				
				// Initialize the row indices on page load
				updateEmptyRowIndices();
				
				// Add new mapping row in edit mode
				$('.add-new-mapping').on('click', function(e) {
					e.preventDefault();
					
					// Generate a unique timestamp for field names
					var newIndex = Date.now();
					
					// Create an empty row structure directly rather than cloning
					var newRowHtml = '<tr class="fb-attribute-row">' +
						'<td>' +
						'<select name="wc_facebook_attribute_mapping[' + newIndex + ']" class="wc-attribute-search" data-placeholder="<?php esc_attr_e( 'Select attribute', 'facebook-for-woocommerce' ); ?>">' +
						'<option value=""><?php esc_html_e( 'Select attribute', 'facebook-for-woocommerce' ); ?></option>';
					
					// Add product attributes options
					<?php foreach ( $product_attributes as $attribute_id => $attribute_label ) : ?>
						newRowHtml += '<option value="<?php echo esc_attr( $attribute_id ); ?>"><?php echo esc_html( $attribute_label ); ?></option>';
					<?php endforeach; ?>
					
					newRowHtml += '</select>' +
						'</td>' +
						'<td>' +
						'<select name="wc_facebook_field_mapping[' + newIndex + ']" class="fb-field-search" data-placeholder="<?php esc_attr_e( 'Select attribute', 'facebook-for-woocommerce' ); ?>">' +
						'<option value=""><?php esc_html_e( 'Select attribute', 'facebook-for-woocommerce' ); ?></option>';
					
					// Add Facebook fields options
					<?php foreach ( $facebook_fields as $field_id => $field_label ) : ?>
						newRowHtml += '<option value="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $field_label ); ?></option>';
					<?php endforeach; ?>
					
					newRowHtml += '</select>' +
						'</td>' +
						'<td>' +
						'<a href="#" class="remove-mapping" title="<?php esc_attr_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>">' +
						'<?php esc_html_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>' +
						'</a>' +
						'</td>' +
						'</tr>';
					
					// Insert the new row before the "Add new mapping" button row
					var $tbody = $('#facebook-attribute-mapping-table tbody');
					var $addButtonRow = $tbody.find('.add-mapping-row');
					$addButtonRow.before(newRowHtml);
					
					// Initialize select2 on the new row's select elements
					var $newRow = $addButtonRow.prev();
					$newRow.find('.wc-attribute-search, .fb-field-search').select2({
						width: '100%',
						placeholder: function() {
							return $(this).data('placeholder');
						}
					});
					
					// Update disabled states after adding new row
					setTimeout(function() {
						updateDisabledAttributes();
					}, 200);
					
					// Focus the first select field in the new row
					setTimeout(function() {
						$newRow.find('.wc-attribute-search').select2('open');
					}, 100);
					
					// Scroll to the newly added row
					$('html, body').animate({
						scrollTop: $newRow.offset().top - 100
					}, 500);
				});
				
				// Add new mapping from view mode - directly goes to edit mode and sets a flag for adding a row
				$('.add-new-mapping-btn').on('click', function(e) {
					e.preventDefault();
					// Store flag in localStorage to add new row after page loads in edit mode
					localStorage.setItem('fb_add_new_mapping_row', 'true');
					// Redirect to edit mode
					window.location.href = "<?php echo esc_url( add_query_arg( 'edit', '1', remove_query_arg( array( 'success', 'new_row' ) ) ) ); ?>";
				});
				
				// Check if we need to add a new row after page load (coming from view mode)
				if (localStorage.getItem('fb_add_new_mapping_row') === 'true') {
					// Clear the flag immediately to prevent it from persisting
					localStorage.removeItem('fb_add_new_mapping_row');
					
					// Wait for DOM and Select2 to be fully initialized
					setTimeout(function() {
						// Add a new row by clicking the button
						$('.add-new-mapping').trigger('click');
					}, 500);
				}
				
				// Remove mapping row
				$('#facebook-attribute-mapping-table').on('click', '.remove-mapping', function(e) {
					e.preventDefault();
					
					// Don't remove if it's the only row
					if ($('#facebook-attribute-mapping-table tbody tr').length > 1) {
						$(this).closest('tr').remove();
					} else {
						// Clear values instead
						$(this).closest('tr').find('select').val('').trigger('change');
						$(this).closest('tr').find('input[type="text"]').val('');
						$(this).closest('tr').find('input[type="hidden"]').remove();
					}
				});
				
				// Handle WooCommerce attribute changes to update field names
				$('#facebook-attribute-mapping-table').on('change', '.wc-attribute-search', function() {
					var $select = $(this);
					var $row = $select.closest('tr');
					var attribute = $select.val();
					
					// Extract the current index from the field name
					var currentName = $select.attr('name');
					var matches = currentName.match(/\[(.*?)\]/);
					var currentIndex = matches ? matches[1] : '';
					
					// For numerical indices, we should preserve the index for all fields in the row
					// This ensures form fields remain correctly associated with each other
					if (currentIndex && !isNaN(parseInt(currentIndex))) {
						// Always use the same index for all fields in this row
						$row.find('.fb-field-search').attr('name', 'wc_facebook_field_mapping[' + currentIndex + ']');
					}
					
					// Update disabled states for 1:1 mapping
					updateDisabledAttributes();
				});
				
				// Handle Meta attribute changes to update disabled states
				$('#facebook-attribute-mapping-table').on('change', '.fb-field-search', function() {
					updateDisabledAttributes();
				});
				
				// Handle row removal to update disabled states
				$('#facebook-attribute-mapping-table').on('click', '.remove-mapping', function(e) {
					var $row = $(this).closest('tr');
					setTimeout(function() {
						updateDisabledAttributes();
					}, 100);
				});
				
				// Function to update disabled attributes for 1:1 mapping
				function updateDisabledAttributes() {
					// Get all currently selected WooCommerce attributes
					var selectedWcAttributes = [];
					$('.wc-attribute-search').each(function() {
						var value = $(this).val();
						if (value && value !== '') {
							selectedWcAttributes.push(value);
						}
					});
					
					// Get all currently selected Meta attributes
					var selectedMetaAttributes = [];
					$('.fb-field-search').each(function() {
						var value = $(this).val();
						if (value && value !== '') {
							selectedMetaAttributes.push(value);
						}
					});
					
					// Update WooCommerce attribute dropdowns
					$('.wc-attribute-search').each(function() {
						var $select = $(this);
						var currentValue = $select.val();
						
						$select.find('option').each(function() {
							var $option = $(this);
							var optionValue = $option.val();
							
							if (optionValue === '' || optionValue === currentValue) {
								// Always enable empty option and current selection
								$option.prop('disabled', false).css({
									'color': '',
									'background-color': ''
								});
							} else if (selectedWcAttributes.includes(optionValue)) {
								// Disable if selected elsewhere
								$option.prop('disabled', true).css({
									'color': '#999',
									'background-color': '#f5f5f5'
								});
							} else {
								// Enable if not selected elsewhere
								$option.prop('disabled', false).css({
									'color': '',
									'background-color': ''
								});
							}
						});
						
						// Refresh Select2 if it exists
						if ($.fn.select2 && $select.hasClass('select2-hidden-accessible')) {
							$select.select2('destroy').select2({
								width: '100%',
								placeholder: function() {
									return $(this).data('placeholder');
								}
							});
						}
					});
					
					// Update Meta attribute dropdowns
					$('.fb-field-search').each(function() {
						var $select = $(this);
						var currentValue = $select.val();
						
						$select.find('option').each(function() {
							var $option = $(this);
							var optionValue = $option.val();
							
							if (optionValue === '' || optionValue === currentValue) {
								// Always enable empty option and current selection
								$option.prop('disabled', false).css({
									'color': '',
									'background-color': ''
								});
							} else if (selectedMetaAttributes.includes(optionValue)) {
								// Disable if selected elsewhere
								$option.prop('disabled', true).css({
									'color': '#999',
									'background-color': '#f5f5f5'
								});
							} else {
								// Enable if not selected elsewhere
								$option.prop('disabled', false).css({
									'color': '',
									'background-color': ''
								});
							}
						});
						
						// Refresh Select2 if it exists
						if ($.fn.select2 && $select.hasClass('select2-hidden-accessible')) {
							$select.select2('destroy').select2({
								width: '100%',
								placeholder: function() {
									return $(this).data('placeholder');
								}
							});
						}
					});
				}
				
				// Initialize disabled states on page load
				setTimeout(function() {
					updateDisabledAttributes();
				}, 500);
			});
		</script>
		<?php
	}


	/**
	 * Renders the attribute mapping table.
	 *
	 * @since 3.5.4
	 *
	 * @param array $field Field data
	 */
	public function render_attribute_mapping_table_field( $field ) {
		// Prevent duplicate rendering by checking for a static flag
		static $rendered = false;

		if ( $rendered ) {
			return;
		}

		$rendered = true;

		$product_attributes = ! empty( $field['product_attributes'] ) ? $field['product_attributes'] : array();
		$facebook_fields    = ! empty( $field['facebook_fields'] ) ? $field['facebook_fields'] : array();
		$current_mappings   = ! empty( $field['current_mappings'] ) ? $field['current_mappings'] : array();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php esc_html_e( 'WooCommerce to Facebook Field Mapping', 'facebook-for-woocommerce' ); ?>
			</th>
			<td class="forminp">
				<table class="widefat striped" id="facebook-attribute-mapping-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'WooCommerce Attribute', 'facebook-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Facebook Attribute', 'facebook-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'facebook-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $current_mappings ) ) : ?>
							<tr class="no-items">
								<td class="colspanchange" colspan="3"><?php esc_html_e( 'No Facebook Product Attributes found.', 'facebook-for-woocommerce' ); ?></td>
							</tr>
						<?php else : ?>
							<?php
							foreach ( $current_mappings as $wc_attribute => $fb_field ) :
								?>
								<tr class="fb-attribute-row">
									<td>
										<select name="wc_facebook_attribute_mapping[<?php echo esc_attr( $wc_attribute ); ?>]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e( 'Select a WooCommerce attribute...', 'facebook-for-woocommerce' ); ?>">
											<option value=""><?php esc_html_e( 'Select a WooCommerce attribute...', 'facebook-for-woocommerce' ); ?></option>
											
											<?php foreach ( $product_attributes as $attribute_id => $attribute_label ) : ?>
												<option value="<?php echo esc_attr( $attribute_id ); ?>" <?php selected( $attribute_id, $wc_attribute ); ?>>
													<?php echo esc_html( $attribute_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<select name="wc_facebook_field_mapping[<?php echo esc_attr( $wc_attribute ); ?>]" class="fb-field-search" data-placeholder="<?php esc_attr_e( 'Select a Facebook attribute...', 'facebook-for-woocommerce' ); ?>">
											<option value=""><?php esc_html_e( 'Select a Facebook attribute...', 'facebook-for-woocommerce' ); ?></option>
											
											<?php foreach ( $facebook_fields as $field_id => $field_label ) : ?>
												<option value="<?php echo esc_attr( $field_id ); ?>" <?php selected( $field_id, $fb_field ); ?>>
													<?php echo esc_html( $field_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<a href="#" class="fb-attributes-remove" title="<?php esc_attr_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>">
											<?php esc_html_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
							
							<!-- Empty row template for new mappings -->
							<tr class="fb-attribute-row">
								<?php $unique_index = time(); ?>
								<td>
									<select name="wc_facebook_attribute_mapping[<?php echo esc_attr( $unique_index ); ?>]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e( 'Select attribute', 'facebook-for-woocommerce' ); ?>">
										<option value=""><?php esc_html_e( 'Select attribute', 'facebook-for-woocommerce' ); ?></option>
										
										<?php foreach ( $product_attributes as $attribute_id => $attribute_label ) : ?>
											<option value="<?php echo esc_attr( $attribute_id ); ?>">
												<?php echo esc_html( $attribute_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<select name="wc_facebook_field_mapping[<?php echo esc_attr( $unique_index ); ?>]" class="fb-field-search" data-placeholder="<?php esc_attr_e( 'Select attribute', 'facebook-for-woocommerce' ); ?>">
										<option value=""><?php esc_html_e( 'Select attribute', 'facebook-for-woocommerce' ); ?></option>
										
										<?php foreach ( $facebook_fields as $field_id => $field_label ) : ?>
											<option value="<?php echo esc_attr( $field_id ); ?>">
												<?php echo esc_html( $field_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<a href="#" class="fb-attributes-remove" title="<?php esc_attr_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>">
										<?php esc_html_e( 'Remove mapping', 'facebook-for-woocommerce' ); ?>
									</a>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="3">
								<button type="button" class="button button-secondary add-mapping-row">
									<?php esc_html_e( 'Add Mapping', 'facebook-for-woocommerce' ); ?>
								</button>
							</td>
						</tr>
					</tfoot>
				</table>
				
				<script type="text/javascript">
					jQuery(document).ready(function($) {
						// Add new mapping row
						$('.add-mapping-row').on('click', function() {
							var newRow = $('#facebook-attribute-mapping-table tbody tr:last-child').clone();
							
							// Clear values
							newRow.find('select').val('').trigger('change');
							
							// Reinitialize select2 if it exists
							if ($.fn.select2) {
								newRow.find('select').select2('destroy').select2({
									width: '100%',
									placeholder: function() {
										return $(this).data('placeholder');
									}
								});
							}
							
							// Append to table
							$('#facebook-attribute-mapping-table tbody').append(newRow);
						});
						
						// Remove mapping row
						$('#facebook-attribute-mapping-table').on('click', '.fb-attributes-remove', function(e) {
							e.preventDefault();
							
							// Don't remove if it's the only row
							if ($('#facebook-attribute-mapping-table tbody tr').length > 1) {
								$(this).closest('tr').remove();
							} else {
								// Clear values instead
								$(this).closest('tr').find('select').val('').trigger('change');
							}
						});
						
						// Initialize enhanced select boxes
						if ($.fn.select2) {
							$('.wc-attribute-search, .fb-field-search').select2({
								width: '100%',
								placeholder: function() {
									return $(this).data('placeholder');
								}
							});
						}
					});
				</script>
			</td>
		</tr>
		<?php
	}


	/**
	 * Renders an info note field.
	 *
	 * @since 3.5.4
	 *
	 * @param array $field Field data
	 */
	public function render_info_note_field( $field ) {
		// Prevent duplicate rendering by checking for a hidden flag
		static $rendered = false;

		if ( $rendered ) {
			return;
		}

		$rendered = true;

		?>
		<tr valign="top">
			<td class="forminp" colspan="2">
				<div class="wc-facebook-info-note">
					<?php echo wp_kses_post( $field['content'] ); ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Gets all WooCommerce product attributes.
	 *
	 * @since 3.5.4
	 *
	 * @return array
	 */
	private function get_product_attributes() {
		$attributes = array();

		// Get all attribute taxonomies
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( ! empty( $attribute_taxonomies ) ) {
			foreach ( $attribute_taxonomies as $taxonomy ) {
				$attributes[ 'pa_' . $taxonomy->attribute_name ] = $taxonomy->attribute_label;
			}
		}

		return $attributes;
	}


	/**
	 * Gets all Facebook catalog fields.
	 *
	 * @since 3.5.4
	 *
	 * @return array
	 */
	private function get_facebook_fields() {
		$fields = array();

		// Get all fields from the mapping class
		$all_fb_fields = ProductAttributeMapper::get_all_facebook_fields();

		// Format for the dropdown
		foreach ( $all_fb_fields as $field_key => $field_variations ) {
			$fields[ $field_key ] = ucfirst( str_replace( '_', ' ', $field_key ) );
		}

		// Sort alphabetically
		asort( $fields );

		return $fields;
	}


	/**
	 * Gets saved attribute mappings from database.
	 *
	 * @since 3.5.4
	 *
	 * @return array
	 */
	private function get_saved_mappings() {
		$saved_mappings = get_option( self::OPTION_CUSTOM_ATTRIBUTE_MAPPINGS, array() );

		if ( ! is_array( $saved_mappings ) ) {
			$saved_mappings = array();
		}

		return $saved_mappings;
	}

	/**
	 * Saves the attribute mappings.
	 *
	 * @since 3.5.4
	 */
	public function save() {
		$this->process_form_submission();
	}

	/**
	 * Processes form submissions.
	 *
	 * @since 3.5.4
	 */
	public function process_form_submission() {
		if ( ! isset( $_POST['save_attribute_mappings'], $_POST['save_attribute_mappings_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['save_attribute_mappings_nonce'] ) ), 'wc_facebook_save_attribute_mappings' ) ) {
			return;
		}

		// Initialize new mappings as empty array
		$new_mappings = array();

		// Process the form submission (even if arrays are empty)
		if ( isset( $_POST['wc_facebook_attribute_mapping'] ) || isset( $_POST['wc_facebook_field_mapping'] ) ) {
			// Get the submitted data (handle empty arrays)
			$wc_attributes = isset( $_POST['wc_facebook_attribute_mapping'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['wc_facebook_attribute_mapping'] ) ) : array();
			$fb_fields     = isset( $_POST['wc_facebook_field_mapping'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['wc_facebook_field_mapping'] ) ) : array();

			// Get current valid attributes to ensure we only save valid mappings
			$current_attributes = $this->get_product_attributes();

			// Process WooCommerce attribute => Facebook field mappings
			foreach ( $wc_attributes as $key => $wc_attribute ) {
				// Skip empty attributes
				if ( empty( $wc_attribute ) ) {
					continue;
				}

				// Skip if the attribute doesn't exist (additional safety check)
				if ( ! isset( $current_attributes[ $wc_attribute ] ) ) {
					continue;
				}

				// Get the corresponding Facebook field
				$fb_field = isset( $fb_fields[ $key ] ) ? $fb_fields[ $key ] : '';

				// Skip if no Facebook field is selected
				if ( empty( $fb_field ) ) {
					continue;
				}

				// Clean the data
				$wc_attribute = sanitize_text_field( $wc_attribute );
				$fb_field     = sanitize_text_field( $fb_field );

				// Add to new mappings
				$new_mappings[ $wc_attribute ] = $fb_field;
			}
		}

		// Save the new mappings (even if empty)
		update_option( self::OPTION_CUSTOM_ATTRIBUTE_MAPPINGS, $new_mappings );

		// Update the static mapping in the ProductAttributeMapper class
		if ( method_exists( 'WooCommerce\Facebook\ProductAttributeMapper', 'set_custom_attribute_mappings' ) ) {
			ProductAttributeMapper::set_custom_attribute_mappings( $new_mappings );
		}

		// Update last sync time
		update_option( 'wc_facebook_last_attribute_sync', current_time( 'mysql' ) );

		// Add success notice
		$message = empty( $new_mappings )
			? __( 'All attribute mappings have been removed.', 'facebook-for-woocommerce' )
			: __( 'Product attribute mappings saved successfully.', 'facebook-for-woocommerce' );

		$this->add_notice( $message, 'success' );

		// Redirect back to view mode
		$redirect_url = add_query_arg( 'success', '1', remove_query_arg( 'edit' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Displays admin notices for this screen.
	 */
	public function display_admin_notices() {
		// Only show notices on our settings page
		if ( ! $this->is_current_screen_page() ) {
			return;
		}

		$notices = get_transient( 'facebook_for_woocommerce_attribute_notices' );

		if ( ! empty( $notices ) ) {
			foreach ( $notices as $key => $notice ) {
				$notice_id = 'fb-attributes-notice-' . $key;
				$class     = 'notice ' . ( 'success' === $notice['type'] ? 'notice-success' : 'notice-error' ) . ' is-dismissible fb-attributes-notice';

				?>
				<div id="<?php echo esc_attr( $notice_id ); ?>" class="<?php echo esc_attr( $class ); ?>" data-notice-id="<?php echo esc_attr( $notice_id ); ?>">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
					<button type="button" class="notice-dismiss">
						<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'facebook-for-woocommerce' ); ?></span>
					</button>
				</div>
				<?php
			}

			// Clear the notices
			delete_transient( 'facebook_for_woocommerce_attribute_notices' );
		}
	}

	/**
	 * Adds an admin notice for this screen.
	 *
	 * @param string $message The notice message.
	 * @param string $type The notice type (success or error).
	 */
	private function add_notice( $message, $type = 'success' ) {
		// Try to use the framework's AdminNoticeHandler if available
		if ( class_exists( '\WooCommerce\Facebook\Framework\AdminNoticeHandler' ) ) {
			$message_id     = $this->generate_notice_id( $message );
			$notice_handler = facebook_for_woocommerce()->get_admin_notice_handler();

			if ( $notice_handler ) {
				$notice_handler->add_admin_notice(
					$message,
					$message_id,
					[
						'dismissible'  => true,
						'notice_class' => 'notice-' . $type,
					]
				);

				return;
			}
		}

		// Fallback to the transient-based notice system
		$notices = isset( $notices ) && is_array( $notices ) ? $notices : array();

		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);

		set_transient( 'facebook_for_woocommerce_attribute_notices', $notices, 60 * 5 ); // 5 minutes expiration
	}

	/**
	 * Generates a unique notice ID based on the message content.
	 *
	 * @param string $message The notice message.
	 * @return string The generated notice ID.
	 */
	private function generate_notice_id( $message ) {
		return 'facebook_attribute_' . md5( $message . time() );
	}

	/**
	 * AJAX handler for dismissing admin notices.
	 */
	public function ajax_dismiss_notice() {
		// Only handle requests for this specific page
		if ( ! $this->is_current_screen_page() ) {
			wp_die( -1, 403 );
		}

		// Check nonce for security
		check_ajax_referer( 'dismiss_facebook_attributes_notice', 'nonce' );

		$notice_id = '';
		if ( isset( $_POST['notice_id'] ) ) {
			$notice_id = sanitize_text_field( wp_unslash( $_POST['notice_id'] ) );
		}

		if ( $notice_id ) {
			// Clear the notices - this is a simple implementation
			delete_transient( 'facebook_for_woocommerce_attribute_notices' );
		}

		wp_die(); // this is required to terminate immediately and return a proper response
	}

	/**
	 * Detects orphaned attribute mappings (mappings that reference non-existent attributes).
	 *
	 * @since 3.5.4
	 *
	 * @return array Array of orphaned mapping keys
	 */
	private function get_orphaned_mappings() {
		$saved_mappings     = $this->get_saved_mappings();
		$current_attributes = $this->get_product_attributes();
		$orphaned           = array();

		foreach ( $saved_mappings as $wc_attribute => $fb_field ) {
			if ( ! isset( $current_attributes[ $wc_attribute ] ) ) {
				$orphaned[] = $wc_attribute;
			}
		}

		return $orphaned;
	}

	/**
	 * Removes orphaned attribute mappings from the database.
	 *
	 * @since 3.5.4
	 *
	 * @return int Number of orphaned mappings removed
	 */
	private function cleanup_orphaned_mappings() {
		$saved_mappings     = $this->get_saved_mappings();
		$current_attributes = $this->get_product_attributes();
		$cleaned_mappings   = array();
		$removed_count      = 0;

		foreach ( $saved_mappings as $wc_attribute => $fb_field ) {
			if ( isset( $current_attributes[ $wc_attribute ] ) ) {
				// Keep valid mappings
				$cleaned_mappings[ $wc_attribute ] = $fb_field;
			} else {
				// Count orphaned mappings
				++$removed_count;
			}
		}

		// Only update if we actually removed something
		if ( $removed_count > 0 ) {
			// Update the option with cleaned mappings
			update_option( self::OPTION_CUSTOM_ATTRIBUTE_MAPPINGS, $cleaned_mappings );

			// Update the static mapping in the ProductAttributeMapper class
			if ( method_exists( 'WooCommerce\Facebook\ProductAttributeMapper', 'set_custom_attribute_mappings' ) ) {
				ProductAttributeMapper::set_custom_attribute_mappings( $cleaned_mappings );
			}
		}

		return $removed_count;
	}
}
