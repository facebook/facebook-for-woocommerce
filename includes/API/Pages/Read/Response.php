<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Pages\Read;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Page API response object.
 *
 * @since 2.0.0
 * @property-read string $name Facebook Page Name.
 * @property-read string $link Facebook Page URL.
 */
class Response extends API\Response {}
