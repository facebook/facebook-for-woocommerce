<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Admin\Settings_Screens;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Admin\Abstract_Settings_Screen;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;

/**
 * The Connection settings screen object.
 */
class Connection extends Abstract_Settings_Screen {
	/** @var string screen ID */
	const ID = 'connection';

	/**
	 * Connection constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'initHook' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'admin_notices', array( $this, 'add_notices' ) );
	}

	/**
	 * Initializes this settings page's properties.
	 */
	public function initHook(): void {
		$this->id    = self::ID;
		$this->label = __( 'Connection', 'facebook-for-woocommerce' );
		$this->title = __( 'Connection', 'facebook-for-woocommerce' );
	}

	/**
	 * Adds admin notices.
	 *
	 * @internal
	 *
	 * @since 3.5.0
	 */
	public function add_notices() {

		// display a notice if the connection has previously failed
		if ( get_transient( 'wc_facebook_connection_failed' ) ) {

			$message = sprintf(
			/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - <a> tag, %4$s - </a> tag, %5$s - <a> tag, %6$s - </a> tag */
				__( '%1$sHeads up!%2$s It looks like there was a problem with reconnecting your site to Facebook. Please %3$sclick here%4$s to try again, or %5$sget in touch with our support team%6$s for assistance.', 'facebook-for-woocommerce' ),
				'<strong>',
				'</strong>',
				'<a href="' . esc_url( facebook_for_woocommerce()->get_connection_handler()->get_connect_url() ) . '">',
				'</a>',
				'<a href="' . esc_url( facebook_for_woocommerce()->get_support_url() ) . '" target="_blank">',
				'</a>'
			);

			facebook_for_woocommerce()->get_admin_notice_handler()->add_admin_notice(
				$message,
				'wc_facebook_connection_failed',
				array(
					'notice_class' => 'error',
				)
			);

			delete_transient( 'wc_facebook_connection_failed' );
		}
	}


	/**
	 * Enqueue the assets.
	 *
	 * @internal
	 */
	public function enqueue_assets() {

		if ( ! $this->is_current_screen_page() ) {
			return;
		}

		wp_enqueue_style( 'wc-facebook-admin-connection-settings', facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/facebook-for-woocommerce-connection.css', array(), \WC_Facebookcommerce::VERSION );
	}


	/**
	 * Renders the screen.
	 *
	 * @since 3.5.0
	 */
	public function render() {
		$is_connected = facebook_for_woocommerce()->get_connection_handler()->is_connected();

		// Always render the CTA box
		$this->render_facebook_box( $is_connected );

		// Don't proceed further if not connected
		if ( ! $is_connected ) {
			return;
		}

		/**
		 * Build the basic static elements.
		 *
		 * At a minimum, we display their raw ID. If they have an API resource, we replace that ID with whatever data
		 * we can get our hands on, with an external link if possible. Current abilities:
		 *
		 * + Page: just the ID
		 * + Pixel: just the ID
		 * + Catalog: name, full URL
		 * + Business manager: name, full URL
		 * + Ad account: not currently available
		 *
		 * TODO: add pixel & ad account API retrieval when we gain the ads_management permission
		 * TODO: add the page name and link when we gain the manage_pages permission
		 */
		$static_items = array(
			'page'                          => array(
				'label' => __( 'Page', 'facebook-for-woocommerce' ),
				'value' => facebook_for_woocommerce()->get_integration()->get_facebook_page_id(),
			),
			'pixel'                         => array(
				'label' => __( 'Pixel', 'facebook-for-woocommerce' ),
				'value' => facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(),
			),
			'catalog'                       => array(
				'label' => __( 'Catalog', 'facebook-for-woocommerce' ),
				'value' => facebook_for_woocommerce()->get_integration()->get_product_catalog_id(),
				'url'   => 'https://facebook.com/products',
			),
			'business-manager'              => array(
				'label' => __( 'Business Manager account', 'facebook-for-woocommerce' ),
				'value' => facebook_for_woocommerce()->get_connection_handler()->get_business_manager_id(),
			),
			'ad-account'                    => array(
				'label' => __( 'Ad Manager account', 'facebook-for-woocommerce' ),
				'value' => facebook_for_woocommerce()->get_connection_handler()->get_ad_account_id(),
			),
			'instagram-business-id'         => array(
				'label' => __( 'Instagram Business ID', 'facebook-for-woocommerce' ),
				'value' => facebook_for_woocommerce()->get_connection_handler()->get_instagram_business_id(),
			),
			'commerce-merchant-settings-id' => array(
				'label' => __( 'Commerce Merchant Settings ID', 'facebook-for-woocommerce' ),
				'value' => facebook_for_woocommerce()->get_connection_handler()->get_commerce_merchant_settings_id(),
			),
		);

		// if the catalog ID is set, update the URL and try to get its name for display
		$catalog_id = $static_items['catalog']['value'];
		if ( ! empty( $catalog_id ) ) {
			$static_items['catalog']['url'] = "https://www.facebook.com/commerce/catalogs/{$catalog_id}/products/";
			try {
				$response = facebook_for_woocommerce()->get_api()->get_catalog( $catalog_id );
				$name     = $response->name ?? '';
				if ( $name ) {
					$static_items['catalog']['value'] = $name;
				}
			} catch ( ApiException $exception ) {
				// Log the exception with additional information
				facebook_for_woocommerce()->log(
					sprintf(
						'Connection failed for catalog %s: %s ',
						$catalog_id,
						$exception->getMessage(),
					)
				);
			}
		}

		?>

		<table class="form-table">
			<tbody>

			<?php
			foreach ( $static_items as $id => $item ) :

				$item = wp_parse_args(
					$item,
					array(
						'label' => '',
						'value' => '',
						'url'   => '',
					)
				);

				?>

				<tr valign="top" class="wc-facebook-connected-<?php echo esc_attr( $id ); ?>">

					<th scope="row" class="titledesc">
						<?php echo esc_html( $item['label'] ); ?>
					</th>

					<td class="forminp">

						<?php if ( $item['url'] ) : ?>

							<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank">

								<?php echo esc_html( $item['value'] ); ?>

								<span
									class="dashicons dashicons-external"
									style="margin-right: 8px; vertical-align: bottom; text-decoration: none;"></span>

							</a>

						<?php elseif ( is_numeric( $item['value'] ) ) : ?>

							<code><?php echo esc_html( $item['value'] ); ?></code>

						<?php elseif ( ! empty( $item['value'] ) ) : ?>

							<?php echo esc_html( $item['value'] ); ?>

						<?php else : ?>

							<?php echo '-'; ?>

						<?php endif; ?>

					</td>
				</tr>

			<?php endforeach; ?>

			</tbody>
		</table>

		<?php

		parent::render();
	}

	/**
	 * Renders the legacy Facebook CTA box.
	 *
	 * @param bool $is_connected whether the plugin is connected
	 *
	 * @since 3.5.0
	 */
	private function render_facebook_box( $is_connected ) {
		if ( $is_connected ) {
			$title = __( 'Reach the Right People and Sell More Online', 'facebook-for-woocommerce' );
		} else {
			$title = __( 'Grow your business on Facebook', 'facebook-for-woocommerce' );
		}

		$subtitle = __( 'Use this WooCommerce and Facebook integration to:', 'facebook-for-woocommerce' );
		$benefits = array(
			__( 'Create an ad in a few steps', 'facebook-for-woocommerce' ),
			__( 'Use built-in best practices for online sales', 'facebook-for-woocommerce' ),
			__( 'Get reporting on sales and revenue', 'facebook-for-woocommerce' ),
		);

		?>
		<div id="wc-facebook-connection-box">
			<div class="logo"></div>
			<h1><?php echo esc_html( $title ); ?></h1>
			<h2><?php echo esc_html( $subtitle ); ?></h2>
			<ul class="benefits">
				<?php foreach ( $benefits as $key => $benefit ) : ?>
					<li class="benefit benefit-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $benefit ); ?></li>
				<?php endforeach; ?>
			</ul>
			<div class="actions">
				<?php if ( $is_connected ) : ?>
					<a href="<?php echo esc_url( facebook_for_woocommerce()->get_connection_handler()->get_disconnect_url() ); ?>"
						class="button button-primary uninstall" onclick="return confirmDialog();">
						<?php esc_html_e( 'Disconnect', 'facebook-for-woocommerce' ); ?>
					</a>
					<script>
						function confirmDialog() {
							return confirm('Are you sure you want to disconnect from Facebook?');
						}
					</script>
				<?php else : ?>
					<a href="<?php echo esc_url( facebook_for_woocommerce()->get_connection_handler()->get_connect_url() ); ?>"
						class="button button-primary">
						<?php esc_html_e( 'Get Started', 'facebook-for-woocommerce' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Gets the screen settings.
	 *
	 * @return array
	 * @since 3.5.0
	 */
	public function get_settings(): array {
		return Shops::get_settings_with_title_static( __( 'Debug', 'facebook-for-woocommerce' ) );
	}
}
