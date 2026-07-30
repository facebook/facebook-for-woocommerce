<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace WooCommerce\Facebook\API\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Connect_WC_API_Exception.
 * Exception is thrown when Connection with FB fails. @see \WooCommerce\Facebook\Handlers\Connection
 *
 * @package WooCommerce\Facebook\API\Exceptions
 */
class ConnectApiException extends \Exception {}
