<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\ProductSync;

use WC_Facebook_Product;
use WC_Facebookcommerce_Integration;
use WC_Product;
use WooCommerce\Facebook\Products;

if ( ! class_exists( 'WC_Facebookcommerce_Utils' ) ) {
	include_once '../fbutils.php';
}

/**
 * Class ProductValidator
 *
 * This class is responsible for validating whether a product should be synced to Facebook.
 *
 * @since 2.5.0
 */
class ProductValidator {

	/**
	 * The meta key used to flag whether a product should be synced in Facebook
	 *
	 * @var string
	 */
	public const SYNC_ENABLED_META_KEY = '_wc_facebook_sync_enabled';

	/**
	 * Maximum allowed attributes in a variation;
	 *
	 * @var int
	 */
	public const MAX_NUMBER_OF_ATTRIBUTES_IN_VARIATION = 4;

	/**
	 * The FB integration instance.
	 *
	 * @var WC_Facebookcommerce_Integration
	 */
	protected $integration;

	/**
	 * The product object to validate.
	 *
	 * @var WC_Product
	 */
	protected $product;

	/**
	 * The product parent object if the product has a parent.
	 *
	 * @var WC_Product
	 */
	protected $product_parent;

	/**
	 * The product parent object if the product has a parent.
	 *
	 * @var WC_Facebook_Product
	 */
	protected $fb_product_parent;

	/**
	 * The product object to validate.
	 *
	 * @var WC_Facebook_Product
	 */
	protected $facebook_product;

	/**
	 * ProductValidator constructor.
	 *
	 * @param WC_Facebookcommerce_Integration $integration The FB integration instance.
	 * @param WC_Product                      $product     The product to validate. Accepts both variations and variable products.
	 */
	public function __construct( WC_Facebookcommerce_Integration $integration, WC_Product $product ) {
		$this->product           = $product;
		$this->product_parent    = null;
		$this->fb_product_parent = null;

		if ( $product->get_parent_id() ) {
			$parent_product = wc_get_product( $product->get_parent_id() );
			if ( $parent_product instanceof WC_Product ) {
				$this->product_parent    = $parent_product;
				$this->fb_product_parent = new WC_Facebook_Product( $parent_product );
			}
		}

		$this->facebook_product = new WC_Facebook_Product( $this->product, $this->fb_product_parent );
		$this->integration      = $integration;
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
		if ( 'facebook_product' === $key ) {
			/* translators: %s property name. */
			_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'The %s property is protected and should not be accessed outside its class.', 'facebook-for-woocommerce' ), esc_html( $key ) ), '3.0.32' );
			return $this->$key;
		}

		return null;
	}

	/**
	 * Validate whether the product should be synced to Facebook.
	 *
	 * @throws ProductExcludedException If product should not be synced.
	 */
	public function validate() {
		$this->validate_sync_enabled_globally();
		$this->validate_product_sync_field();
		$this->validate_product_status();
		$this->validate_product_visibility();
		$this->validate_product_terms();
	}

	/**
	 * Validate whether the product should be synced to Facebook but skip the status check for backwards compatibility.
	 *
	 * @internal Do not use this as it will likely be removed.
	 *
	 * @throws ProductExcludedException If product should not be synced.
	 */
	public function validate_but_skip_status_check() {
		$this->validate_sync_enabled_globally();
		$this->validate_product_sync_field();
		$this->validate_product_visibility();
		$this->validate_product_terms();
	}

	/**
	 * Validate whether the product should be synced to Facebook but skip the sync field check.
	 *
	 * @since 3.0.6
	 * @throws ProductExcludedException|ProductInvalidException If product should not be synced.
	 */
	public function validate_but_skip_sync_field() {
		$this->validate_sync_enabled_globally();
		$this->validate_product_visibility();
		$this->validate_product_terms();
	}

	/**
	 * Validate whether the product should be synced to Facebook.
	 *
	 * @return bool
	 */
	public function passes_all_checks(): bool {
		try {
			$this->validate();
		} catch ( ProductExcludedException $e ) {
			return false;
		} catch ( ProductInvalidException $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the product's terms (categories and tags) allow it to sync.
	 *
	 * @return bool
	 */
	public function passes_product_terms_check(): bool {
		try {
			$this->validate_product_terms();
		} catch ( ProductExcludedException $e ) {
			return false;
		} catch ( ProductInvalidException $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if the product's product sync meta field allows it to sync.
	 *
	 * @return bool
	 */
	public function passes_product_sync_field_check(): bool {
		try {
			$this->validate_product_sync_field();
		} catch ( ProductExcludedException $e ) {
			return false;
		} catch ( ProductInvalidException $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate whether the product should be synced to Facebook, but skip the sync field validation.
	 *
	 * @return bool
	 */
	public function passes_all_checks_except_sync_field(): bool {
		try {
			$this->validate_but_skip_sync_field();
		} catch ( ProductExcludedException $e ) {
			return false;
		} catch ( ProductInvalidException $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether product sync is globally disabled.
	 *
	 * @throws ProductExcludedException If product should not be synced.
	 */
	protected function validate_sync_enabled_globally() {
		if ( ! $this->integration->is_product_sync_enabled() ) {
			throw new ProductExcludedException( __( 'Product sync is globally disabled.', 'facebook-for-woocommerce' ) );
		}
	}

	/**
	 * Check whether the product's status excludes it from sync.
	 *
	 * @throws ProductExcludedException If product should not be synced.
	 */
	protected function validate_product_status() {
		$product = $this->product_parent ? $this->product_parent : $this->product;

		if ( 'publish' !== $product->get_status() ) {
			throw new ProductExcludedException( __( 'Product is not published.', 'facebook-for-woocommerce' ) );
		}
	}

	/**
	 * Check whether the product's visibility excludes it from sync.
	 *
	 * Products are excluded if they are hidden from the store catalog or from search results.
	 *
	 * @throws ProductExcludedException If product should not be synced.
	 */
	protected function validate_product_visibility() {
		$product = $this->product_parent ? $this->product_parent : $this->product;

		/**
		 * Instead of directly calling $product->is_visible(), copying the logic of is_visible() here
		 * excluding the logic for woocommerce_hide_out_of_stock_items because we want to sync out of
		 * stock items as well irrespective of Inventory settings.
		 * ===Logic Starts here===
		 */
		$visible = 'visible' === $product->get_catalog_visibility() || ( is_search() && 'search' === $product->get_catalog_visibility() ) || ( ! is_search() && 'catalog' === $product->get_catalog_visibility() );
		if ( 'trash' === $product->get_status() ) {
			$visible = false;
		} elseif ( 'publish' !== $product->get_status() && ! current_user_can( 'edit_post', $product->get_id() ) ) {
			$visible = false;
		}
		if ( $product->get_parent_id() ) {
			$parent_product = wc_get_product( $product->get_parent_id() );

			if ( $parent_product && 'publish' !== $parent_product->get_status() && ! current_user_can( 'edit_post', $parent_product->get_id() ) ) {
				$visible = false;
			}
		}
		/**
		 * ===Logic Ends here===
		 */

		if ( ! $visible ) {
			throw new ProductExcludedException( __( 'This product cannot be synced to Facebook because it is hidden from your store catalog.', 'facebook-for-woocommerce' ) );
		}
	}

	/**
	 * Check whether the product's categories or tags (terms) exclude it from sync.
	 *
	 * @throws ProductExcludedException If product should not be synced.
	 */
	protected function validate_product_terms() {
		$product = $this->product_parent ? $this->product_parent : $this->product;

		$excluded_categories = $this->integration->get_excluded_product_category_ids();
		if ( $excluded_categories ) {
			if ( ! empty( array_intersect( $product->get_category_ids(), $excluded_categories ) ) ) {
				throw new ProductExcludedException( __( 'Product excluded because of categories.', 'facebook-for-woocommerce' ) );
			}
		}

		$excluded_tags = $this->integration->get_excluded_product_tag_ids();
		if ( $excluded_tags ) {
			if ( ! empty( array_intersect( $product->get_tag_ids(), $excluded_tags ) ) ) {
				throw new ProductExcludedException( __( 'Product excluded because of tags.', 'facebook-for-woocommerce' ) );
			}
		}
	}

	/**
	 * Validate if the product is excluded from at the "product level" (product meta value).
	 *
	 * @throws ProductExcludedException If product should not be synced.
	 */
	protected function validate_product_sync_field() {
		$invalid_exception = new ProductExcludedException( __( 'Sync disabled in product field.', 'facebook-for-woocommerce' ) );

		/**
		 * Filters whether a product should be synced to FB.
		 *
		 * @since 2.6.26
		 *
		 * @param WC_Product $product the product object.
		 */
		if ( ! apply_filters( 'wc_facebook_should_sync_product', true, $this->product ) ) {
			throw new ProductExcludedException( __( 'Product excluded by wc_facebook_should_sync_product filter.', 'facebook-for-woocommerce' ) );
		}
		/**
		 * The variable check will be used when we have create update of a product
		 * Either from Product details page or bulk editor
		 */
		if ( $this->product->is_type( 'variable' ) ) {
			foreach ( $this->product->get_children() as $child_id ) {
				$child_product = wc_get_product( $child_id );
				if ( $child_product && 'no' !== $child_product->get_meta( self::SYNC_ENABLED_META_KEY ) ) {
					// At least one product is "sync-enabled" so bail before exception.
					return;
				}
			}

			// Variable product has no variations with sync enabled so it shouldn't be synced.
			throw $invalid_exception;
		} elseif ( $this->product->get_type() === 'variation' ) {
			/**
			 * This check will run for background jobs like sync all and feeds
			 */
			$parent_sync = $this->product_parent->get_meta( self::SYNC_ENABLED_META_KEY ) || null;

			if ( 'yes' === $parent_sync ) {
				return;
			} elseif ( 'no' === $parent_sync ) {
				throw $invalid_exception;
			} else {
				$variation_sync = false;
				foreach ( $this->product_parent->get_children() as $child_id ) {
					$child_product = wc_get_product( $child_id );
					if ( $child_product && 'no' !== $child_product->get_meta( self::SYNC_ENABLED_META_KEY ) ) {
						// At least one product is "sync-enabled" so bail before exception.
						$variation_sync = true;
						break;
					}
				}

				/**
				 * Updating parent level sync for UI issues and
				 * Future variation checks for sync
				 */
				update_post_meta( $this->product_parent->get_id(), self::SYNC_ENABLED_META_KEY, $variation_sync ? 'yes' : 'no' );
				if ( $variation_sync ) {
					return;
				}
			}

			// Variable product has no variations with sync enabled so it shouldn't be synced.
			throw $invalid_exception;
		} elseif ( 'no' === $this->product->get_meta( self::SYNC_ENABLED_META_KEY ) ) {
				throw $invalid_exception;
		}
	}

	/**
	 * Check if variation product has proper settings.
	 *
	 * @throws ProductInvalidException If product variation violates some requirements.
	 */
	protected function validate_variation_structure() {
		// Check if we are dealing with a variation.
		if ( ! $this->product->is_type( 'variation' ) ) {
			return;
		}
		$attributes = $this->product->get_attributes();

		$used_attributes_count = count(
			array_filter(
				$attributes
			)
		);

		// No more than MAX_NUMBER_OF_ATTRIBUTES_IN_VARIATION ar allowed to be used.
		if ( $used_attributes_count > self::MAX_NUMBER_OF_ATTRIBUTES_IN_VARIATION ) {
			throw new ProductInvalidException( __( 'Too many attributes selected for product. Use 4 or less.', 'facebook-for-woocommerce' ) );
		}
	}
}
