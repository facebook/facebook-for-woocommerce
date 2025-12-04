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

		// Special cases where WordPress/Polylang language codes don't match Facebook's expected codes
		// These must be handled BEFORE other mappings
		$special_mappings = [
			'nb' => 'no_XX',  // Norwegian Bokmål (nb_NO → no_XX)
			'nn' => 'no_XX',  // Norwegian Nynorsk (nn_NO → no_XX)
			'ck' => 'cb_IQ',  // Central Kurdish (ckb → cb_IQ)
			'ce' => 'cx_PH',  // Cebuano (ceb → cx_PH)
		];

		if ( isset( $special_mappings[ $language ] ) ) {
			return $special_mappings[ $language ];
		}

		// Handle special cases for Chinese FIRST (before generic mappings)
		// This is critical because we need to distinguish zh_CN from zh_TW
		if ( 'zh' === $language && isset( $language_parts[1] ) ) {
			$region = strtoupper( $language_parts[1] );
			if ( in_array( $region, [ 'TW', 'HK', 'MO' ] ) ) {
				return 'zh_TW'; // Traditional Chinese
			}
			return 'zh_CN'; // Simplified Chinese (default)
		}

		// Check if this language uses the _XX format
		if ( in_array( $language, self::$facebook_xx_languages, true ) ) {
			return $language . '_XX';
		}

		// Check if we have a specific Facebook override value for this language
		if ( isset( self::$facebook_override_values[ $language ] ) ) {
			return self::$facebook_override_values[ $language ];
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
	 * @throws \WooCommerce\Facebook\Framework\Plugin\Exception If the language is not supported by Facebook.
	 */
	public static function convert_to_facebook_override_value( string $language_code ): string {
		// Extract the language part (before the underscore)
		$language_parts = explode( '_', $language_code );
		$language = strtolower( $language_parts[0] );

		// Handle special cases for Chinese FIRST (before generic mappings)
		// This is critical because we need to distinguish zh_CN from zh_TW
		if ( 'zh' === $language && isset( $language_parts[1] ) ) {
			$region = strtoupper( $language_parts[1] );
			if ( in_array( $region, [ 'TW', 'HK', 'MO' ] ) ) {
				return 'zh_TW'; // Traditional Chinese
			}
			return 'zh_CN'; // Simplified Chinese (default)
		}

		// Check if we have a specific Facebook override value for this language
		if ( isset( self::$facebook_override_values[ $language ] ) ) {
			return self::$facebook_override_values[ $language ];
		}

		// If no mapping found, throw an exception
		throw new \WooCommerce\Facebook\Framework\Plugin\Exception(
			sprintf(
				/* translators: %s: Language code */
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
}
