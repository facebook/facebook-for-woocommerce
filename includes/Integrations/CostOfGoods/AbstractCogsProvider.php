<?php
/**
 * Copyright (c) Meta, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
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

	public abstract function is_available() : bool;

	abstract public function get_cogs_value( $product );
}
