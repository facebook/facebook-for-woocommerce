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

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Events\AAMSettings;
use WooCommerce\Facebook\Events\Normalizer;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\Products\Sync;

if ( ! class_exists( 'WC_Facebookcommerce_Utils' ) ) :

	/**
	 * FB Graph API helper functions
	 */
	class WC_Facebookcommerce_Utils {

		const FB_RETAILER_ID_PREFIX = 'wc_post_id_';
		const PLUGIN_VERSION        = \WC_Facebookcommerce::VERSION; // TODO: remove this in v2.0.0 {CW 2020-02-06}

		// TODO: this constant is no longer used and can probably be removed {WV 2020-01-21}
		const FB_VARIANT_IMAGE   = 'fb_image';
		const FB_VARIANT_SIZE    = 'size';
		const FB_VARIANT_COLOR   = 'color';
		const FB_VARIANT_COLOUR  = 'colour';
		const FB_VARIANT_PATTERN = 'pattern';
		const FB_VARIANT_GENDER  = 'gender';

		public static $ems        = null;
		public static $store_name = null;

		public static $validGenderArray =
		array(
			'male'   => 1,
			'female' => 1,
			'unisex' => 1,
		);

		/**
		 * A deferred events storage.
		 *
		 * @var array
		 *
		 * @since 3.1.6
		 */
		private static $deferred_events = [];

		/**
		 * Prints deferred events into page header.
		 *
		 * @return void
		 *
		 * @since 3.1.6
		 */
		public static function print_deferred_events() {
			$deferred_events = static::load_deferred_events();
			if ( ! empty( $deferred_events ) ) {
				echo '<script>' . implode( PHP_EOL, $deferred_events ) . '</script>'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --- Printing hardcoded JS tracking code.
			}
		}

		/**
		 * Loads deferred events from the storage and cleans the storage immediately after.
		 *
		 * @return array
		 *
		 * @since 3.1.6
		 */
		private static function load_deferred_events(): array {
			$transient_key = static::get_deferred_events_transient_key();
			if ( ! $transient_key ) {
				return array();
			}

			$deferred_events = get_transient( $transient_key );
			if ( ! $deferred_events ) {
				return array();
			}

			delete_transient( $transient_key );
			return $deferred_events;
		}

		/**
		 * Adds event into the list of events to be saved/rendered.
		 *
		 * @param string $code Generated JS code string w/o a script tag.
		 *
		 * @return void
		 *
		 * @since 3.1.6
		 */
		public static function add_deferred_event( string $code ): void {
			static::$deferred_events[] = $code;
		}

		/**
		 * Saves deferred events into the storage.
		 *
		 * @return void
		 *
		 * @since 3.1.6
		 */
		public static function save_deferred_events() {
			$transient_key = static::get_deferred_events_transient_key();
			if ( ! $transient_key ) {
				return;
			}

			$existing_events         = static::load_deferred_events();
			static::$deferred_events = array_merge( $existing_events, static::$deferred_events );

			if ( ! empty( static::$deferred_events ) ) {
				set_transient( $transient_key, static::$deferred_events, DAY_IN_SECONDS );
			}
		}

		/**
		 * Returns the transient key for deferred events based on user session.
		 *
		 * @return string
		 *
		 * @since 3.1.6
		 */
		private static function get_deferred_events_transient_key(): string {
			if ( is_object( WC()->session ) ) {
				return 'facebook_for_woocommerce_async_events_' . md5( WC()->session->get_customer_id() );
			}
			return '';
		}

		/**
		 * WooCommerce 2.1 support for wc_enqueue_js
		 *
		 * @since 1.2.1
		 *
		 * @access public
		 * @param string $code
		 * @return void
		 */
		public static function wc_enqueue_js( $code ) {
			global $wc_queued_js;

			// Immediately renders code in the footer.
			if ( function_exists( 'wc_enqueue_js' ) && empty( $wc_queued_js ) ) {
				wc_enqueue_js( $code );
			} else {
				$wc_queued_js = $code . "\n" . $wc_queued_js;
			}
		}

		/**
		 * Validate URLs, make relative URLs absolute
		 *
		 * @access public
		 * @param string $url
		 * @return string
		 */
		public static function make_url( $url ) {
			if (
			// The first check incorrectly fails for URLs with special chars.
			! filter_var( $url, FILTER_VALIDATE_URL ) &&
			substr( $url, 0, 4 ) !== 'http'
			) {
				return get_site_url() . $url;
			} else {
				return $url;
			}
		}

		/**
		 * Product ID for Dynamic Ads on Facebook can be SKU or wc_post_id_123
		 * This function should be used to get retailer_id based on a WC_Product
		 * from WooCommerce
		 *
		 * @access public
		 * @param WC_Product|WC_Facebook_Product $woo_product
		 * @return string
		 */
		public static function get_fb_retailer_id( $woo_product ) {
			$woo_id = $woo_product->get_id();

			/*
			* Call $woo_product->get_id() instead of ->id to account for Variable
			* products, which have their own variant_ids.
			*/
			$fb_retailer_id = $woo_product->get_sku() ?
				$woo_product->get_sku() . '_' . $woo_id :
				self::FB_RETAILER_ID_PREFIX . $woo_id;

			/**
			 * Filter facebook retailer id value.
			 * This can be used to match retailer id generated by other Facebook plugins.
			 *
			 * @since 2.6.12
			 * @param string     Facebook Retailer ID.
			 * @param WC_Product WooCommerce product.
			 */
			return apply_filters( 'wc_facebook_fb_retailer_id', $fb_retailer_id, $woo_product );
		}

		/**
		 * Return categories for products/pixel
		 *
		 * @access public
		 * @param String $id
		 * @return Array
		 */
		public static function get_product_categories( $wpid ) {
			$category_path          = wp_get_post_terms(
				$wpid,
				'product_cat',
				array( 'fields' => 'all' )
			);
			$content_category       = array_values(
				array_map(
					function( $item ) {
						return $item->name;
					},
					$category_path
				)
			);
			$content_category_slice = array_slice( $content_category, -1 );
			$categories             =
			empty( $content_category ) ? '""' : implode( ', ', $content_category );
			return array(
				'name'       => array_pop( $content_category_slice ),
				'categories' => $categories,
			);
		}

		/**
		 * Returns content id to match on for Pixel fires.
		 *
		 * @access public
		 * @param WC_Product $woo_product
		 * @return array
		 */
		public static function get_fb_content_ids( $woo_product ) {
			return array( self::get_fb_retailer_id( $woo_product ) );
		}

		/**
		 * Clean up strings for FB Graph POSTing.
		 * This function should will:
		 * 1. Replace newlines chars/nbsp with a real space
		 * 2. strip_tags() if not explicitly stated to not
		 * 3. trim()
		 *
		 * @access public
		 * @param String string
		 * @return string
		 */
		public static function clean_string( $string, $strip_html_tags = true ) {

			/**
			 * Filters whether the shortcodes should be applied for a string when syncing a product or be stripped out.
			 *
			 * @since 2.6.19
			 *
			 * @param bool   $apply_shortcodes Shortcodes are applied if set to `true` and stripped out if set to `false`.
			 * @param string $string           String to clean up.
			 */

			 if (empty($string)){
				return '';
			 }
			$apply_shortcodes = apply_filters( 'wc_facebook_string_apply_shortcodes', false, $string );
			if ( $apply_shortcodes ) {
				// Apply active shortcodes
				$string = do_shortcode( $string );
			} else {
				// Strip out active shortcodes
				$string = strip_shortcodes( $string );
			}

			$string = str_replace( array( '&amp%3B', '&amp;' ), '&', $string );
			$string = str_replace( array( "\r", '&nbsp;', "\t" ), ' ', $string );
			if ($strip_html_tags){
				$string = wp_strip_all_tags( $string, false ); // true == remove line breaks
			}
			return $string;
		}

		/**
		 * Returns flat array of woo IDs for variable products, or
		 * an array with a single woo ID for simple products.
		 *
		 * @access public
		 * @param WC_Product|WC_Facebook_Product $woo_product
		 * @return array
		 */
		public static function get_product_array( $woo_product ) {
			$result = [];
			if ( self::is_variable_type( $woo_product->get_type() ) ) {
				foreach ( $woo_product->get_children() as $item_id ) {
					array_push( $result, $item_id );
				}
				return $result;
			} else {
				return array( $woo_product->get_id() );
			}
		}

		/**
		 * Returns true if WooCommerce plugin found.
		 *
		 * @access public
		 * @return bool
		 */
		public static function isWoocommerceIntegration() {
			return class_exists( 'WooCommerce' );
		}

		/**
		 * Returns integration dependent name.
		 *
		 * @access public
		 * @return string
		 */
		public static function getIntegrationName() {
			if ( self::isWoocommerceIntegration() ) {
				return 'WooCommerce';
			} else {
				return 'WordPress';
			}
		}

		/**
		 * Returns user info for the current WP user.
		 *
		 * @access public
		 * @param AAMSettings $aam_settings
		 * @return array
		 */
		public static function get_user_info( $aam_settings ) {
			$current_user = wp_get_current_user();
			if ( 0 === $current_user->ID || $aam_settings == null || ! $aam_settings->get_enable_automatic_matching() ) {
				// User not logged in or pixel not configured with automatic advance matching
				return [];
			} else {
				// Keys documented in
				// https://developers.facebook.com/docs/facebook-pixel/advanced/advanced-matching
				$user_data            = array(
					'em'          => $current_user->user_email,
					'fn'          => $current_user->user_firstname,
					'ln'          => $current_user->user_lastname,
					'external_id' => strval( $current_user->ID ),
				);
				$user_id              = $current_user->ID;
				$user_data['ct']      = get_user_meta( $user_id, 'billing_city', true );
				$user_data['zp']      = get_user_meta( $user_id, 'billing_postcode', true );
				$user_data['country'] = get_user_meta( $user_id, 'billing_country', true );
				$user_data['st']      = get_user_meta( $user_id, 'billing_state', true );
				$user_data['ph']      = get_user_meta( $user_id, 'billing_phone', true );
				// Each field that is not present in AAM settings or is empty is deleted from user data
				foreach ( $user_data as $field => $value ) {
					if ( $value === null || $value === ''
						|| ! in_array( $field, $aam_settings->get_enabled_automatic_matching_fields() )
					) {
						unset( $user_data[ $field ] );
					}
				}
				// Country is a special case, it is returned as country in AAM settings
				// But used as cn in pixel
				if ( array_key_exists( 'country', $user_data ) ) {
					$country         = $user_data['country'];
					$user_data['cn'] = $country;
					unset( $user_data['country'] );
				}
				$user_data = Normalizer::normalize_array( $user_data, true );
				return $user_data;
			}
		}

		/**
		 * Utility function for development logging.
		 */
		public static function fblog(
		$message,
		$object = [],
		$error = false,
		$ems = '' ) {
			if ( $error ) {
				$object['plugin_version'] = self::PLUGIN_VERSION;
				$object['php_version']    = phpversion();
			}
			$message = json_encode(
				array(
					'message' => $message,
					'object'  => $object,
				)
			);
			$ems     = $ems ?: self::$ems;
			if ( $ems ) {
				try {
					facebook_for_woocommerce()->get_api()->log($ems, $message, $error);
				} catch ( ApiException $e ) {
					$message = sprintf( 'There was an error trying to log: %s', $e->getMessage() );
					facebook_for_woocommerce()->log( $message );
				}
			} else {
				error_log(
					'external merchant setting is null, something wrong here: ' .
					$message
				);
			}
		}

		/**
		 * Utility function for development Tip Events logging.
		 */
		public static function tip_events_log( $tip_id, $channel_id, $event, $ems = '' ) {
			$ems = $ems ?: self::$ems;
			if ( $ems ) {
				try {
					facebook_for_woocommerce()->get_api()->log_tip_event($tip_id, $channel_id, $event);
				} catch ( ApiException $e ) {
					$message = sprintf( 'There was an error while logging tip events: %s', $e->getMessage() );
					facebook_for_woocommerce()->log( $message );
				}
			} else {
				error_log( 'external merchant setting is null' );
			}
		}

		public static function is_variation_type( $type ) {
			return $type == 'variation' || $type == 'subscription_variation';
		}

		public static function is_variable_type( $type ) {
			return $type == 'variable' || $type == 'variable-subscription';
		}

		public static function check_woo_ajax_permissions( $action_text, $die ) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				self::log(
					'Non manage_woocommerce user attempting to' . $action_text . '!',
					[],
					true
				);
				if ( $die ) {
					wp_die();
				}
				return false;
			}
			return true;
		}

		/**
		 * Returns true if id is a positive non-zero integer
		 *
		 * @access public
		 * @param string $pixel_id
		 * @return bool
		 */
		public static function is_valid_id( $pixel_id ) {
			return isset( $pixel_id ) && is_numeric( $pixel_id ) && (int) $pixel_id > 0;
		}

		/**
		 * Helper function to query posts.
		 */
		public static function get_wp_posts(
		$product_group_id = null,
		$compare_condition = null,
		$post_type = 'product' ) {
			$args = array(
				'fields'         => 'ids',
				'meta_query'     => array(
					( ( $product_group_id ) ?
					array(
						'key'     => $product_group_id,
						'compare' => $compare_condition,
					) : []
					),
				),
				'post_status'    => 'publish',
				'post_type'      => $post_type,
				'posts_per_page' => -1,
			);
			return get_posts( $args );
		}

		/**
		 * Helper log function for debugging
		 */
		public static function log( $message ) {

			// if this file is being included outside the plugin, or the plugin setting is disabled
			if ( ! function_exists( 'facebook_for_woocommerce' ) || ! facebook_for_woocommerce()->get_integration()->is_debug_mode_enabled() ) {
				return;
			}

			if ( is_array( $message ) || is_object( $message ) ) {
				$message = json_encode( $message );
			} else {
				$message = sanitize_textarea_field( $message );
			}

			facebook_for_woocommerce()->log( $message );
		}

		// Return store name with sanitized apostrophe
		public static function get_store_name() {
			if ( self::$store_name ) {
				return self::$store_name;
			}
			$apos = "\u{2019}";
			$name = trim(
				str_replace(
					"'",
					$apos,
					html_entity_decode(
						get_bloginfo( 'name' ),
						ENT_QUOTES,
						'UTF-8'
					)
				)
			);
			if ( $name ) {
				self::$store_name = $name;
				return $name;
			}
			// Fallback to site url
			$url = get_site_url();
			if ( $url ) {
				self::$store_name = parse_url( $url, PHP_URL_HOST );
				return self::$store_name;
			}
			// If site url doesn't exist, fall back to http host.
			if ( isset( $_SERVER['HTTP_HOST'] ) ) {
				self::$store_name = wc_clean( wp_unslash( $_SERVER['HTTP_HOST'] ) );
				return self::$store_name;
			}

			// If http host doesn't exist, fall back to local host name.
			$url              = gethostname();
			self::$store_name = $url;
			return ( self::$store_name ) ? ( self::$store_name ) : 'A Store Has No Name';
		}


		/**
		 * Get visible name for variant attribute rather than the slug
		 *
		 * @param int    $wp_id         Post ID.
		 * @param string $label         Attribute label.
		 * @param string $default_value Default value to use if the term has no name.
		 * @return string Term name or the default value.
		 */
		public static function get_variant_option_name( $wp_id, $label, $default_value ) {
			$meta           = get_post_meta( $wp_id, $label, true );
			$attribute_name = str_replace( 'attribute_', '', $label );
			$term           = get_term_by( 'slug', $meta, $attribute_name );
			return $term && $term->name ? $term->name : $default_value;
		}

		/**
		 * Get all products for synchronization tasks.
		 *
		 * Warning: While changing this code please make sure that it scales properly.
		 * Sites with big product catalogs should not experience memory problems.
		 *
		 * @return array IDs of all product for synchronization.
		 */
		public static function get_all_product_ids_for_sync() {
			// Get all published products ids. This includes parent products of variations.
			$product_args = array(
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'post_type'      => 'product',
				'posts_per_page' => -1,
			);
			$product_ids  = get_posts( $product_args );

			// Get all variations ids with their parents ids.
			$variation_args     = array(
				'fields'         => 'id=>parent',
				'post_status'    => 'publish',
				'post_type'      => 'product_variation',
				'posts_per_page' => -1,
			);
			$variation_products = get_posts( $variation_args );

			/*
			* Collect all parent products.
			* Exclude variations which parents are not 'publish'.
			*/
			$parent_product_ids = [];
			foreach ( $variation_products as $post_id => $parent_id ) {
				/*
				* Keep track of all parents to remove them from the list of products to sync.
				* Use key to automatically remove duplicated items.
				*/
				$parent_product_ids[ $parent_id ] = true;

				// Include variations with published parents only.
				if ( in_array( $parent_id, $product_ids ) ) {
					$product_ids[] = $post_id;
				}
			}

			// Remove parent products because those can't be represented as Product Items.
			return array_diff( $product_ids, array_keys( $parent_product_ids ) );
		}


		/*
		* Change variant product field name from Woo taxonomy to FB name
		*/
		public static function sanitize_variant_name( $name, $use_custom_data = true ) {
			$name = str_replace( array( 'attribute_', 'pa_' ), '', strtolower( $name ) );

			// British spelling
			if ( $name === self::FB_VARIANT_COLOUR ) {
				$name = self::FB_VARIANT_COLOR;
			}

			if ( $use_custom_data ) {
				switch ( $name ) {
					case self::FB_VARIANT_SIZE:
					case self::FB_VARIANT_COLOR:
					case self::FB_VARIANT_GENDER:
					case self::FB_VARIANT_PATTERN:
						break;
					default:
						$name = 'custom_data:' . strtolower( $name );
						break;
				}
			}

			return $name;
		}

		/*
		* Sanitize attribute names inline with FB name
		*/
		public static function sanitize_attribute_name( $name ) {
			return str_replace(array('-', ' '), '_', $name);
		}

		public static function validateGender( $gender ) {
			if ( $gender && ! isset( self::$validGenderArray[ $gender ] ) ) {
				$first_char = strtolower( substr( $gender, 0, 1 ) );
				// Men, Man, Boys
				if ( $first_char === 'm' || $first_char === 'b' ) {
					return 'male';
				}
				// Women, Woman, Female, Ladies
				if ( $first_char === 'w' || $first_char === 'f' || $first_char === 'l' ) {
					return 'female';
				}
				if ( $first_char === 'u' ) {
					return 'unisex';
				}
				if ( strlen( $gender ) >= 3 ) {
					$gender = strtolower( substr( $gender, 0, 3 ) );
					if ( $gender === 'gir' || $gender === 'her' ) {
						return 'female';
					}
					if ( $gender === 'him' || $gender === 'his' || $gender == 'guy' ) {
						return 'male';
					}
				}
				return null;
			}
			return $gender;
		}

		public static function get_fbid_post_meta( $wp_id, $fbid_type ) {
			return get_post_meta( $wp_id, $fbid_type, true );
		}

		public static function is_all_caps( $value ) {
			if ( $value === null || $value === '' ) {
				return true;
			}
			if ( preg_match( '/[^\\p{Common}\\p{Latin}]/u', $value ) ) {
				// Contains non-western characters
				// So, it can't be all uppercase
				return false;
			}
			$latin_string = preg_replace( '/[^\\p{Latin}]/u', '', $value );
			if ( $latin_string === '' ) {
				// Symbols only
				return true;
			}
			return strtoupper( $latin_string ) === $latin_string;
		}

		public static function decode_json( $json_string, $assoc = false ) {
			// Plugin requires 5.6.0 but for some user use 5.5.9 JSON_BIGINT_AS_STRING
			// will cause 502 issue when redirect.
			return version_compare( phpversion(), '5.6.0' ) >= 0
			? json_decode( $json_string, $assoc, 512, JSON_BIGINT_AS_STRING )
			: json_decode( $json_string, $assoc, 512 );
		}

		public static function set_test_fail_reason( $msg, $trace ) {
			$reason_msg = get_transient( 'facebook_plugin_test_fail' );
			if ( $reason_msg ) {
				$msg = $reason_msg . PHP_EOL . $msg;
			}
			set_transient( 'facebook_plugin_test_fail', $msg );
			set_transient( 'facebook_plugin_test_stack_trace', $trace );
		}

		/**
		 * Helper function to check time cap.
		 */
		public static function check_time_cap( $from, $date_cap ) {
			if ( $from == null ) {
				return true;
			}
			$now         = new DateTime( current_time( 'mysql' ) );
			$diff_in_day = $now->diff( new DateTime( $from ) )->format( '%a' );
			return is_numeric( $diff_in_day ) && (int) $diff_in_day > $date_cap;
		}

		public static function get_cached_best_tip() {
			$cached_best_tip = self::decode_json(
				get_option( 'fb_info_banner_last_best_tip', '' )
			);
			return $cached_best_tip;
		}

		/**
		 * Normalizes product data to be included in a sync request. /items_batch
		 * rather than /batch this time.
		 *
		 * @since 3.1.7
		 *
		 * @param array $data product data.
		 * @return array
		 */
		public static function normalize_product_data_for_items_batch( $data ) {
			/*
			 * To avoid overriding the condition value, we check if the value is set or is not one of
			 * the allowed values before setting it to 'new'. Allowed values are 'refurbished', 'used', and 'new'.
			 */
			if ( ! isset( $data['condition'] ) || ! in_array( $data['condition'], array( 'refurbished', 'used', 'new' ), true ) ) {
				$data['condition'] = 'new';
			}

			// Attributes other than size, color, pattern, or gender need to be included in the additional_variant_attributes field.
			if ( isset( $data['custom_data'] ) && is_array( $data['custom_data'] ) ) {
				$attributes = [];
				foreach ( $data['custom_data'] as $key => $val ) {

					/**
					 * Filter: facebook_for_woocommerce_variant_attribute_comma_replacement
					 *
					 * The Facebook API expects a comma-separated list of attributes in `additional_variant_attribute` field.
					 * https://developers.facebook.com/docs/marketing-api/catalog/reference/
					 * This means that WooCommerce product attributes included in this field should avoid the comma (`,`) character.
					 * Facebook for WooCommerce replaces any `,` with a space by default.
					 * This filter allows a site to provide a different replacement string.
					 *
					 * @since 2.5.0
					 *
					 * @param string $replacement The default replacement string (`,`).
					 * @param string $value Attribute value.
					 * @return string Return the desired replacement string.
					 */
					$attribute_value = str_replace(
						',',
						apply_filters( 'facebook_for_woocommerce_variant_attribute_comma_replacement', ' ', $val ),
						$val
					);
					/** Force replacing , and : characters if those were not cleaned up by filters */
					$attributes[] = str_replace( [ ',', ':' ], ' ', $key ) . ':' . str_replace( [ ',', ':' ], ' ', $attribute_value );
				}

				$data['additional_variant_attribute'] = implode( ',', $attributes );
				unset( $data['custom_data'] );
			}

			return $data;
		}

		/**
		 * Prepares the product data to be included in a sync request.
		 *
		 * @since 3.1.7
		 *
		 * @param \WC_Product $product product object
		 * @return array
		 */
		public static function prepare_product_data_items_batch( $product ) {
			$fb_product = new \WC_Facebook_Product( $product->get_id() );
			$data       = $fb_product->prepare_product( null, \WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH );
			// products that are not variations use their retailer retailer ID as the retailer product group ID
			$data['item_group_id'] = $data['retailer_id'];
			return self::normalize_product_data_for_items_batch( $data );
		}

		/**
		 * Prepares the requests array to be included in a batch api request.
		 *
		 * @since 3.1.7
		 *
		 * @param array $product Array
		 * @return array
		 */
		public static function prepare_product_requests_items_batch( $product ) {
			$product['item_group_id'] = $product['retailer_id'];
			$product_data             = self::normalize_product_data_for_items_batch( $product );

			// extract the retailer_id
			$retailer_id = $product_data['retailer_id'];

			// NB: Changing this to get items_batch to work
			// retailer_id cannot be included in the data object
			unset( $product_data['retailer_id'] );
			$product_data['id'] = $retailer_id;

			$requests = array( [
				'method' => Sync::ACTION_UPDATE,
				'data'   => $product_data,
			] );

			return $requests;
		}

		/**
		 * Prepares the data for a product variation to be included in a sync request.
		 *
		 * @since 3.1.7
		 *
		 * @param \WC_Product $product product object
		 * @return array
		 * @throws PluginException In case no product found.
		 */
		public static function prepare_product_variation_data_items_batch( $product ) {
			$parent_product = wc_get_product( $product->get_parent_id() );

			if ( ! $parent_product instanceof \WC_Product ) {
				throw new PluginException( "No parent product found with ID equal to {$product->get_parent_id()}." );
			}

			$fb_parent_product = new \WC_Facebook_Product( $parent_product->get_id() );
			$fb_product        = new \WC_Facebook_Product( $product->get_id(), $fb_parent_product );

			$data = $fb_product->prepare_product( null, \WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH );

			// product variations use the parent product's retailer ID as the retailer product group ID
			// $data['retailer_product_group_id'] = \WC_Facebookcommerce_Utils::get_fb_retailer_id( $parent_product );
			$data['item_group_id'] = \WC_Facebookcommerce_Utils::get_fb_retailer_id( $parent_product );

			return self::normalize_product_data_for_items_batch( $data );
		}

	}

endif;
