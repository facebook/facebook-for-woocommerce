<?php

namespace WooCommerce\Facebook\AdvertiseASC;

use Exception;

/**
 * Class AccountIsPostPaidException
 *
 * Exception for when a the payment setting is set to postpaid.
 */
class AccountIsPostPaidException extends Exception {
	public function __construct() {
		parent::__construct( 'Ad Account should be prepaid.' );
	}
}
