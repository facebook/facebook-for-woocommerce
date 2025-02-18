<?php

namespace WooCommerce\Facebook\Feed;

/**
 * Example feed class to be used as template for new feeds.
 * Will also be used to conduct testing of the feed flow.
 */
class ExampleFeed extends AbstractFeed {

	/**
	 * @inheritDoc
	 */
	public function schedule_feed_generation() {
		// TODO: Implement schedule_feed_generation() method.
	}

	/**
	 * @inheritDoc
	 */
	public function regenerate_feed() {
		// TODO: Implement regenerate_feed() method.
	}

	/**
	 * @inheritDoc
	 */
	public function send_request_to_upload_feed() {
		// TODO: Implement send_request_to_upload_feed() method.
	}

	/**
	 * @inheritDoc
	 */
	public function handle_feed_data_request() {
		// TODO: Implement handle_feed_data_request() method.
	}

	/**
	 * @inheritDoc
	 */
	public static function get_feed_data_url(): string {
		// TODO: Implement get_feed_data_url() method.
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public static function get_feed_secret(): string {
		// TODO: Implement get_feed_secret() method.
		return '';
	}
}
