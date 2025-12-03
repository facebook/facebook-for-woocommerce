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
	 *
	 * @param string      $external_business_id The external business ID.
	 * @param string|null $catalog_id           Optional catalog ID.
	 */
	public function __construct( string $external_business_id, ?string $catalog_id = null ) {
		parent::__construct( '/fbe_business/fbe_rollout_switches', 'GET' );
	}
}
