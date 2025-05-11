<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\FBE\RolloutSwitches;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * FBE RolloutSwitches API request object.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 */
	public function __construct() {
		parent::__construct( '/fbe_business/fbe_rollout_switches', 'GET' );
	}
}
