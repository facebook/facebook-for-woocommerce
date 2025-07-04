<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Events;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizer class.
 */
class Normalizer {

	/**
	 * Normalizes $data according to its type
	 *
	 * @since 2.0.3
	 *
	 * @param string $field to be normalized.
	 * @param string $data value to be normalized
	 * @return string
	 * @throws InvalidArgumentException Invalid data.
	 */
	public static function normalize( $field, $data ) {
		if ( null === $data || 0 === strlen( $data ) ) {
			return null;
		}

		$data            = trim( mb_strtolower( $data, 'UTF-8' ) );
		$normalized_data = $data;

		switch ( $field ) {
			case 'em':
				$normalized_data = self::normalize_email( $data );
				break;

			case 'ph':
				$normalized_data = self::normalize_phone( $data );
				break;

			case 'zp':
				$normalized_data = self::normalize_zip_code( $data );
				break;

			case 'ct':
				$normalized_data = self::normalize_city( $data );
				break;

			case 'st':
				$normalized_data = self::normalize_state( $data );
				break;

			case 'country':
				$normalized_data = self::normalize_country( $data );
				break;

			case 'cn':
				$normalized_data = self::normalize_country( $data );
				break;

			default:
		}

		return $normalized_data;
	}

	/**
	 * Normalizes an array containing user data
	 *
	 * @since 2.0.3
	 *
	 * @param string[] $data array with user data to be normalized
	 * @param mixed    $is_pixel_data pixel data
	 * @return string[]
	 */
	public static function normalize_array( $data, $is_pixel_data ) {
		// Country is encoded as cn in Pixel events and country in CAPI events
		$keys_to_normalize = array( 'fn', 'ln', 'em', 'ph', 'zp', 'ct', 'st' );
		if ( $is_pixel_data ) {
			$keys_to_normalize[] = 'cn';
		} else {
			$keys_to_normalize[] = 'country';
		}
		foreach ( $keys_to_normalize as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				// If the data is invalid, it is erased from the array
				try {
					$data[ $key ] = self::normalize( $key, $data[ $key ] );
				} catch ( InvalidArgumentException $e ) {
					unset( $data[ $key ] );
				}
			}
		}
		return $data;
	}

	/**
	 * Normalizes an email
	 *
	 * @since 2.0.3
	 *
	 * @param string $email Email address to be normalized.
	 * @return string
	 * @throws InvalidArgumentException Invalid email format.
	 */
	private static function normalize_email( $email ) {
		// Validates email against RFC 822
		$result = filter_var( $email, FILTER_SANITIZE_EMAIL );

		if ( ! filter_var( $result, FILTER_VALIDATE_EMAIL ) ) {
			throw new InvalidArgumentException( 'Invalid email format for the passed email: ' . $email . 'Please check the passed email format.' );
		}

		return $result;
	}

	/**
	 * Normalizes a city name
	 *
	 * @since 2.0.3
	 *
	 * @param string $city city name to be normalized.
	 * @return string
	 */
	private static function normalize_city( $city ) {
		return trim( preg_replace( '/[0-9.\s\-()]/', '', $city ) );
	}

	/**
	 * Normalizes a state code
	 *
	 * @since 2.0.3
	 *
	 * @param string $state state name to be normalized.
	 * @return string
	 */
	private static function normalize_state( $state ) {
		return preg_replace( '/[^a-z]/', '', $state );
	}

	/**
	 * Normalizes a country code
	 *
	 * @since 2.0.3
	 *
	 * @param string $country country code to be normalized(ISO 3166-2).
	 * @return string
	 * @throws InvalidArgumentException If length of country is not 2.
	 */
	private static function normalize_country( $country ) {
		$result = preg_replace( '/[^a-z]/i', '', $country );

		if ( 2 !== strlen( $result ) ) {
			throw new InvalidArgumentException( 'Invalid country format passed(' . $country . '). Country Code should be a two-letter ISO Country Code' );
		}

		return $result;
	}

	/**
	 * Normalizes a zip code
	 *
	 * @since 2.0.3
	 *
	 * @param string $zip postal code to be normalized.
	 * @return string
	 */
	private static function normalize_zip_code( $zip ) {
		// Removing the spaces from the zip code. Eg:
		$zip = preg_replace( '/[ ]/', '', $zip );

		// If the code has more than one part, retain the first part.
		$zip = explode( '-', $zip )[0];
		return $zip;
	}

	/**
	 * Normalizes a phone number
	 *
	 * @since 2.0.3
	 *
	 * @param string $phone phone number to be normalized.
	 * @return string
	 */
	private static function normalize_phone( $phone ) {
		$result = trim( preg_replace( '/[a-z()-]/', '', $phone ) );

		if ( self::is_international_number( $result ) ) {
			$result = preg_replace( '/[\-\s+]/', '', $result );
		}

		return $result;
	}

	/**
	 * Checks if a phone number is international
	 *
	 * @since 2.0.3
	 *
	 * @param string $phone_number Phone number to be normalized.
	 * @return bool
	 */
	private static function is_international_number( $phone_number ) {
		// Remove spaces and hyphens
		$phone_number = preg_replace( '/[\-\s]/', '', $phone_number );

		// Strip + and up to 2 leading 0s
		$phone_number = preg_replace( '/^\+?0{0,2}/', '', $phone_number );

		if ( substr( $phone_number, 0, 1 ) === '0' ) {
			return false;
		}

		// International Phone number with country calling code.
		$international_number_regex = '/^\d{1,4}\(?\d{2,3}\)?\d{4,}$/';

		return preg_match( $international_number_regex, $phone_number );
	}
}
