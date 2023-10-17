<?php
// phpcs:ignoreFile
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\AdvertiseASC;

defined('ABSPATH') || exit;

use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\Framework\Api\Exception as PluginException;
use WooCommerce\Facebook\AdvertiseASC\InvalidPaymentInformationException;
use WooCommerce\Facebook\AdvertiseASC\InstagramActorIdNotFoundException;

abstract class CampaignHandler {

    const STATUS_ACTIVE     = 'ACTIVE';
    const STATUS_PAUSED     = 'PAUSED';
    const STATUS_ARCHIVED   = 'ARCHIVED';
    const ALL_PRODUCTS      = 'All products';

    protected $ad;
    protected $api;
    protected $adset;
    protected $ad_name;
    protected $currency;
    protected $campaign;
    protected $store_url;
    protected $bid_amount;
    protected $adset_name;
    protected $store_name;
    protected $adcreative;
    protected $integration;
    protected $ad_account_id;
    protected $campaign_name;
    protected $conversion_rate;
    protected $min_daily_budget;
    protected $ad_creative_name;
    protected $facebook_page_id;
    protected $instagram_actor_id;
    protected $product_catalog_id;
    protected $default_product_set;

    private $insights;

    protected function __construct($asc_campaign_type) {

        $this->bid_amount         = '3000';
        $facebook_for_woocommerce = facebook_for_woocommerce();
        try {
            $this->api                = $facebook_for_woocommerce->get_api();
            $this->integration        = $facebook_for_woocommerce->get_integration();
            $this->ad_account_id      = $facebook_for_woocommerce->get_connection_handler()->get_ad_account_id();
            $this->store_name         = \WC_Facebookcommerce_Utils::get_store_name();
            $this->store_url          = \WC_Facebookcommerce_Utils::get_store_url();

            if (!$this->is_payment_method_valid()) {
                throw new InvalidPaymentInformationException();
            }

            $this->product_catalog_id = $this->integration->get_product_catalog_id();
            $this->facebook_page_id   = $this->integration->get_facebook_page_id();
            $this->default_product_set = $this->get_default_product_set_id($this->product_catalog_id);
            $this->campaign_name      = $this->store_name . ' ' . $asc_campaign_type . ' Campaign [WOOCOMMERCE-ASC]';
            $this->adset_name         = $this->store_name . ' ' . $asc_campaign_type . ' Ad Set [WOOCOMMERCE-ASC]';
            $this->ad_name            = $this->store_name . ' ' . $asc_campaign_type . ' Ad Set Ad [WOOCOMMERCE-ASC]';
            $this->ad_creative_name   = $this->store_name . ' ' . $asc_campaign_type . ' Creative [WOOCOMMERCE-ASC]';
            $this->currency           = $this->api->get_currency($this->ad_account_id)->get_currency();
            $this->min_daily_budget   = $this->get_min_daily_budget();

            if ($this->is_running()) {
                $this->get_insights();
            }
        } catch (ApiException $e) {

            $message = sprintf('There was an error trying to create the campaign. message: %s', $e->getMessage());
            \WC_Facebookcommerce_Utils::log($message);

            throw new PluginException($message);
        }

        $this->instagram_actor_id = $this->get_instagram_actor_id($this->facebook_page_id);
    }

    abstract public function get_campaign_type(): string;
    abstract public function get_allowed_min_daily_budget();
    abstract protected function get_id();
    abstract protected function get_adcreative_creation_params( $name, $page_id, $link, $message, $product_set_id );

    /**
     * Gets the daily budget if the ad is created. Otherwise it returns the 1.2*min daily budget.
     *
     * @since x.x.x
     *
     * @return string
     */
    public function get_ad_daily_budget() {

        if ( ! $this->is_running() ) {

            $min = $this->get_allowed_min_daily_budget();
            
            return strval($min * 1.2);
        }

        return strval($this->adset['daily_budget'] / 100);
    }

    /**
     * Gets the first instagram acount id from the Marketing Api.
     *
     * @since x.x.x
     * @param string @page_id Facebook Page Id.
     * @return string
     * @throws PluginException
     */
    public function get_instagram_actor_id($page_id) {   
        try {
            $accounts_data = $this->get_current_user_associated_accounts();

            foreach ($accounts_data as $account_data) {

                if ($account_data['id'] == $page_id) {
                    $access_token = $account_data['access_token'];

                    $instagram_accounts = $this->get_instagram_accounts($page_id, $access_token);

                    if (!$instagram_accounts['data']) {

                        $instagram_accounts = $this->get_page_backed_instagram_accounts($page_id, $access_token);

                        if (!$instagram_accounts['data']) {

                            throw new InstagramActorIdNotFoundException();
                        }
                    }

                    return $instagram_accounts['data'][0]['id'];
                }
            }

            throw new InstagramActorIdNotFoundException();

        } catch (ApiException $e) {
            $message = sprintf('There was an error trying to get the instagram account id for this user. message: %s', $e->getMessage());
            \WC_Facebookcommerce_Utils::log($message);
            throw new PluginException($message);
        }
    }

    /**
     * Updates the status of the ad. If the value of state is 'true', then it sets the ad status to 'Active' otherwise paused.
     *
     * @since x.x.x
     * @param string @state. values: 'true' or 'false'
     * @return string
     */
	public function update_ad_status( $state ) {
        $status = $state == 'true' ? 1 : 0;
		$this->set_ad_status( $status );
	}

    /**
     * Checks whether the insights are available for the current campaign or not
     *
     * @return array
     */
    public function are_insights_available() {

        return (bool) $this->insights;
    }

    /**
     * Generates an ad preview with a message in a specified format. 
     *
     * @since x.x.x
     * @param string @message The message to be shown on the ad.
     * @param string @ad_format The output format of the generated ad.
     * @return string
     * @throws PluginException
     */
	public function generate_ad_preview( $message, $ad_format ) {

        try {
			$creative_spec = $this->get_adcreative_creation_params( $this->ad_creative_name, $this->facebook_page_id, $this->store_url, $message, $this->default_product_set );

			return $this->api->generate_ad_preview( $this->ad_account_id, $ad_format, $creative_spec )->get_preview();
		} catch ( ApiException $e ) {

			$message = sprintf( 'There was an error trying to generate the Ad preview with format ' . $ad_format . '. message: %s', $e->getMessage() );
			\WC_Facebookcommerce_Utils::log( $message );
			throw new PluginException( $message );

		}

	}

    /**
     * Gets the Spend for the current Campaign
     *
     * @return array
     */
    public function get_insights_spend() {

        return $this->insights['spend'];
    }

    /**
     * Gets the Reach count for the current Campaign
     *
     * @return array
     */
    public function get_insights_reach() {

        return $this->insights['reach'];
    }

    /**
     * Gets the Events from the Ad Insights structure
     *
     * @return array
     */
    public function get_insights_events() {

        return $this->insights['actions'];
    }

    /**
     * Gets the Ad Preview for the campaign's Ad, in the given format.
     *
     * @param string $ad_format Facebook Ad Format.
     * @return string
     * @throws ApiException
     */
    public function get_ad_preview($ad_format) {

        try {

            return $this->api->get_ad_previews($this->ad['id'], $ad_format)->get_preview();
        } catch (ApiException $e) {
            $message = sprintf('There was an error trying to get the Ad preview for Id: ' . $this->ad['id'] . ' with format ' . $ad_format . '. message: %s', $e->getMessage());
            \WC_Facebookcommerce_Utils::log($message);
            throw new PluginException($message);
        }
    }

    /**
     * Returns the Ad Campaign based on the argument
     *
     * @since x.x.x
     * @param string @campaign_id Facebook Ad Campaign Id.
     * @return mixed
     * @throws ApiException
     */
    public function fetch_campaign($campaign_id) {

        return $this->api->get_with_generic_request($campaign_id, 'id,name,status');
    }

    /**
     * Returns the Adset based on the argument
     *
     * @since x.x.x
     * @param string @adset_id Facebook Adset Id.
     * @return mixed
     * @throws ApiException
     */
    public function fetch_adset($adset_id) {

        return $this->api->get_with_generic_request($adset_id, 'id,name,status,daily_budget,targeting,promoted_object');
    }

    /**
     * Returns the Ad based on the argument
     *
     * @since x.x.x
     * @param string @ad_id Facebook Ad Id.
     * @return mixed
     * @throws ApiException
     */
    public function fetch_ad($ad_id) {

        return $this->api->get_with_generic_request($ad_id, 'id,name,status');
    }

    /**
     * Returns the Adcreative based on the argument
     *
     * @since x.x.x
     * @param string @adcreative_id Facebook Adcreative Id.
     * @return mixed
     * @throws ApiException
     */
    public function fetch_adcreative($adcreative_id) {

        return $this->api->get_with_generic_request($adcreative_id, 'id,name,status,body,product_set_id');
    }

    /**
     * Updates the current campaign, with the given props
     *
     * @param string $campaign_props Facebook Ad Campaign creation properties.
     * @throws ApiException
     */
    public function update_campaign($campaign_props) {

        $this->campaign = $this->api->update_campaign($this->campaign['id'], $campaign_props);
    }

    /**
     * Updates the current adset, with the given props
     *
     * @param string $adset_props Facebook AdSet creation properties.
     * @throws ApiException
     */
    public function update_adset($adset_props) {

        $this->adset = $this->api->update_adset($this->adset['id'], $adset_props);
    }

    /**
     * Updates the current ad, with the given props
     *
     * @param string $ad_props Facebook Ad creation properties.
     * @throws ApiException
     */
    public function update_ad($ad_props) {

        $this->ad = $this->api->update_ad($this->ad['id'], $ad_props);
    }

    /**
     * Updates the current ad creative, with the given props
     *
     * @param string $adcreative_props Facebook Ad Creative creation properties.
     * @throws ApiException
     */
    public function update_adcreative($adcreative_props) {

        $this->adcreative = $this->api->update_adcreative($this->adcreative['id'], $adcreative_props);
    }

    /**
     * Deletes an item with a given id
     *
     * @param string $id Facebook Id.
     * @throws PluginException
     */
    public function delete_item($id) {

        try {
            $this->api->delete_item($id);
        } catch (ApiException $e) {
            throw new PluginException($e->getMessage());
        }
    }

    /**
     * Gets the current running Ad status.
     *
     * @return bool
     * @throws ApiException
     */
    public function get_ad_status() {

        if ( ! $this->is_running()) {

            return false;
        }

        return
            $this->adcreative && ($this->adcreative['status'] == self::STATUS_ACTIVE) &&
            $this->campaign   && ($this->campaign['status']   == self::STATUS_ACTIVE) &&
            $this->adset      && ($this->adset['status']      == self::STATUS_ACTIVE) &&
            $this->ad         && ($this->ad['status']         == self::STATUS_ACTIVE);
    }

    /**
     * Gets the currency for the current ad account.
     *
     * @return string
     */
    public function get_currency() {

        return $this->currency;
    }

    /**
     * Gets the current running Ad's message.
     *
     * @return string
     */
	public function get_ad_message(): string {

		if ( ! $this->is_running() ) {

			return '';

		}

		return $this->adcreative['body'];

	}

    /**
     * Checks whether the campaign is running or not. Returns true if so and false if no campaign is running.
     *
     * @return bool
     */
    public function is_running() {
        $info = $this->get_stored_data();

        if ((!$info) ||
            (!array_key_exists('campaign_id', $info) || !$info['campaign_id'] || !$info['adset_id'] || !$info['adcreative_id'] || !$info['ad_id']) ||
            ($this->ad_account_id != $info['ad_account_id'])
        ) {

            return false;

        } else {

            if ( ! $this->adcreative['status']) {
                $this->load($info['campaign_id'], $info['adset_id'], $info['ad_id'], $info['adcreative_id']);
            }
            
            if (
                $this->adcreative['status'] == self::STATUS_ARCHIVED ||
                $this->campaign['status']   == self::STATUS_ARCHIVED ||
                $this->adset['status']      == self::STATUS_ARCHIVED ||
                $this->ad['status']         == self::STATUS_ARCHIVED
            ) {

                return false;
            }

            return true;
        }
    }

    protected function get_insights(){
        $this->insights = $this->api->get_insights($this->campaign['id'])->get_result();
    }

    protected function get_min_daily_budget() {
        $get_daily_budget_for_given_currency = function($data, $currency) {
            foreach ($data as $min_budget) {
                if ($min_budget['currency'] == $currency) {
                    return $min_budget['min_daily_budget_high_freq'];
                }
            }
            return -1;
        };

        $result = $this->api->get_with_generic_request('act_' . $this->ad_account_id, 'minimum_budgets');
        $min_budgets = $result["minimum_budgets"]["data"];

        $usd_min_budget = $get_daily_budget_for_given_currency($min_budgets, "USD");
        $my_currency_min_budget = $get_daily_budget_for_given_currency($min_budgets, $this->get_currency());

        $this->conversion_rate = $my_currency_min_budget/$usd_min_budget;

        return ceil(($my_currency_min_budget * 1.2) / 1000.0) * 10;
    }

    protected function set_ad_status(bool $status) {

        try {
            if ($status) {

                $this->update_adcreative(array('status' => self::STATUS_ACTIVE));
                $this->update_campaign(array('status' => self::STATUS_ACTIVE));
                $this->update_adset(array('status' => self::STATUS_ACTIVE));
                $this->update_ad(array('status' => self::STATUS_ACTIVE));
            } else {

                $this->update_campaign(array('status' => self::STATUS_PAUSED));
            }
        } catch (ApiException $e) {

            $message = sprintf('An exception happened trying to change ad status. ' . $e->getMessage());
            \WC_Facebookcommerce_Utils::log($message);
            throw new PluginException($message);
        }
    }

    protected function create_campaign($campaign_props) {

        $result = $this->api->create_campaign($this->ad_account_id, $campaign_props);
        return $result->get_campaign();
    }

    protected function create_adset($adset_props) {

        $result = $this->api->create_adset($this->ad_account_id, $adset_props);
        return $result->get_data();
    }

    protected function create_adcreative($adcreative_props) {

        return $this->api->create_adcreative($this->ad_account_id, $adcreative_props);
    }

    protected function create_ad($ad_props) {

        $result = $this->api->create_ad($this->ad_account_id, $ad_props);
        return $result->get_data();
    }

    protected function get_countries(): array {
        $countries = array(
            'AF' => 'Afghanistan',
            'AX' => 'Aland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua And Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia And Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, Democratic Republic',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Cote D"Ivoire',
            'HR' => 'Croatia',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island & Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle Of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KR' => 'Korea',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People"s Democratic Republic',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States Of',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'AN' => 'Netherlands Antilles',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory, Occupied',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthelemy',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts And Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin',
            'PM' => 'Saint Pierre And Miquelon',
            'VC' => 'Saint Vincent And Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome And Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia And Sandwich Isl.',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard And Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad And Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks And Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.S.',
            'WF' => 'Wallis And Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe'
        );
        return $countries;
    }

    protected function update_stored_data() {

        $data = $this->integration->get_advertise_asc_information();

        $data[$this->get_id()] = array(
            'ad_account_id'     =>  $this->ad_account_id,
            'campaign_id'       =>  $this->campaign['id'],
            'adset_id'          =>  $this->adset['id'],
            'ad_id'             =>  $this->ad['id'],
            'adcreative_id'     =>  $this->adcreative['id']
        );

        $this->integration->update_advertise_asc_information($data);
    }

    private function load($campaign_id, $adset_id, $ad_id, $adcreative_id) {

        $this->adcreative = $this->fetch_adcreative($adcreative_id);
        $this->campaign = $this->fetch_campaign($campaign_id);
        $this->adset = $this->fetch_adset($adset_id);
        $this->ad = $this->fetch_ad($ad_id);
    }

    private function get_default_product_set_id($catalog_id) {
        try {

            $result = $this->api->get_product_sets($catalog_id);
            $product_sets = $result->get_data();

            foreach ($product_sets as $product_set) {
                if ($product_set['name'] == self::ALL_PRODUCTS) {
                    return $product_set['id'];
                }
            }
            throw new PluginException("No product sets found on this catalog");

        } catch (ApiException $e) {

            $message = sprintf('There was an error trying to get the Ad Insights. message: %s', $e->getMessage());
            \WC_Facebookcommerce_Utils::log($message);

            return null;
        }
    }

    private function is_payment_method_valid() {
        $result = $this->api->get_with_generic_request('act_' . $this->ad_account_id, 'funding_source_details');

        return array_key_exists('funding_source_details', $result->response_data);
    }

    private function get_stored_data() {

        $data = $this->integration->get_advertise_asc_information();

        if (!$data) {

            $data = array();
        }
        if (!array_key_exists($this->get_id(), $data)) {

            $data[$this->get_id()] = array();
        }

        return $data[$this->get_id()];
    }

    /**
     * Gets the accounts that are associated with 'me' entity.
     *
     * @since x.x.x
     *
     * @return array
     * @throws ApiException
     */
    private function get_current_user_associated_accounts() {

        $result = $this->api->get_with_generic_request('me', 'accounts');
        return $result->response_data['accounts']['data'];
    }

    /**
     * Gets the instagram accounts that are associated with the Page with the given Id. For this, we first need to set the appropriate access token.
     *
     * @since x.x.x
     *
     * @return array
     * @throws ApiException
     */
    private function get_instagram_accounts($page_id, $page_access_token) {

        $current_access_token = $this->api->get_access_token();

        try {
            $this->api->set_access_token($page_access_token);
            $result = $this->api->get_with_generic_request($page_id, 'instagram_accounts');
            return $result->response_data['instagram_accounts'];
        } finally {
            $this->api->set_access_token($current_access_token);
        }
    }

    /**
     * Gets the page backed instagram accounts that are associated with the Page with the given Id. For this, we first need to set the appropriate access token.
     *
     * @since x.x.x
     *
     * @return array
     * @throws ApiException
     */
    private function get_page_backed_instagram_accounts($page_id, $page_access_token) {

        $current_access_token = $this->api->get_access_token();

        try {
            $this->api->set_access_token($page_access_token);
            $result = $this->api->get_with_generic_request($page_id, 'page_backed_instagram_accounts');
            return $result->response_data['page_backed_instagram_accounts'];
        } finally {
            $this->api->set_access_token($current_access_token);
        }
    }

    /**
     * Returns the escaped translation of the input
     *
     * @since x.x.x
     * @param string $text Input text.
     * @return string
     */
    protected function get_escaped_translation( $text ) {
        return esc_html( $text, 'facebook-for-woocommerce' );
    }
}
