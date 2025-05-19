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

use WP_Term;
use WooCommerce\Facebook\RolloutSwitches;

/**
 * General handler for the product set admin functionality.
 *
 * @since 2.3.0
 */
class Product_Sets {

	/**
	 * Allowed HTML for wp_kses
	 *
	 * @since 2.3.0
	 *
	 * @var array
	 */
	protected $allowed_html = array(
		'label' => array(
			'for' => [],
		),
		'input' => array(
			'type' => [],
			'name' => [],
			'id'   => [],
		),
		'p'     => array(
			'class' => [],
		),
	);

	/**
	 * Categories field name
	 *
	 * @since 2.3.0
	 *
	 * @var string
	 */
	protected $categories_field = '';


	/**
	 * Handler constructor.
	 *
	 * @since 2.3.0
	 */
	public function __construct() {
		$this->categories_field = \WC_Facebookcommerce::PRODUCT_SET_META;
		// add taxonomy custom field
		add_action( 'fb_product_set_add_form_fields', array( $this, 'category_field_on_new' ) );
		add_action( 'fb_product_set_edit_form', array( $this, 'category_field_on_edit' ) );
		// save custom field data
		add_action( 'created_fb_product_set', array( $this, 'save_custom_field' ), 10, 2 );
		add_action( 'edited_fb_product_set', array( $this, 'save_custom_field' ), 10, 2 );
		// show a banner about chnages to product sets sync
		add_action( 'admin_notices', array( $this, 'display_fb_product_sets_banner' ) );
	}

	public function display_fb_product_sets_banner() {
		if ( isset( $_GET['taxonomy'] ) && 'fb_product_set' === $_GET['taxonomy'] ) {
			$is_product_sets_sync_enbaled = facebook_for_woocommerce()->get_rollout_switches()->is_switch_enabled(
				RolloutSwitches::SWITCH_PRODUCT_SETS_SYNC_ENABLED
			);
			if ( $is_product_sets_sync_enbaled ) {
				$fb_catalog_id = facebook_for_woocommerce()->get_integration()->get_product_catalog_id();

				?>
					<div class="notice notice-info">
						<p><b>Your categories now automatically sync as product sets on Facebook</b></p>
						<p>To make changes to automatically synced sets going forward, you should <a href="edit-tags.php?taxonomy=product_cat" target="_blank">edit your categories on WooCommerce</a>. This may take some time to update initially, but then will automatically sync every few minutes. There are no changes to any sets you previously created.
						To see what’s synced, go to <a href="https://business.facebook.com/commerce/catalogs/<?php echo esc_attr( $fb_catalog_id ); ?>/sets" target="_blank">sets in Commerce Manager</a>.</p>
					</div>
				<?php
			}
		}
	}



	/**
	 * Add field to Facebook Product Set new term
	 *
	 * @since 2.3.0
	 */
	public function category_field_on_new() {
		?>
		<div class="form-field">
			<?php $this->get_field_label(); ?>
			<?php $this->get_field(); ?>
		</div>
		<?php
	}


	/**
	 * Add field to Facebook Product Set new term
	 *
	 * @since 2.3.0
	 *
	 * @param WP_Term $term Term object.
	 */
	public function category_field_on_edit( $term ) {
		// gets term id
		$term_id = empty( $term->term_id ) ? '' : $term->term_id;
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr class="form-field product-categories-wrap">
					<th scope="row"><?php $this->get_field_label(); ?></th>
					<td><?php $this->get_field( $term_id ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}


	/**
	 * Saves custom field data
	 *
	 * @since 2.3.0
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id Term taxonomy ID.
	 */
	public function save_custom_field( $term_id, $tt_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$wc_product_cats = empty( $_POST[ $this->categories_field ] ) ? '' : wc_clean( wp_unslash( $_POST[ $this->categories_field ] ) ); //phpcs:ignore
		if ( ! empty( $wc_product_cats ) ) {
			$wc_product_cats = array_map(
				function ( $item ) {
					return absint( $item );
				},
				$wc_product_cats
			);
		}
		update_term_meta( $term_id, $this->categories_field, $wc_product_cats );
	}


	/**
	 * Return field label HTML
	 *
	 * @since 2.3.0
	 */
	protected function get_field_label() {
		?>
		<label for="<?php echo esc_attr( $this->categories_field ); ?>"><?php echo esc_html__( 'WC Product Categories', 'facebook-for-woocommerce' ); ?></label>
		<?php
	}


	/**
	 * Return field HTML
	 *
	 * @since 2.3.0
	 *
	 * @param int $term_id The Term ID that is editing.
	 */
	protected function get_field( $term_id = '' ) {
		$saved_items  = get_term_meta( $term_id, $this->categories_field, true );
		$product_cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		?>
		<div class="select2 updating-message"><p></p></div>
		<select
		id="<?php echo esc_attr( $this->categories_field ); ?>"
		name="<?php echo esc_attr( $this->categories_field ); ?>[]"
		multiple="multiple"
		disabled="disabled"
		class="select2 wc-facebook product_cats"
		style="display:none;"
		>
		<?php foreach ( $product_cats as $product_cat ) : ?>
			<?php $selected = ( is_array( $saved_items ) && in_array( $product_cat->term_id, $saved_items, true ) ) ? ' selected="selected"' : ''; ?>
			<option value="<?php echo esc_attr( $product_cat->term_id ); ?>" <?php echo esc_attr( $selected ); ?>><?php echo esc_attr( $product_cat->name ); ?></option>
		<?php endforeach; ?>
		</select>
		<p class="description"><?php echo esc_html__( 'Map Facebook Product Set to WC Product Categories', 'facebook-for-woocommerce' ); ?>.</p>
		<?php
	}
}
