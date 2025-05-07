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
		if ( ! $this->is_current_screen_page() ) {
			return;
		}

		wp_enqueue_script(
			'facebook-for-woocommerce-product-attributes',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/product-attributes.js',
			array( 'jquery', 'jquery-tiptip', 'wc-enhanced-select' ),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);

		// Add custom CSS for the attribute mapping page
		wp_add_inline_style(
			'woocommerce_admin_styles',
			'
			/* Facebook Product Attributes Styles */
			.facebook-woo-attributes-screen {
				background-color: #f0f0f1;
				padding: 20px;
				margin: -10px -20px;
				max-width: none;
			}
			
			.facebook-woo-attributes-screen h1 {
				margin-bottom: 15px;
			}
			
			.facebook-attributes-form-section {
				margin-bottom: 20px;
			}
			
			.facebook-attributes-form-section h2 {
				font-size: 16px;
				font-weight: 600;
				margin: 0 0 15px 0;
			}
			
			.form-field {
				margin-bottom: 15px;
			}
			
			.form-field label {
				display: block;
				margin-bottom: 5px;
				font-weight: 600;
			}
			
			.form-field input,
			.form-field textarea {
				width: 100%;
				max-width: 100%;
				padding: 8px;
			}
			
			.form-field .description {
				color: #666;
				font-size: 13px;
				margin: 5px 0 0 0;
			}
			
			.submit {
				margin: 15px 0 0 0;
				padding: 0;
			}
			
			.facebook-attribute-table {
				background: #fff;
				border: 1px solid #ddd;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			
			.facebook-attribute-table th {
				font-weight: 600;
			}
			
			.facebook-attribute-table .column-name {
				width: 30%;
			}
			
			.facebook-attribute-table .column-description {
				width: 40%;
			}
			
			.facebook-attribute-table .column-slug {
				width: 25%;
			}
			
			.facebook-attribute-table .check-column {
				width: 5%;
			}
			
			.facebook-attribute-table .no-items td {
				text-align: center;
				padding: 15px;
			}
			'
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
		
		// Get the last synchronization time
		$last_sync = get_option('wc_facebook_last_attribute_sync', '');
		if (empty($last_sync)) {
			$last_sync = current_time('mysql');
		}
		$last_sync_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync));
		
		?>
		<div class="wrap woocommerce facebook-woo-attributes-screen">
			<h1><?php esc_html_e('Facebook Product Attributes', 'facebook-for-woocommerce'); ?></h1>
			
			<p><?php esc_html_e('Map your WooCommerce product attributes to Facebook catalog attributes. This helps Facebook properly display your products with the correct properties like size, color, gender, etc.', 'facebook-for-woocommerce'); ?></p>
			
			<div style="display: flex; gap: 20px; margin-top: 20px;">
				<!-- Left column: Form to add new attribute mapping -->
				<div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ddd;">
					<h2><?php esc_html_e('Add new Facebook Product Attribute Mapping', 'facebook-for-woocommerce'); ?></h2>
					
					<form method="post" id="add-attribute-form" action=""><?php // empty action will submit to the current page ?>
						<div class="form-field">
							<label for="wc-facebook-attribute"><?php esc_html_e('WooCommerce Attribute', 'facebook-for-woocommerce'); ?></label>
							<select id="wc-facebook-attribute" name="wc_facebook_attribute" class="wc-enhanced-select" style="width: 100%;">
								<option value=""><?php esc_html_e('Select a WooCommerce attribute...', 'facebook-for-woocommerce'); ?></option>
								<?php foreach ($product_attributes as $attribute_id => $attribute_label) : ?>
									<option value="<?php echo esc_attr($attribute_id); ?>"><?php echo esc_html($attribute_label); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e('Select the WooCommerce product attribute to map.', 'facebook-for-woocommerce'); ?></p>
						</div>
						
						<div class="form-field">
							<label for="wc-facebook-field"><?php esc_html_e('Facebook Attribute', 'facebook-for-woocommerce'); ?></label>
							<select id="wc-facebook-field" name="wc_facebook_field" class="wc-enhanced-select" style="width: 100%;">
								<option value=""><?php esc_html_e('Select a Facebook attribute...', 'facebook-for-woocommerce'); ?></option>
								<?php foreach ($facebook_fields as $field_id => $field_label) : ?>
									<option value="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($field_label); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e('Select the Facebook catalog attribute this maps to.', 'facebook-for-woocommerce'); ?></p>
						</div>
						
						<div class="form-field">
							<label for="wc-facebook-default"><?php esc_html_e('Default Value', 'facebook-for-woocommerce'); ?></label>
							<input type="text" id="wc-facebook-default" name="wc_facebook_default" class="regular-text" />
							<p class="description"><?php esc_html_e('Optional default value to use when the attribute is not set.', 'facebook-for-woocommerce'); ?></p>
							
							<!-- Dropdown fields for specific attributes - hidden by default -->
							<div id="condition-dropdown" style="display: none; margin-top: 10px;">
								<select id="wc-facebook-default-condition" name="wc_facebook_default_condition" class="wc-enhanced-select" style="width: 100%;">
									<option value=""><?php esc_html_e('Select condition...', 'facebook-for-woocommerce'); ?></option>
									<option value="new"><?php esc_html_e('New', 'facebook-for-woocommerce'); ?></option>
									<option value="used"><?php esc_html_e('Used', 'facebook-for-woocommerce'); ?></option>
									<option value="refurbished"><?php esc_html_e('Refurbished', 'facebook-for-woocommerce'); ?></option>
								</select>
							</div>
							
							<div id="gender-dropdown" style="display: none; margin-top: 10px;">
								<select id="wc-facebook-default-gender" name="wc_facebook_default_gender" class="wc-enhanced-select" style="width: 100%;">
									<option value=""><?php esc_html_e('Select gender...', 'facebook-for-woocommerce'); ?></option>
									<option value="male"><?php esc_html_e('Male', 'facebook-for-woocommerce'); ?></option>
									<option value="female"><?php esc_html_e('Female', 'facebook-for-woocommerce'); ?></option>
									<option value="unisex"><?php esc_html_e('Unisex', 'facebook-for-woocommerce'); ?></option>
								</select>
							</div>
							
							<div id="age-group-dropdown" style="display: none; margin-top: 10px;">
								<select id="wc-facebook-default-age-group" name="wc_facebook_default_age_group" class="wc-enhanced-select" style="width: 100%;">
									<option value=""><?php esc_html_e('Select age group...', 'facebook-for-woocommerce'); ?></option>
									<option value="adult"><?php esc_html_e('Adult', 'facebook-for-woocommerce'); ?></option>
									<option value="all ages"><?php esc_html_e('All Ages', 'facebook-for-woocommerce'); ?></option>
									<option value="teen"><?php esc_html_e('Teen', 'facebook-for-woocommerce'); ?></option>
									<option value="kids"><?php esc_html_e('Kids', 'facebook-for-woocommerce'); ?></option>
									<option value="toddler"><?php esc_html_e('Toddler', 'facebook-for-woocommerce'); ?></option>
									<option value="infant"><?php esc_html_e('Infant', 'facebook-for-woocommerce'); ?></option>
									<option value="newborn"><?php esc_html_e('Newborn', 'facebook-for-woocommerce'); ?></option>
								</select>
							</div>
							
							<div id="availability-dropdown" style="display: none; margin-top: 10px;">
								<select id="wc-facebook-default-availability" name="wc_facebook_default_availability" class="wc-enhanced-select" style="width: 100%;">
									<option value=""><?php esc_html_e('Select availability...', 'facebook-for-woocommerce'); ?></option>
									<option value="in stock"><?php esc_html_e('In Stock', 'facebook-for-woocommerce'); ?></option>
									<option value="out of stock"><?php esc_html_e('Out of Stock', 'facebook-for-woocommerce'); ?></option>
								</select>
							</div>
						</div>
						
						<p class="submit">
							<button type="submit" name="add_attribute_mapping" class="button button-primary">
								<?php esc_html_e('Add Attribute Mapping', 'facebook-for-woocommerce'); ?>
							</button>
						</p>
						
						<?php wp_nonce_field('wc_facebook_add_attribute_mapping', 'add_attribute_mapping_nonce'); ?>
					</form>
				</div>
				
				<!-- Right column: Table of existing mappings -->
				<div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ddd;">
					<form method="post" id="existing-mappings-form" action=""><?php // empty action will submit to the current page ?>
						<table class="widefat striped" id="facebook-attribute-mapping-table">
							<thead>
								<tr>
									<th class="check-column"><input type="checkbox" id="select-all-mappings" /></th>
									<th class="name-column"><?php esc_html_e('WooCommerce Attribute', 'facebook-for-woocommerce'); ?> <?php echo wc_help_tip(__('The product attribute from your store', 'facebook-for-woocommerce')); ?></th>
									<th class="desc-column"><?php esc_html_e('Facebook Attribute', 'facebook-for-woocommerce'); ?> <?php echo wc_help_tip(__('The corresponding Facebook catalog field', 'facebook-for-woocommerce')); ?></th>
									<th class="slug-column"><?php esc_html_e('Default', 'facebook-for-woocommerce'); ?> <?php echo wc_help_tip(__('Default value used when attribute is not available', 'facebook-for-woocommerce')); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if (empty($current_mappings)) : ?>
									<tr class="no-items">
										<td class="colspanchange" colspan="4"><?php esc_html_e('No Facebook Product Attributes found.', 'facebook-for-woocommerce'); ?></td>
									</tr>
								<?php else : ?>
									<?php 
									// Display existing mappings
									foreach ($current_mappings as $wc_attribute => $fb_field) {
										$default_value = isset($saved_defaults[$wc_attribute]) ? $saved_defaults[$wc_attribute] : '';
										$wc_attribute_label = isset($product_attributes[$wc_attribute]) ? $product_attributes[$wc_attribute] : $wc_attribute;
										$fb_field_label = isset($facebook_fields[$fb_field]) ? $facebook_fields[$fb_field] : $fb_field;
										?>
										<tr>
											<th scope="row" class="check-column">
												<input type="checkbox" name="selected_mappings[]" value="<?php echo esc_attr($wc_attribute); ?>" />
											</th>
											<td class="name-column">
												<?php echo esc_html($wc_attribute_label); ?>
											</td>
											<td class="desc-column">
												<?php echo esc_html($fb_field_label); ?>
											</td>
											<td class="slug-column">
												<?php if ($fb_field === 'condition'): ?>
													<?php $this->render_condition_dropdown($wc_attribute, $default_value); ?>
												<?php elseif ($fb_field === 'gender'): ?>
													<?php $this->render_gender_dropdown($wc_attribute, $default_value); ?>
												<?php elseif ($fb_field === 'age_group'): ?>
													<?php $this->render_age_group_dropdown($wc_attribute, $default_value); ?>
												<?php elseif ($fb_field === 'availability'): ?>
													<?php $this->render_availability_dropdown($wc_attribute, $default_value); ?>
												<?php else: ?>
													<?php echo esc_html($default_value); ?>
												<?php endif; ?>
											</td>
										</tr>
										<?php
									}
									?>
								<?php endif; ?>
							</tbody>
							<tfoot>
								<?php if (!empty($current_mappings)) : ?>
									<tr>
										<td colspan="4">
											<div class="bulkactions">
												<button type="submit" name="delete_mappings" class="button button-secondary delete-mappings">
													<?php esc_html_e('Delete Selected', 'facebook-for-woocommerce'); ?>
												</button>
												<?php if (!empty($last_sync)) : ?>
													<span class="last-sync-info" style="float: right;">
														<?php printf(esc_html__('Last synchronized: %s', 'facebook-for-woocommerce'), $last_sync_formatted); ?>
													</span>
												<?php endif; ?>
											</div>
										</td>
									</tr>
								<?php endif; ?>
							</tfoot>
						</table>
						
						<input type="hidden" name="screen_id" value="<?php echo esc_attr($this->get_id()); ?>">
						<?php wp_nonce_field('wc_facebook_delete_attribute_mappings', 'delete_mappings_nonce'); ?>
					</form>
				</div>
			</div>
		</div>
		
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Initialize enhanced select boxes
				if ($.fn.select2) {
					$('.wc-enhanced-select').select2({
						width: '100%',
						placeholder: function() {
							return $(this).data('placeholder');
						}
					});
				}
				
				// Select all checkboxes
				$('#select-all-mappings').on('click', function() {
					var isChecked = $(this).prop('checked');
					$('#facebook-attribute-mapping-table tbody input[type="checkbox"]').prop('checked', isChecked);
				});
				
				// Handle Facebook field selection to show appropriate default value field
				$('#wc-facebook-field').on('change', function() {
					var selectedField = $(this).val();
					
					// Hide all dropdowns first
					$('#condition-dropdown, #gender-dropdown, #age-group-dropdown, #availability-dropdown').hide();
					$('#wc-facebook-default').show();
					
					// Show appropriate dropdown based on selected field
					if (selectedField === 'condition') {
						$('#condition-dropdown').show();
						$('#wc-facebook-default').hide();
					} else if (selectedField === 'gender') {
						$('#gender-dropdown').show();
						$('#wc-facebook-default').hide();
					} else if (selectedField === 'age_group') {
						$('#age-group-dropdown').show();
						$('#wc-facebook-default').hide();
					} else if (selectedField === 'availability') {
						$('#availability-dropdown').show();
						$('#wc-facebook-default').hide();
					}
				});
				
				// Sync the dropdown values with the hidden input field
				$('#wc-facebook-default-condition, #wc-facebook-default-gender, #wc-facebook-default-age-group, #wc-facebook-default-availability').on('change', function() {
					$('#wc-facebook-default').val($(this).val());
				});
				
				// Handle form submission to ensure the correct value is submitted
				$('#add-attribute-form').on('submit', function() {
					var selectedField = $('#wc-facebook-field').val();
					
					if (selectedField === 'condition') {
						$('#wc-facebook-default').val($('#wc-facebook-default-condition').val());
					} else if (selectedField === 'gender') {
						$('#wc-facebook-default').val($('#wc-facebook-default-gender').val());
					} else if (selectedField === 'age_group') {
						$('#wc-facebook-default').val($('#wc-facebook-default-age-group').val());
					} else if (selectedField === 'availability') {
						$('#wc-facebook-default').val($('#wc-facebook-default-availability').val());
					}
				});
				
				// Add this code to the jQuery section before the existing script ends 
				$('.wc-enhanced-select').on('change', function() {
					var $this = $(this);
					var fieldName = $this.attr('name');
					
					// Check if this is one of our special dropdowns
					if (fieldName && (fieldName.indexOf('condition') > -1 || fieldName.indexOf('gender') > -1 || fieldName.indexOf('age_group') > -1 || fieldName.indexOf('availability') > -1)) {
						var $hiddenField = $(this).siblings('input[type="hidden"]');
						if ($hiddenField.length) {
							$hiddenField.val($this.val());
						}
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
								<td>
									<select name="wc_facebook_attribute_mapping[]" class="wc-attribute-search" data-placeholder="<?php esc_attr_e('Select a WooCommerce attribute...', 'facebook-for-woocommerce'); ?>">
										<option value=""><?php esc_html_e('Select a WooCommerce attribute...', 'facebook-for-woocommerce'); ?></option>
										
										<?php foreach ($product_attributes as $attribute_id => $attribute_label) : ?>
											<option value="<?php echo esc_attr($attribute_id); ?>">
												<?php echo esc_html($attribute_label); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<select name="wc_facebook_field_mapping[]" class="fb-field-search" data-placeholder="<?php esc_attr_e('Select a Facebook attribute...', 'facebook-for-woocommerce'); ?>">
										<option value=""><?php esc_html_e('Select a Facebook attribute...', 'facebook-for-woocommerce'); ?></option>
										
										<?php foreach ($facebook_fields as $field_id => $field_label) : ?>
											<option value="<?php echo esc_attr($field_id); ?>">
												<?php echo esc_html($field_label); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<input type="text" class="fb-default-value" name="wc_facebook_attribute_default[]" placeholder="<?php esc_attr_e('Enter default value (optional)', 'facebook-for-woocommerce'); ?>" value="">
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
		// Debug info
		error_log('FB Product Attributes process_form_submission method called');
		error_log('POST data: ' . print_r($_POST, true));
		
		// Get existing mappings
		$existing_mappings = $this->get_saved_mappings();
		$existing_defaults = $this->get_saved_defaults();
		
		// Process adding a new mapping
		if (isset($_POST['add_attribute_mapping']) && 
			isset($_POST['add_attribute_mapping_nonce']) && 
			wp_verify_nonce($_POST['add_attribute_mapping_nonce'], 'wc_facebook_add_attribute_mapping')) {
			
			$wc_attribute = isset($_POST['wc_facebook_attribute']) ? sanitize_text_field($_POST['wc_facebook_attribute']) : '';
			$fb_field = isset($_POST['wc_facebook_field']) ? sanitize_text_field($_POST['wc_facebook_field']) : '';
			$default = isset($_POST['wc_facebook_default']) ? sanitize_text_field($_POST['wc_facebook_default']) : '';
			
			// Check for special field types and use their dropdown values if available
			if ($fb_field === 'condition' && isset($_POST['wc_facebook_default_condition'])) {
				$default = sanitize_text_field($_POST['wc_facebook_default_condition']);
			} elseif ($fb_field === 'gender' && isset($_POST['wc_facebook_default_gender'])) {
				$default = sanitize_text_field($_POST['wc_facebook_default_gender']);
			} elseif ($fb_field === 'age_group' && isset($_POST['wc_facebook_default_age_group'])) {
				$default = sanitize_text_field($_POST['wc_facebook_default_age_group']);
			} elseif ($fb_field === 'availability' && isset($_POST['wc_facebook_default_availability'])) {
				$default = sanitize_text_field($_POST['wc_facebook_default_availability']);
			}
			
			if (!empty($wc_attribute) && !empty($fb_field)) {
				// Add or update the mapping
				$existing_mappings[$wc_attribute] = $fb_field;
				
				// Add or update the default value if provided
				if (!empty($default)) {
					$existing_defaults[$wc_attribute] = $default;
				} elseif (isset($existing_defaults[$wc_attribute])) {
					// Remove existing default if new value is empty
					unset($existing_defaults[$wc_attribute]);
				}
				
				$this->add_notice(__('Attribute mapping added successfully.', 'facebook-for-woocommerce'), 'success');
			} else {
				$this->add_notice(__('Please select both a WooCommerce attribute and a Facebook attribute.', 'facebook-for-woocommerce'), 'error');
			}
		}
		
		// Process deleting selected mappings
		if (isset($_POST['delete_mappings']) && 
			isset($_POST['delete_mappings_nonce']) && 
			wp_verify_nonce($_POST['delete_mappings_nonce'], 'wc_facebook_delete_attribute_mappings')) {
			
			if (isset($_POST['selected_mappings']) && is_array($_POST['selected_mappings'])) {
				$deleted_count = 0;
				
				foreach ($_POST['selected_mappings'] as $mapping_key) {
					$mapping_key = sanitize_text_field($mapping_key);
					
					if (isset($existing_mappings[$mapping_key])) {
						unset($existing_mappings[$mapping_key]);
						$deleted_count++;
						
						// Also remove any default value
						if (isset($existing_defaults[$mapping_key])) {
							unset($existing_defaults[$mapping_key]);
						}
					}
				}
				
				if ($deleted_count > 0) {
					$this->add_notice(
						sprintf(
							_n(
								'%d attribute mapping deleted.',
								'%d attribute mappings deleted.',
								$deleted_count,
								'facebook-for-woocommerce'
							),
							$deleted_count
						),
						'success'
					);
				}
			}
		}
		
		// Process updates to existing mapping defaults from special dropdowns
		foreach (array('condition', 'gender', 'age_group', 'availability') as $special_field) {
			$field_key = 'wc_facebook_attribute_default_' . $special_field;
			
			if (isset($_POST[$field_key]) && is_array($_POST[$field_key])) {
				foreach ($_POST[$field_key] as $attr_key => $value) {
					if (!empty($value)) {
						$existing_defaults[sanitize_text_field($attr_key)] = sanitize_text_field($value);
					}
				}
			}
		}
		
		// Process updates to regular default values
		if (isset($_POST['wc_facebook_attribute_default']) && is_array($_POST['wc_facebook_attribute_default'])) {
			foreach ($_POST['wc_facebook_attribute_default'] as $attr_key => $value) {
				$attr_key = sanitize_text_field($attr_key);
				$value = sanitize_text_field($value);
				
				if (!empty($value)) {
					$existing_defaults[$attr_key] = $value;
				} else {
					// Remove default if empty
					unset($existing_defaults[$attr_key]);
				}
			}
		}
		
		// Save mappings to WordPress options
		update_option(self::OPTION_CUSTOM_ATTRIBUTE_MAPPINGS, $existing_mappings);
		
		// Save defaults to WordPress options
		update_option('wc_facebook_attribute_defaults', $existing_defaults);
		
		// Update the static mapping in the ProductAttributeMapper class
		if (method_exists('WooCommerce\Facebook\ProductAttributeMapper', 'set_custom_attribute_mappings')) {
			\WooCommerce\Facebook\ProductAttributeMapper::set_custom_attribute_mappings($existing_mappings);
		}
		
		// Update last sync time
		update_option('wc_facebook_last_attribute_sync', current_time('mysql'));
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
			foreach ( $notices as $notice ) {
				$class = 'notice ' . ( $notice['type'] === 'success' ? 'notice-success' : 'notice-error' );
				printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $notice['message'] ) );
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
		$notices = get_transient( 'facebook_for_woocommerce_attribute_notices' ) ?: array();
		
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		
		set_transient( 'facebook_for_woocommerce_attribute_notices', $notices, 60 * 5 ); // 5 minutes expiration
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
		
		echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" class="wc-enhanced-select" style="width: 100%;">';
		
		foreach ($options as $value => $label) {
			echo '<option value="' . esc_attr($value) . '" ' . selected($value, $selected_value, false) . '>' . esc_html($label) . '</option>';
		}
		
		echo '</select>';
		
		// Hidden field to store the actual value (for compatibility with existing code)
		echo '<input type="hidden" name="wc_facebook_attribute_default[' . esc_attr($attribute_name) . ']" value="' . esc_attr($selected_value) . '" class="' . esc_attr($field_type) . '-default-value-' . esc_attr($attribute_name) . '">';
	}
} 