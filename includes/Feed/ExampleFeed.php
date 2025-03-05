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
use WooCommerce\Facebook\Framework\Api\Exception;
use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Utilities\Heartbeat;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;

/**
 * Example Feed class
 *
 * Extends Abstract Feed class to handle example feed requests and generation for Facebook integration.
 *
 * @package WooCommerce\Facebook\Feed
 * @since 3.5.0
 */
class ExampleFeed extends AbstractFeed {
	/** Header for the example feed file. @var string */
	const EXAMPLE_FEED_HEADER = 'aggregator,store.name,store.id,store.store_urls,review_id,rating,title,content,created_at,' .
		'reviewer.name,reviewer.reviewerID,product.name,product.url,' .
		'product.image_urls,product.product_identifiers.skus,country' . PHP_EOL;

	/**
	 * Constructor.
	 *
	 * @since 3.5.0
	 */
	public function __construct() {
		$this->data_stream_name            = FeedManager::EXAMPLE;
		$this->gen_feed_interval           = DAY_IN_SECONDS;
		$this->feed_type                   = 'PRODUCT_RATINGS_AND_REVIEWS';
		$this->feed_url_secret_option_name = self::OPTION_FEED_URL_SECRET . $this->data_stream_name;

		$this->feed_handler   = new ExampleFeedHandler( new CsvFeedFileWriter( $this->data_stream_name, self::EXAMPLE_FEED_HEADER ) );
		$scheduler            = new ActionScheduler();
		$this->feed_generator = new ExampleFeedGenerator( $scheduler, $this->feed_handler->get_feed_writer(), $this->data_stream_name );
		$this->feed_generator->init();
		$this->add_hooks( Heartbeat::HOURLY );
	}

	/**
	 * Adds the necessary hooks for feed generation and data request handling.
	 *
	 * @param string $heartbeat The heartbeat interval for the feed generation.
	 * @since 3.5.0
	 */
	protected function add_hooks( string $heartbeat ): void {
		add_action( $heartbeat, array( $this, self::SCHEDULE_CALL_BACK ) );
		add_action( self::GENERATE_FEED_ACTION . $this->data_stream_name, array( $this, self::REGENERATE_CALL_BACK ) );
		add_action( self::FEED_GEN_COMPLETE_ACTION . $this->data_stream_name, array( $this, self::UPLOAD_CALL_BACK ) );
		add_action( self::LEGACY_API_PREFIX . self::REQUEST_FEED_ACTION . $this->data_stream_name, array( $this, self::STREAM_CALL_BACK ) );
	}

	/**
	 * Trigger the upload flow
	 * Once feed regenerated, trigger upload via create_upload API
	 * This will hit the url defined in the class and trigger the handle streaming file
	 *
	 * @since 3.5.0
	 */
	public function send_request_to_upload_feed(): void {
		$name = $this->data_stream_name;
		$data = array(
			'url'         => 'http://44.243.196.123/?wc-api=wc_facebook_get_feed_data_example&secret=secret',
			'feed_type'   => $this->feed_type,
			'update_type' => 'CREATE',
		);

		try {
			$cpi_id = \WC_Facebookcommerce::instance()->get_integration()->get_commerce_partner_integration_id();
			facebook_for_woocommerce()->
			get_api()->
			create_common_data_feed_upload( $cpi_id, $data );
		} catch ( Exception $e ) {
			// Log the error and continue.
			\WC_Facebookcommerce_Utils::log( "{$name} feed: Failed to create feed upload request: " . $e->getMessage() );
		}
	}

	/**
	 * Gets the secret value that should be included in the legacy WooCommerce REST API URL.
	 *
	 * @since 3.5.0
	 * @return string
	 */
	public function get_feed_secret(): string {
		return 'secret';
	}
}
