<?php
// phpcs:ignoreFile
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

require_once __DIR__ . '/fbutils.php';

use WooCommerce\Facebook\Framework\Plugin\Compatibility;
use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Products;

defined( 'ABSPATH' ) || exit;

/**
 * Custom FB Product proxy class
 */
class WC_Facebook_Product {


	/**
	 * Product-related constants used for form rendering.
	 * These constants are used in the admin interface for product settings forms.
	 * The actual product data handling uses the same constants defined in WC_Facebook_Product.
	 */

	/** @var string the "new" condition */
	const CONDITION_NEW = 'new';

	/** @var string the "used" condition */
	const CONDITION_USED = 'used';

	/** @var string the "refurbished" condition */
	const CONDITION_REFURBISHED = 'refurbished';

	/** @var string the "adult" age group */
	const AGE_GROUP_ADULT = 'adult';

	/** @var string the "all ages" age group */
	const AGE_GROUP_ALL_AGES = 'all ages';

	/** @var string the "teen" age group */
	const AGE_GROUP_TEEN = 'teen';

	/** @var string the "kids" age group */
	const AGE_GROUP_KIDS = 'kids';

	/** @var string the "toddler" age group */
	const AGE_GROUP_TODDLER = 'toddler';

	/** @var string the "infant" age group */
	const AGE_GROUP_INFANT = 'infant';

	/** @var string the "newborn" age group */
	const AGE_GROUP_NEWBORN = 'newborn';

	/** @var string the "male" gender */
	const GENDER_MALE = 'male';

	/** @var string the "female" gender */
	const GENDER_FEMALE = 'female';

	/** @var string the "unisex" gender */
	const GENDER_UNISEX = 'unisex';

	// Used for the background sync
	const PRODUCT_PREP_TYPE_ITEMS_BATCH = 'items_batch';
	// Used for the background feed upload
	const PRODUCT_PREP_TYPE_FEED = 'feed';
	// Used for direct update and create calls
	const PRODUCT_PREP_TYPE_NORMAL = 'normal';

	// Should match facebook-commerce.php while we migrate that code over
	// to this object.
	const FB_PRODUCT_DESCRIPTION   = 'fb_product_description';
	const FB_PRODUCT_PRICE         = 'fb_product_price';
	const FB_SIZE                  = 'fb_size';
	const FB_COLOR                 = 'fb_color';
	const FB_MATERIAL              = 'fb_material';
	const FB_PATTERN               = 'fb_pattern';
	const FB_PRODUCT_IMAGE         = 'fb_product_image';
	const FB_PRODUCT_CONDITION     = 'fb_product_condition';
	const FB_AGE_GROUP             = 'fb_age_group';
	const FB_GENDER                = 'fb_gender';
	const FB_PRODUCT_VIDEO         = 'fb_product_video';
	const FB_VARIANT_IMAGE         = 'fb_image';
	const FB_VISIBILITY            = 'fb_visibility';
	const FB_REMOVE_FROM_SYNC      = 'fb_remove_from_sync';
	const FB_RICH_TEXT_DESCRIPTION = 'fb_rich_text_description';
	const FB_BRAND                 = 'fb_brand';
	const FB_VARIABLE_BRAND        = 'fb_variable_brand';
	const FB_MPN                   = 'fb_mpn';

	const MIN_DATE_1 = '1970-01-29';
	const MIN_DATE_2 = '1970-01-30';
	const MAX_DATE   = '2038-01-17';
	const MAX_TIME   = 'T23:59+00:00';
	const MIN_TIME   = 'T00:00+00:00';

	static $use_checkout_url = array(
		'simple'    => 1,
		'variable'  => 1,
		'variation' => 1,
	);

	/**
	 * @var int WC_Product ID.
	 */
	public $id;

	/**
	 * @var WC_Product
	 */
	public $woo_product;

	/**
	 * @var string Facebook Product Description.
	 */
	private $fb_description;

	/**
	 * @var array Gallery URLs.
	 */
	private $gallery_urls;

	/**
	 * @var bool Use parent image for variable products.
	 */
	private $fb_use_parent_image;

	/**
	 * @var string Product Description.
	 */
	private $main_description;

	/**
	 * @var bool  Sync short description.
	 */
	private $sync_short_description;

	/**
	 * @var bool Product visibility on Facebook.
	 */
	public $fb_visibility;

	/**
	 * @var bool Product rich text description.
	 */
	public $rich_text_description;

	public function __construct( $wpid, $parent_product = null ) {

		if ( $wpid instanceof WC_Product ) {
			$this->id          = $wpid->get_id();
			$this->woo_product = $wpid;
		} else {
			$this->id          = $wpid;
			$this->woo_product = wc_get_product( $wpid );
		}

		$this->fb_description         = '';
		$this->gallery_urls           = null;
		$this->fb_use_parent_image    = null;
		$this->main_description       = '';
		$this->sync_short_description = \WC_Facebookcommerce_Integration::PRODUCT_DESCRIPTION_MODE_SHORT === facebook_for_woocommerce()->get_integration()->get_product_description_mode();
		$this->rich_text_description  = '';

		if ( $meta = get_post_meta( $this->id, self::FB_VISIBILITY, true ) ) {
			$this->fb_visibility = wc_string_to_bool( $meta );
		} else {
			$this->fb_visibility = '';
			// for products that haven't synced yet
		}

		// Variable products should use some data from the parent_product
		// For performance reasons, that data shouldn't be regenerated every time.
		if ( $parent_product ) {
			$this->gallery_urls          = $parent_product->get_gallery_urls();
			$this->fb_use_parent_image   = $parent_product->get_use_parent_image();
			$this->main_description      = $parent_product->get_fb_description();
			$this->rich_text_description = $parent_product->get_rich_text_description();
		}
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
		if ( in_array( $key, array( 'fb_description', 'gallery_urls', 'fb_use_parent_image', 'main_description', 'sync_short_description' ), true ) ) {
			/* translators: %s property name. */
			_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'The %s property is private and should not be accessed outside its class.', 'facebook-for-woocommerce' ), esc_html( $key ) ), '3.0.32' );
			return $this->$key;
		}

		return null;
	}

	public function exists() {
		return ( $this->woo_product !== null && $this->woo_product !== false );
	}

	// Fall back to calling method on $woo_product
	public function __call( $function, $args ) {
		if ( $this->woo_product ) {
			return call_user_func_array( array( $this->woo_product, $function ), $args );
		} else {
			$e         = new Exception();
			$backtrace = var_export( $e->getTraceAsString(), true );
			WC_Facebookcommerce_Utils::fblog(
				"Calling $function on Null Woo Object. Trace:\n" . $backtrace,
				array(),
				true
			);
			return null;
		}
	}

	public function get_gallery_urls() {
		if ( $this->gallery_urls === null ) {
			if ( is_callable( array( $this, 'get_gallery_image_ids' ) ) ) {
				$image_ids = $this->get_gallery_image_ids();
			} else {
				$image_ids = $this->get_gallery_attachment_ids();
			}
			$gallery_urls = array();
			foreach ( $image_ids as $image_id ) {
				$image_url = wp_get_attachment_url( $image_id );
				if ( ! empty( $image_url ) ) {
					array_push(
						$gallery_urls,
						WC_Facebookcommerce_Utils::make_url( $image_url )
					);
				}
			}
			$this->gallery_urls = array_filter( $gallery_urls );
		}

		return $this->gallery_urls;
	}

	public function get_post_data() {
		if ( is_callable( 'get_post' ) ) {
			return get_post( $this->id );
		} else {
			return $this->get_post_data();
		}
	}

	public function get_fb_price( $for_items_batch = false ) {
		$product_price = Products::get_product_price( $this->woo_product );

		return $for_items_batch ? self::format_price_for_fb_items_batch( $product_price ) : $product_price;
	}

	private static function format_price_for_fb_items_batch( $price ) {
		// items_batch endpoint requires a string and a currency code
		$formatted = ( $price / 100.0 ) . ' ' . get_woocommerce_currency();
		return $formatted;
	}


	/**
	 * Determines whether the current product is a WooCommerce Bookings product.
	 *
	 * TODO: add an integration that filters the Facebook price instead {WV 2020-07-22}
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private function is_bookable_product() {

		return facebook_for_woocommerce()->is_plugin_active( 'woocommerce-bookings.php' ) && class_exists( 'WC_Product_Booking' ) && is_callable( 'is_wc_booking_product' ) && is_wc_booking_product( $this );
	}


	/**
	 * Gets a list of image URLs to use for this product in Facebook sync.
	 *
	 * @return array
	 */
	public function get_all_image_urls() {

		$image_urls = array();

		/**
		 * Filters the FB product image size.
		 *
		 * @since 3.0.34
		 *
		 * @param string $size The image size. e.g. 'full', 'medium', 'thumbnail'.
		 */
		$image_size               = apply_filters( 'facebook_for_woocommerce_fb_product_image_size', 'full' );
		$product_image_url        = wp_get_attachment_image_url( $this->woo_product->get_image_id(), $image_size );
		$parent_product_image_url = null;
		$custom_image_url         = $this->woo_product->get_meta( self::FB_PRODUCT_IMAGE );

		if ( $this->woo_product->is_type( 'variation' ) ) {

			if ( $parent_product = wc_get_product( $this->woo_product->get_parent_id() ) ) {

				$parent_product_image_url = wp_get_attachment_image_url( $parent_product->get_image_id(), $image_size );
			}
		}

		switch ( $this->woo_product->get_meta( Products::PRODUCT_IMAGE_SOURCE_META_KEY ) ) {

			case Products::PRODUCT_IMAGE_SOURCE_CUSTOM:
				$image_urls = array( $custom_image_url, $product_image_url, $parent_product_image_url );
				break;

			case Products::PRODUCT_IMAGE_SOURCE_PARENT_PRODUCT:
				$image_urls = array( $parent_product_image_url, $product_image_url );
				break;

			case Products::PRODUCT_IMAGE_SOURCE_PRODUCT:
			default:
				$image_urls = array( $product_image_url, $parent_product_image_url );
				break;
		}

		$image_urls = array_merge( $image_urls, $this->get_gallery_urls() );
		$image_urls = array_filter( array_unique( $image_urls ) );

		// Regenerate $image_url PHP array indexes after filtering.
		// The array_filter does not touches indexes so if something gets removed we may end up with gaps.
		// Later parts of the code expect something to exist under the 0 index.
		$image_urls = array_values( $image_urls );

		if ( empty( $image_urls ) ) {
			$image_urls[] = wc_placeholder_img_src();
		}

		return $image_urls;
	}

	/**
	 * Gets a list of video URLs to use for this product in Facebook sync.
	 *
	 * @return array
	 */
	public function get_all_video_urls() {

		$video_urls = array();

		$attached_videos = get_attached_media( 'video', $this->id );

			$custom_video_urls = $this->woo_product->get_meta( self::FB_PRODUCT_VIDEO );

		if ( empty( $attached_videos ) && empty( $custom_video_urls ) ) {
			return $video_urls;
		}

			// Add custom video URLs to the list
		if ( ! empty( $custom_video_urls ) && is_array( $custom_video_urls ) ) {
			foreach ( $custom_video_urls as $custom_url ) {
				$custom_url = trim( $custom_url );
				if ( ! empty( $custom_url ) ) {
					$video_urls[] = array( 'url' => $custom_url );
				}
			}
		}

			// Add attached video URLs to the list, excluding duplicates from custom video URLs
		if ( ! empty( $attached_videos ) ) {
			$custom_video_url_set = array_flip( array_column( $video_urls, 'url' ) );
			foreach ( $attached_videos as $video ) {
				$url = wp_get_attachment_url( $video->ID );
				if ( $url && ! isset( $custom_video_url_set[ $url ] ) ) {
					$video_urls[] = array( 'url' => $url );
				}
			}
		}

		return $video_urls;
	}



	/**
	 * Gets the list of additional image URLs for the product from the complete list of image URLs.
	 *
	 * It assumes the first URL will be used as the product image.
	 * It returns 20 or less image URLs because Facebook doesn't allow more items on the additional_image_urls field.
	 *
	 * @since 2.0.2
	 *
	 * @param array $image_urls all image URLs for the product.
	 * @return array
	 */
	private function get_additional_image_urls( $image_urls ) {

		return array_slice( $image_urls, 1, 20 );
	}


	// Returns the parent image id for variable products only.
	public function get_parent_image_id() {
		if ( WC_Facebookcommerce_Utils::is_variation_type( $this->woo_product->get_type() ) ) {
			$parent_data = $this->get_parent_data();
			return $parent_data['image_id'];
		}
		return null;
	}

	public function set_description( $description ) {
		$description          = stripslashes( WC_Facebookcommerce_Utils::clean_string( $description ) );
		$this->fb_description = $description;
		update_post_meta(
			$this->id,
			self::FB_PRODUCT_DESCRIPTION,
			$description
		);
	}

	public function set_product_image( $image ) {
		if ( $image !== null && strlen( $image ) !== 0 ) {
			$image = WC_Facebookcommerce_Utils::clean_string( $image );
			$image = WC_Facebookcommerce_Utils::make_url( $image );
			update_post_meta(
				$this->id,
				self::FB_PRODUCT_IMAGE,
				$image
			);
		}
	}

	public function set_product_video_urls( $attachment_ids ) {
		$video_urls = array_filter(
			array_map(
				function ( $id ) {
					return trim( wp_get_attachment_url( $id ) );
				},
				explode( ',', $attachment_ids )
			)
		);
		update_post_meta(
			$this->id,
			self::FB_PRODUCT_VIDEO,
			$video_urls
		);
	}

	public function set_rich_text_description( $rich_text_description ) {
		$rich_text_description       =
			WC_Facebookcommerce_Utils::clean_string( $rich_text_description, false );
		$this->rich_text_description = $rich_text_description;
		update_post_meta(
			$this->id,
			self::FB_RICH_TEXT_DESCRIPTION,
			$rich_text_description
		);
	}

	public function set_fb_brand( $fb_brand ) {
		$fb_brand = stripslashes(
			WC_Facebookcommerce_Utils::clean_string( $fb_brand )
		);
		update_post_meta(
			$this->id,
			self::FB_BRAND,
			$fb_brand
		);
	}

	/**
	 * Utility method to set basic Facebook product attributes
	 * 
	 * @param string $key The meta key to store the value under
	 * @param string $value The value to store
	 * @return void
	 */
	private function set_fb_attribute($key, $value) {
		$value = stripslashes(
			WC_Facebookcommerce_Utils::clean_string($value)
		);
		update_post_meta(
			$this->id,
			$key,
			$value
		);
	}
		
	public function set_fb_material( $fb_material ) {
		$this->set_fb_attribute(self::FB_MATERIAL, $fb_material);
	}
	
	public function set_fb_pattern( $fb_pattern ) {
		$this->set_fb_attribute(self::FB_PATTERN, $fb_pattern);
	}

	public function set_fb_mpn( $fb_mpn ) {
		$this->set_fb_attribute(self::FB_MPN, $fb_mpn);
	}

	public function set_fb_condition( $fb_condition ) {
		$this->set_fb_attribute(self::FB_PRODUCT_CONDITION, $fb_condition);
	}

	public function set_fb_age_group( $fb_age_group ) {
		$this->set_fb_attribute(self::FB_AGE_GROUP, $fb_age_group);
	}
	
	public function set_fb_gender( $fb_gender ) {
		$this->set_fb_attribute(self::FB_GENDER, $fb_gender);
	}

	public function set_fb_color( $fb_color ) {
		$this->set_fb_attribute(self::FB_COLOR, $fb_color);
	}

	public function set_fb_size( $fb_size ) {
		$this->set_fb_attribute(self::FB_SIZE, $fb_size);
	}

	public function set_price( $price ) {
		if ( is_numeric( $price ) ) {
			update_post_meta(
				$this->id,
				self::FB_PRODUCT_PRICE,
				$price
			);
		} else {
			delete_post_meta(
				$this->id,
				self::FB_PRODUCT_PRICE
			);
		}
	}

	public function get_use_parent_image() {
		if ( $this->fb_use_parent_image === null ) {
			$variant_image_setting     =
			get_post_meta( $this->id, self::FB_VARIANT_IMAGE, true );
			$this->fb_use_parent_image = ( $variant_image_setting ) ? true : false;
		}
		return $this->fb_use_parent_image;
	}

	public function set_use_parent_image( $setting ) {
		$this->fb_use_parent_image = ( $setting === 'yes' );
		update_post_meta(
			$this->id,
			self::FB_VARIANT_IMAGE,
			$this->fb_use_parent_image
		);
	}

	public function get_fb_brand() {
		// If this is a variation, first check for variation-specific brand
		if ($this->is_type('variation')) {
			// Get brand directly from variation's post meta
			$fb_brand = get_post_meta(
				$this->id,
				self::FB_BRAND,
				true
			);

			// If variation has no brand set, get from parent
			if (empty($fb_brand)) {
				$parent_id = $this->get_parent_id();
				if ($parent_id) {
					$fb_brand = get_post_meta($parent_id, self::FB_BRAND, true);
				}
			}
		} else {
			// Get brand directly from post meta for non-variation products
			$fb_brand = get_post_meta(
				$this->id,
				self::FB_BRAND,
				true
			);
		}

		// Only fallback to store name if no brand is found on product or parent
		if (empty($fb_brand)) {
			$brand = get_post_meta($this->id, Products::ENHANCED_CATALOG_ATTRIBUTES_META_KEY_PREFIX . 'brand', true);
			$brand_taxonomy = get_the_term_list($this->id, 'product_brand', '', ', ');
			
			if ($brand) {
				$fb_brand = $brand;
			} elseif (!is_wp_error($brand_taxonomy) && $brand_taxonomy) {
				$fb_brand = $brand_taxonomy;
			} else {
				$fb_brand = wp_strip_all_tags(WC_Facebookcommerce_Utils::get_store_name());
			}
		}

		return WC_Facebookcommerce_Utils::clean_string($fb_brand);
	}

	public function get_fb_description() {
		$description = '';

		if ( $this->fb_description ) {
			$description = $this->fb_description;
		}

		if ( empty( $description ) ) {
			// Try to get description from post meta
			$description = get_post_meta(
				$this->id,
				self::FB_PRODUCT_DESCRIPTION,
				true
			);
		}

		// Check if the product type is a variation and no description is found yet
		if ( empty( $description ) && WC_Facebookcommerce_Utils::is_variation_type( $this->woo_product->get_type() ) ) {
			$description = WC_Facebookcommerce_Utils::clean_string( $this->woo_product->get_description() );

			// Fallback to main description
			if ( empty( $description ) && $this->main_description ) {
				$description = $this->main_description;
			}
		}

		// If no description is found from meta or variation, get from post
		if ( empty( $description ) ) {
			$post         = $this->get_post_data();
			$post_content = WC_Facebookcommerce_Utils::clean_string( $post->post_content );
			$post_excerpt = WC_Facebookcommerce_Utils::clean_string( $post->post_excerpt );
			$post_title   = WC_Facebookcommerce_Utils::clean_string( $post->post_title );

			// Prioritize content, then excerpt, then title
			if ( ! empty( $post_content ) ) {
				$description = $post_content;
			}

			if ( $this->sync_short_description || ( empty( $description ) && ! empty( $post_excerpt ) ) ) {
				$description = $post_excerpt;
			}

			if ( empty( $description ) ) {
				$description = $post_title;
			}
		}
		/**
		 * Filters the FB product description.
		 *
		 * @since 3.2.6
		 *
		 * @param string  $description Facebook product description.
		 * @param int     $id          WooCommerce Product ID.
		 */
		return apply_filters( 'facebook_for_woocommerce_fb_product_description', $description, $this->id );
	}

	/**
	 * Get the rich text description for a product.
	 *
	 * This function retrieves the rich text product description, prioritizing Facebook
	 * rich text descriptions over WooCommerce product descriptions.
	 * 1. Check if the Facebook rich text description is set and not empty.
	 * 2. If the rich text description is not set or empty, use the WooCommerce RTD if available.
	 *
	 * @return string The rich text description for the product.
	 */
	public function get_rich_text_description() {
		$rich_text_description = '';

		// Check if the fb description is set as that takes preference
		if ( $this->rich_text_description ) {
			$rich_text_description = $this->rich_text_description;
		} elseif ( $this->fb_description ) {
			$rich_text_description = $this->fb_description;
		}

		// Try to get rich text description from post meta if description has been set
		if ( empty( $rich_text_description ) ) {
			$rich_text_description = get_post_meta(
				$this->id,
				self::FB_RICH_TEXT_DESCRIPTION,
				true
			);

		}

		// For variable products, we want to use the rich text description of the variant.
		// If that's not available, fall back to the main (parent) product's rich text description.
		if ( empty( $rich_text_description ) && WC_Facebookcommerce_Utils::is_variation_type( $this->woo_product->get_type() ) ) {
			$rich_text_description = WC_Facebookcommerce_Utils::clean_string( $this->woo_product->get_description(), false );

			// If the variant's rich text description is still empty, use the main product's rich text description as a fallback
			if ( empty( $rich_text_description ) && $this->main_description ) {
				$rich_text_description = $this->main_description;
			}
		}

		// If no description is found from meta or variation, get from product
		if ( empty( $rich_text_description ) ) {
			$post         = $this->get_post_data();
			$post_content = WC_Facebookcommerce_Utils::clean_string( $post->post_content, false );
			$post_excerpt = WC_Facebookcommerce_Utils::clean_string( $post->post_excerpt, false );

			if ( ! empty( $post_content ) ) {
				$rich_text_description = $post_content;
			}

			if ( $this->sync_short_description || ( empty( $rich_text_description ) && ! empty( $post_excerpt ) ) ) {
				$rich_text_description = $post_excerpt;
			}
		}

		return apply_filters( 'facebook_for_woocommerce_fb_rich_text_description', $rich_text_description, $this->id );
	}

	/**
	 * @param array $product_data
	 * @param bool  $for_items_batch
	 *
	 * @return array
	 */
	public function add_sale_price( $product_data, $for_items_batch = false ) {

		$sale_price                = $this->woo_product->get_sale_price();
		$sale_price_effective_date = '';
		$sale_start                = '';
		$sale_end                  = '';

		// check if sale exist
		if ( is_numeric( $sale_price ) && $sale_price > 0 ) {
			$sale_start =
				( $date     = $this->woo_product->get_date_on_sale_from() )
					? date_i18n( WC_DateTime::ATOM, $date->getOffsetTimestamp() )
					: self::MIN_DATE_1 . self::MIN_TIME;
			$sale_end =
				( $date   = $this->woo_product->get_date_on_sale_to() )
					? date_i18n( WC_DateTime::ATOM, $date->getOffsetTimestamp() )
					: self::MAX_DATE . self::MAX_TIME;
			$sale_price_effective_date =
				( $sale_start == self::MIN_DATE_1 . self::MIN_TIME && $sale_end == self::MAX_DATE . self::MAX_TIME )
				? ''
				: $sale_start . '/' . $sale_end;
				$sale_price            =
				intval( round( $this->get_price_plus_tax( $sale_price ) * 100 ) );

			// Set Sale start and end as empty if set to default values
			if ( $sale_start == self::MIN_DATE_1 . self::MIN_TIME && $sale_end == self::MAX_DATE . self::MAX_TIME ) {
				$sale_start = '';
				$sale_end   = '';
			}
		}

		// check if sale is expired and sale time range is valid
		if ( $for_items_batch ) {
			$product_data['sale_price_effective_date'] = $sale_price_effective_date;
			$product_data['sale_price']                = is_numeric( $sale_price ) ? self::format_price_for_fb_items_batch( $sale_price ) : '';
		} else {
			$product_data['sale_price_start_date'] = $sale_start;
			$product_data['sale_price_end_date']   = $sale_end;
			$product_data['sale_price']            = is_numeric( $sale_price ) ? $sale_price : 0;
		}

		return $product_data;
	}

	public function get_price_plus_tax( $price ) {
		$woo_product = $this->woo_product;
		// // wc_get_price_including_tax exist for Woo > 2.7
		if ( function_exists( 'wc_get_price_including_tax' ) ) {
			$args = array(
				'qty'   => 1,
				'price' => $price,
			);
			return get_option( 'woocommerce_tax_display_shop' ) === 'incl'
					? wc_get_price_including_tax( $woo_product, $args )
					: wc_get_price_excluding_tax( $woo_product, $args );
		} else {
			return get_option( 'woocommerce_tax_display_shop' ) === 'incl'
					? $woo_product->get_price_including_tax( 1, $price )
					: $woo_product->get_price_excluding_tax( 1, $price );
		}
	}

	public function get_grouped_product_option_names( $key, $option_values ) {
		// Convert all slug_names in $option_values into the visible names that
		// advertisers have set to be the display names for a given attribute value
		$terms = get_the_terms( $this->id, $key );
		return ! is_array( $terms ) ? array() : array_map(
			function ( $slug_name ) use ( $terms ) {
				foreach ( $terms as $term ) {
					if ( $term->slug === $slug_name ) {
						return $term->name;
					}
				}
				return $slug_name;
			},
			$option_values
		);
	}

	public function get_fb_condition() {
		// Get condition directly from post meta
		$fb_condition = get_post_meta(
			$this->id,
			self::FB_PRODUCT_CONDITION,
			true
		);

		// If empty and this is a variation, get the parent condition
		if ( empty( $fb_condition ) && $this->is_type( 'variation' ) ) {
			$parent_id = $this->get_parent_id();
			if ( $parent_id ) {
				$fb_condition = get_post_meta( $parent_id, self::FB_PRODUCT_CONDITION, true );
			}
		}

		return WC_Facebookcommerce_Utils::clean_string( $fb_condition ) ?: self::CONDITION_NEW;
	}


	public function get_fb_age_group() {
		// If this is a variation, get its specific age group value
		if ($this->is_type('variation')) {
			$attributes = $this->woo_product->get_attributes();
			
			foreach ($attributes as $key => $value) {
				$attr_key = strtolower($key);
				if ($attr_key === 'age_group') {
					return WC_Facebookcommerce_Utils::clean_string($value);
				}
			}
		}

		// Get age group directly from post meta
		$fb_age_group = get_post_meta(
			$this->id,
			self::FB_AGE_GROUP,
			true
		);

		// If empty and this is a variation, get the parent age group
		if ( empty( $fb_age_group ) && $this->is_type( 'variation' ) ) {
			$parent_id = $this->get_parent_id();
			if ( $parent_id ) {
				$fb_age_group = get_post_meta( $parent_id, self::FB_AGE_GROUP, true );
			}
		}

		return WC_Facebookcommerce_Utils::clean_string( $fb_age_group );
	}

	public function get_fb_gender() {
		// If this is a variation, get its specific gender value
		if ($this->is_type('variation')) {
			$attributes = $this->woo_product->get_attributes();
			
			foreach ($attributes as $key => $value) {
				$attr_key = strtolower($key);
				if ($attr_key === 'gender') {
					return WC_Facebookcommerce_Utils::clean_string($value);
				}
			}
		}

		// Get gender directly from post meta
		$fb_gender = get_post_meta(
			$this->id,
			self::FB_GENDER,
			true
		);

		// If empty and this is a variation, get the parent condition
		if ( empty( $fb_gender ) && $this->is_type( 'variation' ) ) {
			$parent_id = $this->get_parent_id();
			if ( $parent_id ) {
				$fb_gender = get_post_meta( $parent_id, self::FB_GENDER, true );
			}
		}

		return WC_Facebookcommerce_Utils::clean_string( $fb_gender );
	}


	/**
	 * Gets the FB size value for the product.
	 *
	 * @return string
	 */
	public function get_fb_size() {
		// If this is a variation, get its specific size value
		if ($this->is_type('variation')) {
			$attributes = $this->woo_product->get_attributes();
			
			foreach ($attributes as $key => $value) {
				$attr_key = strtolower($key);
				if ($attr_key === 'size') {
					return mb_substr(WC_Facebookcommerce_Utils::clean_string($value), 0, 200);
				}
			}
		}

		// Get size directly from post meta
		$fb_size = get_post_meta(
			$this->id,
			self::FB_SIZE,
			true
		);

		// If empty and this is a variation, get the parent condition
		if ( empty( $fb_size ) && $this->is_type( 'variation' ) ) {
			$parent_id = $this->get_parent_id();
			if ( $parent_id ) {
				$fb_size = get_post_meta( $parent_id, self::FB_SIZE, true );
			}
		}

		return mb_substr( WC_Facebookcommerce_Utils::clean_string( $fb_size ), 0, 200 );
	}


	/**
	 * Gets the FB color value for the product.
	 *
	 * @return string
	 */
	public function get_fb_color() {
		// If this is a variation, get its specific color value
		if ($this->is_type('variation')) {
			$attributes = $this->woo_product->get_attributes();
			
			foreach ($attributes as $key => $value) {
				$attr_key = strtolower($key);
				if ($attr_key === 'color' || $attr_key === 'colour') {
					return mb_substr(WC_Facebookcommerce_Utils::clean_string($value), 0, 200);
				}
			}
		}

		// Get color directly from post meta for non-variation products
		$fb_color = get_post_meta(
			$this->id,
			self::FB_COLOR,
			true
		);

		// If empty and this is a variation, get the parent color
		if ( empty( $fb_color ) && $this->is_type( 'variation' ) ) {
			$parent_id = $this->get_parent_id();
			if ( $parent_id ) {
				$fb_color = get_post_meta( $parent_id, self::FB_COLOR, true );
			}
		}

		return mb_substr(WC_Facebookcommerce_Utils::clean_string($fb_color), 0, 200);
	}

	/**
	 * Gets the FB material value for the product.
	 *
	 * @return string
	 */
	public function get_fb_material() {
		// If this is a variation, get its specific material value
		if ($this->is_type('variation')) {
			$attributes = $this->woo_product->get_attributes();
			
			// Check for material attribute
			foreach ($attributes as $key => $value) {
				$attr_key = strtolower($key);
				if ($attr_key === 'material') {
					return mb_substr(WC_Facebookcommerce_Utils::clean_string($value), 0, 200);
				}
			}
		}

		// Get material directly from post meta for non-variation products
		$fb_material = get_post_meta(
			$this->id,
			self::FB_MATERIAL,
			true
		);

		return mb_substr(WC_Facebookcommerce_Utils::clean_string($fb_material), 0, 200);
	}

	public function get_fb_mpn() {
		// If this is a variation, get its specific mpn value
		if ($this->is_type('variation')) {
			$attributes = $this->woo_product->get_attributes();
			
			// Check for mpn attribute
			foreach ($attributes as $key => $value) {
				$attr_key = strtolower($key);
				if ($attr_key === 'mpn') {
					return mb_substr(WC_Facebookcommerce_Utils::clean_string($value), 0, 200);
				}
			}
		}

		// Get material directly from post meta for non-variation products
		$fb_mpn = get_post_meta(
			$this->id,
			self::FB_MPN,
			true
		);

		// If empty and this is a variation, get the parent mpn
		if ( empty( $fb_mpn ) && $this->is_type( 'variation' ) ) {
			$parent_id = $this->get_parent_id();
			if ( $parent_id ) {
				$fb_mpn = get_post_meta( $parent_id, self::FB_MPN, true );
			}
		}

		return WC_Facebookcommerce_Utils::clean_string( $fb_mpn );
	}

	/**
	 * Gets the FB pattern value for the product.
	 *
	 * @return string
	 */
	public function get_fb_pattern() {
		// If this is a variation, get its specific material value
		if ($this->is_type('variation')) {
			$attributes = $this->woo_product->get_attributes();
			
			// Check for material attribute
			foreach ($attributes as $key => $value) {
				$attr_key = strtolower($key);
				if ($attr_key === 'pattern') {
					return mb_substr(WC_Facebookcommerce_Utils::clean_string($value), 0, 200);
				}
			}
		}

		// Get pattern directly from post meta
		$fb_pattern = get_post_meta(
			$this->id,
			self::FB_PATTERN,
			true
		);

		// If empty and this is a variation, get the parent pattern
		if ( empty( $fb_pattern ) && $this->is_type( 'variation' ) ) {
			$parent_id = $this->get_parent_id();
			if ( $parent_id ) {
				$fb_pattern = get_post_meta( $parent_id, self::FB_PATTERN, true );
			}
		}

		return mb_substr( WC_Facebookcommerce_Utils::clean_string( $fb_pattern ), 0, 200 );
	}


	public function update_visibility( $is_product_page, $visible_box_checked ) {
		$visibility = get_post_meta( $this->id, self::FB_VISIBILITY, true );
		if ( $visibility && ! $is_product_page ) {
			// If the product was previously set to visible, keep it as visible
			// (unless we're on the product page)
			$this->fb_visibility = $visibility;
		} else {
			// If the product is not visible OR we're on the product page,
			// then update the visibility as needed.
			$this->fb_visibility = $visible_box_checked ? true : false;
			update_post_meta( $this->id, self::FB_VISIBILITY, $this->fb_visibility );
		}
	}

	// wrapper function to find item_id for default variation
	function find_matching_product_variation() {
		if ( is_callable( array( $this, 'get_default_attributes' ) ) ) {
			$default_attributes = $this->get_default_attributes();
		} else {
			$default_attributes = $this->get_variation_default_attributes();
		}

		if ( ! $default_attributes ) {
			return;
		}
		foreach ( $default_attributes as $key => $value ) {
			if ( strncmp( $key, 'attribute_', strlen( 'attribute_' ) ) === 0 ) {
				continue;
			}
			unset( $default_attributes[ $key ] );
			$default_attributes[ sprintf( 'attribute_%s', $key ) ] = $value;
		}
		if ( class_exists( 'WC_Data_Store' ) ) {
			// for >= woo 3.0.0
			$data_store = WC_Data_Store::load( 'product' );
			return $data_store->find_matching_product_variation(
				$this,
				$default_attributes
			);
		} else {
			return $this->get_matching_variation( $default_attributes );
		}
	}

	private function build_checkout_url( $product_url ) {
		// Use product_url for external/bundle product setting.
		$product_type = $this->get_type();
		if ( ! $product_type || ! isset( self::$use_checkout_url[ $product_type ] ) ) {
				$checkout_url = $product_url;
		} elseif ( wc_get_cart_url() ) {
			$char = '?';
			// Some merchant cart pages are actually a querystring
			if ( strpos( wc_get_cart_url(), '?' ) !== false ) {
				$char = '&';
			}

			$checkout_url = WC_Facebookcommerce_Utils::make_url(
				wc_get_cart_url() . $char
			);

			if ( WC_Facebookcommerce_Utils::is_variation_type( $this->get_type() ) ) {
				$query_data = array(
					'add-to-cart'  => $this->get_parent_id(),
					'variation_id' => $this->get_id(),
				);

				$query_data = array_merge(
					$query_data,
					$this->get_variation_attributes()
				);

			} else {
				$query_data = array(
					'add-to-cart' => $this->get_id(),
				);
			}

			$checkout_url = $checkout_url . http_build_query( $query_data );

		} else {
			$checkout_url = null;
		}//end if
	}

	/**
	 * Gets product data to send to Facebook.
	 *
	 * @param string $retailer_id the retailer ID of the product
	 * @param string $type_to_prepare_for whether the data is going to be used in a feed upload, an items_batch update or a direct api call
	 * @return array
	 */
	public function prepare_product( $retailer_id = null, $type_to_prepare_for = self::PRODUCT_PREP_TYPE_NORMAL ) {

		if ( ! $retailer_id ) {
			$retailer_id =
			WC_Facebookcommerce_Utils::get_fb_retailer_id( $this );
		}
		$image_urls = $this->get_all_image_urls();

		// Replace WordPress sanitization's ampersand with a real ampersand.
		$product_url = str_replace(
			'&amp%3B',
			'&',
			html_entity_decode( $this->get_permalink() )
		);

		$id = $this->get_id();
		if ( WC_Facebookcommerce_Utils::is_variation_type( $this->get_type() ) ) {
			$id = $this->get_parent_id();
		}

		$categories = WC_Facebookcommerce_Utils::get_product_categories( $id );
		
		$product_data = array();
		$product_data[ 'description' ] = $this->get_fb_description();
		$product_data[ 'rich_text_description' ] = $this->get_rich_text_description();
		$product_data[ 'product_type' ] = $categories['categories'];
		$product_data[ 'brand' ] = Helper::str_truncate( $this->get_fb_brand(), 100 );
		$product_data[ 'mpn' ] = Helper::str_truncate( $this->get_fb_mpn(), 100 );
		$product_data[ 'availability' ] = $this->is_in_stock() ? 'in stock' : 'out of stock';
		$product_data[ 'visibility' ] = Products::is_product_visible( $this->woo_product ) ? \WC_Facebookcommerce_Integration::FB_SHOP_PRODUCT_VISIBLE : \WC_Facebookcommerce_Integration::FB_SHOP_PRODUCT_HIDDEN;
		$product_data[ 'retailer_id' ] = $retailer_id;
		$product_data[ 'external_variant_id' ] = $this->get_id();
		$product_data[ 'condition' ] = $this->get_fb_condition();
		$product_data[ 'size' ] = $this->get_fb_size();
		$product_data[ 'color' ] = $this->get_fb_color();
		$product_data[ 'mpn' ] = $this->get_fb_mpn();
		$product_data[ 'pattern' ] = Helper::str_truncate( $this->get_fb_pattern(), 100 );
		$product_data[ 'age_group' ] = $this->get_fb_age_group();
		$product_data[ 'gender' ] = $this->get_fb_gender();
		$product_data[ 'material' ] = Helper::str_truncate( $this->get_fb_material(), 100 );
		$product_data[ 'pattern' ] = Helper::str_truncate( $this->get_fb_pattern(), 100 );

		if ( self::PRODUCT_PREP_TYPE_ITEMS_BATCH === $type_to_prepare_for ) {
			$product_data['title'] = WC_Facebookcommerce_Utils::clean_string( $this->get_title() );
			$product_data['image_link'] = $image_urls[0];
			$product_data['additional_image_link'] = $this->get_additional_image_urls( $image_urls );
			$product_data['link'] = $product_url;
			$product_data['price'] = $this->get_fb_price( true );

			$product_data = $this->add_sale_price( $product_data, true );
		} else {
			$product_data['name'] = WC_Facebookcommerce_Utils::clean_string( $this->get_title() );
			$product_data['image_url'] = $image_urls[0];
			$product_data['additional_image_urls'] = $this->get_additional_image_urls( $image_urls );
			$product_data['url'] = $product_url;
			$product_data['price']                 = $this->get_fb_price();
			$product_data['currency']              = get_woocommerce_currency();
			
			/**
			 * 'category' is a required field for creating a ProductItem object when posting to /{product_catalog_id}/products.
			 * This field should have the Google product category for the item. Google product category is not a required field
			 * in the WooCommerce product editor. Hence, we are setting 'category' to Woo product categories by default and overriding
			 * it when a Google product category is set.
			 *
			 * @see https://developers.facebook.com/docs/marketing-api/reference/product-catalog/products/#parameters-2
			 * @see https://github.com/woocommerce/facebook-for-woocommerce/pull/2575
			 * @see https://github.com/woocommerce/facebook-for-woocommerce/issues/2593
			 */
			$product_data['category'] = $categories['categories'];
			
			$product_data   = $this->add_sale_price( $product_data );
		}//end if

		$video_urls = $this->get_all_video_urls();
		if ( ! empty( $video_urls ) && self::PRODUCT_PREP_TYPE_NORMAL !== $type_to_prepare_for ) {
			$product_data['video'] = $video_urls;
		}

		$google_product_category = Products::get_google_product_category_id( $this->woo_product );
		if ( $google_product_category ) {
			$product_data['google_product_category'] = $google_product_category;
		}

		// Currently only items batch and feed support enhanced catalog fields
		if ( $google_product_category && self::PRODUCT_PREP_TYPE_NORMAL !== $type_to_prepare_for ) {
			$product_data = $this->apply_enhanced_catalog_fields_from_attributes( $product_data, $google_product_category );
		}

		// Add stock quantity if the product or variant is stock managed.
		// In case if variant is not stock managed but parent is, fallback on parent value.
		if ( $this->woo_product->managing_stock() ) {
			$product_data['quantity_to_sell_on_facebook'] = (int) max( 0, $this->woo_product->get_stock_quantity() );
		} else if ( $this->woo_product->is_type( 'variation' ) ) {
			$parent_product = wc_get_product( $this->woo_product->get_parent_id() );
			if ( $parent_product && $parent_product->managing_stock() ) {
				$product_data['quantity_to_sell_on_facebook'] = (int) max( 0, $parent_product->get_stock_quantity() );
			}
		}

		// Add GTIN (Global Trade Item Number)
		if ( method_exists( $this->woo_product, 'get_global_unique_id' ) && $gtin = $this->woo_product->get_global_unique_id() ) {
			$product_data['gtin'] = $gtin;
		}

		if ( $date_modified = $this->woo_product->get_date_modified() ) {
			$product_data[ 'external_update_time' ] = $date_modified->getTimestamp();
		}

		// Only use checkout URLs if they exist.
		$checkout_url = $this->build_checkout_url( $product_url );
		if ( $checkout_url ) {
			$product_data['checkout_url'] = $checkout_url;
		}

		// If using WPML, set the product to hidden unless it is in the
		// default language. WPML >= 3.2 Supported.
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			if ( class_exists( 'WC_Facebook_WPML_Injector' ) && WC_Facebook_WPML_Injector::should_hide( $id ) ) {
				$product_data['visibility'] = \WC_Facebookcommerce_Integration::FB_SHOP_PRODUCT_HIDDEN;
			}
		}

		// Exclude variations that are "virtual" products from export to Facebook &&
		// No Visibility Option for Variations
		// get_virtual() returns true for "unassembled bundles", so we exclude
		// bundles from this check.
		if ( true === $this->get_virtual() && 'bundle' !== $this->get_type() ) {
			$product_data['visibility'] = \WC_Facebookcommerce_Integration::FB_SHOP_PRODUCT_HIDDEN;
		}

		if ( self::PRODUCT_PREP_TYPE_FEED !== $type_to_prepare_for ) {
			$this->prepare_variants_for_item( $product_data );
		} elseif (
			WC_Facebookcommerce_Utils::is_all_caps( $product_data['description'] )
		) {
			$product_data['description'] =
			mb_strtolower( $product_data['description'] );
		}

		/**
		* Filters the generated product data.
		*
		* @param int   $id           Woocommerce product id
		* @param array $product_data An array of product data
		*/
		return apply_filters(
			'facebook_for_woocommerce_integration_prepare_product',
			$product_data,
			$id
		);
	}

	/**
	 * Adds enhanced catalog fields to product data array. Separated from
	 * the main function to make it easier to develop and debug, potentially
	 * worth refactoring into main prepare_product function when complete.
	 *
	 * @param array  $product_data       The preparted product data map.
	 * @param string $google_category_id The Google product category id string.
	 * @return array
	 */
	private function apply_enhanced_catalog_fields_from_attributes( $product_data, $google_category_id ) {
		$category_handler = facebook_for_woocommerce()->get_facebook_category_handler();
		if ( empty( $google_category_id ) || ! $category_handler->is_category( $google_category_id ) ) {
			return $product_data;
		}
		$enhanced_data = array();

		$all_attributes = $category_handler->get_attributes_with_fallback_to_parent_category( $google_category_id );

		if ( empty( $all_attributes ) ) {
			return $product_data;
		}

		foreach ( $all_attributes as $attribute ) {
			$value            = Products::get_enhanced_catalog_attribute( $attribute['key'], $this->woo_product );
			$convert_to_array = (
				isset( $attribute['can_have_multiple_values'] ) &&
				true === $attribute['can_have_multiple_values'] &&
				'string' === $attribute['type']
			);

			if ( ! empty( $value ) &&
				$category_handler->is_valid_value_for_attribute( $google_category_id, $attribute['key'], $value )
			) {
				if ( $convert_to_array ) {
					$value = array_map( 'trim', explode( ',', $value ) );
				}
				$enhanced_data[ $attribute['key'] ] = $value;
			}
		}

		return array_merge( $product_data, $enhanced_data );
	}


	/**
	 * Filters list of attributes to only those available for a given product
	 *
	 * @param \WC_Product $product WooCommerce Product
	 * @param array       $all_attributes List of Enhanced Catalog attributes to match
	 * @return array
	 */
	public function get_matched_attributes_for_product( $product, $all_attributes ) {
		$matched_attributes = array();
		$sanitized_keys     = array_map(
			function ( $key ) {
					return \WC_Facebookcommerce_Utils::sanitize_variant_name( $key, false );
			},
			array_keys( $product->get_attributes() )
		);

		$matched_attributes = array_filter(
			$all_attributes,
			function ( $attribute ) use ( $sanitized_keys ) {
				if ( is_array( $attribute ) && isset( $attribute['key'] ) ) {
					return in_array( $attribute['key'], $sanitized_keys );
				}
				return false; // Return false if $attribute is not valid
			}
		);

		return $matched_attributes;
	}


	/**
	 * Normalizes variant data for Facebook.
	 *
	 * @param array $product_data variation product data
	 * @return array
	 */
	public function prepare_variants_for_item( &$product_data ) {

		/** @var \WC_Product_Variation $product */
		$product = $this;

		if ( ! $product->is_type( 'variation' ) ) {
			return array();
		}

		$attributes = $product->get_variation_attributes();

		if ( ! $attributes ) {
			return array();
		}

		$variant_names = array_keys( $attributes );
		$variant_data  = array();

		// Loop through variants (size, color, etc) if they exist
		// For each product field type, pull the single variant
		foreach ( $variant_names as $original_variant_name ) {

			// Ensure that the attribute exists before accessing it
			if ( ! isset( $attributes[ $original_variant_name ] ) ) {
				continue; // Skip if the attribute is not set
			}

			// don't handle any attributes that are designated as Commerce attributes
			if ( in_array( str_replace( 'attribute_', '', strtolower( $original_variant_name ) ), Products::get_distinct_product_attributes( $this->woo_product ), true ) ) {
				continue;
			}

			// Retrieve label name for attribute
			$label = wc_attribute_label( $original_variant_name, $product );

			// Clean up variant name (e.g. pa_color should be color)
			$new_name = \WC_Facebookcommerce_Utils::sanitize_variant_name( $original_variant_name, false );

			// Sometimes WC returns an array, sometimes it's an assoc array, depending
			// on what type of taxonomy it's using.  array_values will guarantee we
			// only get a flat array of values.
			if ( $options = \WC_Facebookcommerce_Utils::get_variant_option_name( $this->id, $label, $attributes[ $original_variant_name ] ) ) {

				if ( is_array( $options ) ) {

					$option_values = array_values( $options );

				} else {

					$option_values = array( $options );

					// If this attribute has value 'any', options will be empty strings
					// Redirect to product page to select variants.
					// Reset checkout url since checkout_url (build from query data will
					// be invalid in this case.
					if ( count( $option_values ) === 1 && empty( $option_values[0] ) ) {
							$option_values[0]             = 'any';
							$product_data['checkout_url'] = $product_data['url'];
					}
				}

				if ( \WC_Facebookcommerce_Utils::FB_VARIANT_GENDER === $new_name && ! isset( $product_data[ \WC_Facebookcommerce_Utils::FB_VARIANT_GENDER ] ) ) {

					// If we can't validate the gender, this will be null.
					$product_data[ $new_name ] = \WC_Facebookcommerce_Utils::validateGender( $option_values[0] );
				}

				switch ( $new_name ) {

					case \WC_Facebookcommerce_Utils::FB_VARIANT_GENDER:
						// If we can't validate the GENDER field, we'll fall through to the
						// default case and set the gender into custom data.
						if ( $product_data[ $new_name ] ) {

							$variant_data[] = array(
								'product_field' => $new_name,
								'label'         => $label,
								'options'       => $option_values,
							);
						}

						break;

					default:
						// This is for any custom_data.
						if ( ! isset( $product_data['custom_data'] ) ) {
							$product_data['custom_data'] = array();
						}
						$new_name                                 = wc_attribute_label( $new_name, $product );
						$product_data['custom_data'][ $new_name ] = urldecode( $option_values[0] );
						break;
				}//end switch
			} else {

				\WC_Facebookcommerce_Utils::log( $product->get_id() . ': No options for ' . $original_variant_name );
				continue;
			}//end if
		}//end foreach

		return $variant_data;
	}


	/**
	 * Normalizes variable product variations data for Facebook.
	 *
	 * @param bool $feed_data whether this is used for feed data
	 * @return array
	 */
	public function prepare_variants_for_group( $feed_data = false ) {

		/** @var \WC_Product_Variable $product */
		$product        = $this;
		$final_variants = array();

		try {

			if ( ! $product->is_type( 'variable' ) ) {
				throw new \Exception( 'prepare_variants_for_group called on non-variable product' );
			}

			$variation_attributes = $product->get_variation_attributes();

			if ( ! $variation_attributes ) {
				return array();
			}

			foreach ( array_keys( $product->get_attributes() ) as $name ) {

				$label = wc_attribute_label( $name, $product );

				if ( taxonomy_is_product_attribute( $name ) ) {
					$key = $name;
				} else {
					// variation_attributes keys are labels for custom attrs for some reason
					$key = $label;
				}

				if ( ! $key ) {
					throw new \Exception( "Critical error: can't get attribute name or label!" );
				}

				if ( isset( $variation_attributes[ $key ] ) ) {
					// array of the options (e.g. small, medium, large)
					$option_values = $variation_attributes[ $key ];
				} else {
					// skip variations without valid attribute options
					\WC_Facebookcommerce_Utils::log( $product->get_id() . ': No options for ' . $name );
					continue;
				}

				// If this is a variable product, check default attribute.
				// If it's being used, show it as the first option on Facebook.
				if ( $first_option = $product->get_variation_default_attribute( $key ) ) {

					$index = array_search( $first_option, $option_values, false );

					unset( $option_values[ $index ] );

					array_unshift( $option_values, $first_option );
				}

				if ( function_exists( 'taxonomy_is_product_attribute' ) && taxonomy_is_product_attribute( $name ) ) {
					$option_values = $this->get_grouped_product_option_names( $key, $option_values );
				}

				switch ( $name ) {

					case Products::get_product_color_attribute( $this->woo_product ):
						$name = WC_Facebookcommerce_Utils::FB_VARIANT_COLOR;
						break;

					case Products::get_product_size_attribute( $this->woo_product ):
						$name = WC_Facebookcommerce_Utils::FB_VARIANT_SIZE;
						break;

					case Products::get_product_pattern_attribute( $this->woo_product ):
						$name = WC_Facebookcommerce_Utils::FB_VARIANT_PATTERN;
						break;

					default:
						/**
						 * For API approach, product_field need to start with 'custom_data:'
						 *
						 * @link https://developers.facebook.com/docs/marketing-api/reference/product-variant/
						 */
						$name = \WC_Facebookcommerce_Utils::sanitize_variant_name( $name );
				}//end switch

				// for feed uploading, product field should remove prefix 'custom_data:'
				if ( $feed_data ) {
					$name = str_replace( 'custom_data:', '', $name );
				}

				$final_variants[] = array(
					'product_field' => $name,
					'label'         => $label,
					'options'       => $option_values,
				);
			}//end foreach
		} catch ( \Exception $e ) {

			\WC_Facebookcommerce_Utils::fblog( $e->getMessage() );

			return array();
		}//end try

		return $final_variants;
	}

}
