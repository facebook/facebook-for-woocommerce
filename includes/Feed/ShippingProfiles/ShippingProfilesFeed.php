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
	/** Header for the ratings and reviews feed file. @var string */
	const SHIPPING_PROFILES_FEED_HEADER = 'shipping_profile_id,name,shipping_zones,shipping_rates,applicable_products,applies_to_all_products,applies_to_rest_of_world,is_active' . PHP_EOL;

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		$file_writer  = new CsvFeedFileWriter( self::get_data_stream_name(), self::SHIPPING_PROFILES_FEED_HEADER );
		$feed_handler = new ShippingProfilesFeedHandler( $file_writer );

		$scheduler      = new ActionScheduler();
		$feed_generator = new ShippingProfilesFeedGenerator( $scheduler, $file_writer, self::get_data_stream_name() );

		$this->init(
			$file_writer,
			$feed_handler,
			$feed_generator,
		);
	}

	protected static function get_feed_type(): string {
		return 'SHIPPING_PROFILES';
	}

	protected static function get_data_stream_name(): string {
		return FeedManager::SHIPPING_PROFILES;
	}

	protected static function get_feed_gen_interval(): int {
		return HOUR_IN_SECONDS;
	}


	public static function get_shipping_profiles_data(): array {
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
					return (array) $method;
				},
				$zone['shipping_methods']
			);

			$free_shipping_methods = array_filter(
				$shipping_methods,
				function ( $shipping_method ) {
					return 'free_shipping' === $shipping_method['id'];
				}
			);
			$shipping_rates        = [];
			foreach ( $free_shipping_methods as $free_shipping_method ) {
				$shipping_settings = $free_shipping_method['instance_settings'];

				$requires_coupon = 'both' === $shipping_settings['requires'] || 'coupon' === $shipping_settings['requires'];
				if ( $requires_coupon ) {
					continue;
				}
				$min_spend = $free_shipping_method['instance_settings']['min_amount'];

				$shipping_rate = array(
					'name'              => $free_shipping_method['method_title'],
					'has_free_shipping' => 'true',
				);
				if ( ( $min_spend ?? 0 ) > 0 ) {
					$shipping_rate['cart_minimum_for_free_shipping'] = $min_spend . ' ' . get_woocommerce_currency();
				}
				$shipping_rates[] = $shipping_rate;
			}
			// Because were only handling free shipping which applies to all products for the zone, we only
			// need to return one data shape here. When we need to handle classes, we will want to split this up
			// based on which products the methods apply to. For now, hard code the id suffix.
			$id_suffix                = 'all_products';
			$data                     = array(
				'shipping_profile_id'     => $zone['id'] . '-' . $id_suffix,
				'applies_to_all_products' => 'true',
				'is_active'               => 'true',
				'shipping_zones'          => $countries_with_states,
				'shipping_rates'          => $shipping_rates,
			);
			$shipping_profiles_data[] = $data;
		}
		return $shipping_profiles_data;
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
}
