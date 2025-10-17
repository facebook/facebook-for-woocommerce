<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\API\Pixel\Events;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;
use WooCommerce\Facebook\Events\Event;

/**
 * Base S2S API request object.
 *
 * @since 2.0.0
 */
class Request extends API\Request {


	/** @var Event[] events to send */
	private $events;


	/**
	 * Request constructor.
	 *
	 * @param string  $pixel_id
	 * @param Event[] $events events to send
	 */
	public function __construct( $pixel_id, array $events ) {

		$this->events = $events;

		parent::__construct( "/{$pixel_id}/events", 'POST' );
	}


	/**
	 * Gets the request data.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_data() {

		$data = array(
			'data'          => array(),
			'partner_agent' => Event::get_platform_identifier(),
		);

		foreach ( $this->events as $event ) {

			if ( ! $event instanceof Event ) {
				continue;
			}

			$event_data = $event->get_data();

			if ( ! empty( $event_data['user_data']['click_id'] ) ) {

				$event_data['user_data']['fbc'] = $event_data['user_data']['click_id'];
			}

			if ( ! empty( $event_data['user_data']['browser_id'] ) ) {

				$event_data['user_data']['fbp'] = $event_data['user_data']['browser_id'];
			}

			// These are valid field names, so when they're provided they
			// create event validation issues.
			// unset these no matter what.
			unset( $event_data['user_data']['click_id'] );
			unset( $event_data['user_data']['browser_id'] );

			$data['data'][] = array_filter( $event_data );
		}

		/**
		 * Filters the Pixel event API request data.
		 *
		 * @since 2.0.0
		 *
		 * @param array $data request data
		 * @param Request $request request object
		 */
		return apply_filters( 'wc_facebook_api_pixel_event_request_data', $data, $this );
	}
}
