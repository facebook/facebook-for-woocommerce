<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\AdvertiseASC;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\AdvertiseASC\NewBuyers;
use WooCommerce\Facebook\AdvertiseASC\Retargeting;

/**
 * This class is used to hold a singleton for each of:
 * - NewBuyers, and
 * - Retargeting
 * campaigns.
 *
 * @since x.x.x
 */
class AscSelector {

	/** @var array holding the campaign objects */
	private $asc_handlers = array();

	public function get_or_create_handler( string $type ) {
		if ( ! array_key_exists( $type, $this->asc_handlers ) ) {
			$this->asc_handlers[ $type ] = $this->create_asc_handler( $type );
		}
		return $this->asc_handlers[ $type ];
	}

	private function create_asc_handler( string $type ) {
		if ( Retargeting::ID === $type ) {
			return new Retargeting();
		} elseif ( NewBuyers::ID === $type ) {
			return new NewBuyers();
		} else {
			throw new \ErrorException( esc_html( 'Invalid handler: ' . $type, 'facebook-for-woocommerce' ) );
		}
	}
}
