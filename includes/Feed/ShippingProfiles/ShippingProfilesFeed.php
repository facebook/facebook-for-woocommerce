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
use WooCommerce\Facebook\Framework\Logger;

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
	const SHIPPING_PROFILES_FEED_HEADER = 'shipping_profile_id,name,shipping_zones,shipping_rates,applies_to_all_products,applicable_products_filter,applies_to_rest_of_world' . PHP_EOL;
	// This gets returned by get_shipping_class from product if no class is set on the product
	const NO_SHIPPING_CLASS_ID      = '0';
	const NO_SHIPPING_CLASS_TAG     = 'no_shipping_class';
	const SHIPPING_CLASS_TAG_PREFIX = 'shipping_class_';

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

				// An empty location list indicates that the shipping profile applies to the whole world.
				if ( empty( $locations ) ) {
					$locations = array_map(
						function ( $country_code ) {
							return [
								'code' => $country_code,
								'type' => 'country',
							];
						},
						array_keys( WC()->countries->get_countries() )
					);
				}

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

				$shipping_method_data = [];
				foreach ( $shipping_methods as $shipping_method ) {
					if ( 'yes' !== $shipping_method['enabled'] ) {
						continue;
					}
					try {
						if ( 'free_shipping' === $shipping_method['id'] ) {
							$shipping_method_data[] = self::get_free_shipping_method_data( $zone, $shipping_method );
						}
						if ( 'flat_rate' === $shipping_method['id'] ) {
							$shipping_method_data[] = self::get_flat_rate_shipping_method_data( $zone, $shipping_method );
						}
					} catch ( \Exception $e ) {
						\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
							$e,
							array(
								'event'      => FeedUploadUtils::SHIPPING_PROFILES_SYNC_LOGGING_FLOW_NAME,
								'event_type' => 'get_shipping_method_data',
							)
						);
					}
				}

				$shipping_rates_per_shipping_class = [];
				foreach ( $shipping_method_data as $method_datum ) {
					if ( null === $method_datum ) {
						continue;
					}

					$shipping_rate = array(
						'name'              => $method_datum['name'],
						'has_free_shipping' => $method_datum['has_free_shipping'],
					);
					if ( key_exists( 'cart_minimum_for_free_shipping', $method_datum ) ) {
						$shipping_rate['cart_minimum_for_free_shipping'] = $method_datum['cart_minimum_for_free_shipping'];
					}

					if ( $method_datum['applies_to_all_products'] ) {
						$shipping_rates_per_shipping_class['all_products'][] = $shipping_rate;
					} else {
						foreach ( $method_datum['shipping_class_ids'] as $class_id ) {
							$shipping_rates_per_shipping_class[ $class_id ][] = $shipping_rate;
						}
					}
				}

				foreach ( $shipping_rates_per_shipping_class as $shipping_class => $shipping_rates ) {
					// Don't send shipping profile if there are no rates since the shipping profile won't be usable.
					if ( 0 === count( $shipping_rates ) ) {
						continue;
					}
					$shipping_class_string = (string) $shipping_class;
					$data                  = array(
						'shipping_profile_id'      => sprintf( '%s-%s', $zone['id'], $shipping_class_string ),
						'name'                     => $zone['zone_name'],
						'shipping_zones'           => $countries_with_states,
						'shipping_rates'           => $shipping_rates,
						'applies_to_rest_of_world' => 'false',
					);

					if ( 'all_products' === $shipping_class ) {
						$data['applies_to_all_products'] = 'true';
					} else {
						$data['applies_to_all_products']    = 'false';
						$data['applicable_products_filter'] = sprintf( '{"tags":{"eq":"%s"}}', self::get_shipping_class_tag_for_class( $shipping_class_string ) );
					}
					$shipping_profiles_data[] = $data;
				}
			}
			return $shipping_profiles_data;
		} catch ( \Exception $e ) {
			\WC_Facebookcommerce_Utils::log_exception_immediately_to_meta(
				$e,
				array(
					'event'      => FeedUploadUtils::SHIPPING_PROFILES_SYNC_LOGGING_FLOW_NAME,
					'event_type' => 'get_shipping_profiles_data',
				)
			);
			throw $e;
		}
	}


	private static function get_free_shipping_method_data( array $zone, array $free_shipping_method ): ?array {
		$shipping_settings = $free_shipping_method['instance_settings'];

		$shipping_data = array(
			'name'                    => $free_shipping_method['title'],
			'has_free_shipping'       => 'true',
			'applies_to_all_products' => true,
			'shipping_class_ids'      => [],
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
			$shipping_data['cart_minimum_for_free_shipping'] = $min_spend . ' ' . get_woocommerce_currency();
		}
		return $shipping_data;
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
		// The value is the additional cost to ship for products of that class when using the shipping method. If
		// // some classes have a cost, data will be synced as a separate shipping profile for any class that has a 0 cost.
		$shipping_class_ids_to_costs = [];
		$class_cost_prefix           = 'class_cost_';
		$prefix_length               = strlen( $class_cost_prefix );

		foreach ( $shipping_settings as $key => $value ) {
			if ( str_starts_with( $key, $class_cost_prefix ) ) {
				$shipping_class_id                                 = substr( $key, $prefix_length );
				$shipping_class_ids_to_costs[ $shipping_class_id ] = $value;
			}
		}
		$shipping_class_ids_to_costs[ self::NO_SHIPPING_CLASS_ID ] = $shipping_settings['no_class_cost'] ?? '0';

		$free_shipping_class_ids = [];
		$paid_shipping_class_ids = [];
		foreach ( $shipping_class_ids_to_costs as $class_id => $cost ) {
			if ( self::is_zero_cost( $cost ) ) {
				$free_shipping_class_ids[] = $class_id;
			} else {
				$paid_shipping_class_ids[] = $class_id;
			}
		}

		if ( count( $free_shipping_class_ids ) === 0 ) {
			self::log_map_shipping_method_issue_to_meta( $zone, $flat_rate_method, 'Flat rate shipping has no free classes', 'map_flat_rate_shipping_method' );
			return null;
		}

		$free_shipping_applies_to_all_products = empty( $paid_shipping_class_ids );

		return array(
			'name'                    => $flat_rate_method['title'],
			'has_free_shipping'       => 'true',
			'applies_to_all_products' => $free_shipping_applies_to_all_products,
			'shipping_class_ids'      => $free_shipping_class_ids,
		);
	}

	private static function log_map_shipping_method_issue_to_meta( array $zone, array $shipping_method, string $message, string $flow_step ): void {
		Logger::log(
			$message,
			array(
				'flow_name'  => FeedUploadUtils::SHIPPING_PROFILES_SYNC_LOGGING_FLOW_NAME,
				'flow_step'  => $flow_step,
				'extra_data' => [
					'zone_id'   => $zone['id'],
					'zone_name' => $zone['zone_name'],
					'method_id' => $shipping_method['instance_id'],
				],
			),
			array(
				'should_send_log_to_meta'        => true,
				'should_save_log_in_woocommerce' => true,
				'woocommerce_log_level'          => \WC_Log_Levels::DEBUG,
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
		$countries_to_states[ $country_code ]['applies_to_entire_country'] = true;
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

	public static function get_shipping_class_tag_for_class( string $class_id ): string {
		if ( self::NO_SHIPPING_CLASS_ID === $class_id ) {
			return self::NO_SHIPPING_CLASS_TAG;
		}
		return self::SHIPPING_CLASS_TAG_PREFIX . $class_id;
	}
}
