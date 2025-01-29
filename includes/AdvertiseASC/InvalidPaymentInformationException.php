<?php

namespace WooCommerce\Facebook\AdvertiseASC;

use Exception;

/**
 * Class InvalidPaymentInformationException
 *
 * Exception for when a the payment setting is invalid.
 */
class InvalidPaymentInformationException extends Exception {
	public function __construct() {
		parent::__construct( 'Payment needs to be set up.' );
	}
}
