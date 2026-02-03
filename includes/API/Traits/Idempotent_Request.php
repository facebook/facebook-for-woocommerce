<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\API\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Idempotent request trait.
 *
 * @since 2.1.0
 */
trait Idempotent_Request {


	/** @var string holds the request's idempotency key */
	protected $idempotency_key;


	/**
	 * Gets the value of idempotency key.
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	public function get_idempotency_key() {

		if ( empty( $this->idempotency_key ) ) {

			$this->idempotency_key = wp_generate_uuid4();
		}

		return $this->idempotency_key;
	}
}
