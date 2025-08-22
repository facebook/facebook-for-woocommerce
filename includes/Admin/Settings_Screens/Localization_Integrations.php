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

use WooCommerce\Facebook\Admin\Abstract_Settings_Screen;
use WooCommerce\Facebook\Integrations\IntegrationRegistry;
use WooCommerce\Facebook\Feed\Localization\TranslationDataExtractor;
use WooCommerce\Facebook\Feed\Localization\LanguageFeedData;

defined( 'ABSPATH' ) || exit;

/**
 * Localization Integrations settings screen.
 *
 * @since 3.5.5
 */
class Localization_Integrations extends Abstract_Settings_Screen {

	/** @var string screen ID */
	const ID = 'localization_integrations';

	/**
	 * Gets the screen ID.
	 *
	 * @since 3.5.5
	 *
	 * @return string
	 */
	public function get_id() {
		return self::ID;
	}

	/**
	 * Gets the screen label.
	 *
	 * @since 3.5.5
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Localization', 'facebook-for-woocommerce' );
	}

	/**
	 * Gets the screen title.
	 *
	 * @since 3.5.5
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Localization Integrations', 'facebook-for-woocommerce' );
	}

	/**
	 * Gets the screen description.
	 *
	 * @since 3.5.5
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'View the status of localization and multilingual plugin integrations available for Facebook for WooCommerce.', 'facebook-for-woocommerce' );
	}

	/**
	 * Renders the screen.
	 *
	 * @since 3.5.5
	 */
	public function render() {
		$integrations = IntegrationRegistry::get_all_localization_integrations();

		// Handle test actions
		$this->handle_test_actions();

		?>
		<div class="wc-facebook-localization-integrations">
			<?php $this->render_test_section(); ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-name column-primary">
							<?php esc_html_e( 'Plugin', 'facebook-for-woocommerce' ); ?>
						</th>
						<th scope="col" class="manage-column column-status">
							<?php esc_html_e( 'Status', 'facebook-for-woocommerce' ); ?>
						</th>
						<th scope="col" class="manage-column column-version">
							<?php esc_html_e( 'Version', 'facebook-for-woocommerce' ); ?>
						</th>
						<th scope="col" class="manage-column column-languages">
							<?php esc_html_e( 'Languages', 'facebook-for-woocommerce' ); ?>
						</th>
						<th scope="col" class="manage-column column-default-language">
							<?php esc_html_e( 'Default Language', 'facebook-for-woocommerce' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $integrations ) ) : ?>
						<tr>
							<td colspan="5" class="no-integrations">
								<?php esc_html_e( 'No localization integrations found.', 'facebook-for-woocommerce' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $integrations as $key => $integration ) : ?>
							<?php
							$status = $this->get_integration_status( $integration );
							$version = $integration->get_plugin_version();
							$languages = $integration->get_available_languages();
							$default_language = $integration->get_default_language();
							?>
							<tr>
								<td class="column-name column-primary">
									<strong><?php echo esc_html( $integration->get_plugin_name() ); ?></strong>
									<div class="row-actions">
										<span class="plugin-file"><?php echo esc_html( $integration->get_plugin_file_name() ); ?></span>
									</div>
								</td>
								<td class="column-status">
									<?php echo $this->render_status_badge( $status ); ?>
								</td>
								<td class="column-version">
									<?php echo $version ? esc_html( $version ) : '<span class="na">—</span>'; ?>
								</td>
								<td class="column-languages">
									<?php if ( ! empty( $languages ) ) : ?>
										<span class="language-count"><?php echo esc_html( count( $languages ) ); ?> <?php esc_html_e( 'languages', 'facebook-for-woocommerce' ); ?></span>
										<div class="language-list" style="margin-top: 4px;">
											<?php echo esc_html( implode( ', ', array_slice( $languages, 0, 5 ) ) ); ?>
											<?php if ( count( $languages ) > 5 ) : ?>
												<span class="more-languages">
													<?php
													/* translators: %d: number of additional languages */
													printf( esc_html__( ' and %d more', 'facebook-for-woocommerce' ), count( $languages ) - 5 );
													?>
												</span>
											<?php endif; ?>
										</div>
									<?php else : ?>
										<span class="na">—</span>
									<?php endif; ?>
								</td>
								<td class="column-default-language">
									<?php echo $default_language ? esc_html( $default_language ) : '<span class="na">—</span>'; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $integrations ) ) : ?>
				<div class="integration-info" style="margin-top: 20px;">
					<h3><?php esc_html_e( 'Integration Information', 'facebook-for-woocommerce' ); ?></h3>
					<p><?php esc_html_e( 'This table shows the status of localization and multilingual plugins that Facebook for WooCommerce can integrate with. Active integrations help ensure your products are properly synchronized across different languages.', 'facebook-for-woocommerce' ); ?></p>

					<h4><?php esc_html_e( 'Status Meanings:', 'facebook-for-woocommerce' ); ?></h4>
					<ul style="margin-left: 20px;">
						<li><strong><?php esc_html_e( 'Active:', 'facebook-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Plugin is installed, activated, and ready to use.', 'facebook-for-woocommerce' ); ?></li>
						<li><strong><?php esc_html_e( 'Installed:', 'facebook-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Plugin is installed but not activated.', 'facebook-for-woocommerce' ); ?></li>
						<li><strong><?php esc_html_e( 'Not Available:', 'facebook-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Plugin is not installed.', 'facebook-for-woocommerce' ); ?></li>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<style>
			.wc-facebook-localization-integrations .wp-list-table {
				margin-top: 20px;
			}

			.wc-facebook-localization-integrations .column-name {
				width: 25%;
			}

			.wc-facebook-localization-integrations .column-status {
				width: 15%;
			}

			.wc-facebook-localization-integrations .column-version {
				width: 15%;
			}

			.wc-facebook-localization-integrations .column-languages {
				width: 25%;
			}

			.wc-facebook-localization-integrations .column-default-language {
				width: 20%;
			}

			.wc-facebook-localization-integrations .status-badge {
				display: inline-block;
				padding: 4px 8px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 600;
				text-transform: uppercase;
			}

			.wc-facebook-localization-integrations .status-active {
				background-color: #d4edda;
				color: #155724;
				border: 1px solid #c3e6cb;
			}

			.wc-facebook-localization-integrations .status-installed {
				background-color: #fff3cd;
				color: #856404;
				border: 1px solid #ffeaa7;
			}

			.wc-facebook-localization-integrations .status-not-available {
				background-color: #f8d7da;
				color: #721c24;
				border: 1px solid #f5c6cb;
			}

			.wc-facebook-localization-integrations .na {
				color: #999;
				font-style: italic;
			}

			.wc-facebook-localization-integrations .no-integrations {
				text-align: center;
				padding: 40px 20px;
				color: #666;
				font-style: italic;
			}

			.wc-facebook-localization-integrations .row-actions {
				color: #999;
				font-size: 12px;
				margin-top: 4px;
			}

			.wc-facebook-localization-integrations .language-count {
				font-weight: 600;
			}

			.wc-facebook-localization-integrations .language-list {
				font-size: 12px;
				color: #666;
			}

			.wc-facebook-localization-integrations .more-languages {
				font-style: italic;
			}

			.wc-facebook-localization-integrations .integration-info {
				background: #f9f9f9;
				border: 1px solid #ddd;
				border-radius: 4px;
				padding: 20px;
			}

			.wc-facebook-localization-integrations .integration-info h3 {
				margin-top: 0;
			}

			.wc-facebook-localization-integrations .integration-info h4 {
				margin-bottom: 8px;
			}

			.wc-facebook-localization-integrations .integration-info ul {
				margin-bottom: 0;
			}
		</style>
		<?php
	}

	/**
	 * Gets the integration status.
	 *
	 * @since 3.5.5
	 *
	 * @param \WooCommerce\Facebook\Integrations\Abstract_Localization_Integration $integration
	 * @return string
	 */
	private function get_integration_status( $integration ) {
		if ( $integration->is_plugin_active() ) {
			return 'active';
		} elseif ( $integration->is_plugin_installed() ) {
			return 'installed';
		} else {
			return 'not-available';
		}
	}

	/**
	 * Renders a status badge.
	 *
	 * @since 3.5.5
	 *
	 * @param string $status
	 * @return string
	 */
	private function render_status_badge( $status ) {
		$labels = array(
			'active'        => __( 'Active', 'facebook-for-woocommerce' ),
			'installed'     => __( 'Installed', 'facebook-for-woocommerce' ),
			'not-available' => __( 'Not Available', 'facebook-for-woocommerce' ),
		);

		$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		$class = 'status-badge status-' . esc_attr( $status );

		return sprintf( '<span class="%s">%s</span>', $class, esc_html( $label ) );
	}

	/**
	 * Gets the screen settings.
	 *
	 * This screen is read-only, so no settings are needed.
	 *
	 * @since 3.5.5
	 *
	 * @return array
	 */
	public function get_settings(): array {
		return array();
	}

	/**
	 * Saves the screen settings.
	 *
	 * This screen is read-only, so no save functionality is needed.
	 *
	 * @since 3.5.5
	 */
	public function save() {
		// This screen is read-only, no save functionality needed
	}

	/**
	 * Handle test actions
	 *
	 * @since 3.6.0
	 */
	private function handle_test_actions() {
		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		$action = sanitize_text_field( $_GET['action'] );
		$nonce = sanitize_text_field( $_GET['_wpnonce'] );

		if ( ! wp_verify_nonce( $nonce, 'localization_test_action' ) ) {
			return;
		}

		if ( $action === 'download_sample' ) {
			$this->download_sample_csv();
		}
	}

	/**
	 * Render test section
	 *
	 * @since 3.6.0
	 */
	private function render_test_section() {
		$extractor = new TranslationDataExtractor();
		$has_plugins = $extractor->has_active_localization_plugin();
		$languages = $extractor->get_available_languages();
		$active_plugins = $extractor->get_active_localization_plugins();

		?>
		<div class="wc-facebook-test-section" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
			<h3><?php esc_html_e( '🧪 Translation Data Test', 'facebook-for-woocommerce' ); ?></h3>

			<!-- Status Overview -->
			<div style="margin-bottom: 20px;">
				<h4><?php esc_html_e( 'Current Status', 'facebook-for-woocommerce' ); ?></h4>
				<?php if ( $has_plugins ) : ?>
					<p><strong>✅ <?php esc_html_e( 'Active Plugins:', 'facebook-for-woocommerce' ); ?></strong> <?php echo esc_html( implode( ', ', $active_plugins ) ); ?></p>
					<p><strong>🗣️ <?php esc_html_e( 'Available Languages:', 'facebook-for-woocommerce' ); ?></strong> <?php echo empty( $languages ) ? esc_html__( 'None configured', 'facebook-for-woocommerce' ) : esc_html( implode( ', ', $languages ) ); ?></p>
				<?php else : ?>
					<p><strong>❌ <?php esc_html_e( 'No Active Plugins:', 'facebook-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Install WPML or Polylang to test translation extraction.', 'facebook-for-woocommerce' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- Product Test -->
			<?php $this->render_product_test_section( $extractor ); ?>

			<!-- Sample CSV Download -->
			<div style="margin-top: 20px;">
				<h4><?php esc_html_e( 'Sample CSV Download', 'facebook-for-woocommerce' ); ?></h4>
				<p><?php esc_html_e( 'Download a sample CSV file to see the expected format for Facebook language override feeds.', 'facebook-for-woocommerce' ); ?></p>
				<?php
				$nonce = wp_create_nonce( 'localization_test_action' );
				$download_url = add_query_arg( [
					'page' => 'wc-facebook',
					'tab' => 'localization_integrations',
					'action' => 'download_sample',
					'_wpnonce' => $nonce,
				], admin_url( 'admin.php' ) );
				?>
				<a href="<?php echo esc_url( $download_url ); ?>" class="button button-primary"><?php esc_html_e( 'Download Sample CSV', 'facebook-for-woocommerce' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render product test section
	 *
	 * @since 3.6.0
	 */
	private function render_product_test_section( TranslationDataExtractor $extractor ) {
		// Get products from default language using the new method
		$product_ids = $extractor->get_products_from_default_language( 5 );

		?>
		<div style="margin-bottom: 20px;">
			<h4><?php esc_html_e( 'Product Translation Test', 'facebook-for-woocommerce' ); ?></h4>

			<?php if ( empty( $product_ids ) ) : ?>
				<p><?php esc_html_e( 'No products found in the default language. Create some products to test translation extraction.', 'facebook-for-woocommerce' ); ?></p>
			<?php else : ?>
				<p><?php printf( esc_html__( 'Testing translation extraction with %d products from the default language:', 'facebook-for-woocommerce' ), count( $product_ids ) ); ?></p>

				<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product ID', 'facebook-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Title', 'facebook-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Default Language', 'facebook-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Translations', 'facebook-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Translated Fields', 'facebook-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $product_ids as $product_id ) : ?>
							<?php
							$product = wc_get_product( $product_id );
							if ( ! $product ) continue;

							$details = $extractor->get_product_translation_details( $product_id );
							$has_translations = ! empty( $details['translations'] );
							$languages = $has_translations ? array_keys( $details['translations'] ) : [];
							$default_language = $details['default_language'] ?? 'Unknown';

							// Get translated fields summary
							$translated_fields_summary = [];
							if ( isset( $details['translated_fields'] ) ) {
								foreach ( $details['translated_fields'] as $lang => $fields ) {
									if ( ! empty( $fields ) ) {
										$translated_fields_summary[] = $lang . ': ' . implode( ', ', $fields );
									}
								}
							}
							?>
							<tr>
								<td><?php echo esc_html( $product_id ); ?></td>
								<td>
									<strong><?php echo esc_html( $product->get_name() ); ?></strong>
									<div style="font-size: 12px; color: #666;">
										<?php echo esc_html( $product->get_type() ); ?>
									</div>
								</td>
								<td><?php echo esc_html( $default_language ); ?></td>
								<td>
									<?php if ( $has_translations ) : ?>
										<span style="color: #46b450;">✅ <?php echo count( $languages ); ?> <?php esc_html_e( 'languages', 'facebook-for-woocommerce' ); ?></span>
										<div style="font-size: 12px; color: #666; margin-top: 2px;">
											<?php echo esc_html( implode( ', ', $languages ) ); ?>
										</div>
									<?php else : ?>
										<span style="color: #dc3232;">❌ <?php esc_html_e( 'None', 'facebook-for-woocommerce' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $translated_fields_summary ) ) : ?>
										<div style="font-size: 12px;">
											<?php foreach ( $translated_fields_summary as $summary ) : ?>
												<div style="margin-bottom: 2px;"><?php echo esc_html( $summary ); ?></div>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<span style="color: #666; font-style: italic;"><?php esc_html_e( 'No fields translated', 'facebook-for-woocommerce' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div style="margin-top: 15px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa; font-size: 13px;">
					<strong><?php esc_html_e( 'Note:', 'facebook-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'This table shows products from the default language and their translation status. Only products with actual translated content will appear in the language override feeds.', 'facebook-for-woocommerce' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Download sample CSV file
	 *
	 * @since 3.6.0
	 */
	private function download_sample_csv() {
		$feed_data = new LanguageFeedData();
		$sample_data = $feed_data->get_sample_csv_data();
		$csv_content = $feed_data->convert_to_csv_string( $sample_data );

		// Set headers for download
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sample_localization_feed.csv"' );
		header( 'Content-Length: ' . strlen( $csv_content ) );

		echo $csv_content;
		exit;
	}
}
