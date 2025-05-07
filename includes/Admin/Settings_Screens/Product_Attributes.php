<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Admin\Settings_Screens;

defined( 'ABSPATH' ) || exit;

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
			
			<form method="post" id="mainform" action="" enctype="multipart/form-data">
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
						<?php 
						// Display existing mappings
						if (!empty($current_mappings)) {
							foreach ($current_mappings as $wc_attribute => $fb_field) {
								$default_value = isset($saved_defaults[$wc_attribute]) ? $saved_defaults[$wc_attribute] : '';
								$this->render_mapping_row($wc_attribute, $fb_field, $product_attributes, $facebook_fields, $default_value);
							}
						}
						
						// Add an empty row for new mappings
						$this->render_mapping_row('', '', $product_attributes, $facebook_fields, '');
						?>
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
				
				<p class="submit">
					<button type="submit" name="save" class="button button-primary">
						<?php esc_html_e('Save Changes', 'facebook-for-woocommerce'); ?>
					</button>
					<?php if (!empty($last_sync)) : ?>
						<span class="last-sync-info">
							<?php printf(esc_html__('Last synchronized: %s', 'facebook-for-woocommerce'), $last_sync_formatted); ?>
						</span>
					<?php endif; ?>
				</p>
				
				<input type="hidden" name="screen_id" value="<?php echo esc_attr($this->get_id()); ?>">
				<input type="hidden" name="save_product_attributes" value="1">
				<?php wp_nonce_field('wc_facebook_admin_save_' . $this->get_id() . '_settings'); ?>
			</form>
			
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
		</div>
		<?php
	}


	/**
	 * Renders a single mapping row.
	 *
	 * @since 3.0.0
	 *
	 * @param string $wc_attribute WC attribute
	 * @param string $fb_field FB field
	 * @param array $product_attributes All product attributes
	 * @param array $facebook_fields All Facebook fields
	 * @param string $default_value Default value for this mapping
	 */
	private function render_mapping_row($wc_attribute, $fb_field, $product_attributes, $facebook_fields, $default_value) {
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
						<?php 
						// Display existing mappings
						if (!empty($current_mappings)) {
							foreach ($current_mappings as $wc_attribute => $fb_field) {
								$this->render_mapping_row($wc_attribute, $fb_field, $product_attributes, $facebook_fields, '');
							}
						}
						
						// Add an empty row for new mappings
						$this->render_mapping_row('', '', $product_attributes, $facebook_fields, '');
						?>
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
						$('#facebook-attribute-mapping-table').on('click', '.remove-mapping-row', function() {
							// Don't remove if it's the only row
							if ($('#facebook-attribute-mapping-table tbody tr').length > 1) {
								$(this).closest('tr').remove();
							} else {
								// Clear values instead
								$(this).closest('tr').find('select').val('').trigger('change');
								$(this).closest('tr').find('input[type="text"]').val('');
							}
						});
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
		// Check if we're saving product attributes
		if (!isset($_POST['save_product_attributes'])) {
			return;
		}
		
		// Verify nonce
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wc_facebook_admin_save_' . $this->get_id() . '_settings')) {
			wp_die(esc_html__('Action failed. Please refresh the page and retry.', 'facebook-for-woocommerce'));
		}
		
		$mappings = array();
		$defaults = array();
		
		// Process attribute mappings
		if (isset($_POST['wc_facebook_attribute_mapping']) && is_array($_POST['wc_facebook_attribute_mapping']) && 
			isset($_POST['wc_facebook_field_mapping']) && is_array($_POST['wc_facebook_field_mapping'])) {
			
			foreach ($_POST['wc_facebook_attribute_mapping'] as $key => $wc_attribute) {
				$wc_attribute = sanitize_text_field($wc_attribute);
				
				if (!empty($wc_attribute) && isset($_POST['wc_facebook_field_mapping'][$key])) {
					$fb_field = sanitize_text_field($_POST['wc_facebook_field_mapping'][$key]);
					
					if (!empty($fb_field)) {
						$mappings[$wc_attribute] = $fb_field;
						
						// Store default value if provided
						if (isset($_POST['wc_facebook_attribute_default'][$key]) && '' !== $_POST['wc_facebook_attribute_default'][$key]) {
							$defaults[$wc_attribute] = sanitize_text_field($_POST['wc_facebook_attribute_default'][$key]);
						}
					}
				}
			}
		}
		
		// Save mappings to WordPress options
		update_option(self::OPTION_CUSTOM_ATTRIBUTE_MAPPINGS, $mappings);
		
		// Save defaults to WordPress options
		update_option('wc_facebook_attribute_defaults', $defaults);
		
		// Update the static mapping in the ProductAttributeMapper class
		if (method_exists('ProductAttributeMapper', 'set_custom_attribute_mappings')) {
			ProductAttributeMapper::set_custom_attribute_mappings($mappings);
		}
		
		// Update last sync time
		update_option('wc_facebook_last_attribute_sync', current_time('mysql'));
		
		// Add success notice
		wc_add_notice(__('Facebook product attributes updated.', 'facebook-for-woocommerce'), 'success');
	}
} 