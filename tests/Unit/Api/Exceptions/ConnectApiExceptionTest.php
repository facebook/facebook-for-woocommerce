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

 use WooCommerce\Facebook\API\Exceptions\ConnectApiException;
 use WP_UnitTestCase;

class ConnectApiExceptionTest extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public function test_exception_can_be_thrown(): void {
		$this->expectException(ConnectApiException::class);

		throw new ConnectApiException('Connection failed');
	}

	/**
	 * @return void
	 */
	public function test_exception_message(): void {
		$exception = new ConnectApiException('Connection failed');

		$this->assertEquals('Connection failed', $exception->getMessage());
	}
}


