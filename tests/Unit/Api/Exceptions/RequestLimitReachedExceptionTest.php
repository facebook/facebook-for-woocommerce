<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

declare( strict_types=1 );

namespace Unit\Api\Exceptions;

use WooCommerce\Facebook\API\Exceptions\Request_Limit_Reached;
use WP_UnitTestCase;

class RequestLimitReachedExceptionTest extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public function test_exception_can_be_thrown(): void {
		$this->expectException(Request_Limit_Reached::class);

		throw new Request_Limit_Reached('Request limit reached');
	}

	/**
	 * @return void
	 */
	public function test_exception_message(): void {
		$exception = new Request_Limit_Reached('Request limit reached');
		$throttle_end_date = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$exception->set_throttle_end($throttle_end_date);

		$this->assertEquals('Request limit reached', $exception->getMessage());
		$this->assertEquals($throttle_end_date->getTimestamp(), $exception->get_throttle_end()->getTimestamp());
	}
}


