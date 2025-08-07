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

use WooCommerce\Facebook\Integrations\IntegrationIsNotAvailableException;

/**
 * Integration for the WPFactory Cost-of-Goods feature on WooCommerce plugin (Free & Pro versions).
 *
 * @since 2.0.0-dev.1
 */

class WPFactoryCogsProvider extends AbstractCogsProvider {

    const INTEGRATION_NAME = 'WooCommerce Cost of Goods by WPFactory';

    public function __construct()
    {
        if ( ! self::is_available() ) {
            throw new IntegrationIsNotAvailableException( self::INTEGRATION_NAME );
        }
    }

    public function get_cogs_value( $product ) {
        // for WPFactory simple & variable product cost is retrieved by the same following method
        return alg_wc_cog()->core->products->get_product_cost( $product->get_id() );
    }

    public static function is_available() {
        
        // This method is loaded whether 
        return function_exists('alg_wc_cog');
    }
}