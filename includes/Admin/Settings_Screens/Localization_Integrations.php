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

defined( 'ABSPATH' ) || exit;

// Manually include the localization classes
require_once __DIR__ . '/../../Feed/Localization/LanguageFeedData.php';

use WooCommerce\Facebook\Feed\Localization\LanguageFeedData;

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
		} elseif ( $action === 'download_language_csv' ) {
			$language_code = sanitize_text_field( $_GET['language'] ?? '' );
			if ( $language_code ) {
				$this->download_language_csv( $language_code );
			}
		} elseif ( $action === 'download_all_csvs' ) {
			$this->download_all_language_csvs();
		}
	}

	/**
	 * Render test section
	 *
	 * @since 3.6.0
	 */
	private function render_test_section() {
		$feed_data = new LanguageFeedData();
		$has_plugins = $feed_data->has_active_localization_plugin();
		$languages = $feed_data->get_available_languages();
		$active_plugins = $feed_data->get_active_localization_plugins();

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
			<?php $this->render_product_test_section( $feed_data ); ?>

			<!-- Language CSV Generation -->
			<?php if ( $has_plugins && ! empty( $languages ) ) : ?>
				<div style="margin-top: 20px;">
					<h4><?php esc_html_e( 'Generate Language Override CSV Files', 'facebook-for-woocommerce' ); ?></h4>
					<p><?php esc_html_e( 'Generate CSV files containing actual translated product data for Facebook language override feeds.', 'facebook-for-woocommerce' ); ?></p>

					<?php
					$feed_data = new LanguageFeedData();
					$statistics = $feed_data->get_language_feed_statistics();
					?>

					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin: 15px 0;">
						<?php foreach ( $languages as $language_code ) : ?>
							<?php
							$stats = $statistics[ $language_code ] ?? [];
							$product_count = $stats['translated_products_count'] ?? 0;
							$estimated_size = $stats['estimated_csv_size'] ?? 'Unknown';

							$nonce = wp_create_nonce( 'localization_test_action' );
							$download_url = add_query_arg( [
								'page' => 'wc-facebook',
								'tab' => 'localization_integrations',
								'action' => 'download_language_csv',
								'language' => $language_code,
								'_wpnonce' => $nonce,
							], admin_url( 'admin.php' ) );
							?>
							<div style="border: 1px solid #ddd; border-radius: 4px; padding: 15px; background: white;">
								<h5 style="margin: 0 0 10px 0; color: #0073aa;"><?php echo esc_html( strtoupper( $language_code ) ); ?></h5>
								<div style="font-size: 13px; color: #666; margin-bottom: 10px;">
									<div><strong><?php esc_html_e( 'Products:', 'facebook-for-woocommerce' ); ?></strong> <?php echo esc_html( $product_count ); ?></div>
									<div><strong><?php esc_html_e( 'Est. Size:', 'facebook-for-woocommerce' ); ?></strong> <?php echo esc_html( $estimated_size ); ?></div>
								</div>
								<?php if ( $product_count > 0 ) : ?>
									<a href="<?php echo esc_url( $download_url ); ?>" class="button button-secondary" style="width: 100%;">
										<?php esc_html_e( 'Download CSV', 'facebook-for-woocommerce' ); ?>
									</a>
								<?php else : ?>
									<button class="button button-secondary" disabled style="width: 100%;">
										<?php esc_html_e( 'No Translations', 'facebook-for-woocommerce' ); ?>
									</button>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<?php
					$total_languages_with_products = count( array_filter( $statistics, function( $stats ) {
						return ( $stats['translated_products_count'] ?? 0 ) > 0;
					}));
					?>

					<?php if ( $total_languages_with_products > 1 ) : ?>
						<div style="margin-top: 15px; padding: 15px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px;">
							<h5 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Download All Languages', 'facebook-for-woocommerce' ); ?></h5>
							<p style="margin: 0 0 10px 0; font-size: 13px;"><?php printf( esc_html__( 'Download CSV files for all %d languages with translated products as a ZIP archive.', 'facebook-for-woocommerce' ), $total_languages_with_products ); ?></p>
							<?php
							$download_all_url = add_query_arg( [
								'page' => 'wc-facebook',
								'tab' => 'localization_integrations',
								'action' => 'download_all_csvs',
								'_wpnonce' => $nonce,
							], admin_url( 'admin.php' ) );
							?>
							<a href="<?php echo esc_url( $download_all_url ); ?>" class="button button-primary">
								<?php esc_html_e( 'Download All as ZIP', 'facebook-for-woocommerce' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

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

		<!-- Language Feed Sync -->
		<?php if ( $has_plugins && ! empty( $languages ) ) : ?>
			<div class="wc-facebook-language-sync-section" style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 20px;">
				<h3><?php esc_html_e( '🚀 Sync Language Override Feeds', 'facebook-for-woocommerce' ); ?></h3>
				<p><?php esc_html_e( 'Generate and upload language override feeds to Facebook for all available languages. This will create separate feeds for each language with translated product data.', 'facebook-for-woocommerce' ); ?></p>

				<div style="margin: 15px 0;">
					<strong><?php esc_html_e( 'Available Languages:', 'facebook-for-woocommerce' ); ?></strong>
					<span style="color: #666;"><?php echo esc_html( implode( ', ', $languages ) ); ?></span>
				</div>

				<button
					id="wc-facebook-sync-language-feeds"
					class="button button-primary"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'wc_facebook_sync_language_feeds' ) ); ?>"
					style="margin-right: 10px;"
				>
					<?php esc_html_e( 'Sync Language Feeds', 'facebook-for-woocommerce' ); ?>
				</button>

				<div id="language-sync-status" style="margin-top: 15px; display: none; padding: 10px; border-radius: 4px;"></div>

				<div style="margin-top: 15px; padding: 10px; background: #f0f8ff; border-left: 4px solid #0073aa; font-size: 13px;">
					<strong><?php esc_html_e( 'Note:', 'facebook-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'This process will generate CSV files for each language and upload them to Facebook as language override feeds. The process may take a few minutes depending on the number of products and languages.', 'facebook-for-woocommerce' ); ?>
				</div>
			</div>

			<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#wc-facebook-sync-language-feeds').on('click', function(e) {
					e.preventDefault();

					var $button = $(this);
					var $status = $('#language-sync-status');
					var nonce = $button.data('nonce');

					// Update button state
					$button.prop('disabled', true).text('<?php esc_html_e( 'Syncing...', 'facebook-for-woocommerce' ); ?>');
					$status.show().html('<span style="color: #0073aa; font-weight: bold;">⏳ <?php esc_html_e( 'Generating and uploading language feeds...', 'facebook-for-woocommerce' ); ?></span>');
					$status.css({
						'background': '#f0f8ff',
						'border': '1px solid #0073aa',
						'color': '#0073aa'
					});

					$.post(ajaxurl, {
						action: 'wc_facebook_sync_language_feeds',
						nonce: nonce
					}, function(response) {
						if (response.success) {
							$status.html('<span style="color: #46b450; font-weight: bold;">✅ ' + response.data + '</span>');
							$status.css({
								'background': '#d4edda',
								'border': '1px solid #46b450',
								'color': '#46b450'
							});
						} else {
							var errorMessage = response.data || '<?php esc_html_e( 'Unknown error occurred', 'facebook-for-woocommerce' ); ?>';

							// Check if it's a language not supported error
							if (errorMessage.indexOf('Language Feed not supported for override value') !== -1) {
								$status.html('<span style="color: #dc3232; font-weight: bold;">❌ <?php esc_html_e( 'Language Not Supported:', 'facebook-for-woocommerce' ); ?> ' + errorMessage + '</span><br><br>' +
									'<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-top: 10px;">' +
									'<strong><?php esc_html_e( 'Note:', 'facebook-for-woocommerce' ); ?></strong> <?php esc_html_e( 'This language is not supported by Facebook for language override feeds. Only specific languages with Facebook-approved locale codes can be used for language overrides.', 'facebook-for-woocommerce' ); ?>' +
									'</div>');
							} else {
								$status.html('<span style="color: #dc3232; font-weight: bold;">❌ <?php esc_html_e( 'Error:', 'facebook-for-woocommerce' ); ?> ' + errorMessage + '</span>');
							}

							$status.css({
								'background': '#f8d7da',
								'border': '1px solid #dc3232',
								'color': '#dc3232'
							});
						}
					}).fail(function() {
						$status.html('<span style="color: #dc3232; font-weight: bold;">❌ <?php esc_html_e( 'Network error occurred.', 'facebook-for-woocommerce' ); ?></span>');
						$status.css({
							'background': '#f8d7da',
							'border': '1px solid #dc3232',
							'color': '#dc3232'
						});
					}).always(function() {
						$button.prop('disabled', false).text('<?php esc_html_e( 'Sync Language Feeds', 'facebook-for-woocommerce' ); ?>');
					});
				});
			});
			</script>
		<?php endif; ?>

		<?php
	}

	/**
	 * Render product test section
	 *
	 * @since 3.6.0
	 */
	private function render_product_test_section( LanguageFeedData $feed_data ) {
		// Get products from default language using the new method
		$product_ids = $feed_data->get_products_from_default_language( 5 );

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

							$details = $feed_data->get_product_translation_details( $product_id );
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
		// Debug: Log that we reached this method
		error_log( 'download_sample_csv: Method called' );

		try {
			$feed_data = new LanguageFeedData();
			error_log( 'download_sample_csv: LanguageFeedData created' );

			$sample_data = $feed_data->get_sample_csv_data();
			error_log( 'download_sample_csv: Sample data retrieved, count: ' . count( $sample_data ) );

			// Create a sample CSV result structure with basic columns
			$sample_csv_result = [
				'data' => $sample_data,
				'columns' => ['id', 'override', 'title', 'description', 'link'],
				'translated_fields' => ['name', 'description']
			];

			$csv_content = $feed_data->convert_to_csv_string( $sample_csv_result );
			error_log( 'download_sample_csv: CSV content generated, length: ' . strlen( $csv_content ) );

			// Clear any output buffers
			if ( ob_get_level() ) {
				ob_end_clean();
			}

			// Set headers for download
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="sample_localization_feed.csv"' );
			header( 'Content-Length: ' . strlen( $csv_content ) );

			echo $csv_content;
			error_log( 'download_sample_csv: CSV content sent' );
			exit;
		} catch ( Exception $e ) {
			error_log( 'download_sample_csv: Exception - ' . $e->getMessage() );
			wp_die( 'Error generating CSV: ' . $e->getMessage() );
		} catch ( Error $e ) {
			error_log( 'download_sample_csv: Fatal Error - ' . $e->getMessage() );
			wp_die( 'Fatal error generating CSV: ' . $e->getMessage() );
		}
	}

	/**
	 * Download language-specific CSV file
	 *
	 * @since 3.6.0
	 */
	private function download_language_csv( string $language_code ) {
		// Debug: Log that we reached this method
		error_log( "download_language_csv: Method called for language: $language_code" );

		try {
			$feed_data = new LanguageFeedData();
			error_log( 'download_language_csv: LanguageFeedData created' );

			$result = $feed_data->generate_language_csv( $language_code, 1000 ); // Generate up to 1000 products
			error_log( 'download_language_csv: CSV generation completed, success: ' . ($result['success'] ? 'true' : 'false') );

			if ( ! $result['success'] ) {
				error_log( 'download_language_csv: CSV generation failed - ' . ($result['error'] ?? 'Unknown error') );
				wp_die(
					esc_html( $result['error'] ?? 'Failed to generate CSV file.' ),
					esc_html__( 'CSV Generation Error', 'facebook-for-woocommerce' ),
					[ 'back_link' => true ]
				);
			}

			error_log( 'download_language_csv: CSV data length: ' . strlen( $result['data'] ) . ', filename: ' . $result['filename'] );

			// Clear any output buffers
			if ( ob_get_level() ) {
				ob_end_clean();
			}

			// Set headers for download
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . esc_attr( $result['filename'] ) . '"' );
			header( 'Content-Length: ' . strlen( $result['data'] ) );

			echo $result['data'];
			error_log( 'download_language_csv: CSV content sent successfully' );
			exit;
		} catch ( Exception $e ) {
			error_log( 'download_language_csv: Exception - ' . $e->getMessage() );
			wp_die( 'Error generating language CSV: ' . $e->getMessage() );
		} catch ( Error $e ) {
			error_log( 'download_language_csv: Fatal Error - ' . $e->getMessage() );
			wp_die( 'Fatal error generating language CSV: ' . $e->getMessage() );
		}
	}

	/**
	 * Download all language CSV files as a ZIP
	 *
	 * @since 3.6.0
	 */
	private function download_all_language_csvs() {
		$feed_data = new LanguageFeedData();
		$results = $feed_data->generate_all_language_csvs( 1000 ); // Generate up to 1000 products per language

		if ( empty( $results ) ) {
			wp_die(
				esc_html__( 'No language CSV files could be generated.', 'facebook-for-woocommerce' ),
				esc_html__( 'CSV Generation Error', 'facebook-for-woocommerce' ),
				[ 'back_link' => true ]
			);
		}

		// Check if ZIP extension is available
		if ( ! class_exists( 'ZipArchive' ) ) {
			// Fallback: download the first available language CSV
			foreach ( $results as $language_code => $result ) {
				if ( $result['success'] ) {
					$this->download_language_csv( $language_code );
					return;
				}
			}

			wp_die(
				esc_html__( 'ZIP extension not available and no valid CSV files found.', 'facebook-for-woocommerce' ),
				esc_html__( 'CSV Generation Error', 'facebook-for-woocommerce' ),
				[ 'back_link' => true ]
			);
		}

		// Create ZIP file
		$zip = new \ZipArchive();
		$zip_filename = 'facebook_language_feeds_' . date( 'Y-m-d_H-i-s' ) . '.zip';
		$temp_zip_path = sys_get_temp_dir() . '/' . $zip_filename;

		if ( $zip->open( $temp_zip_path, \ZipArchive::CREATE ) !== TRUE ) {
			wp_die(
				esc_html__( 'Failed to create ZIP file.', 'facebook-for-woocommerce' ),
				esc_html__( 'ZIP Creation Error', 'facebook-for-woocommerce' ),
				[ 'back_link' => true ]
			);
		}

		$files_added = 0;
		foreach ( $results as $language_code => $result ) {
			if ( $result['success'] ) {
				$zip->addFromString( $result['filename'], $result['data'] );
				$files_added++;
			}
		}

		$zip->close();

		if ( $files_added === 0 ) {
			unlink( $temp_zip_path );
			wp_die(
				esc_html__( 'No valid CSV files to include in ZIP.', 'facebook-for-woocommerce' ),
				esc_html__( 'CSV Generation Error', 'facebook-for-woocommerce' ),
				[ 'back_link' => true ]
			);
		}

		// Set headers for download
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $zip_filename ) . '"' );
		header( 'Content-Length: ' . filesize( $temp_zip_path ) );

		readfile( $temp_zip_path );
		unlink( $temp_zip_path ); // Clean up temp file
		exit;
	}
}
