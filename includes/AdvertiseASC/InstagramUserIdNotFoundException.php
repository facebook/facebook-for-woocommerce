<?php

namespace WooCommerce\Facebook\AdvertiseASC;

use Exception;

/**
 * Class InstagramUserIdNotFoundException
 *
 * Exception for when a the payment setting is invalid.
 */
class InstagramUserIdNotFoundException extends Exception {
	public function __construct() {
		parent::__construct( 'Instagram User Id cannot be found.' );
	}
}
