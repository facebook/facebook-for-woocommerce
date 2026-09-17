<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Tests\Unit\Utilities;

use WooCommerce\Facebook\Tests\AbstractWPUnitTestWithSafeFiltering;
use WooCommerce\Facebook\Utilities\InstalledFeatures;

/**
 * Unit tests for the InstalledFeatures helper.
 *
 * @since 3.7.7
 */
class InstalledFeaturesTest extends AbstractWPUnitTestWithSafeFiltering {

	/**
	 * A representative MBE payload: the page is repeated across features and
	 * only the "pixel" feature is authoritative for the pixel.
	 *
	 * @return array
	 */
	private function get_installed_features(): array {
		return [
			[
				'feature_type'     => 'pixel',
				'connected_assets' => [
					'page_id'  => 'page_123',
					'pixel_id' => 'pixel_current',
				],
			],
			[
				'feature_type'     => 'external_client',
				'connected_assets' => [
					'business_manager_id' => 'bm_123',
				],
			],
			[
				'feature_type'     => 'catalog',
				'connected_assets' => [
					'page_id'    => 'page_123',
					'pixel_id'   => 'pixel_stale',
					'catalog_id' => 'catalog_123',
				],
			],
		];
	}

	/**
	 * An asset with no feature_type filter comes from the first feature carrying it.
	 */
	public function test_returns_asset_without_feature_type_filter() {
		$this->assertEquals(
			'page_123',
			InstalledFeatures::get_connected_asset( $this->get_installed_features(), 'page_id' )
		);
	}

	/**
	 * A feature_type filter restricts the search to that feature.
	 */
	public function test_feature_type_filter_selects_the_owning_feature() {
		$this->assertEquals(
			'pixel_current',
			InstalledFeatures::get_connected_asset( $this->get_installed_features(), 'pixel_id', 'pixel' )
		);
	}

	/**
	 * Without the filter, the first feature carrying the asset wins.
	 */
	public function test_without_filter_first_match_wins() {
		$this->assertEquals(
			'pixel_current',
			InstalledFeatures::get_connected_asset( $this->get_installed_features(), 'pixel_id' )
		);
	}

	/**
	 * Features are skipped when the requested feature_type is absent.
	 */
	public function test_returns_empty_when_feature_type_not_present() {
		$this->assertEquals(
			'',
			InstalledFeatures::get_connected_asset( $this->get_installed_features(), 'page_id', 'ads' )
		);
	}

	/**
	 * A missing asset key yields an empty string.
	 */
	public function test_returns_empty_for_unknown_asset_key() {
		$this->assertEquals(
			'',
			InstalledFeatures::get_connected_asset( $this->get_installed_features(), 'instagram_business_id' )
		);
	}

	/**
	 * Empty-string assets are skipped in favour of a later feature that has one.
	 */
	public function test_skips_empty_values() {
		$features = [
			[
				'feature_type'     => 'pixel',
				'connected_assets' => [ 'page_id' => '' ],
			],
			[
				'feature_type'     => 'catalog',
				'connected_assets' => [ 'page_id' => 'page_456' ],
			],
		];

		$this->assertEquals( 'page_456', InstalledFeatures::get_connected_asset( $features, 'page_id' ) );
	}

	/**
	 * Numeric asset IDs are returned as strings.
	 */
	public function test_casts_numeric_ids_to_string() {
		$features = [
			[
				'feature_type'     => 'pixel',
				'connected_assets' => [ 'page_id' => 37702827787712 ],
			],
		];

		$result = InstalledFeatures::get_connected_asset( $features, 'page_id' );

		$this->assertSame( '37702827787712', $result );
	}

	/**
	 * Malformed payloads never raise; they return an empty string.
	 *
	 * @dataProvider provide_malformed_payloads
	 *
	 * @param mixed $installed_features Payload to feed the helper.
	 */
	public function test_malformed_payloads_return_empty( $installed_features ) {
		$this->assertEquals( '', InstalledFeatures::get_connected_asset( $installed_features, 'page_id' ) );
	}

	/**
	 * Data provider for malformed payloads.
	 *
	 * @return array
	 */
	public function provide_malformed_payloads(): array {
		return [
			'null'                    => [ null ],
			'empty array'             => [ [] ],
			'string'                  => [ 'installed_features' ],
			'list of scalars'         => [ [ 'pixel', 'catalog' ] ],
			'feature without assets'  => [ [ [ 'feature_type' => 'pixel' ] ] ],
			'assets not an array'     => [ [ [ 'connected_assets' => 'nope' ] ] ],
			'asset value is an array' => [ [ [ 'connected_assets' => [ 'page_id' => [ 'nested' ] ] ] ] ],
		];
	}
}
