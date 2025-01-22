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

use WooCommerce\Facebook\Products as Products_Handler;

/**
 * Enhanced Catalog attribute fields.
 */
class Enhanced_Catalog_Attribute_Fields {
	const FIELD_ENHANCED_CATALOG_ATTRIBUTE_PREFIX = 'wc_facebook_enhanced_catalog_attribute_';
	const OPTIONAL_SELECTOR_KEY                   = '__optional_selector';
	const FIELD_ENHANCED_CATALOG_ATTRIBUTES_ID    = 'wc_facebook_enhanced_catalog_attributes_id';
	const FIELD_CAN_SHOW_ENHANCED_ATTRIBUTES_ID   = 'wc_facebook_can_show_enhanced_catalog_attributes_id';

	const PAGE_TYPE_EDIT_CATEGORY = 'edit_category';
	const PAGE_TYPE_ADD_CATEGORY  = 'add_category';
	const PAGE_TYPE_EDIT_PRODUCT  = 'edit_product';

	/**
	 * @var string Facebook page type.
	 */
	private $page_type;

	/**
	 * @var \WP_Term
	 */
	private $term;

	/**
	 * @var \WC_Product
	 */
	private $product;

	/**
	 * @var \WooCommerce\Facebook\Products\FBCategories
	 */
	private $category_handler;

	public function __construct( $page_type, ?\WP_Term $term = null, ?\WC_Product $product = null ) {
		$this->page_type        = $page_type;
		$this->term             = $term;
		$this->product          = $product;
		$this->category_handler = facebook_for_woocommerce()->get_facebook_category_handler();
	}

	/**
	 * __get method for backward compatibility.
	 *
	 * @param string $key property name
	 * @return mixed
	 * @since 3.0.32
	 */
	public function __get( $key ) {
		// Add warning for private properties.
		if ( in_array( $key, array( 'page_type', 'term', 'product', 'category_handler' ), true ) ) {
			/* translators: %s property name. */
			_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'The %s property is private and should not be accessed outside its class.', 'facebook-for-woocommerce' ), esc_html( $key ) ), '3.0.32' );
			return $this->$key;
		}

		return null;
	}

	public static function render_hidden_input_can_show_attributes() {
		?>
		<input type="hidden" id="<?php echo esc_attr( self::FIELD_CAN_SHOW_ENHANCED_ATTRIBUTES_ID ); ?>"
			name="<?php echo esc_attr( self::FIELD_CAN_SHOW_ENHANCED_ATTRIBUTES_ID ); ?>"
			value="true"/>
		<?php
	}

	private function extract_attribute( &$attributes, $key ) {
		$index     = array_search( $key, array_column( $attributes, 'key' ), true );
		$extracted = false === $index ? array() : array_splice( $attributes, $index, 1 );
		return empty( $extracted ) ? null : array_shift( $extracted );
	}

	public function render( $category_id ) {
		$all_attributes             = $this->category_handler->get_attributes_with_fallback_to_parent_category( $category_id );
		$all_attributes_with_values = array_map(
			function ( $attribute ) use ( $category_id ) {
				return array_merge( $attribute, array( 'value' => $this->get_value( $attribute['key'], $category_id ) ) );
			},
			$all_attributes
		);
		$recommended_attributes     = array_filter(
			$all_attributes_with_values,
			function ( $attr ) {
				return $attr['recommended'];
			}
		);
		$optional_attributes        = array_filter(
			$all_attributes_with_values,
			function ( $attr ) {
				return ! $attr['recommended'];
			}
		);

		// Some google mappings don't have any recommendations
		// to avoid there being no attributes to see we extract color, gender
		// and size from optional.
		if ( empty( $recommended_attributes ) ) {
			$recommended_attributes = array_filter(
				array(
					$this->extract_attribute( $optional_attributes, 'color' ),
					$this->extract_attribute( $optional_attributes, 'size' ),
					$this->extract_attribute( $optional_attributes, 'gender' ),
				),
				function ( $attr ) {
					return ! is_null( $attr );
				}
			);
		}

		/**
		 * Attributes should be ordered by priority in this order: Length, Width, Height, Depth, Other measurement attributes, others.
		 */
		$priorities = array(
			'product_length' => 140,
			'product_width'  => 130,
			'product_height' => 120,
			'product_depth'  => 110,
			'default'        => 100,
		);

		foreach ( $recommended_attributes as $key => $attribute ) {
			$recommended_attributes[ $key ]['priority'] = 5; // Assign 5 initially to each attribute
			if ( 'measurement' === $attribute['type'] ) {
				$recommended_attributes[ $key ]['priority'] = isset( $priorities[ $attribute['key'] ] ) ? $priorities[ $attribute['key'] ] : $priorities['default'];
			}
			$priority[ $key ] = $recommended_attributes[ $key ]['priority'];
		}

		array_multisort( $priority, SORT_DESC, $recommended_attributes );

		foreach ( $recommended_attributes as $attribute ) {
			$this->render_attribute( $attribute );
		}

		$selector_value      = $this->get_value( self::OPTIONAL_SELECTOR_KEY, $category_id );
		$is_showing_optional = 'on' === $selector_value;
		$this->render_selector_checkbox( $is_showing_optional );

		foreach ( $optional_attributes as $attribute ) {
			$this->render_attribute( $attribute, true, $is_showing_optional );
		}
	}

	private function render_selector_checkbox( $is_showing_optional ) {
		$selector_id    = self::FIELD_ENHANCED_CATALOG_ATTRIBUTE_PREFIX . self::OPTIONAL_SELECTOR_KEY;
		$selector_label = __( 'Show advanced options', 'facebook-for-woocommerce' );
		$checked_attr   = $is_showing_optional ? 'checked="checked"' : '';

		if ( self::PAGE_TYPE_EDIT_PRODUCT === $this->page_type ) {
			?>
			<p class="form-field wc-facebook-enhanced-catalog-attribute-row term-<?php echo esc_attr( $selector_id ); ?>-wrap">
				<label for="<?php echo esc_attr( $selector_id ); ?>">
					<?php echo esc_html( $selector_label ); ?>
				</label>
				<input type="checkbox" name="<?php echo esc_attr( $selector_id ); ?>" id="<?php echo esc_attr( $selector_id ); ?>" <?php echo esc_attr( $checked_attr ); ?>/>
			</p>
			<?php
		} else {
			?>
			<tr class="form-field wc-facebook-enhanced-catalog-attribute-row term-<?php echo esc_attr( $selector_id ); ?>-wrap">
				<th colspan="2" scope="row">
					<label for="<?php echo esc_attr( $selector_id ); ?>">
						<?php echo esc_html( $selector_label ); ?>
						<input type="checkbox" name="<?php echo esc_attr( $selector_id ); ?>" id="<?php echo esc_attr( $selector_id ); ?>" <?php echo esc_attr( $checked_attr ); ?>/>
					</label>
				</th>
			</tr>
			<?php
		}//end if
	}

	private function get_value( $attribute_key, $category_id ) {
		$value = null;
		if ( ! is_null( $this->product ) ) {
			$value = Products_Handler::get_enhanced_catalog_attribute( $attribute_key, $this->product );
		} elseif ( ! is_null( $this->term ) ) {
			$meta_key = \WooCommerce\Facebook\Products::ENHANCED_CATALOG_ATTRIBUTES_META_KEY_PREFIX . $attribute_key;
			$value    = get_term_meta( $this->term->term_id, $meta_key, true );
		}

		// Check if it's valid
		if ( self::OPTIONAL_SELECTOR_KEY !== $attribute_key && ! empty( $value ) ) {
			$is_valid = $this->category_handler->is_valid_value_for_attribute( $category_id, $attribute_key, $value );
			$value    = $is_valid ? $value : null;
		}
		return $value;
	}

	private function render_attribute( $attribute, $optional = false, $is_showing_optional = false ) {
		$attr_id = self::FIELD_ENHANCED_CATALOG_ATTRIBUTE_PREFIX . $attribute['key'];
		$classes = array(
			'form-field',
			'wc-facebook-enhanced-catalog-attribute-row',
			'term-' . esc_attr( $attr_id ) . '-wrap',
		);
		if ( $optional ) {
			$classes[] = 'wc-facebook-enhanced-catalog-attribute-optional-row';
			if ( ! $is_showing_optional ) {
				$classes[] = 'hidden';
			}
		}

		if ( self::PAGE_TYPE_EDIT_PRODUCT === $this->page_type ) {
			?>
			<p
				class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
				<?php $this->render_label( $attr_id, $attribute ); ?>
				<?php $this->render_field( $attr_id, $attribute ); ?>
			</p>
			<?php
		} else {
			?>
			<tr
				class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
				<th scope="row">
					<?php $this->render_label( $attr_id, $attribute ); ?>
				</th>
				<td>
					<?php $this->render_field( $attr_id, $attribute ); ?>
				</td>
			</tr>
			<?php
		}//end if
	}

	private function render_label( $attr_id, $attribute ) {
		$label = ucwords( str_replace( '_', ' ', $attribute['key'] ) );
		?>
		<label for="<?php echo esc_attr( $attr_id ); ?>">
			<?php echo esc_html( $label ); ?>
			<span class="woocommerce-help-tip" data-tip="<?php echo esc_attr( $attribute['description'] ); ?>"></span>
		</label>
		<?php
	}

	private function render_field( $attr_id, $attribute ) {
		$placeholder              = isset( $attribute['example'] ) ? $attribute['example'] : '';
		$can_have_multiple_values = isset( $attribute['can_have_multiple_values'] ) && $attribute['can_have_multiple_values'];
		switch ( $attribute['type'] ) {
			case 'boolean':
			case 'enum':
				if ( $can_have_multiple_values ) {
					$this->render_text_field( $attr_id, $attribute, $placeholder );
				} else {
					$this->render_select_field( $attr_id, $attribute );
				}
				break;

			default:
				// string
				$this->render_text_field( $attr_id, $attribute, $placeholder );
		}
	}

	private function render_select_field( $attr_id, $attribute ) {
		?>
		<select name="<?php echo esc_attr( $attr_id ); ?>" id="<?php echo esc_attr( $attr_id ); ?>">
			<option value="">--</option>
			<?php
			foreach ( $attribute['enum_values'] as $opt ) {
				$is_selected   = $attribute['value'] === $opt;
				$selected_attr = $is_selected ? 'selected="selected"' : '';
				?>
				<option value="<?php echo esc_attr( $opt ); ?>" <?php echo esc_attr( $selected_attr ); ?>> <?php echo esc_html( $opt ); ?></option>
			<?php } ?>
		</select/>
		<?php
	}

	private function render_text_field( $attr_id, $attribute, $placeholder ) {
		if ( is_array( $placeholder ) ) {
			$placeholder = implode( ', ', $placeholder );
		}
		?>
		<input type="text" value="<?php echo esc_attr( $attribute['value'] ); ?>" name="<?php echo esc_attr( $attr_id ); ?>" id="<?php echo esc_attr( $attr_id ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"/>
		<?php
	}
}
