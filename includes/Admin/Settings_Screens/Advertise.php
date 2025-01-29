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

namespace WooCommerce\Facebook\Admin\Settings_Screens;

defined( 'ABSPATH' ) or exit;

use WooCommerce\Facebook\API;
use WooCommerce\Facebook\Locale;
use WooCommerce\Facebook\Admin\Abstract_Settings_Screen;
use WooCommerce\Facebook\Framework\Plugin\Exception as PluginException;
use WooCommerce\Facebook\AdvertiseASC\AscNotSupportedException;
use WooCommerce\Facebook\AdvertiseASC\NonDiscriminationNotAcceptedException;
use WooCommerce\Facebook\AdvertiseASC\InstagramActorIdNotFoundException;
use WooCommerce\Facebook\AdvertiseASC\InvalidPaymentInformationException;
use WooCommerce\Facebook\AdvertiseASC\LWIeUserException;
/**
 * The Advertise settings screen object.
 */
class Advertise extends Abstract_Settings_Screen {

	/** @var string screen ID */
	const ID = 'advertise';

	/** @var string The prefix for the ids of the elements that are used for the ASC views */
	const ADVERTISE_ASC_ELEMENTS_ID_PREFIX	= "woocommerce-facebook-settings-advertise-asc-";

	/** @var string Ad Preview Ajax Action text */
	const ACTION_GET_AD_PREVIEW				= 'wc_facebook_get_ad_preview';

	/** @var string Publish Changes Ajax Action text */
	const ACTION_PUBLISH_AD_CHANGES			= 'wc_facebook_advertise_asc_publish_changes';

	/** @var string View name for the New-Buyers ASC campaign */
	const ASC_CAMPAIGN_TYPE_NEW_BUYERS 		= 'new-buyers';

	/** @var string View name for the Retargeting ASC campaign */
	const ASC_CAMPAIGN_TYPE_RETARGETING 	= 'retargeting';

	const STATUS_DISABLED					= 'disabled';

	/**
	 * Advertise settings constructor.
	 *
	 * @since 2.2.0
	 */
	public function __construct() {
		$this->id    = self::ID;
		$this->label             = __( 'Advertise', 'facebook-for-woocommerce' );
		$this->title             = __( 'Advertise', 'facebook-for-woocommerce' );
		$this->documentation_url = 'https://woocommerce.com/document/facebook-for-woocommerce/#how-to-create-ads-on-facebook';

		$this->add_hooks();
	}


	/**
	 * Adds hooks.
	 *
	 * @since 2.2.0
	 */
	private function add_hooks() {
		add_action( 'admin_head', array( $this, 'output_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'add_frontend_hooks' ) );
	}


	/**
	 * Adds the WP hooks to be able to run the frontend
	 *
	 * @since x.x.x
	 *
	 */
	public function add_frontend_hooks(){

		wp_enqueue_script(
			'wc_facebook_metabox_jsx',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/metabox.js',
			array(),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);

		wp_enqueue_script(
			'facebook-for-woocommerce-settings-advertise-asc',
			facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/settings-advertise-asc.js',
			array('jquery', 'select2', 'jquery-tiptip'),
			\WC_Facebookcommerce::PLUGIN_VERSION
		);

		wp_localize_script(
			'facebook-for-woocommerce-settings-advertise-asc',
			'facebook_for_woocommerce_settings_advertise_asc',
			array(
				'ajax_url'               	=>	admin_url( 'admin-ajax.php' ),
				'get_ad_preview_nonce'   	=>	wp_create_nonce( self::ACTION_GET_AD_PREVIEW ),
				'publish_changes_nonce'		=>	wp_create_nonce( self::ACTION_PUBLISH_AD_CHANGES ),
			)
		);
	}


	/**
	 * Enqueues assets for the current screen.
	 *
	 * @internal
	 *
	 * @since 2.2.0
	 */
	public function enqueue_assets() {
		if ( ! $this->is_current_screen_page() ) {
			return;
		}
		wp_enqueue_style( 'wc-facebook-admin-advertise-settings', facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/facebook-for-woocommerce-advertise.css', array(), \WC_Facebookcommerce::VERSION );
		wp_enqueue_style( 'wc-facebook-admin-advertise-settings-asc', facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/facebook-for-woocommerce-advertise-asc.css', array(), \WC_Facebookcommerce::VERSION );
	}


	/**
	 * Outputs the LWI Ads script.
	 *
	 * @internal
	 *
	 * @since 2.1.0-dev.1
	 */
	public function output_scripts() {
		$connection_handler = facebook_for_woocommerce()->get_connection_handler();
		if ( ! $connection_handler || ! $connection_handler->is_connected() || ! $this->is_current_screen_page() ) {
			return;
		}

		?>
		<script>
			window.fbAsyncInit = function() {
				
				FB.init( {
					appId            : '<?php echo esc_js( $connection_handler->get_client_id() ); ?>',
					autoLogAppEvents : true,
					xfbml            : true,
					version          : '<?php echo esc_js( API::API_VERSION )?>',
				} );
			};
		</script>
		<?php
	}


	/**
	 * Gets the LWI Ads configuration to output the FB iframes.
	 *
	 * @since 2.2.0
	 *
	 * @return array
	 */
	private function get_lwi_ads_configuration_data() {

		$connection_handler = facebook_for_woocommerce()->get_connection_handler();

		if ( ! $connection_handler || ! $connection_handler->is_connected() ) {
			return array();
		}

		return array(
			'business_config' => array(
				'business' => array(
					'name' => $connection_handler->get_business_name(),
				),
			),
			'setup'           => array(
				'external_business_id' => $connection_handler->get_external_business_id(),
				'timezone'             => $this->parse_timezone( wc_timezone_string(), wc_timezone_offset() ),
				'currency'             => get_woocommerce_currency(),
				'business_vertical'    => 'ECOMMERCE',
			),
			'repeat'          => false,
		);
	}


	/**
	 * Converts the given timezone string to a name if needed.
	 *
	 * @since 2.2.0
	 *
	 * @param string $timezone_string Timezone string
	 * @param int|float $timezone_offset Timezone offset
	 * @return string timezone string
	 */
	private function parse_timezone( $timezone_string, $timezone_offset = 0 ) {

		// no need to look for the equivalent timezone
		if ( false !== strpos( $timezone_string, '/' ) ) {
			return $timezone_string;
		}

		// look up the timezones list based on the given offset
		$timezones_list = timezone_abbreviations_list();

		foreach ( $timezones_list as $timezone ) {
			foreach ( $timezone as $city ) {
				if ( isset( $city['offset'], $city['timezone_id'] ) && (int) $city['offset'] === (int) $timezone_offset ) {
					return $city['timezone_id'];
				}
			}
		}

		// fallback to default timezone
		return 'Etc/GMT';
	}


	/**
	 * Gets the LWI Ads SDK URL.
	 *
	 * @since 2.2.0
	 *
	 * @return string
	 */
	private function get_lwi_ads_sdk_url() {

		$locale = get_user_locale();

		if ( ! Locale::is_supported_locale( $locale ) ) {
			$locale = Locale::DEFAULT_LOCALE;
		}

		return "https://connect.facebook.net/{$locale}/sdk.js";
	}


	/**
	 * Renders the screen HTML.
	 *
	 * The contents of the Facebook box will be populated by the LWI Ads script through iframes.
	 *
	 * @since 2.2.0
	 */
	public function render() {

		$connection_handler = facebook_for_woocommerce()->get_connection_handler();

		if ( ! $connection_handler || ! $connection_handler->is_connected() ) {

			printf(
				/* translators: Placeholders: %1$s - opening <a> HTML link tag, %2$s - closing </a> HTML link tag */
				esc_html__( 'Please %1$sconnect your store%2$s to Facebook to create ads.', 'facebook-for-woocommerce' ),
				'<a href="' . esc_url( add_query_arg( array( 'tab' => Connection::ID ), facebook_for_woocommerce()->get_settings_url() ) ) . '">',
				'</a>'
			);

			return;
		}

		$this->experimental_view_render();

		parent::render();
	}


	/**
	 * Renders the ASC Experimental view.
	 *
	 * @since x.x.x
	 *
	 */
	private function experimental_view_render() {

		if ( $this->can_try_experimental_view() ) {

		 	$this->try_render_experimental_view();

		} else {

			$this->render_lwi_view();

		}
	}


	/**
	 * Generates the HTML DOM for a given dashboard.
	 *
	 * @since x.x.x
	 * @param string @type. Sets the input type. values: (new-buyers, retargeting)
	 * @param string @title. The title of the dashboard
	 * @param string @subtitle_row1. Row1 of the subtitle of the dashboard
	 * @param string @subtitle_row2. Row2 of the subtitle of the dashboard
	 *
	 */
	private function render_dashboard( $type, $title, $subtitle_row1, $subtitle_row2 ) {
		$campaign_handler	= facebook_for_woocommerce()->get_advertise_asc_handler($type);
		$min_daily_budget	= $campaign_handler->get_allowed_min_daily_budget();
		$currency			= $campaign_handler->get_currency();
		$daily_budget		= $campaign_handler->get_ad_daily_budget();
		$message 			= $campaign_handler->get_ad_message();
		?>
		<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-min-ad-daily-budget-' . $type?>" value="<?php echo number_format((float)$min_daily_budget, 2, '.', '')?>" />
		<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-currency-' . $type ?>" value="<?php echo $currency?>" />
		<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-ad-daily-budget-' . $type ?>" value="<?php echo number_format((float)$daily_budget, 2, '.', '')?>" />
		<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-ad-message-' . $type ?>" value="<?php echo $message?>" />
		<?php
		if ($campaign_handler->is_running()) {
			$selected_countries = $campaign_handler->get_selected_countries();
			$status = $campaign_handler->get_ad_status();
		?>
			<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-targeting-' . $type ?>" value="<?php echo implode(',',$selected_countries)?>" />
			<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-ad-status-' . $type ?>" value="<?php echo $status?>" />
			<?php
			if ($campaign_handler->are_insights_available()) {
				$spend = $campaign_handler->get_insights_spend();
				$reach = $campaign_handler->get_insights_reach();
				$events = $campaign_handler->get_insights_events();
				$clicks = $events[ 'clicks' ];
				$views = $events[ 'views' ];
				$addToCarts = $events[ 'cart' ];
				$purchases = $events[ 'purchases' ];
			} else {
				$spend = $reach = $events = $clicks = $views = $addToCarts = $purchases = 0;
			}
			?>
			<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-ad-insights-spend-' . $type ?>" value="<?php echo $spend?>" />
			<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-ad-insights-reach-' . $type ?>" value="<?php echo $reach?>" />
			<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-ad-insights-clicks-' . $type ?>" value="<?php echo $clicks?>" />
			<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-ad-insights-views-' . $type ?>" value="<?php echo $views?>" />
			<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-ad-insights-carts-' . $type ?>" value="<?php echo $addToCarts?>" />
			<input type="hidden" id="<?php echo 'woocommerce-facebook-settings-advertise-asc-ad-insights-purchases-' . $type ?>" value="<?php echo $purchases?>" />
			<div id="woocommerce-facebook-settings-advertise-asc-insights-placeholder-root-<?php echo $type?>" style="width:100%;"></div>
		<?php
		} else {
		?>
			<div class="main-ui-container">
				<div style="width: auto;">
					<div class="main-ui-container-item">
						<img id="<?php echo $type?>-create-campaign-img" style="width:50px;height:50px;"/>
					</div>
					<div class="main-ui-container-item">
						<p class="main-ui-header"><?php echo $title?></p>
					</div>
					<div class="main-ui-container-item">
						<button class='button button-large' id='<?php echo $type?>-create-campaign-btn' disabled>Get Started</button>
					</div>
					<div class="main-ui-container-item">
						<p style="line-height: 10px;"><?php echo $subtitle_row1?></p>
						<p style="line-height: 10px;"><?php echo $subtitle_row2?></p>
					</div>
				</div>
			</div>
		<?php 
		}
	}


	/** 
	 * Checks whether the tool can show the experimental view or not
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	private function can_try_experimental_view() {
		$ad_acc_id = facebook_for_woocommerce()->get_connection_handler()->get_ad_account_id();
		$trimmed_val = trim($ad_acc_id);
		
		if (empty($trimmed_val)) {
			return false;
		}

		$least_significant_digit = strrev($trimmed_val)[0];

		if ($least_significant_digit < '0' || $least_significant_digit > '9') {
			return false;
		}

		return intval($least_significant_digit) % 2 == 0;
	}


	/** 
	 * Creates the translated text including a link
	 *
	 * @since x.x.x
	 * @param string $pretext. Any text before the link text.
	 * @param string $link. The link url
	 * @param string $link_text. The text for the link
	 * @param string $rest_of_text. Any text that should come after the link
	 * 
	 * @return bool
	 */
	private function translate_with_link( $pretext, $link, $link_text, $rest_of_text ) {
		return $this->get_escaped_translation( $pretext ) . " <a target='_blank' href='" . $link . "'>" . $this->get_escaped_translation( $link_text ) . "</a>" . $this->get_escaped_translation( $rest_of_text );
	}


	/**
	 * Tries to render the experimental view. If something fails, it shows the issue.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	private function try_render_experimental_view() {
		
		try {
			?>
			<div class="fb-asc-ads">
				<div id='overlay-view-ui' class='hidden_view'>
					<div id='asc-overlay-root'></div>
				</div>	
				<div id='base-view-row'>
					<table>
						<tr>
							<td>
								<?php $this->render_dashboard(self::ASC_CAMPAIGN_TYPE_NEW_BUYERS, "Find new customers", "Reach out to potential new buyers for your products", "using Advantage+ Shopping (ASC)"); ?>
							</td>
						</tr>
						<tr>
							<td>
								<?php $this->render_dashboard(self::ASC_CAMPAIGN_TYPE_RETARGETING, "Engage with your website visitors", "Bring back visitors who visited your website and didn't complete", "their purchase using Advantage+ Catalog (DPA)"); ?>
							</td>
						</tr>
					</table>
				</div>
			</div>
			<?php

			wp_enqueue_script(
				'facebook-for-woocommerce-advertise-asc-ui',
				facebook_for_woocommerce()->get_asset_build_dir_url() . '/admin/advertise-asc-ui.js',
				array('react', 'react-dom' ),
				\WC_Facebookcommerce::PLUGIN_VERSION
			);


		} catch ( AscNotSupportedException $e ) {

			facebook_for_woocommerce()->get_integration()->set_advertise_asc_status( self::STATUS_DISABLED );
			?>
			<script>
				window.location.reload();
			</script>
			<?php

		} catch ( InvalidPaymentInformationException $ipie ) {

			\WC_Facebookcommerce_Utils::log( $ipie->getMessage() );
			$this->remove_rendered_when_exception_happened();

			$ad_acc_id = facebook_for_woocommerce()->get_connection_handler()->get_ad_account_id();
			$link = "https://business.facebook.com/ads/manager/account_settings/account_billing/?act=" . $ad_acc_id;

			?>
			<h2><?php echo $this->get_escaped_translation( "Your payment settings need to be updated before we can proceed." ); ?></h2>
			<h4><?php echo $this->get_escaped_translation( "Here's how:" ); ?></h3>
			<ul>
				<li><?php echo $this->translate_with_link( "1.", $link, "Click here", " to go to the \"Payment Settings\" section in your Ads Manager" ); ?></li>
				<li><?php echo $this->get_escaped_translation( "2. Click the \"Add payment method\" button and follow instructions to ad a payment method" ); ?></li>
				<li><?php echo $this->get_escaped_translation( "3. Go back to this screen and refresh it. It may take a few minutes before we can see the updated settings." ); ?></li>
			</ul>
			<?php
		} catch ( InstagramActorIdNotFoundException $iaif ) {
			
			\WC_Facebookcommerce_Utils::log( $iaif->getMessage() );
			$this->remove_rendered_when_exception_happened();
		
			?>
			<div class='fb-asc-ads'>
				<h2 style='margin: 5px 0;'><?php echo $this->get_escaped_translation( "You need to use a page that has an instagram account connected to it." ); ?></h2>
				<h3 class="zero-border-element secondary-header-color"><?php echo $this->get_escaped_translation( "This requires re-connecting through Meta Business Extension." ); ?></h2>
				<h4><?php echo $this->get_escaped_translation( "Here's how:" ); ?></h3>
				<ul>
					<li><?php echo $this->get_escaped_translation( "1. Click the \"Connection\" tab." ); ?></li>
					<li><?php echo $this->get_escaped_translation( "2. Click \"disconnect\". This will disconnect your Facebook Account from your WooCommerce store and refreshes the page" ); ?></li>
					<li><?php echo $this->get_escaped_translation( "3. From the same page, click \"Get Started\". This will take you through the Meta Business Extension onboarding flow. When prompted to select a Page, make sure to select a Page that has an Instagram account linked to it." ) . $this->translate_with_link( "(", "https://www.facebook.com/business/help/connect-instagram-to-page", "How?", ")" ); ?></li>
				</ul>
			</div>
			<?php
		} catch ( NonDiscriminationNotAcceptedException $nde ) {

			\WC_Facebookcommerce_Utils::log( $nde->getMessage() );
			$this->remove_rendered_when_exception_happened();

			$link = "https://business.facebook.com/settings/system-users?business_id=" . facebook_for_woocommerce()->get_connection_handler()->get_business_manager_id();
			?>
			<h2><?php echo $this->get_escaped_translation( "A business Admin must review and accept our non-discrimination policy before you can run ads." ); ?></h2>
			<h4><?php echo $this->get_escaped_translation( "Here's how:" ); ?></h3>
			<ul>
				<li><?php echo $this->translate_with_link( "1.", $link, "Click here", " to go to the \"System Users\" section in your Business Manager" ); ?></li>
				<li><?php echo $this->get_escaped_translation( "2. Click the \"Add\" button to review our Discriminatory Practices policy" ); ?></li>
				<li><?php echo $this->get_escaped_translation( "3. Click the \"I accept\" button to confirm compliance on behalf of your system users" ); ?></li>
				<li><?php echo $this->get_escaped_translation( "4. Close the pop-up window by clicking on X or \"Done\"" ); ?></li>
				<li><?php echo $this->get_escaped_translation( "5. Go back to this screen and refresh it" ); ?></li>
			</ul>
			<?php

		} catch ( LWIeUserException $lwie ) {

			$this->remove_rendered_when_exception_happened();

			facebook_for_woocommerce()->get_integration()->set_advertise_asc_status( self::STATUS_DISABLED );
			?>
			<script>
				window.location.reload();
			</script>
			<?php

		} catch ( \Throwable $pe ) {
			
			\WC_Facebookcommerce_Utils::log( $pe->getMessage() );
			$this->remove_rendered_when_exception_happened();
			
			$ad_account_id = facebook_for_woocommerce()->get_connection_handler()->get_ad_account_id();
			
			$subject = $ad_account_id . '_' . 'PluginException';
			$body = 'message: ' . $pe->getMessage() . '  stack-trace: ' . $pe->getTraceAsString();
			$body = urlencode($body);
			$link = 'mailto:woofeedback@meta.com?subject=' . $subject . '&body=' . $body;
			?>
			<h2><?php echo $this->translate_with_link( "An unexpected error happened.", $link, "Click here", " to mail us the bug report." ); ?></h2>
			<?php
			
		}
	}


	/** 
	 * Closes the open html tags in case of an exception.
	 *
	 * @since x.x.x
	 * 
	 */
	private function remove_rendered_when_exception_happened() {

		?>
		</td></tr></table></div></div> <!-- This is to make sure the error message or anything after this won't be a part of the form in which error happened. -->
		 <script>
			jQuery( '.fb-asc-ads' ).remove();
		</script>
		<?php

	}


	/**
	 * Returns an escaped translation of the input text, in the realm of this plugin
	 *
	 * @since x.x.x
	 *
	 * @param string $text
	 * @returns string
	 */
	private function get_escaped_translation( $text ) {
		return esc_html__( $text, 'facebook-for-woocommerce' );
	}


	/**
	 * Creates the html elements needed for the LWI-E view.
	 *
	 * @since x.x.x
	 *
	 */
	private function render_lwi_view() {

		$fbe_extras = wp_json_encode( $this->get_lwi_ads_configuration_data() );

		?>
		<script async defer src="<?php echo esc_url( $this->get_lwi_ads_sdk_url() ); ?>"></script>
		<div
			class="fb-lwi-ads-creation"
			data-hide-manage-button="true"
			data-fbe-extras="<?php echo esc_attr( $fbe_extras ); ?>"
			data-fbe-scopes="manage_business_extension"
			data-fbe-redirect-uri="https://business.facebook.com/fbe-iframe-handler"
			data-title="<?php esc_attr_e( 'If you are connected to Facebook but cannot display ads, please contact Facebook support.', 'facebook-for-woocommerce' ); ?>"></div>
		<div
			class="fb-lwi-ads-insights"
			data-fbe-extras="<?php echo esc_attr( $fbe_extras ); ?>"
			data-fbe-scopes="manage_business_extension"
			data-fbe-redirect-uri="https://business.facebook.com/fbe-iframe-handler"></div>
		<?php
		$this->maybe_render_learn_more_link( __( 'Advertising', 'facebook-for-woocommerce' ) );

		parent::render();
	}


	/**
	 * Gets the screen settings.
	 *
	 * @since 2.2.0
	 *
	 * @return array
	 */
	public function get_settings() {
		return array();
	}
}