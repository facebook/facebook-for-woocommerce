<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Admin;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\ProductAttributeMapper;

/**
 * Global Attributes Banner handler.
 *
 * Shows informational banners when users create global attributes
 * that don't have direct mappings to Meta catalog fields.
 *
 * @since 3.4.11
 */
class Global_Attributes_Banner {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook into global attribute creation
		add_action( 'created_term', array( $this, 'check_new_attribute_mapping' ), 20, 3 );
		
		// Hook into attribute page display
		add_action( 'admin_notices', array( $this, 'display_unmapped_attribute_banner' ) );
		
		// AJAX handler for dismissing banner
		add_action( 'wp_ajax_dismiss_fb_unmapped_attribute_banner', array( $this, 'dismiss_banner' ) );
	}

	/**
	 * Check if a newly created attribute has a direct mapping to Meta.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function check_new_attribute_mapping( $term_id, $tt_id, $taxonomy ) {
		// Only check for attribute taxonomies
		if ( ! $this->is_attribute_taxonomy( $taxonomy ) ) {
			return;
		}

		// Only show to users who can manage WooCommerce
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Get the attribute name from taxonomy
		$attribute_name = str_replace( 'pa_', '', $taxonomy );
		
		// Check if this attribute maps to any Meta field
		if ( ! $this->attribute_maps_to_meta( $attribute_name ) ) {
			$this->queue_unmapped_attribute_banner( $attribute_name );
		}
	}

	/**
	 * Check if a taxonomy is an attribute taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	private function is_attribute_taxonomy( $taxonomy ) {
		return strpos( $taxonomy, 'pa_' ) === 0;
	}

	/**
	 * Check if an attribute name maps to a Meta field.
	 *
	 * @param string $attribute_name Attribute name (without pa_ prefix).
	 * @return bool
	 */
	private function attribute_maps_to_meta( $attribute_name ) {
		if ( ! class_exists( 'WooCommerce\Facebook\ProductAttributeMapper' ) ) {
			return false;
		}

		// Use the same comprehensive logic as the ProductAttributeMapper
		$mapped_field = ProductAttributeMapper::check_attribute_mapping( 'pa_' . $attribute_name );
		
		// If we get a mapping result, the attribute is mapped
		return false !== $mapped_field;
	}

	/**
	 * Queue a banner for an unmapped attribute.
	 *
	 * @param string $attribute_name The attribute name.
	 */
	private function queue_unmapped_attribute_banner( $attribute_name ) {
		$banner_data = array(
			'attribute_name' => $attribute_name,
			'timestamp' => time(),
		);

		// Increase duration to 30 minutes to account for page redirects
		set_transient( 'fb_new_unmapped_attribute_banner', $banner_data, 1800 );
	}

	/**
	 * Display the unmapped attribute banner.
	 */
	public function display_unmapped_attribute_banner() {
		if ( ! $this->should_show_banner() ) {
			return;
		}

		$banner_data = get_transient( 'fb_new_unmapped_attribute_banner' );
		if ( ! $banner_data || ! isset( $banner_data['attribute_name'] ) ) {
			return;
		}

		$attribute_name = $banner_data['attribute_name'];
		$display_name = ucfirst( str_replace( array( '_', '-' ), ' ', $attribute_name ) );

		// Build the mapper URL
		$mapper_url = add_query_arg(
			array(
				'page' => 'wc-facebook',
				'tab'  => 'product-attributes',
			),
			admin_url( 'admin.php' )
		);

		?>
		<div class="notice notice-info is-dismissible fb-unmapped-attribute-banner" style="position: relative;">
			<p>
				<strong><?php esc_html_e( 'Meta for WooCommerce', 'facebook-for-woocommerce' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %1$s - attribute name, %2$s - link start, %3$s - link end */
					esc_html__( 'Your new "%1$s" attribute doesn\'t directly map to a Meta catalog field. %2$sMap it to Meta%3$s to improve product visibility in Meta ads and help customers find your products more easily.', 'facebook-for-woocommerce' ),
					esc_html( $display_name ),
					'<a href="' . esc_url( $mapper_url ) . '">',
					'</a>'
				);
				?>
			</p>
			<button type="button" class="notice-dismiss" data-attribute="<?php echo esc_attr( $attribute_name ); ?>">
				<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'facebook-for-woocommerce' ); ?></span>
			</button>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.fb-unmapped-attribute-banner .notice-dismiss').on('click', function() {
				var attributeName = $(this).data('attribute');
				$.post(ajaxurl, {
					action: 'dismiss_fb_unmapped_attribute_banner',
					attribute: attributeName,
					nonce: '<?php echo wp_create_nonce( 'dismiss_fb_banner' ); ?>'
				});
				$(this).closest('.notice').fadeOut();
			});
		});
		</script>
		<?php
	}

	/**
	 * Check if we should show the banner.
	 *
	 * @return bool
	 */
	private function should_show_banner() {
		// Only show to users who can manage WooCommerce
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		// Show on multiple relevant admin pages, not just the attributes page
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		// Show on: attributes page, products page, and Facebook settings page
		$allowed_screens = array(
			'product_page_product_attributes',  // Global attributes page
			'edit-product',                     // Products list
			'product',                          // Single product edit
			'woocommerce_page_wc-facebook',     // Facebook settings page
		);

		return in_array( $screen->id, $allowed_screens, true );
	}

	/**
	 * Dismiss the banner via AJAX.
	 */
	public function dismiss_banner() {
		check_ajax_referer( 'dismiss_fb_banner', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		delete_transient( 'fb_new_unmapped_attribute_banner' );
		wp_die();
	}
} 