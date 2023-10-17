<?php

namespace WooCommerce\Facebook\AdvertiseASC;

use Exception;

/**
 * Class InstagramActorIdNotFoundException
 *
 * Exception for when a the payment setting is invalid.
 */
class InstagramActorIdNotFoundException extends Exception {
	public function __construct() {
		parent::__construct( 'Instagram Actor Id cannot be found.' );
	}
}
