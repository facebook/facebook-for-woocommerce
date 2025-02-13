<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Insights;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API\Response as ApiResponse;

/**
 * Response object for Facebook Ad Campaign Insights request.
 *
 * @since x.x.x
 */
class Response extends ApiResponse {

	public function get_result() {
		$data = $this->get_data();

		$result = array(
			'spend'   => 0,
			'reach'   => 0,
			'actions' => array(
				'clicks'    => 0,
				'views'     => 0,
				'cart'      => 0,
				'purchases' => 0,
			),
		);

		$result['spend'] = $data['spend'] ?? 0;
		$result['reach'] = $data['reach'] ?? 0;

		if ( array_key_exists( 'actions', $data ) ) {
			foreach ( $data['actions'] as $action ) {
				if ( 'link_click' === $action['action_type'] ) {
					$result['actions']['clicks'] = $action['value'];
				} elseif ( 'view_content' === $action['action_type'] ) {
					$result['actions']['views'] = $action['value'];
				} elseif ( 'add_to_cart' === $action['action_type'] ) {
					$result['actions']['cart'] = $action['value'];
				} elseif ( 'purchase' === $action['action_type'] ) {
					$result['actions']['purchases'] = $action['value'];
				}
			}
		}

		return $result;
	}

	private function get_data() {
		if ( ! array_key_exists( 'data', $this->response_data ) ) {
			return array();
		}
		return $this->response_data['data'];
	}
}
