<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook;

defined( 'ABSPATH' ) || exit;

/**
 * Helper class with utility methods for handling locales in Facebook.
 *
 * @since 2.2.0
 */
class Locale {


	/** @var string default locale */
	const DEFAULT_LOCALE = 'en_US';


	/** @var string[] an array of supported locale identifiers */
	private static $supported_locales = array(
		'af_ZA',
		'ar_AR',
		'as_IN',
		'az_AZ',
		'be_BY',
		'bg_BG',
		'bn_IN',
		'br_FR',
		'bs_BA',
		'ca_ES',
		'cb_IQ',
		'co_FR',
		'cs_CZ',
		'cx_PH',
		'cy_GB',
		'da_DK',
		'de_DE',
		'el_GR',
		'en_GB',
		'en_US',
		'es_ES',
		'es_LA',
		'et_EE',
		'eu_ES',
		'fa_IR',
		'ff_NG',
		'fi_FI',
		'fo_FO',
		'fr_CA',
		'fr_FR',
		'fy_NL',
		'ga_IE',
		'gl_ES',
		'gn_PY',
		'gu_IN',
		'ha_NG',
		'he_IL',
		'hi_IN',
		'hr_HR',
		'hu_HU',
		'hy_AM',
		'id_ID',
		'is_IS',
		'it_IT',
		'ja_JP',
		'ja_KS',
		'jv_ID',
		'ka_GE',
		'kk_KZ',
		'km_KH',
		'kn_IN',
		'ko_KR',
		'ku_TR',
		'lt_LT',
		'lv_LV',
		'mg_MG',
		'mk_MK',
		'ml_IN',
		'mn_MN',
		'mr_IN',
		'ms_MY',
		'mt_MT',
		'my_MM',
		'nb_NO',
		'ne_NP',
		'nl_BE',
		'nl_NL',
		'nn_NO',
		'or_IN',
		'pa_IN',
		'pl_PL',
		'ps_AF',
		'pt_BR',
		'pt_PT',
		'qz_MM',
		'ro_RO',
		'ru_RU',
		'rw_RW',
		'sc_IT',
		'si_LK',
		'sk_SK',
		'sl_SI',
		'so_SO',
		'sq_AL',
		'sr_RS',
		'sv_SE',
		'sw_KE',
		'sz_PL',
		'ta_IN',
		'te_IN',
		'tg_TJ',
		'th_TH',
		'tl_PH',
		'tr_TR',
		'tz_MA',
		'uk_UA',
		'ur_PK',
		'uz_UZ',
		'vi_VN',
		'zh_CN',
		'zh_HK',
		'zh_TW',
	);


	/**
	 * Gets a list of locales supported by Facebook.
	 *
	 * @link https://developers.facebook.com/docs/messenger-platform/messenger-profile/supported-locales/
	 * If the Locale extension is not available, will attempt to match locales to WordPress available language names.
	 *
	 * @since 2.2.0
	 *
	 * @return array associative array of locale identifiers and language labels
	 */
	public static function get_supported_locales() {

		$locales = array();

		if ( class_exists( 'Locale' ) ) {

			foreach ( self::$supported_locales as $locale ) {

				$name = \Locale::getDisplayName( $locale, substr( $locale, 0, 2 ) );
				if ( $name ) {

					$locales[ $locale ] = ucfirst( $name );
				}
			}
		} else {

			include_once ABSPATH . '/wp-admin/includes/translation-install.php';

			$translations = wp_get_available_translations();

			foreach ( self::$supported_locales as $locale ) {

				if ( isset( $translations[ $locale ]['native_name'] ) ) {

					$locales[ $locale ] = $translations[ $locale ]['native_name'];

				} else { // generic match e.g. <it>_IT, <it>_CH (any language in the the <it> group )

					$matched_locale = substr( $locale, 0, 2 );

					if ( isset( $translations[ $matched_locale ]['native_name'] ) ) {
						$locales[ $locale ] = $translations[ $matched_locale ]['native_name'];
					}
				}
			}

			// always include US English
			$locales['en_US'] = _x( 'English (United States)', 'language', 'facebook-for-woocommerce' );
		}

		/**
		 * Filters the locales supported by Facebook Messenger.
		 *
		 * @since 1.10.0
		 *
		 * @param array $locales locales supported by Facebook, in $locale => $name format
		 */
		$locales = (array) apply_filters( 'wc_facebook_messenger_supported_locales', array_unique( $locales ) );

		natcasesort( $locales );

		return $locales;
	}


	/**
	 * Determines if a locale is supported by Facebook.
	 *
	 * @since 2.2.0
	 *
	 * @param string $locale a locale identifier
	 * @return bool
	 */
	public static function is_supported_locale( $locale ) {

		return array_key_exists( $locale, self::get_supported_locales() );
	}

	/**
	 * Languages that Facebook supports using the generic _XX format for language override feeds.
	 * Any regional variant of these languages will be converted to {language}_XX
	 *
	 * @since 3.6.0
	 * @var array
	 */
	private static $facebook_xx_languages = [
		'en', // English (en_US, en_GB, en_CA, etc. → en_XX)
		'es', // Spanish (es_ES, es_MX, es_AR, etc. → es_XX)
		'fr', // French (fr_FR, fr_CA, fr_BE, etc. → fr_XX)
		'nl', // Dutch (nl_NL, nl_BE, etc. → nl_XX)
		'pt', // Portuguese (pt_BR, pt_PT, etc. → pt_XX)
		'no', // Norwegian (no_NO, nb_NO, nn_NO, etc. → no_XX)
		'ja', // Japanese (ja_JP, etc. → ja_XX)
		'tl', // Tagalog (tl_PH, etc. → tl_XX)
	];

	/**
	 * Facebook's valid override values mapping for language override feeds.
	 * Complete mapping of language codes to Facebook's accepted override values.
	 *
	 * @since 3.6.0
	 * @var array
	 */
	private static $facebook_override_values = [
		'af' => 'af_ZA', // Afrikaans
		'ak' => 'ak_GH', // Akan
		'am' => 'am_ET', // Amharic
		'ar' => 'ar_AR', // Arabic
		'as' => 'as_IN', // Assamese
		'ay' => 'ay_BO', // Aymara
		'az' => 'az_AZ', // Azerbaijani
		'be' => 'be_BY', // Belarusian
		'bg' => 'bg_BG', // Bulgarian
		'bm' => 'bm_ML', // Bambara
		'bn' => 'bn_IN', // Bengali
		'bo' => 'bo_CN', // Tibetan
		'br' => 'br_FR', // Breton
		'bs' => 'bs_BA', // Bosnian
		'ca' => 'ca_ES', // Catalan
		'cb' => 'cb_IQ', // Kurdish
		'ci' => 'ci_IT', // Sicilian
		'ck' => 'ck_US', // Cherokee
		'cs' => 'cs_CZ', // Czech
		'cx' => 'cx_PH', // Cebuano
		'cy' => 'cy_GB', // Welsh
		'da' => 'da_DK', // Danish
		'de' => 'de_DE', // German
		'dv' => 'dv_MV', // Dhivehi
		'el' => 'el_GR', // Greek
		'en' => 'en_XX', // English
		'eo' => 'eo_EO', // Esperanto
		'es' => 'es_XX', // Spanish
		'et' => 'et_EE', // Estonian
		'eu' => 'eu_ES', // Basque
		'fa' => 'fa_IR', // Persian
		'ff' => 'ff_NG', // Fulah
		'fi' => 'fi_FI', // Finnish
		'fo' => 'fo_FO', // Faroese
		'fr' => 'fr_XX', // French
		'fy' => 'fy_NL', // Frisian
		'ga' => 'ga_IE', // Irish
		'gd' => 'gd_GB', // Scottish Gaelic
		'gl' => 'gl_ES', // Galician
		'gn' => 'gn_PY', // Guaraní
		'gu' => 'gu_IN', // Gujarati
		'ha' => 'ha_NG', // Hausa
		'he' => 'he_IL', // Hebrew
		'hi' => 'hi_IN', // Hindi
		'hr' => 'hr_HR', // Croatian
		'ht' => 'ht_HT', // Haitian
		'hu' => 'hu_HU', // Hungarian
		'hy' => 'hy_AM', // Armenian
		'id' => 'id_ID', // Indonesian
		'ig' => 'ig_NG', // Igbo
		'is' => 'is_IS', // Icelandic
		'it' => 'it_IT', // Italian
		'iu' => 'iu_CA', // Inuktitut
		'ja' => 'ja_XX', // Japanese
		'jv' => 'jv_ID', // Javanese
		'ka' => 'ka_GE', // Georgian
		'kg' => 'kg_AO', // Kongo
		'kk' => 'kk_KZ', // Kazakh
		'km' => 'km_KH', // Khmer
		'kn' => 'kn_IN', // Kannada
		'ko' => 'ko_KR', // Korean
		'ku' => 'ku_TR', // Kurdish
		'ky' => 'ky_KG', // Kirghiz
		'la' => 'la_VA', // Latin
		'lg' => 'lg_UG', // Ganda
		'li' => 'li_NL', // Limburgish
		'ln' => 'ln_CD', // Lingala
		'lo' => 'lo_LA', // Lao
		'lt' => 'lt_LT', // Lithuanian
		'lv' => 'lv_LV', // Latvian
		'mg' => 'mg_MG', // Malagasy
		'mi' => 'mi_NZ', // Maori
		'mk' => 'mk_MK', // Macedonian
		'ml' => 'ml_IN', // Malayalam
		'mn' => 'mn_MN', // Mongolian
		'mr' => 'mr_IN', // Marathi
		'ms' => 'ms_MY', // Malay
		'mt' => 'mt_MT', // Maltese
		'my' => 'my_MM', // Burmese
		'ne' => 'ne_NP', // Nepali
		'nl' => 'nl_XX', // Dutch
		'no' => 'no_XX', // Norwegian
		'ns' => 'ns_ZA', // Northern Sotho
		'ny' => 'ny_MW', // Nyanja
		'om' => 'om_KE', // Oromo
		'or' => 'or_IN', // Oriya
		'pa' => 'pa_IN', // Punjabi
		'pl' => 'pl_PL', // Polish
		'ps' => 'ps_AF', // Pashto
		'pt' => 'pt_XX', // Portuguese
		'qa' => 'qa_MM', // Shan
		'qd' => 'qd_MM', // Kachin
		'qf' => 'qf_CM', // Ewondo
		'qh' => 'qh_PH', // Iloko
		'qj' => 'qj_ML', // Koyra Chiini Songhay
		'qm' => 'qm_AO', // Umbundu
		'qn' => 'qn_AO', // Kimbundu
		'qp' => 'qp_AO', // Chokwe
		'qq' => 'qq_KE', // EkeGusii
		'qw' => 'qw_KE', // Kalenjin
		'qy' => 'qy_KE', // Dholuo
		'qx' => 'qx_KE', // Kikamba
		'q2' => 'q2_KH', // Western Cham
		'q3' => 'q3_CV', // Kabuverdianui
		'qu' => 'qu_PE', // Quechua
		'rm' => 'rm_CH', // Romansh
		'ro' => 'ro_RO', // Romanian
		'ru' => 'ru_RU', // Russian
		'rw' => 'rw_RW', // Kinyarwanda
		'sa' => 'sa_IN', // Sanskrit
		'sc' => 'sc_IT', // Sardinian
		'sd' => 'sd_PK', // Sindhi
		'se' => 'se_NO', // Northern Sami
		'si' => 'si_LK', // Sinhala
		'sk' => 'sk_SK', // Slovak
		'sl' => 'sl_SI', // Slovenian
		'sn' => 'sn_ZW', // Shona
		'so' => 'so_SO', // Somali
		'sq' => 'sq_AL', // Albanian
		'sr' => 'sr_RS', // Serbian
		'ss' => 'ss_SZ', // Swati
		'st' => 'st_ZA', // Southern Sotho
		'su' => 'su_ID', // Sundanese
		'sv' => 'sv_SE', // Swedish
		'sw' => 'sw_KE', // Swahili
		'sy' => 'sy_SY', // Syriac
		'sz' => 'sz_PL', // Silesian
		'ta' => 'ta_IN', // Tamil
		'te' => 'te_IN', // Telugu
		'tg' => 'tg_TJ', // Tajik
		'th' => 'th_TH', // Thai
		'ti' => 'ti_ET', // Tigrinya
		'tl' => 'tl_XX', // Tagalog
		'tn' => 'tn_BW', // Tswana
		'tr' => 'tr_TR', // Turkish
		'ts' => 'ts_ZA', // Tsonga
		'tt' => 'tt_RU', // Tatar
		'tz' => 'tz_MA', // Tamazight
		'ug' => 'ug_CN', // Uighur
		'uk' => 'uk_UA', // Ukrainian
		'ur' => 'ur_PK', // Urdu
		'uz' => 'uz_UZ', // Uzbek
		've' => 've_ZA', // Venda
		'vi' => 'vi_VN', // Vietnamese
		'wy' => 'wy_PH', // Winaray
		'wo' => 'wo_SN', // Wolof
		'xh' => 'xh_ZA', // Xhosa
		'yi' => 'yi_DE', // Yiddish
		'yo' => 'yo_NG', // Yoruba
		'zh' => 'zh_CN', // Chinese (China) - default to simplified
		'zu' => 'zu_ZA', // Zulu
		'zz' => 'zz_TR', // Zazaki
	];

	/**
	 * Convert locale code to Facebook's supported language override value for language override feeds.
	 *
	 * @since 3.6.0
	 * @param string $locale_code Locale code from localization plugin (e.g., 'es_ES', 'fr_FR')
	 * @return string Facebook-supported language override value (e.g., 'es_XX', 'fr_XX')
	 */
	public static function convert_to_facebook_language_code( string $locale_code ): string {
		// Extract the language part (before the underscore)
		$language_parts = explode( '_', $locale_code );
		$language = strtolower( $language_parts[0] );

		// Check if this language uses the _XX format
		if ( in_array( $language, self::$facebook_xx_languages, true ) ) {
			return $language . '_XX';
		}

		// Check if we have a specific Facebook override value for this language
		if ( isset( self::$facebook_override_values[ $language ] ) ) {
			return self::$facebook_override_values[ $language ];
		}

		// Handle special cases for Chinese
		if ( $language === 'zh' && isset( $language_parts[1] ) ) {
			$region = strtoupper( $language_parts[1] );
			if ( in_array( $region, [ 'TW', 'HK', 'MO' ] ) ) {
				return 'zh_TW'; // Traditional Chinese
			}
			return 'zh_CN'; // Simplified Chinese (default)
		}

		// Fallback: return the original code if no mapping found
		return $locale_code;
	}

	/**
	 * Convert language code to Facebook's accepted override value format for language override feeds.
	 * This method throws an exception for unsupported languages (stricter validation).
	 *
	 * @since 3.6.0
	 * @param string $language_code Language code (e.g., 'es_ES', 'fr_FR')
	 * @return string Facebook override value (e.g., 'es_XX', 'fr_XX')
	 * @throws \WooCommerce\Facebook\Framework\Plugin\Exception If the language is not supported by Facebook
	 */
	public static function convert_to_facebook_override_value( string $language_code ): string {
		// Extract the language part (before the underscore)
		$language_parts = explode( '_', $language_code );
		$language = strtolower( $language_parts[0] );

		// Check if we have a specific Facebook override value for this language
		if ( isset( self::$facebook_override_values[ $language ] ) ) {
			return self::$facebook_override_values[ $language ];
		}

		// Handle special cases for Chinese
		if ( $language === 'zh' && isset( $language_parts[1] ) ) {
			$region = strtoupper( $language_parts[1] );
			if ( in_array( $region, [ 'TW', 'HK', 'MO' ] ) ) {
				return 'zh_TW'; // Traditional Chinese
			}
			return 'zh_CN'; // Simplified Chinese (default)
		}

		// If no mapping found, throw an exception
		throw new \WooCommerce\Facebook\Framework\Plugin\Exception(
			sprintf(
				__( 'Language Feed not supported for override value: %s', 'facebook-for-woocommerce' ),
				$language_code
			),
			400
		);
	}

	/**
	 * Check if a language code is supported by Facebook for language override feeds.
	 *
	 * @since 3.6.0
	 * @param string $language_code Language code to check
	 * @return bool True if supported, false otherwise
	 */
	public static function is_language_override_supported( string $language_code ): bool {
		try {
			self::convert_to_facebook_override_value( $language_code );
			return true;
		} catch ( \WooCommerce\Facebook\Framework\Plugin\Exception $e ) {
			return false;
		}
	}

	/**
	 * Get all supported Facebook language override codes.
	 *
	 * @since 3.6.0
	 * @return array Array of supported language override codes
	 */
	public static function get_supported_language_override_codes(): array {
		$supported = [];

		// Add _XX languages
		foreach ( self::$facebook_xx_languages as $lang ) {
			$supported[] = $lang . '_XX';
		}

		// Add specific override values
		$supported = array_merge( $supported, array_values( self::$facebook_override_values ) );

		return array_unique( $supported );
	}

	/**
	 * Get the mapping of language codes to Facebook override values.
	 *
	 * @since 3.6.0
	 * @return array Complete mapping array
	 */
	public static function get_language_override_mapping(): array {
		return self::$facebook_override_values;
	}

	/**
	 * Comprehensive list of world country codes and names.
	 * Used for country-based feed generation and shipping zone validation.
	 *
	 * @since 3.0.18
	 * @var array
	 */
	private static $world_countries = [
		'AD' => 'Andorra',
		'AE' => 'United Arab Emirates',
		'AF' => 'Afghanistan',
		'AG' => 'Antigua',
		'AI' => 'Anguilla',
		'AL' => 'Albania',
		'AM' => 'Armenia',
		'AN' => 'Netherlands Antilles',
		'AO' => 'Angola',
		'AQ' => 'Antarctica',
		'AR' => 'Argentina',
		'AS' => 'American Samoa',
		'AT' => 'Austria',
		'AU' => 'Australia',
		'AW' => 'Aruba',
		'AX' => 'Aland Islands',
		'AZ' => 'Azerbaijan',
		'BA' => 'Bosnia and Herzegovina',
		'BB' => 'Barbados',
		'BD' => 'Bangladesh',
		'BE' => 'Belgium',
		'BF' => 'Burkina Faso',
		'BG' => 'Bulgaria',
		'BH' => 'Bahrain',
		'BI' => 'Burundi',
		'BJ' => 'Benin',
		'BL' => 'Saint Barthelemy',
		'BM' => 'Bermuda',
		'BN' => 'Brunei',
		'BO' => 'Bolivia',
		'BQ' => 'Bonaire, Sint Eustatius and Saba',
		'BR' => 'Brazil',
		'BS' => 'The Bahamas',
		'BT' => 'Bhutan',
		'BV' => 'Bouvet Island',
		'BW' => 'Botswana',
		'BY' => 'Belarus',
		'BZ' => 'Belize',
		'CA' => 'Canada',
		'CC' => 'Cocos (Keeling) Islands',
		'CD' => 'Democratic Republic of the Congo',
		'CF' => 'Central African Republic',
		'CG' => 'Republic of the Congo',
		'CH' => 'Switzerland',
		'CI' => 'Côte d\'Ivoire',
		'CK' => 'Cook Islands',
		'CL' => 'Chile',
		'CM' => 'Cameroon',
		'CN' => 'China',
		'CO' => 'Colombia',
		'CR' => 'Costa Rica',
		'CV' => 'Cape Verde',
		'CW' => 'Curacao',
		'CX' => 'Christmas Island',
		'CY' => 'Cyprus',
		'CZ' => 'Czech Republic',
		'DE' => 'Germany',
		'DJ' => 'Djibouti',
		'DK' => 'Denmark',
		'DM' => 'Dominica',
		'DO' => 'Dominican Republic',
		'DZ' => 'Algeria',
		'EC' => 'Ecuador',
		'EE' => 'Estonia',
		'EG' => 'Egypt',
		'EH' => 'Western Sahara',
		'ER' => 'Eritrea',
		'ES' => 'Spain',
		'ET' => 'Ethiopia',
		'FI' => 'Finland',
		'FJ' => 'Fiji',
		'FK' => 'Falkland Islands',
		'FM' => 'Federated States of Micronesia',
		'FO' => 'Faroe Islands',
		'FR' => 'France',
		'GA' => 'Gabon',
		'GB' => 'Great Britain',
		'GD' => 'Grenada',
		'GE' => 'Georgia',
		'GF' => 'French Guiana',
		'GG' => 'Guernsey',
		'GH' => 'Ghana',
		'GI' => 'Gibraltar',
		'GL' => 'Greenland',
		'GM' => 'The Gambia',
		'GN' => 'Guinea',
		'GP' => 'Guadeloupe',
		'GQ' => 'Equatorial Guinea',
		'GR' => 'Greece',
		'GS' => 'South Georgia and the South Sandwich Islands',
		'GT' => 'Guatemala',
		'GU' => 'Guam',
		'GW' => 'Guinea-Bissau',
		'GY' => 'Guyana',
		'HK' => 'Hong Kong',
		'HM' => 'Heard Island and McDonald Islands',
		'HN' => 'Honduras',
		'HR' => 'Croatia',
		'HT' => 'Haiti',
		'HU' => 'Hungary',
		'ID' => 'Indonesia',
		'IE' => 'Ireland',
		'IL' => 'Israel',
		'IM' => 'Isle of Man',
		'IN' => 'India',
		'IO' => 'British Indian Ocean Territory',
		'IQ' => 'Iraq',
		'IS' => 'Iceland',
		'IT' => 'Italy',
		'JE' => 'Jersey, Channel Islands',
		'JM' => 'Jamaica',
		'JO' => 'Jordan',
		'JP' => 'Japan',
		'KE' => 'Kenya',
		'KG' => 'Kyrgyzstan',
		'KH' => 'Cambodia',
		'KI' => 'Kiribati',
		'KM' => 'Comoros',
		'KN' => 'Saint Kitts and Nevis',
		'KR' => 'South Korea',
		'KW' => 'Kuwait',
		'KY' => 'Cayman Islands',
		'KZ' => 'Kazakhstan',
		'LA' => 'Laos',
		'LB' => 'Lebanon',
		'LC' => 'St. Lucia',
		'LI' => 'Liechtenstein',
		'LK' => 'Sri Lanka',
		'LR' => 'Liberia',
		'LS' => 'Lesotho',
		'LT' => 'Lithuania',
		'LU' => 'Luxembourg',
		'LV' => 'Latvia',
		'LY' => 'Libya',
		'MA' => 'Morocco',
		'MC' => 'Monaco',
		'MD' => 'Moldova',
		'ME' => 'Montenegro',
		'MF' => 'Saint Martin',
		'MG' => 'Madagascar',
		'MH' => 'Marshall Islands',
		'MK' => 'Republic of North Macedonia',
		'ML' => 'Mali',
		'MM' => 'Myanmar',
		'MN' => 'Mongolia',
		'MO' => 'Macau',
		'MP' => 'Northern Mariana Islands',
		'MQ' => 'Martinique',
		'MR' => 'Mauritania',
		'MS' => 'Montserrat',
		'MT' => 'Malta',
		'MU' => 'Mauritius',
		'MV' => 'Maldives',
		'MW' => 'Malawi',
		'MX' => 'Mexico',
		'MY' => 'Malaysia',
		'MZ' => 'Mozambique',
		'NA' => 'Namibia',
		'NC' => 'New Caledonia',
		'NE' => 'Niger',
		'NF' => 'Norfolk Island',
		'NG' => 'Nigeria',
		'NI' => 'Nicaragua',
		'NL' => 'Netherlands',
		'NO' => 'Norway',
		'NP' => 'Nepal',
		'NR' => 'Nauru',
		'NU' => 'Niue',
		'NZ' => 'New Zealand',
		'OM' => 'Oman',
		'PA' => 'Panama',
		'PE' => 'Peru',
		'PF' => 'French Polynesia',
		'PG' => 'Papua New Guinea',
		'PH' => 'Philippines',
		'PK' => 'Pakistan',
		'PL' => 'Poland',
		'PM' => 'Saint Pierre and Miquelon',
		'PN' => 'Pitcairn Islands',
		'PR' => 'Puerto Rico',
		'PS' => 'Palestine',
		'PT' => 'Portugal',
		'PW' => 'Palau',
		'PY' => 'Paraguay',
		'QA' => 'Qatar',
		'RE' => 'Reunion',
		'RO' => 'Romania',
		'RS' => 'Serbia',
		'RU' => 'Russia',
		'RW' => 'Rwanda',
		'SA' => 'Saudi Arabia',
		'SB' => 'Solomon Islands',
		'SC' => 'Seychelles',
		'SE' => 'Sweden',
		'SG' => 'Singapore',
		'SH' => 'Saint Helena',
		'SI' => 'Slovenia',
		'SJ' => 'Svalbard and Jan Mayen',
		'SK' => 'Slovakia',
		'SL' => 'Sierra Leone',
		'SM' => 'San Marino',
		'SN' => 'Senegal',
		'SO' => 'Somalia',
		'SR' => 'Suriname',
		'SS' => 'South Sudan',
		'ST' => 'Sao Tome and Principe',
		'SV' => 'El Salvador',
		'SX' => 'Sint Maarten',
		'SZ' => 'Eswatini',
		'TC' => 'Turks and Caicos Islands',
		'TD' => 'Chad',
		'TF' => 'French Southern and Antarctic Lands',
		'TG' => 'Togo',
		'TH' => 'Thailand',
		'TJ' => 'Tajikistan',
		'TK' => 'Tokelau',
		'TL' => 'Timor-Leste',
		'TM' => 'Turkmenistan',
		'TN' => 'Tunisia',
		'TO' => 'Tonga',
		'TR' => 'Turkey',
		'TT' => 'Trinidad and Tobago',
		'TV' => 'Tuvalu',
		'TW' => 'Taiwan',
		'TZ' => 'Tanzania',
		'UA' => 'Ukraine',
		'UG' => 'Uganda',
		'UM' => 'United States Minor Outlying Islands',
		'US' => 'United States',
		'UY' => 'Uruguay',
		'UZ' => 'Uzbekistan',
		'VA' => 'Vatican City',
		'VC' => 'Saint Vincent and the Grenadines',
		'VE' => 'Venezuela',
		'VG' => 'British Virgin Islands',
		'VI' => 'US Virgin Islands',
		'VN' => 'Vietnam',
		'VU' => 'Vanuatu',
		'WF' => 'Wallis and Futuna',
		'WS' => 'Samoa',
		'XK' => 'Kosovo',
		'YE' => 'Yemen',
		'YT' => 'Mayotte',
		'ZA' => 'South Africa',
		'ZM' => 'Zambia',
		'ZW' => 'Zimbabwe',
	];

	/**
	 * Get the comprehensive list of world countries.
	 *
	 * @since 3.0.18
	 * @return array Array of country code => country name pairs
	 */
	public static function get_world_countries(): array {
		return self::$world_countries;
	}

	/**
	 * Get country name by country code.
	 *
	 * @since 3.0.18
	 * @param string $country_code Two-letter country code
	 * @return string|null Country name or null if not found
	 */
	public static function get_country_name( string $country_code ): ?string {
		$country_code = strtoupper( $country_code );
		return self::$world_countries[ $country_code ] ?? null;
	}

	/**
	 * Check if a country code is valid.
	 *
	 * @since 3.0.18
	 * @param string $country_code Two-letter country code
	 * @return bool True if country code exists
	 */
	public static function is_valid_country_code( string $country_code ): bool {
		$country_code = strtoupper( $country_code );
		return isset( self::$world_countries[ $country_code ] );
	}

	/**
	 * Get all country codes.
	 *
	 * @since 3.0.18
	 * @return array Array of country codes
	 */
	public static function get_country_codes(): array {
		return array_keys( self::$world_countries );
	}

	/**
	 * List of countries supported by Meta/Facebook for commerce.
	 * This list should be updated based on Meta's official supported countries.
	 *
	 * @since 3.0.18
	 * @var array
	 */
	private static $meta_supported_countries = [
		'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT',
		'AU', 'AW', 'AX', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI',
		'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW', 'BY',
		'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
		'CO', 'CR', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO',
		'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM',
		'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM',
		'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM', 'HN',
		'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IS', 'IT',
		'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KR', 'KW',
		'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV',
		'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN',
		'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ',
		'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ',
		'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS',
		'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC',
		'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS',
		'ST', 'SV', 'SX', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL',
		'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US',
		'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'XK',
		'YE', 'YT', 'ZA', 'ZM', 'ZW'
	];

	/**
	 * Get countries supported by Meta/Facebook for commerce.
	 *
	 * @since 3.0.18
	 * @return array Array of country codes supported by Meta
	 */
	public static function get_meta_supported_countries(): array {
		return self::$meta_supported_countries;
	}

	/**
	 * Check if a country is supported by Meta/Facebook for commerce.
	 *
	 * @since 3.0.18
	 * @param string $country_code Two-letter country code
	 * @return bool True if country is supported by Meta
	 */
	public static function is_meta_supported_country( string $country_code ): bool {
		$country_code = strtoupper( $country_code );
		return in_array( $country_code, self::$meta_supported_countries, true );
	}

	/**
	 * Filter countries to only include those supported by Meta.
	 *
	 * @since 3.0.18
	 * @param array $countries Array of country codes
	 * @return array Filtered array containing only Meta-supported countries
	 */
	public static function filter_meta_supported_countries( array $countries ): array {
		return array_intersect( $countries, self::$meta_supported_countries );
	}

	/**
	 * Native currency mapping for countries.
	 * Maps each country to its primary/native currency code.
	 *
	 * @since 3.0.18
	 * @var array
	 */
	private static $native_currencies = [
		'AD' => 'EUR', // Andorra
		'AE' => 'AED', // United Arab Emirates
		'AF' => 'AFN', // Afghanistan
		'AG' => 'XCD', // Antigua and Barbuda
		'AI' => 'XCD', // Anguilla
		'AL' => 'ALL', // Albania
		'AM' => 'AMD', // Armenia
		'AO' => 'AOA', // Angola
		'AR' => 'ARS', // Argentina
		'AS' => 'USD', // American Samoa
		'AT' => 'EUR', // Austria
		'AU' => 'AUD', // Australia
		'AW' => 'AWG', // Aruba
		'AX' => 'EUR', // Aland Islands
		'AZ' => 'AZN', // Azerbaijan
		'BA' => 'BAM', // Bosnia and Herzegovina
		'BB' => 'BBD', // Barbados
		'BD' => 'BDT', // Bangladesh
		'BE' => 'EUR', // Belgium
		'BF' => 'XOF', // Burkina Faso
		'BG' => 'BGN', // Bulgaria
		'BH' => 'BHD', // Bahrain
		'BI' => 'BIF', // Burundi
		'BJ' => 'XOF', // Benin
		'BL' => 'EUR', // Saint Barthelemy
		'BM' => 'BMD', // Bermuda
		'BN' => 'BND', // Brunei
		'BO' => 'BOB', // Bolivia
		'BQ' => 'USD', // Bonaire, Sint Eustatius and Saba
		'BR' => 'BRL', // Brazil
		'BS' => 'BSD', // The Bahamas
		'BT' => 'BTN', // Bhutan
		'BV' => 'NOK', // Bouvet Island
		'BW' => 'BWP', // Botswana
		'BY' => 'BYN', // Belarus
		'BZ' => 'BZD', // Belize
		'CA' => 'CAD', // Canada
		'CC' => 'AUD', // Cocos (Keeling) Islands
		'CD' => 'CDF', // Democratic Republic of the Congo
		'CF' => 'XAF', // Central African Republic
		'CG' => 'XAF', // Republic of the Congo
		'CH' => 'CHF', // Switzerland
		'CI' => 'XOF', // Côte d'Ivoire
		'CK' => 'NZD', // Cook Islands
		'CL' => 'CLP', // Chile
		'CM' => 'XAF', // Cameroon
		'CN' => 'CNY', // China
		'CO' => 'COP', // Colombia
		'CR' => 'CRC', // Costa Rica
		'CV' => 'CVE', // Cape Verde
		'CW' => 'ANG', // Curacao
		'CX' => 'AUD', // Christmas Island
		'CY' => 'EUR', // Cyprus
		'CZ' => 'CZK', // Czech Republic
		'DE' => 'EUR', // Germany
		'DJ' => 'DJF', // Djibouti
		'DK' => 'DKK', // Denmark
		'DM' => 'XCD', // Dominica
		'DO' => 'DOP', // Dominican Republic
		'DZ' => 'DZD', // Algeria
		'EC' => 'USD', // Ecuador
		'EE' => 'EUR', // Estonia
		'EG' => 'EGP', // Egypt
		'EH' => 'MAD', // Western Sahara
		'ER' => 'ERN', // Eritrea
		'ES' => 'EUR', // Spain
		'ET' => 'ETB', // Ethiopia
		'FI' => 'EUR', // Finland
		'FJ' => 'FJD', // Fiji
		'FK' => 'FKP', // Falkland Islands
		'FM' => 'USD', // Federated States of Micronesia
		'FO' => 'DKK', // Faroe Islands
		'FR' => 'EUR', // France
		'GA' => 'XAF', // Gabon
		'GB' => 'GBP', // Great Britain
		'GD' => 'XCD', // Grenada
		'GE' => 'GEL', // Georgia
		'GF' => 'EUR', // French Guiana
		'GG' => 'GBP', // Guernsey
		'GH' => 'GHS', // Ghana
		'GI' => 'GIP', // Gibraltar
		'GL' => 'DKK', // Greenland
		'GM' => 'GMD', // The Gambia
		'GN' => 'GNF', // Guinea
		'GP' => 'EUR', // Guadeloupe
		'GQ' => 'XAF', // Equatorial Guinea
		'GR' => 'EUR', // Greece
		'GS' => 'GBP', // South Georgia and the South Sandwich Islands
		'GT' => 'GTQ', // Guatemala
		'GU' => 'USD', // Guam
		'GW' => 'XOF', // Guinea-Bissau
		'GY' => 'GYD', // Guyana
		'HK' => 'HKD', // Hong Kong
		'HM' => 'AUD', // Heard Island and McDonald Islands
		'HN' => 'HNL', // Honduras
		'HR' => 'HRK', // Croatia
		'HT' => 'HTG', // Haiti
		'HU' => 'HUF', // Hungary
		'ID' => 'IDR', // Indonesia
		'IE' => 'EUR', // Ireland
		'IL' => 'ILS', // Israel
		'IM' => 'GBP', // Isle of Man
		'IN' => 'INR', // India
		'IO' => 'USD', // British Indian Ocean Territory
		'IQ' => 'IQD', // Iraq
		'IS' => 'ISK', // Iceland
		'IT' => 'EUR', // Italy
		'JE' => 'GBP', // Jersey, Channel Islands
		'JM' => 'JMD', // Jamaica
		'JO' => 'JOD', // Jordan
		'JP' => 'JPY', // Japan
		'KE' => 'KES', // Kenya
		'KG' => 'KGS', // Kyrgyzstan
		'KH' => 'KHR', // Cambodia
		'KI' => 'AUD', // Kiribati
		'KM' => 'KMF', // Comoros
		'KN' => 'XCD', // Saint Kitts and Nevis
		'KR' => 'KRW', // South Korea
		'KW' => 'KWD', // Kuwait
		'KY' => 'KYD', // Cayman Islands
		'KZ' => 'KZT', // Kazakhstan
		'LA' => 'LAK', // Laos
		'LB' => 'LBP', // Lebanon
		'LC' => 'XCD', // St. Lucia
		'LI' => 'CHF', // Liechtenstein
		'LK' => 'LKR', // Sri Lanka
		'LR' => 'LRD', // Liberia
		'LS' => 'LSL', // Lesotho
		'LT' => 'EUR', // Lithuania
		'LU' => 'EUR', // Luxembourg
		'LV' => 'EUR', // Latvia
		'LY' => 'LYD', // Libya
		'MA' => 'MAD', // Morocco
		'MC' => 'EUR', // Monaco
		'MD' => 'MDL', // Moldova
		'ME' => 'EUR', // Montenegro
		'MF' => 'EUR', // Saint Martin
		'MG' => 'MGA', // Madagascar
		'MH' => 'USD', // Marshall Islands
		'MK' => 'MKD', // Republic of North Macedonia
		'ML' => 'XOF', // Mali
		'MM' => 'MMK', // Myanmar
		'MN' => 'MNT', // Mongolia
		'MO' => 'MOP', // Macau
		'MP' => 'USD', // Northern Mariana Islands
		'MQ' => 'EUR', // Martinique
		'MR' => 'MRU', // Mauritania
		'MS' => 'XCD', // Montserrat
		'MT' => 'EUR', // Malta
		'MU' => 'MUR', // Mauritius
		'MV' => 'MVR', // Maldives
		'MW' => 'MWK', // Malawi
		'MX' => 'MXN', // Mexico
		'MY' => 'MYR', // Malaysia
		'MZ' => 'MZN', // Mozambique
		'NA' => 'NAD', // Namibia
		'NC' => 'XPF', // New Caledonia
		'NE' => 'XOF', // Niger
		'NF' => 'AUD', // Norfolk Island
		'NG' => 'NGN', // Nigeria
		'NI' => 'NIO', // Nicaragua
		'NL' => 'EUR', // Netherlands
		'NO' => 'NOK', // Norway
		'NP' => 'NPR', // Nepal
		'NR' => 'AUD', // Nauru
		'NU' => 'NZD', // Niue
		'NZ' => 'NZD', // New Zealand
		'OM' => 'OMR', // Oman
		'PA' => 'PAB', // Panama
		'PE' => 'PEN', // Peru
		'PF' => 'XPF', // French Polynesia
		'PG' => 'PGK', // Papua New Guinea
		'PH' => 'PHP', // Philippines
		'PK' => 'PKR', // Pakistan
		'PL' => 'PLN', // Poland
		'PM' => 'EUR', // Saint Pierre and Miquelon
		'PN' => 'NZD', // Pitcairn Islands
		'PR' => 'USD', // Puerto Rico
		'PS' => 'ILS', // Palestine (Israeli shekel commonly used)
		'PT' => 'EUR', // Portugal
		'PW' => 'USD', // Palau
		'PY' => 'PYG', // Paraguay
		'QA' => 'QAR', // Qatar
		'RE' => 'EUR', // Reunion
		'RO' => 'RON', // Romania
		'RS' => 'RSD', // Serbia
		'RU' => 'RUB', // Russia
		'RW' => 'RWF', // Rwanda
		'SA' => 'SAR', // Saudi Arabia
		'SB' => 'SBD', // Solomon Islands
		'SC' => 'SCR', // Seychelles
		'SE' => 'SEK', // Sweden
		'SG' => 'SGD', // Singapore
		'SH' => 'SHP', // Saint Helena
		'SI' => 'EUR', // Slovenia
		'SJ' => 'NOK', // Svalbard and Jan Mayen
		'SK' => 'EUR', // Slovakia
		'SL' => 'SLE', // Sierra Leone
		'SM' => 'EUR', // San Marino
		'SN' => 'XOF', // Senegal
		'SO' => 'SOS', // Somalia
		'SR' => 'SRD', // Suriname
		'SS' => 'SSP', // South Sudan
		'ST' => 'STN', // Sao Tome and Principe
		'SV' => 'USD', // El Salvador
		'SX' => 'ANG', // Sint Maarten
		'SZ' => 'SZL', // Eswatini
		'TC' => 'USD', // Turks and Caicos Islands
		'TD' => 'XAF', // Chad
		'TF' => 'EUR', // French Southern and Antarctic Lands
		'TG' => 'XOF', // Togo
		'TH' => 'THB', // Thailand
		'TJ' => 'TJS', // Tajikistan
		'TK' => 'NZD', // Tokelau
		'TL' => 'USD', // Timor-Leste
		'TM' => 'TMT', // Turkmenistan
		'TN' => 'TND', // Tunisia
		'TO' => 'TOP', // Tonga
		'TR' => 'TRY', // Turkey
		'TT' => 'TTD', // Trinidad and Tobago
		'TV' => 'AUD', // Tuvalu
		'TW' => 'TWD', // Taiwan
		'TZ' => 'TZS', // Tanzania
		'UA' => 'UAH', // Ukraine
		'UG' => 'UGX', // Uganda
		'UM' => 'USD', // United States Minor Outlying Islands
		'US' => 'USD', // United States
		'UY' => 'UYU', // Uruguay
		'UZ' => 'UZS', // Uzbekistan
		'VA' => 'EUR', // Vatican City
		'VC' => 'XCD', // Saint Vincent and the Grenadines
		'VE' => 'VES', // Venezuela
		'VG' => 'USD', // British Virgin Islands
		'VI' => 'USD', // US Virgin Islands
		'VN' => 'VND', // Vietnam
		'VU' => 'VUV', // Vanuatu
		'WF' => 'XPF', // Wallis and Futuna
		'WS' => 'WST', // Samoa
		'XK' => 'EUR', // Kosovo
		'YE' => 'YER', // Yemen
		'YT' => 'EUR', // Mayotte
		'ZA' => 'ZAR', // South Africa
		'ZM' => 'ZMW', // Zambia
		'ZW' => 'ZWL', // Zimbabwe
	];

	/**
	 * Get the native/primary currency for a specific country.
	 *
	 * @since 3.0.18
	 * @param string $country_code Two-letter country code
	 * @return string|null Currency code or null if not found
	 */
	public static function get_native_currency( string $country_code ): ?string {
		$country_code = strtoupper( $country_code );
		return self::$native_currencies[ $country_code ] ?? null;
	}

	/**
	 * Get all native currency mappings.
	 *
	 * @since 3.0.18
	 * @return array Array mapping country codes to their native currencies
	 */
	public static function get_native_currency_mapping(): array {
		return self::$native_currencies;
	}

	/**
	 * Check if a country uses a specific currency as its native currency.
	 *
	 * @since 3.0.18
	 * @param string $country_code Two-letter country code
	 * @param string $currency_code Three-letter currency code
	 * @return bool True if the country uses this currency natively
	 */
	public static function country_uses_native_currency( string $country_code, string $currency_code ): bool {
		$native_currency = self::get_native_currency( $country_code );
		return $native_currency && strtoupper( $currency_code ) === strtoupper( $native_currency );
	}

	/**
	 * Get countries that use a specific currency as their native currency.
	 *
	 * @since 3.0.18
	 * @param string $currency_code Three-letter currency code
	 * @return array Array of country codes that use this currency natively
	 */
	public static function get_countries_for_native_currency( string $currency_code ): array {
		$currency_code = strtoupper( $currency_code );
		$countries = array();

		foreach ( self::$native_currencies as $country => $native_currency ) {
			if ( $native_currency === $currency_code ) {
				$countries[] = $country;
			}
		}

		return $countries;
	}
}
