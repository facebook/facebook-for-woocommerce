<?php

/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Integrations\CostOfGoods;

defined('ABSPATH') || exit;

/**
 * Integration with Cost-of-Goods Plugins.
 *
 * @since 2.0.0-dev.1
 */
abstract class AbstractCogsProvider
{
    const INTEGRATION_NAME = '';

    // TODO: Log information about the available plugins and send to Meta
    public static function is_available() {
        return false;
    }

    abstract public function get_cogs_value( $product );
}