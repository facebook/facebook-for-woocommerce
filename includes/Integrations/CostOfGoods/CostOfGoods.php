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
class CostOfGoods
{
    /* TODO: Optimization & Test
    1. cogs_providers should not be instantiated each time
    2. Add unit tests for this class + provider classes
    3. Add integration tests for value retrieval, breaking changes
    4. Should we create a configuration page so users can choose?
    5. Maybe we don't need to add PYS support? People who have that, also tend to have PYS CAPI plugin, and don't use ours
    6. Add this too https://yithemes.com/themes/plugins/yith-woocommerce-cost-of-goods/?
    */

    private static $available_integrations = array();
    private static $already_fetched = false;

    public static function calculate_cogs_for_products($products)
    {
        if (! self::is_cogs_provider_available()) {
            return false;
        }

        $order_cogs = 0;
        foreach ($products as $product) {

            $cogs = self::get_cogs_for_product($product);

            // If cogs was 0 for one product, the value is invalid for the order
            if (! $cogs || $cogs < 0) {
                return false;
            }
            $order_cogs += $cogs;
        }

        return $order_cogs;
    }

    public static function get_supported_integrations()
    {
        return array(
            'WooC'      => 'WooCCogsProvider',
            'WPFactory' => 'WPFactoryCogsProvider',
        );
    }

    private static function get_cogs_providers()
    {
        if ( ! self::$already_fetched ) {
            self::$available_integrations = array();
            foreach (self::get_supported_integrations() as $integration => $class_name) {
                $class = "WooCommerce\\Facebook\\Integrations\\CostOfGoods\\" . $class_name;
                if ($class::is_available()) {
                    self::$available_integrations[] = new $class();                
                }
            }
            self::$already_fetched = true;
        }
        return self::$available_integrations;
    }

    private static function get_cogs_for_product($product)
    {
        $cogs_providers = self::get_cogs_providers();
        foreach ($cogs_providers as $provider) {
            $cogs = $provider->get_cogs_value($product);
            if (is_numeric($cogs) && $cogs > 0) {
                return $cogs;
            }
        }

        return false;
    }

    private static function is_cogs_provider_available()
    {
        return count(self::get_cogs_providers()) > 0;
    }
}
