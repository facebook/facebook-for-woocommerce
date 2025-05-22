<?php
/** Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

use WooCommerce\Facebook\FBSignedData\FBPublicKey;
use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithOptionIsolationAndSafeFiltering;

/**
 * Class FBPublicKeyTest
 */
class FBPublicKeyTest extends AbstractWPUnitTestWithOptionIsolationAndSafeFiltering
{
	public function test_fb_public_key_getters(): void {
		$algorithm = FBPublicKey::ALGORITHM_ES256;
		$key = 'test_key';
		$encoding_format = FBPublicKey::ENCODING_FORMAT_PEM;
		$project = 'test_project';

		$fb_public_key = new FBPublicKey($key, $algorithm, $encoding_format, $project );

		$this->assertEquals($fb_public_key->get_key(), $key);
		$this->assertEquals($fb_public_key->get_algorithm(), $algorithm);
		$this->assertEquals($fb_public_key->get_encoding_format(), $encoding_format);
		$this->assertEquals($fb_public_key->get_project(), $project);
	}
}
