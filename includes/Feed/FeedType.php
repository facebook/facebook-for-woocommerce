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

/**
 * FeedType for use as an enum
 * Add types as they are onboarded.
 */
class FeedType {
	const PROMOTIONS = 'promotions';

	/**
	 * Get the list of feed types.
	 *
	 * @return array
	 */
	public static function get_feed_types(): array {
		return array( self::PROMOTIONS );
	}

	/**
	 * Get the feed file writer for the given data stream name.
	 *
	 * @param string $data_stream_name The name of the data stream.
	 *
	 * @return FeedFileWriter
	 * @throws \InvalidArgumentException If the data stream doesn't correspond to a FeedType.
	 */
	public static function get_feed_file_writer( string $data_stream_name ): FeedFileWriter {
		switch ( $data_stream_name ) {
			case self::PROMOTIONS:
				return new CsvFeedFileWriter( $data_stream_name );
			default:
				throw new \InvalidArgumentException( 'Invalid data stream name' );
		}
	}
}
