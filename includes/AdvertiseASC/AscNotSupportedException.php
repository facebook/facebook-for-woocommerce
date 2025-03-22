<?php

namespace WooCommerce\Facebook\AdvertiseASC;

use Exception;

/**
 * Class AscNotSupportedException
 *
 * Exception for when a the ASC campaign is not created.
 */
class AscNotSupportedException extends Exception {
	public function __construct() {
		parent::__construct( 'ASC is not supported.' );
	}
}
