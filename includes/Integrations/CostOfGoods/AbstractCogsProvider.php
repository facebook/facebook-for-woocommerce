<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package MetaCommerce
 */

namespace WooCommerce\Facebook\Integrations\CostOfGoods;

defined( 'ABSPATH' ) || exit;

/**
 * Integration with Cost-of-Goods Plugins.
 *
 * @since 2.0.0-dev.1
 */
abstract class AbstractCogsProvider {
	const INTEGRATION_NAME = '';

	/** @var ?bool to cache the availablity of this integration. */
	private $is_cogs_available = null;

	abstract protected function get_availability(): bool;

	public function is_available() {
		if ( null === $this->is_cogs_available ) {
			$this->is_cogs_available = $this->get_availability();
		}
		return $this->is_cogs_available;
	}

	abstract public function get_cogs_value( $product );
}
