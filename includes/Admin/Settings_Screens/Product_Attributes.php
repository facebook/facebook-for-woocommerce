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
 * @since 3.0.0
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
		$this->id                = self::ID;
		$this->label             = __( 'Product Attributes', 'facebook-for-woocommerce' );
		$this->title             = __( 'Product Attribute', 'facebook-for-woocommerce' );
	}


	/**
	 * Enqueues the assets.
	 *
	 * @internal
	 *
	 * @since 3.0.0
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
			\WC_Facebookcommerce::PLUGIN_VERSION
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
						security: '" . wp_create_nonce('fb_dismiss_attribute_notice') . "'
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
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_settings() {
		// The settings array is empty because we'll be rendering the content directly
		return array();
	}


	/**
	 * Custom rendering for the attribute mapping page.
	 *
	 * @since 3.0.0
	 */
	public function render() {
		$product_attributes = $this->get_product_attributes();
		$facebook_fields = $this->get_facebook_fields();
		$current_mappings = $this->get_saved_mappings();
		$saved_defaults = $this->get_saved_defaults();
		
		
		// Check if we're in edit mode (either via GET parameter or when there are no mappings)
		$edit_mode = isset($_GET['edit']) || empty($current_mappings);
		
		// Check for success message from redirect
		$show_success = isset($_GET['success']) && $_GET['success'] === '1';
		
		?>
		<div class="wrap woocommerce">
			<h2><?php esc_html_e('Map custom WooCommerce attributes to Meta', 'facebook-for-woocommerce'); ?></h2>
			<p><?php esc_html_e('Map your WooCommerce product attributes to Meta catalog attributes. This helps Meta properly display your products with the correct properties like size, color, gender, etc.', 'facebook-for-woocommerce'); ?> <a href="<?php echo esc_url(admin_url('edit.php?post_type=product&page=product_attributes')); ?>"><?php esc_html_e('Manage WooCommerce attributes', 'facebook-for-woocommerce'); ?></a>.</p>
			
			<?php if ($show_success): ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e('Product attribute mappings saved successfully.', 'facebook-for-woocommerce'); ?></p>
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
			
			<?php if ($edit_mode): ?>
				<!-- Edit Mode -->
				<form method="post" id="attribute-mapping-form" action="">
					<?php wp_nonce_field('wc_facebook_save_attribute_mappings', 'save_attribute_mappings_nonce'); ?>
					
					<table class="widefat" id="facebook-attribute-mapping-table">
						<thead>
							<tr>
								<th><?php esc_html_e('WooCommerce Attribute', 'facebook-for-woocommerce'); ?> <?php echo wc_help_tip(__('The product attribute from your store', 'facebook-for-woocommerce')); ?></th>
								<th><?php esc_html_e('Meta Attribute', 'facebook-for-woocommerce'); ?> <?php echo wc_help_tip(__('The corresponding Meta catalog field', 'facebook-for-woocommerce')); ?></th>
								<th><?php esc_html_e('Default Value', 'facebook-for-woocommerce'); ?> <?php echo wc_help_tip(__('Optional default value when attribute is not set', 'facebook-for-woocommerce')); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($current_mappings)) : ?>
								<tr class="fb-attribute-row">
									<td>
										<select name="wc_facebook_attribute_mapping[]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">
											<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>
											
											<?php foreach ($product_attributes as $attribute_id => $attribute_label) : ?>
												<option value="<?php echo esc_attr($attribute_id); ?>">
													<?php echo esc_html($attribute_label); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<select name="wc_facebook_field_mapping[]" class="fb-field-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">
											<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>
											
											<?php foreach ($facebook_fields as $field_id => $field_label) : ?>
												<option value="<?php echo esc_attr($field_id); ?>">
													<?php echo esc_html($field_label); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<input type="text" name="wc_facebook_attribute_default[]" class="fb-default-value" placeholder="<?php esc_attr_e('Optional', 'facebook-for-woocommerce'); ?>">
									</td>
									<td>
										<a href="#" class="remove-mapping-row" style="color: #a00;"><?php esc_html_e('Remove mapping', 'facebook-for-woocommerce'); ?></a>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ($current_mappings as $wc_attribute => $fb_field) : 
									$default_value = isset($saved_defaults[$wc_attribute]) ? $saved_defaults[$wc_attribute] : '';
									?>
									<tr class="fb-attribute-row">
										<td>
											<select name="wc_facebook_attribute_mapping[<?php echo esc_attr($wc_attribute); ?>]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">
												<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>
												
												<?php foreach ($product_attributes as $attribute_id => $attribute_label) : ?>
													<option value="<?php echo esc_attr($attribute_id); ?>" <?php selected($attribute_id, $wc_attribute); ?>>
														<?php echo esc_html($attribute_label); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
										<td>
											<select name="wc_facebook_field_mapping[<?php echo esc_attr($wc_attribute); ?>]" class="fb-field-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">
												<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>
												
												<?php foreach ($facebook_fields as $field_id => $field_label) : ?>
													<option value="<?php echo esc_attr($field_id); ?>" <?php selected($field_id, $fb_field); ?>>
														<?php echo esc_html($field_label); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</td>
										<td>
											<input type="text" name="wc_facebook_attribute_default[<?php echo esc_attr($wc_attribute); ?>]" class="fb-default-value" value="<?php echo esc_attr($default_value); ?>" placeholder="<?php esc_attr_e('Optional', 'facebook-for-woocommerce'); ?>">
										</td>
										<td>
											<a href="#" class="remove-mapping-row" style="color: #a00;"><?php esc_html_e('Remove mapping', 'facebook-for-woocommerce'); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
								
								<!-- Always add an empty row for new mappings -->
								<?php $unique_index = time(); ?>
								<tr class="fb-attribute-row">
									<td>
										<select name="wc_facebook_attribute_mapping[<?php echo $unique_index; ?>]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">
											<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>
											
											<?php foreach ($product_attributes as $attribute_id => $attribute_label) : ?>
												<option value="<?php echo esc_attr($attribute_id); ?>">
													<?php echo esc_html($attribute_label); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<select name="wc_facebook_field_mapping[<?php echo $unique_index; ?>]" class="fb-field-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">
											<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>
											
											<?php foreach ($facebook_fields as $field_id => $field_label) : ?>
												<option value="<?php echo esc_attr($field_id); ?>">
													<?php echo esc_html($field_label); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<input type="text" name="wc_facebook_attribute_default[<?php echo $unique_index; ?>]" class="fb-default-value" placeholder="<?php esc_attr_e('Optional', 'facebook-for-woocommerce'); ?>">
									</td>
									<td>
										<a href="#" class="remove-mapping-row" style="color: #a00;"><?php esc_html_e('Remove mapping', 'facebook-for-woocommerce'); ?></a>
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
					
					<p>
						<button type="button" class="button add-new-mapping" style="margin-top: 15px;"><?php esc_html_e('Add new mapping', 'facebook-for-woocommerce'); ?></button>
					</p>
					
					<p class="submit">
						<button type="submit" name="save_attribute_mappings" class="button button-primary" id="btn_save_fb_settings">
							<?php esc_html_e('Save Changes', 'facebook-for-woocommerce'); ?>
						</button>
						<?php if (!empty($current_mappings)): ?>
						<a href="<?php echo esc_url(remove_query_arg('edit')); ?>" class="button" style="margin-left: 10px;"><?php esc_html_e('Cancel', 'facebook-for-woocommerce'); ?></a>
						<?php endif; ?>
					</p>
					
				</form>
			<?php else: ?>
				<!-- View Mode -->
				<div id="attribute-mappings-view">
					<table class="widefat" id="facebook-attribute-mapping-table-view">
						<thead>
							<tr>
								<th><?php esc_html_e('WooCommerce Attribute', 'facebook-for-woocommerce'); ?></th>
								<th><?php esc_html_e('Meta Attribute', 'facebook-for-woocommerce'); ?></th>
								<th><?php esc_html_e('Default Value', 'facebook-for-woocommerce'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($current_mappings)) : ?>
								<tr>
									<td colspan="3" style="text-align: center; padding: 20px;"><?php esc_html_e('No attribute mappings configured.', 'facebook-for-woocommerce'); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ($current_mappings as $wc_attribute => $fb_field) : 
									$default_value = isset($saved_defaults[$wc_attribute]) ? $saved_defaults[$wc_attribute] : '';
									$wc_attribute_label = isset($product_attributes[$wc_attribute]) ? $product_attributes[$wc_attribute] : $wc_attribute;
									$fb_field_label = isset($facebook_fields[$fb_field]) ? $facebook_fields[$fb_field] : $fb_field;
									?>
									<tr>
										<td><?php echo esc_html($wc_attribute_label); ?></td>
										<td><?php echo esc_html($fb_field_label); ?></td>
										<td><?php echo !empty($default_value) ? esc_html($default_value) : '<em>' . esc_html__('None', 'facebook-for-woocommerce') . '</em>'; ?></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					
					<p style="margin-top: 15px;">
						<a href="<?php echo esc_url(add_query_arg('edit', '1', remove_query_arg('success'))); ?>" class="button button-primary"><?php esc_html_e('Edit Mappings', 'facebook-for-woocommerce'); ?></a>
					</p>
					
				</div>
			<?php endif; ?>
			
			<h3 style="margin-top: 30px;"><?php esc_html_e('Troubleshooting', 'facebook-for-woocommerce'); ?></h3>
			<div id="troubleshooting-section" style="margin-top: 10px; background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
				<p><?php esc_html_e('If your products are not displaying correctly in Meta:', 'facebook-for-woocommerce'); ?></p>
				<ul style="list-style-type: disc; margin-left: 20px;">
					<li><?php esc_html_e('Make sure your product attributes are correctly mapped to Meta catalog fields.', 'facebook-for-woocommerce'); ?></li>
					<li><?php esc_html_e('Check that required attributes like gender, size, and color are properly configured for relevant products.', 'facebook-for-woocommerce'); ?></li>
					<li><?php esc_html_e('After changing mappings, products will be updated during the next sync with Meta.', 'facebook-for-woocommerce'); ?></li>
					<li><a href="https://www.facebook.com/business/help/2302017289821154" target="_blank"><?php esc_html_e('Learn more about Meta catalog attributes', 'facebook-for-woocommerce'); ?></a></li>
				</ul>
			</div>
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
							$row.find('.fb-default-value').attr('name', 'wc_facebook_attribute_default[' + index + ']');
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
						'<select name="wc_facebook_attribute_mapping[' + newIndex + ']" class="wc-attribute-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">' +
						'<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>';
					
					// Add product attributes options
					<?php foreach ($product_attributes as $attribute_id => $attribute_label) : ?>
						newRowHtml += '<option value="<?php echo esc_attr($attribute_id); ?>"><?php echo esc_html($attribute_label); ?></option>';
					<?php endforeach; ?>
					
					newRowHtml += '</select>' +
						'</td>' +
						'<td>' +
						'<select name="wc_facebook_field_mapping[' + newIndex + ']" class="fb-field-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">' +
						'<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>';
					
					// Add Facebook fields options
					<?php foreach ($facebook_fields as $field_id => $field_label) : ?>
						newRowHtml += '<option value="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field_label); ?></option>';
					<?php endforeach; ?>
					
					newRowHtml += '</select>' +
						'</td>' +
						'<td>' +
						'<input type="text" name="wc_facebook_attribute_default[' + newIndex + ']" class="fb-default-value" placeholder="<?php esc_attr_e('Optional', 'facebook-for-woocommerce'); ?>">' +
						'</td>' +
						'<td>' +
						'<a href="#" class="remove-mapping-row" style="color: #a00;"><?php esc_html_e('Remove mapping', 'facebook-for-woocommerce'); ?></a>' +
						'</td>' +
						'</tr>';
					
					// Append the new row to the table
					var $tbody = $('#facebook-attribute-mapping-table tbody');
					$tbody.append(newRowHtml);
					
					// Initialize select2 on the new row's select elements
					var $newRow = $tbody.find('tr:last');
					$newRow.find('.wc-attribute-search, .fb-field-search').select2({
						width: '100%',
						placeholder: function() {
							return $(this).data('placeholder');
						}
					});
					
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
					window.location.href = "<?php echo esc_url(add_query_arg('edit', '1', remove_query_arg(array('success', 'new_row')))); ?>";
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
				$('#facebook-attribute-mapping-table').on('click', '.remove-mapping-row', function(e) {
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
						$row.find('.fb-default-value').attr('name', 'wc_facebook_attribute_default[' + currentIndex + ']');
					}
				});
			});
		</script>
		<?php
	}


	/**
	 * Renders the attribute mapping table.
	 *
	 * @since 3.0.0
	 *
	 * @param array $field Field data
	 */
	public function render_attribute_mapping_table_field($field) {
		// Prevent duplicate rendering by checking for a static flag
		static $rendered = false;
		
		if ($rendered) {
			return;
		}
		
		$rendered = true;
		
		$product_attributes = !empty($field['product_attributes']) ? $field['product_attributes'] : array();
		$facebook_fields = !empty($field['facebook_fields']) ? $field['facebook_fields'] : array();
		$current_mappings = !empty($field['current_mappings']) ? $field['current_mappings'] : array();
		
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php esc_html_e('WooCommerce to Facebook Field Mapping', 'facebook-for-woocommerce'); ?>
			</th>
			<td class="forminp">
				<table class="widefat striped" id="facebook-attribute-mapping-table">
					<thead>
						<tr>
							<th><?php esc_html_e('WooCommerce Attribute', 'facebook-for-woocommerce'); ?></th>
							<th><?php esc_html_e('Facebook Attribute', 'facebook-for-woocommerce'); ?></th>
							<th><?php esc_html_e('Default', 'facebook-for-woocommerce'); ?></th>
							<th><?php esc_html_e('Actions', 'facebook-for-woocommerce'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($current_mappings)) : ?>
							<tr class="no-items">
								<td class="colspanchange" colspan="4"><?php esc_html_e('No Facebook Product Attributes found.', 'facebook-for-woocommerce'); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ($current_mappings as $wc_attribute => $fb_field) : 
								$default_value = isset($saved_defaults[$wc_attribute]) ? $saved_defaults[$wc_attribute] : '';
								?>
								<tr class="fb-attribute-row">
									<td>
										<select name="wc_facebook_attribute_mapping[<?php echo esc_attr($wc_attribute); ?>]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e('Select a WooCommerce attribute...', 'facebook-for-woocommerce'); ?>">
											<option value=""><?php esc_html_e('Select a WooCommerce attribute...', 'facebook-for-woocommerce'); ?></option>
											
											<?php foreach ($product_attributes as $attribute_id => $attribute_label) : ?>
												<option value="<?php echo esc_attr($attribute_id); ?>" <?php selected($attribute_id, $wc_attribute); ?>>
													<?php echo esc_html($attribute_label); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<select name="wc_facebook_field_mapping[<?php echo esc_attr($wc_attribute); ?>]" class="fb-field-search" data-placeholder="<?php esc_attr_e('Select a Facebook attribute...', 'facebook-for-woocommerce'); ?>">
											<option value=""><?php esc_html_e('Select a Facebook attribute...', 'facebook-for-woocommerce'); ?></option>
											
											<?php foreach ($facebook_fields as $field_id => $field_label) : ?>
												<option value="<?php echo esc_attr($field_id); ?>" <?php selected($field_id, $fb_field); ?>>
													<?php echo esc_html($field_label); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<input type="text" class="fb-default-value" name="wc_facebook_attribute_default[<?php echo esc_attr($wc_attribute); ?>]" placeholder="<?php esc_attr_e('Enter default value (optional)', 'facebook-for-woocommerce'); ?>" value="<?php echo esc_attr($default_value); ?>">
									</td>
									<td>
										<a href="#" class="fb-attributes-remove" title="<?php esc_attr_e('Remove mapping', 'facebook-for-woocommerce'); ?>">
											<?php esc_html_e('Remove', 'facebook-for-woocommerce'); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
							
							<!-- Empty row template for new mappings -->
							<tr class="fb-attribute-row">
								<?php $unique_index = time(); ?>
								<td>
									<select name="wc_facebook_attribute_mapping[<?php echo $unique_index; ?>]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">
										<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>
										
										<?php foreach ($product_attributes as $attribute_id => $attribute_label) : ?>
											<option value="<?php echo esc_attr($attribute_id); ?>">
												<?php echo esc_html($attribute_label); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<select name="wc_facebook_field_mapping[<?php echo $unique_index; ?>]" class="fb-field-search" data-placeholder="<?php esc_attr_e('Select attribute', 'facebook-for-woocommerce'); ?>">
										<option value=""><?php esc_html_e('Select attribute', 'facebook-for-woocommerce'); ?></option>
										
										<?php foreach ($facebook_fields as $field_id => $field_label) : ?>
											<option value="<?php echo esc_attr($field_id); ?>">
												<?php echo esc_html($field_label); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="text" class="fb-default-value" name="wc_facebook_attribute_default[<?php echo $unique_index; ?>]" placeholder="<?php esc_attr_e('Enter default value (optional)', 'facebook-for-woocommerce'); ?>" value="">
								</td>
								<td>
									<a href="#" class="fb-attributes-remove" title="<?php esc_attr_e('Remove mapping', 'facebook-for-woocommerce'); ?>">
										<?php esc_html_e('Remove', 'facebook-for-woocommerce'); ?>
									</a>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="4">
								<button type="button" class="button button-secondary add-mapping-row">
									<?php esc_html_e('Add Mapping', 'facebook-for-woocommerce'); ?>
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
							newRow.find('input[type="text"]').val('');
							
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
								$(this).closest('tr').find('input[type="text"]').val('');
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
	 * @since 3.0.0
	 *
	 * @param array $field Field data
	 */
	public function render_info_note_field($field) {
		// Prevent duplicate rendering by checking for a hidden flag
		static $rendered = false;
		
		if ($rendered) {
			return;
		}
		
		$rendered = true;
		
		?>
		<tr valign="top">
			<td class="forminp" colspan="2">
				<div class="wc-facebook-info-note">
					<?php echo wp_kses_post($field['content']); ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Gets all WooCommerce product attributes.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_product_attributes() {
		$attributes = array();
		
		// Get all attribute taxonomies
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		
		if (!empty($attribute_taxonomies)) {
			foreach ($attribute_taxonomies as $taxonomy) {
				$attributes['pa_' . $taxonomy->attribute_name] = $taxonomy->attribute_label;
			}
		}
		
		return $attributes;
	}
	
	
	/**
	 * Gets all Facebook catalog fields.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_facebook_fields() {
		$fields = array();
		
		// Get all fields from the mapping class
		$all_fb_fields = ProductAttributeMapper::get_all_facebook_fields();
		
		// Format for the dropdown
		foreach ($all_fb_fields as $field_key => $field_variations) {
			$fields[$field_key] = ucfirst(str_replace('_', ' ', $field_key));
		}
		
		// Sort alphabetically
		asort($fields);
		
		return $fields;
	}
	
	
	/**
	 * Gets saved attribute mappings from database.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_saved_mappings() {
		$saved_mappings = get_option(self::OPTION_CUSTOM_ATTRIBUTE_MAPPINGS, array());
		
		if (!is_array($saved_mappings)) {
			$saved_mappings = array();
		}
		
		return $saved_mappings;
	}

	/**
	 * Gets saved default values for attributes.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	private function get_saved_defaults() {
		$saved_defaults = get_option('wc_facebook_attribute_defaults', array());
		
		if (!is_array($saved_defaults)) {
			$saved_defaults = array();
		}
		
		return $saved_defaults;
	}


	/**
	 * Saves the attribute mappings.
	 *
	 * @since 3.0.0
	 */
	public function save() {
		$this->process_form_submission();
	}

	/**
	 * Processes form submissions.
	 *
	 * @since 3.0.0
	 */
	public function process_form_submission() {
		// Check if we're on the right page
		if (!$this->is_current_screen_page()) {
			return;
		}
		
		// Get existing mappings and defaults
		$existing_mappings = $this->get_saved_mappings();
		$existing_defaults = $this->get_saved_defaults();
		
		// Process the new single form submission
		if (isset($_POST['save_attribute_mappings']) && 
			isset($_POST['save_attribute_mappings_nonce']) && 
			wp_verify_nonce($_POST['save_attribute_mappings_nonce'], 'wc_facebook_save_attribute_mappings')) {
			
			// Start with an empty array to rebuild the mappings
			$new_mappings = array();
			$new_defaults = array();
			
			// Process WooCommerce attribute => Facebook field mappings
			$wc_attributes = isset($_POST['wc_facebook_attribute_mapping']) ? (array) $_POST['wc_facebook_attribute_mapping'] : array();
			$fb_fields = isset($_POST['wc_facebook_field_mapping']) ? (array) $_POST['wc_facebook_field_mapping'] : array();
			$default_values = isset($_POST['wc_facebook_attribute_default']) ? (array) $_POST['wc_facebook_attribute_default'] : array();
			
			// Process all mappings (both associative array format and indexed array format)
			foreach ($wc_attributes as $key => $wc_attribute) {
				$wc_attribute = sanitize_text_field($wc_attribute);
				
				// Skip empty selections
				if (empty($wc_attribute)) {
					continue;
				}
				
				// Get corresponding Facebook field if it exists
				$fb_field = isset($fb_fields[$key]) ? sanitize_text_field($fb_fields[$key]) : '';
				
				// Skip if no Facebook field was selected
				if (empty($fb_field)) {
					continue;
				}
				
				// Add to mappings
				$new_mappings[$wc_attribute] = $fb_field;
				
				// Check for default value
				if (isset($default_values[$key]) && !empty($default_values[$key])) {
					$new_defaults[$wc_attribute] = sanitize_text_field($default_values[$key]);
				}
			}
			
			// Save the new mappings
			update_option(self::OPTION_CUSTOM_ATTRIBUTE_MAPPINGS, $new_mappings);
			update_option('wc_facebook_attribute_defaults', $new_defaults);
			
			// Update the static mapping in the ProductAttributeMapper class
			if (method_exists('WooCommerce\Facebook\ProductAttributeMapper', 'set_custom_attribute_mappings')) {
				ProductAttributeMapper::set_custom_attribute_mappings($new_mappings);
			}
			
			// Update last sync time
			update_option('wc_facebook_last_attribute_sync', current_time('mysql'));
			
			// Add success notice
			$this->add_notice(
				__('Product attribute mappings saved successfully.', 'facebook-for-woocommerce'),
				'success'
			);
			
			// Redirect back to view mode
			$redirect_url = add_query_arg('success', '1', remove_query_arg('edit'));
			wp_safe_redirect($redirect_url);
			exit;
		}
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
				$class = 'notice ' . ( $notice['type'] === 'success' ? 'notice-success' : 'notice-error' ) . ' is-dismissible fb-attributes-notice';
				
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
			$message_id = $this->generate_notice_id( $message );
			$notice_handler = facebook_for_woocommerce()->get_admin_notice_handler();
			
			if ( $notice_handler ) {
				$notice_handler->add_admin_notice(
					$message,
					$message_id,
					[
						'dismissible'    => true,
						'notice_class'   => 'notice-' . $type,
					]
				);
				
				return;
			}
		}
		
		// Fallback to the transient-based notice system
		$notices = get_transient( 'facebook_for_woocommerce_attribute_notices' ) ?: array();
		
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
	 * Renders a dropdown for condition values.
	 *
	 * @since 3.0.0
	 *
	 * @param string $attribute_name The attribute name
	 * @param string $selected_value The currently selected value
	 */
	private function render_condition_dropdown($attribute_name, $selected_value) {
		$options = array(
			'' => __('Select condition...', 'facebook-for-woocommerce'),
			'new' => __('New', 'facebook-for-woocommerce'),
			'used' => __('Used', 'facebook-for-woocommerce'),
			'refurbished' => __('Refurbished', 'facebook-for-woocommerce'),
		);
		
		$this->render_dropdown('condition', $attribute_name, $options, $selected_value);
	}

	/**
	 * Renders a dropdown for gender values.
	 *
	 * @since 3.0.0
	 *
	 * @param string $attribute_name The attribute name
	 * @param string $selected_value The currently selected value
	 */
	private function render_gender_dropdown($attribute_name, $selected_value) {
		$options = array(
			'' => __('Select gender...', 'facebook-for-woocommerce'),
			'male' => __('Male', 'facebook-for-woocommerce'),
			'female' => __('Female', 'facebook-for-woocommerce'),
			'unisex' => __('Unisex', 'facebook-for-woocommerce'),
		);
		
		$this->render_dropdown('gender', $attribute_name, $options, $selected_value);
	}

	/**
	 * Renders a dropdown for age group values.
	 *
	 * @since 3.0.0
	 *
	 * @param string $attribute_name The attribute name
	 * @param string $selected_value The currently selected value
	 */
	private function render_age_group_dropdown($attribute_name, $selected_value) {
		$options = array(
			'' => __('Select age group...', 'facebook-for-woocommerce'),
			'adult' => __('Adult', 'facebook-for-woocommerce'),
			'all ages' => __('All Ages', 'facebook-for-woocommerce'),
			'teen' => __('Teen', 'facebook-for-woocommerce'),
			'kids' => __('Kids', 'facebook-for-woocommerce'),
			'toddler' => __('Toddler', 'facebook-for-woocommerce'),
			'infant' => __('Infant', 'facebook-for-woocommerce'),
			'newborn' => __('Newborn', 'facebook-for-woocommerce'),
		);
		
		$this->render_dropdown('age_group', $attribute_name, $options, $selected_value);
	}

	/**
	 * Renders a dropdown for availability values.
	 *
	 * @since 3.0.0
	 *
	 * @param string $attribute_name The attribute name
	 * @param string $selected_value The currently selected value
	 */
	private function render_availability_dropdown($attribute_name, $selected_value) {
		$options = array(
			'' => __('Select availability...', 'facebook-for-woocommerce'),
			'in stock' => __('In Stock', 'facebook-for-woocommerce'),
			'out of stock' => __('Out of Stock', 'facebook-for-woocommerce'),
		);
		
		$this->render_dropdown('availability', $attribute_name, $options, $selected_value);
	}

	/**
	 * Renders a dropdown with the given options.
	 *
	 * @since 3.0.0
	 *
	 * @param string $field_type The field type
	 * @param string $attribute_name The attribute name
	 * @param array $options The dropdown options
	 * @param string $selected_value The currently selected value
	 */
	private function render_dropdown($field_type, $attribute_name, $options, $selected_value) {
		$name = 'wc_facebook_attribute_default_' . $field_type . '[' . esc_attr($attribute_name) . ']';
		$id = 'wc-facebook-default-' . $field_type . '-' . esc_attr($attribute_name);
		
		// Display the selected value as static text
		if (!empty($selected_value)) {
			$label = isset($options[$selected_value]) ? $options[$selected_value] : $selected_value;
			echo '<div class="static-value-display">' . esc_html($label) . '</div>';
		} else {
			echo '<div class="static-value-display static-value-empty">—</div>';
		}
		
		// Hidden field to store the actual value
		echo '<input type="hidden" name="wc_facebook_attribute_default[' . esc_attr($attribute_name) . ']" value="' . esc_attr($selected_value) . '" class="' . esc_attr($field_type) . '-default-value-' . esc_attr($attribute_name) . '">';
	}

	/**
	 * AJAX handler for dismissing notices.
	 * 
	 * @since 3.0.0
	 */
	public function ajax_dismiss_notice() {
		check_ajax_referer( 'fb_dismiss_attribute_notice', 'security' );
		
		if ( isset( $_POST['notice_id'] ) ) {
			$notice_id = sanitize_text_field( $_POST['notice_id'] );
			
			// Get current dismissed notices
			$dismissed_notices = get_user_meta( get_current_user_id(), 'facebook_wc_dismissed_attribute_notices', true );
			if ( ! is_array( $dismissed_notices ) ) {
				$dismissed_notices = array();
			}
			
			// Add this notice to dismissed list
			$dismissed_notices[] = $notice_id;
			$dismissed_notices = array_unique( $dismissed_notices );
			
			// Update user meta
			update_user_meta( get_current_user_id(), 'facebook_wc_dismissed_attribute_notices', $dismissed_notices );
			
			wp_send_json_success();
		}
		
		wp_send_json_error();
	}
} 