<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Feed;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler;
use WC_Shipping_Zones;

/**
 * Ratings and Reviews Feed class
 *
 * Extends Abstract Feed class to handle ratings and reviews feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class ShippingProfilesFeed extends AbstractFeed {

	/** Header for the shipping profiles feed file. @var string */
	const SHIPPING_PROFILES_FEED_HEADER = 'shipping_profile_id,name,shipping_zones,shipping_rates,applicable_products,applies_to_all_products,applies_to_rest_of_world' . PHP_EOL;

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		$file_writer  = new CsvFeedFileWriter( self::get_data_stream_name(), self::SHIPPING_PROFILES_FEED_HEADER, "\t" );
		$feed_handler = new ShippingProfilesFeedHandler( $file_writer );

		$scheduler      = new ActionScheduler();
		$feed_generator = new ShippingProfilesFeedGenerator( $scheduler, $file_writer, self::get_data_stream_name() );

		$this->init(
			$file_writer,
			$feed_handler,
			$feed_generator,
		);
	}

	public static function get_feed_type(): string {
		return 'SHIPPING_PROFILES';
	}

	protected static function get_data_stream_name(): string {
		return FeedManager::SHIPPING_PROFILES;
	}

	protected static function get_feed_gen_interval(): int {
		return HOUR_IN_SECONDS;
	}


	/**
	 * @throws \Exception If an error is encountered mapping shipping profile data.
	 */
	public static function get_shipping_profiles_data(): array {
		try {
			$shipping_profiles_data = [];
			$zones                  = WC_Shipping_Zones::get_zones();

			foreach ( $zones as $zone ) {
				$locations           = $zone['zone_locations'];
				$countries_to_states = array();

				foreach ( $locations as $location ) {
					$location = (array) $location;
					if ( 'continent' === $location['type'] ) {
						$countries_to_states = self::add_continent_location( $location['code'], $countries_to_states );
					}
					if ( 'country' === $location['type'] ) {
						$countries_to_states = self::add_country_location( $location['code'], $countries_to_states );
					}
					if ( 'state' === $location['type'] ) {
						list($country_code, $state_code)                  = explode( ':', $location['code'] );
						$countries_to_states[ $country_code ]['states'][] = $state_code;
					}
				}

				if ( empty( $countries_to_states ) ) {
					return [];
				}

				// Flattens map structure to an array of struct/shape with 'country' and 'states' keys.
				$countries_with_states = [];
				foreach ( $countries_to_states as $country_code => $country_info ) {
					$countries_with_states[] = array(
						'country'                   => $country_code,
						'states'                    => array_unique( $country_info['states'] ?? [] ),
						'applies_to_entire_country' => $country_info['applies_to_entire_country'] ?? false,
					);
				}

				$shipping_methods = array_map(
					function ( $method ) {
						// Converting shipping method objects to arrays to use [] accessors.
						return (array) $method;
					},
					$zone['shipping_methods']
				);

				$free_shipping_methods      = array_filter(
					$shipping_methods,
					function ( $shipping_method ) {
						return 'free_shipping' === $shipping_method['id'];
					}
				);
				$flat_rate_shipping_methods = array_filter(
					$shipping_methods,
					function ( $shipping_method ) {
						return 'flat_rate' === $shipping_method['id'];
					}
				);

				$shipping_rates = [];
				foreach ( $free_shipping_methods as $free_shipping_method ) {
					$shipping_rates[] = self::get_free_shipping_method_data( $zone, $free_shipping_method );
				}
				foreach ( $flat_rate_shipping_methods as $flat_rate_method ) {
					$shipping_rates[] = self::get_flat_rate_shipping_method_data( $zone, $flat_rate_method );
				}
				// Filter out null shipping rate data;
				$shipping_rates = array_filter( $shipping_rates );

				// Don't send shipping profile if there are no rates since the shipping profile won't be usable.
				if ( 0 === count( $shipping_rates ) ) {
					continue;
				}
				// Because were only handling free shipping which applies to all products for the zone, we only
				// need to return one data shape here. When we need to handle classes, we will want to split this up
				// based on which products the methods apply to. For now, hard code the id suffix.
				$id_suffix                = 'all_products';
				$data                     = array(
					'shipping_profile_id'      => $zone['id'] . '-' . $id_suffix,
					'name'                     => $zone['zone_name'],
					'applies_to_all_products'  => 'true',
					'shipping_zones'           => $countries_with_states,
					'shipping_rates'           => $shipping_rates,
					'applies_to_rest_of_world' => 'false',
				);
				$shipping_profiles_data[] = $data;
			}
			return $shipping_profiles_data;
		} catch ( \Exception $e ) {
			\WC_Facebookcommerce_Utils::log_to_meta(
				'Exception while trying to map shipping profile data for feed',
				array(
					'flow_name'  => FeedUploadUtils::SHIPPING_PROFILES_SYNC_LOGGING_FLOW_NAME,
					'flow_step'  => 'get_shipping_profiles_data',
					'extra_data' => [
						'exception_message' => $e->getMessage(),
					],
				)
			);
			throw $e;
		}
	}


	private static function get_free_shipping_method_data( array $zone, array $free_shipping_method ): ?array {
		$shipping_settings = $free_shipping_method['instance_settings'];

		$shipping_rate = array(
			'name'              => $free_shipping_method['title'],
			'has_free_shipping' => 'true',
		);

		// Today free shipping via coupons is displayed solely through the discounts data model. This does not
		// need to be synced to Meta here, as display details will need to be synced through coupon sync.
		$requires_coupon = ( 'both' === $shipping_settings['requires'] ) || ( 'coupon' === $shipping_settings['requires'] );
		if ( $requires_coupon ) {
			self::log_map_shipping_method_issue_to_meta( $zone, $free_shipping_method, 'Free shipping requires coupon', 'map_free_shipping_method' );
			return null;
		}

		// Since we aren't syncing coupon based shipping profiles here, we just treat 'either' as a requirement for min_amount.
		$requires_min_spend = ( 'min_amount' === $shipping_settings['requires'] ) || ( 'either' === $shipping_settings['requires'] );

		if ( $requires_min_spend ) {
			// Minimum spend requirements on Facebook and Instagram are determined by post-discount subtotals.
			// Don't sync rate if using pre-discount amounts
			if ( 'yes' === $shipping_settings['ignore_discounts'] ) {
				self::log_map_shipping_method_issue_to_meta( $zone, $free_shipping_method, 'Min spend free shipping ignores discounts', 'map_free_shipping_method' );
				return null;
			}
			$min_spend                                       = $free_shipping_method['instance_settings']['min_amount'] ?? 0;
			$shipping_rate['cart_minimum_for_free_shipping'] = $min_spend . ' ' . get_woocommerce_currency();
		}
		return $shipping_rate;
	}

	/**
	 * Flat rate shipping can still be configured to be free for all or a subset of products based on shipping classes.
	 * TODO - Currently syncs only if free for all products, need to extract free and non-free products based on shipping class.
	 *
	 * @param array $zone
	 * @param array $flat_rate_method
	 * @return array|null
	 */
	private static function get_flat_rate_shipping_method_data( array $zone, array $flat_rate_method ): ?array {
		$shipping_settings = $flat_rate_method['instance_settings'];

		// If the base cost isn't free we don't need to bother syncing this shipping method.
		if ( ! self::is_zero_cost( $shipping_settings['cost'] ?? '0' ) ) {
			self::log_map_shipping_method_issue_to_meta( $zone, $flat_rate_method, 'Flat rate shipping has base cost', 'map_flat_rate_shipping_method' );
			return null;
		}

		// For each shipping class, a new key is inserted into the methods settings with form 'class_cost_{class_id}'
		// The value is the additional cost to ship for products of that class when using the shipping method. We want
		// to find if any of these costs are non-free. For now, if there are any non-free costs, don't sync the method.
		$non_free_shipping_class_costs = [];
		$class_cost_prefix             = 'class_cost_';
		$prefix_length                 = strlen( $class_cost_prefix );

		foreach ( $shipping_settings as $key => $value ) {
			if ( str_starts_with( $key, $class_cost_prefix ) ) {
				$shipping_class_id = substr( $key, $prefix_length );
				// We could short circuit out of the whole function here, but populating the cost array sets up
				// future work to evaluate which subset of products should be seen as having a 'free shipping'
				// profile and which are paid.
				if ( ! self::is_zero_cost( $value ) ) {
					$non_free_shipping_class_costs[ $shipping_class_id ] = $value;
				}
			}
		}
		$no_class_cost = $shipping_settings['no_class_cost'] ?? '0';
		if ( ! self::is_zero_cost( $no_class_cost ) ) {
			$non_free_shipping_class_costs['no_class'] = $no_class_cost;
		}

		if ( count( $non_free_shipping_class_costs ) !== 0 ) {
			self::log_map_shipping_method_issue_to_meta( $zone, $flat_rate_method, 'Flat rate shipping shipping class with cost', 'map_flat_rate_shipping_method' );
			return null;
		}

		return array(
			'name'              => $flat_rate_method['title'],
			'has_free_shipping' => 'true',
		);
	}

	private static function log_map_shipping_method_issue_to_meta( array $zone, array $shipping_method, string $message, string $flow_step ): void {
		\WC_Facebookcommerce_Utils::log_to_meta(
			$message,
			array(
				'flow_name'  => FeedUploadUtils::SHIPPING_PROFILES_SYNC_LOGGING_FLOW_NAME,
				'flow_step'  => $flow_step,
				'extra_data' => [
					'zone_id'   => $zone['id'],
					'zone_name' => $zone['zone_name'],
					'method_id' => $shipping_method['instance_id'],
				],
			)
		);
	}

	private static function add_continent_location( string $continent_code, array $countries_to_states ): array {
		$country_codes = WC()->countries->get_continents()[ $continent_code ]['countries'];
		foreach ( $country_codes as $country_code ) {
			$countries_to_states = self::add_country_location( $country_code, $countries_to_states );
		}
		return $countries_to_states;
	}

	private static function add_country_location( string $country_code, array $countries_to_states ): array {
		$countries_to_states[ $country_code ]['applies_to_entire_country'] = 'true';
		return $countries_to_states;
	}

	private static function is_zero_cost( string $cost_string ): bool {
		if ( empty( $cost_string ) ) {
			return true;
		}
		if ( is_numeric( $cost_string ) ) {
			return 0.0 === (float) $cost_string;
		}
		return false;
	}
}
