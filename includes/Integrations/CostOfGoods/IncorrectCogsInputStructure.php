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

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * An Exception which indicates that the input data to the Cogs calculate method is not structured properly
 */
class IncorrectCogsInputStructure extends \Exception {
	public function __construct( $code = 0, ?Throwable $previous = null ) {

		parent::__construct( 'The input data is not in the correct format', $code, $previous );
	}
}
