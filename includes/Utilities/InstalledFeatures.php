<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

declare( strict_types=1 );

namespace WooCommerce\Facebook\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for reading asset IDs out of an MBE `installed_features` payload.
 *
 * MBE reports connected assets per installed feature rather than at the top
 * level of the response, e.g.:
 *
 *     [
 *         [
 *             'feature_type'     => 'pixel',
 *             'connected_assets' => [ 'pixel_id' => '123', 'page_id' => '456' ],
 *         ],
 *         ...
 *     ]
 *
 * @since 3.7.7
 */
class InstalledFeatures {

	/**
	 * Finds a connected asset ID within an installed_features payload.
	 *
	 * When $feature_type is given, only features of that type are considered;
	 * this matters for assets such as the pixel, where the value attached to
	 * the "pixel" feature is authoritative and other features may carry a
	 * stale copy. Assets that are not owned by a particular feature type —
	 * the page, for instance — are read from the first feature that reports
	 * one, since MBE repeats the same value across features.
	 *
	 * @since 3.7.7
	 *
	 * @param mixed  $installed_features Installed features payload, as received from MBE.
	 * @param string $asset_key          Key within connected_assets, e.g. 'page_id'.
	 * @param string $feature_type       Optional feature_type to restrict the search to.
	 * @return string The asset ID, or an empty string when not present.
	 */
	public static function get_connected_asset( $installed_features, string $asset_key, string $feature_type = '' ): string {
		if ( ! is_array( $installed_features ) ) {
			return '';
		}

		foreach ( $installed_features as $feature ) {
			if ( ! is_array( $feature ) ) {
				continue;
			}

			if ( '' !== $feature_type && $feature_type !== ( $feature['feature_type'] ?? '' ) ) {
				continue;
			}

			$value = $feature['connected_assets'][ $asset_key ] ?? '';

			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}

		return '';
	}
}
