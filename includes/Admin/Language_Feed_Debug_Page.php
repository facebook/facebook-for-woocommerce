<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Admin;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\Framework\Helper;
use WooCommerce\Facebook\Framework\Logger;

/**
 * Language Feed Debug Page.
 *
 * Provides a debug interface for monitoring language override feed
 * scheduling, generation, and upload status.
 *
 * @since 3.6.0
 */
class Language_Feed_Debug_Page {

	/**
	 * Page slug for WordPress admin
	 */
	const PAGE_SLUG = 'facebook-language-feed-debug';

	/**
	 * Constructor
	 *
	 * @since 3.6.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add admin menu item
	 *
	 * @since 3.6.0
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Facebook Language Feed Debug', 'facebook-for-woocommerce' ),
			__( 'FB Language Debug', 'facebook-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle page actions
	 *
	 * @since 3.6.0
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$action = Helper::get_requested_value( 'action' );
		$nonce = Helper::get_requested_value( '_wpnonce' );

		if ( ! wp_verify_nonce( $nonce, 'language_feed_debug_action' ) ) {
			return;
		}

		switch ( $action ) {
			case 'reschedule_feeds':
				$this->reschedule_language_feeds();
				break;
			case 'unschedule_feeds':
				$this->unschedule_language_feeds();
				break;
			case 'trigger_generation':
				$this->trigger_feed_generation();
				break;
			case 'clear_logs':
				$this->clear_debug_logs();
				break;
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @since 3.6.0
	 */
	public function enqueue_scripts( $hook ) {
		if ( $hook !== 'woocommerce_page_' . self::PAGE_SLUG ) {
			return;
		}

		wp_enqueue_style(
			'facebook-language-feed-debug',
			facebook_for_woocommerce()->get_plugin_url() . '/assets/css/admin/language-feed-debug.css',
			array(),
			facebook_for_woocommerce()->get_version()
		);

		wp_enqueue_script(
			'facebook-language-feed-debug',
			facebook_for_woocommerce()->get_plugin_url() . '/assets/js/admin/language-feed-debug.js',
			array( 'jquery' ),
			facebook_for_woocommerce()->get_version(),
			true
		);

		wp_localize_script(
			'facebook-language-feed-debug',
			'facebook_language_feed_debug',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'language_feed_debug_ajax' ),
			)
		);
	}

	/**
	 * Render the debug page
	 *
	 * @since 3.6.0
	 */
	public function render_page() {
		$language_feed = facebook_for_woocommerce()->get_language_override_feed();

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Facebook Language Feed Debug', 'facebook-for-woocommerce' ); ?></h1>

			<?php $this->render_status_messages(); ?>

			<div class="facebook-debug-container">
				<div class="facebook-debug-section">
					<h2><?php echo esc_html__( 'System Status', 'facebook-for-woocommerce' ); ?></h2>
					<?php $this->render_system_status(); ?>
				</div>

				<div class="facebook-debug-section">
					<h2><?php echo esc_html__( 'Scheduled Actions', 'facebook-for-woocommerce' ); ?></h2>
					<?php $this->render_scheduled_actions(); ?>
				</div>

				<div class="facebook-debug-section">
					<h2><?php echo esc_html__( 'Recent Feed Generation Activity', 'facebook-for-woocommerce' ); ?></h2>
					<?php $this->render_recent_activity(); ?>
				</div>

				<div class="facebook-debug-section">
					<h2><?php echo esc_html__( 'Feed Files Status', 'facebook-for-woocommerce' ); ?></h2>
					<?php $this->render_feed_files_status(); ?>
				</div>

				<div class="facebook-debug-section">
					<h2><?php echo esc_html__( 'Debug Logs', 'facebook-for-woocommerce' ); ?></h2>
					<?php $this->render_debug_logs(); ?>
				</div>

				<div class="facebook-debug-section">
					<h2><?php echo esc_html__( 'Actions', 'facebook-for-woocommerce' ); ?></h2>
					<?php $this->render_actions(); ?>
				</div>
			</div>
		</div>

		<style>
		.facebook-debug-container {
			max-width: 1200px;
		}
		.facebook-debug-section {
			background: #fff;
			border: 1px solid #ccd0d4;
			border-radius: 4px;
			margin-bottom: 20px;
			padding: 20px;
		}
		.facebook-debug-section h2 {
			margin-top: 0;
			border-bottom: 1px solid #eee;
			padding-bottom: 10px;
		}
		.status-table, .scheduled-actions-table, .activity-table, .files-table, .logs-table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 15px;
		}
		.status-table th, .status-table td,
		.scheduled-actions-table th, .scheduled-actions-table td,
		.activity-table th, .activity-table td,
		.files-table th, .files-table td,
		.logs-table th, .logs-table td {
			text-align: left;
			padding: 8px 12px;
			border-bottom: 1px solid #eee;
		}
		.status-table th, .scheduled-actions-table th, .activity-table th, .files-table th, .logs-table th {
			background-color: #f9f9f9;
			font-weight: 600;
		}
		.status-good { color: #007cba; }
		.status-warning { color: #f56e28; }
		.status-error { color: #d63638; }
		.debug-actions {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
			margin-top: 15px;
		}
		.log-entry {
			background: #f8f9fa;
			border-left: 4px solid #007cba;
			padding: 10px;
			margin-bottom: 10px;
			font-family: monospace;
			font-size: 12px;
		}
		.log-entry.error {
			border-left-color: #d63638;
		}
		.log-entry.warning {
			border-left-color: #f56e28;
		}
		.auto-refresh {
			float: right;
			margin-bottom: 10px;
		}
		</style>
		<?php
	}

	/**
	 * Render status messages
	 *
	 * @since 3.6.0
	 */
	private function render_status_messages() {
		$action = Helper::get_requested_value( 'action' );
		$status = Helper::get_requested_value( 'status' );

		if ( $action && $status ) {
			$class = $status === 'success' ? 'notice-success' : 'notice-error';
			$message = '';

			switch ( $action ) {
				case 'reschedule_feeds':
					$message = $status === 'success'
						? __( 'Language feeds rescheduled successfully.', 'facebook-for-woocommerce' )
						: __( 'Failed to reschedule language feeds.', 'facebook-for-woocommerce' );
					break;
				case 'unschedule_feeds':
					$message = $status === 'success'
						? __( 'Language feeds unscheduled successfully.', 'facebook-for-woocommerce' )
						: __( 'Failed to unschedule language feeds.', 'facebook-for-woocommerce' );
					break;
				case 'trigger_generation':
					$message = $status === 'success'
						? __( 'Feed generation triggered successfully.', 'facebook-for-woocommerce' )
						: __( 'Failed to trigger feed generation.', 'facebook-for-woocommerce' );
					break;
				case 'clear_logs':
					$message = $status === 'success'
						? __( 'Debug logs cleared successfully.', 'facebook-for-woocommerce' )
						: __( 'Failed to clear debug logs.', 'facebook-for-woocommerce' );
					break;
			}

			if ( $message ) {
				printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
			}
		}
	}

	/**
	 * Render system status section
	 *
	 * @since 3.6.0
	 */
	private function render_system_status() {
		$integration = facebook_for_woocommerce()->get_integration();
		$connection_handler = facebook_for_woocommerce()->get_connection_handler();
		$language_feed = facebook_for_woocommerce()->get_language_override_feed();

		$status_items = array();

		// Facebook connection status
		$is_connected = $connection_handler && $connection_handler->is_connected();
		$status_items[] = array(
			'label' => __( 'Facebook Connection', 'facebook-for-woocommerce' ),
			'value' => $is_connected ? __( 'Connected', 'facebook-for-woocommerce' ) : __( 'Not Connected', 'facebook-for-woocommerce' ),
			'status' => $is_connected ? 'good' : 'error'
		);

		// Integration configuration
		$is_configured = $integration && $integration->is_configured();
		$status_items[] = array(
			'label' => __( 'Integration Configuration', 'facebook-for-woocommerce' ),
			'value' => $is_configured ? __( 'Configured', 'facebook-for-woocommerce' ) : __( 'Not Configured', 'facebook-for-woocommerce' ),
			'status' => $is_configured ? 'good' : 'error'
		);

		// Product sync enabled
		$sync_enabled = $integration && $integration->is_product_sync_enabled();
		$status_items[] = array(
			'label' => __( 'Product Sync', 'facebook-for-woocommerce' ),
			'value' => $sync_enabled ? __( 'Enabled', 'facebook-for-woocommerce' ) : __( 'Disabled', 'facebook-for-woocommerce' ),
			'status' => $sync_enabled ? 'good' : 'warning'
		);

		// Language feed generation enabled
		$language_feeds_enabled = $integration && $integration->is_language_override_feed_generation_enabled();
		$status_items[] = array(
			'label' => __( 'Language Override Feeds', 'facebook-for-woocommerce' ),
			'value' => $language_feeds_enabled ? __( 'Enabled', 'facebook-for-woocommerce' ) : __( 'Disabled', 'facebook-for-woocommerce' ),
			'status' => $language_feeds_enabled ? 'good' : 'warning'
		);

		// Localization plugin status
		if ( $language_feed && method_exists( $language_feed->get_feed_handler(), 'language_feed_data' ) ) {
			$language_feed_data = $language_feed->get_feed_handler()->language_feed_data;
			$has_localization = $language_feed_data && $language_feed_data->has_active_localization_plugin();
			$active_integration = $has_localization ? $language_feed_data->get_active_integration_name() : 'None';
		} else {
			$has_localization = false;
			$active_integration = 'None';
		}

		$status_items[] = array(
			'label' => __( 'Localization Plugin', 'facebook-for-woocommerce' ),
			'value' => $active_integration,
			'status' => $has_localization ? 'good' : 'warning'
		);

		// Commerce IDs
		$cpi_id = $connection_handler ? $connection_handler->get_commerce_partner_integration_id() : '';
		$status_items[] = array(
			'label' => __( 'Commerce Partner Integration ID', 'facebook-for-woocommerce' ),
			'value' => !empty( $cpi_id ) ? substr( $cpi_id, 0, 10 ) . '...' : __( 'Not Set', 'facebook-for-woocommerce' ),
			'status' => !empty( $cpi_id ) ? 'good' : 'error'
		);

		?>
		<table class="status-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Component', 'facebook-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'facebook-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $status_items as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['label'] ); ?></td>
						<td class="status-<?php echo esc_attr( $item['status'] ); ?>">
							<?php echo esc_html( $item['value'] ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render scheduled actions section
	 *
	 * @since 3.6.0
	 */
	private function render_scheduled_actions() {
		global $wpdb;

		// Get scheduled actions for language feeds
		$hook_pattern = 'wc_facebook_regenerate_feed_language_override';

		$scheduled_actions = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}actionscheduler_actions
			WHERE hook = %s
			AND status IN ('pending', 'in-progress', 'complete', 'failed')
			ORDER BY scheduled_date_gmt DESC
			LIMIT 10",
			$hook_pattern
		) );

		?>
		<div class="auto-refresh">
			<button type="button" onclick="location.reload()" class="button">
				<?php echo esc_html__( 'Refresh', 'facebook-for-woocommerce' ); ?>
			</button>
		</div>

		<?php if ( empty( $scheduled_actions ) ): ?>
			<p><?php echo esc_html__( 'No scheduled language feed actions found.', 'facebook-for-woocommerce' ); ?></p>
		<?php else: ?>
			<table class="scheduled-actions-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Status', 'facebook-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Scheduled', 'facebook-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Last Attempt', 'facebook-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Attempts', 'facebook-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Next Run', 'facebook-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $scheduled_actions as $action ): ?>
						<tr>
							<td class="status-<?php echo $action->status === 'complete' ? 'good' : ($action->status === 'failed' ? 'error' : 'warning'); ?>">
								<?php echo esc_html( ucfirst( $action->status ) ); ?>
							</td>
							<td><?php echo esc_html( $action->scheduled_date_gmt ); ?></td>
							<td><?php echo esc_html( $action->last_attempt_gmt ?: 'Never' ); ?></td>
							<td><?php echo esc_html( $action->attempts ); ?></td>
							<td>
								<?php
								if ( $action->status === 'pending' ) {
									$next_run = as_next_scheduled_action( $hook_pattern );
									echo esc_html( $next_run ? date( 'Y-m-d H:i:s', $next_run ) : 'Not scheduled' );
								} else {
									echo '—';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render recent activity section
	 *
	 * @since 3.6.0
	 */
	private function render_recent_activity() {
		global $wpdb;

		// Get recent completed/failed actions
		$recent_actions = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, al.log_date_gmt, al.message
			FROM {$wpdb->prefix}actionscheduler_actions a
			LEFT JOIN {$wpdb->prefix}actionscheduler_logs al ON a.action_id = al.action_id
			WHERE a.hook = %s
			AND a.status IN ('complete', 'failed')
			ORDER BY a.last_attempt_gmt DESC
			LIMIT 20",
			'wc_facebook_regenerate_feed_language_override'
		) );

		?>
		<?php if ( empty( $recent_actions ) ): ?>
			<p><?php echo esc_html__( 'No recent language feed activity found.', 'facebook-for-woocommerce' ); ?></p>
		<?php else: ?>
			<table class="activity-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Date', 'facebook-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Status', 'facebook-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Duration', 'facebook-for-woocommerce' ); ?></th>
						<th><?php echo esc_html__( 'Message', 'facebook-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_actions as $action ): ?>
						<tr>
							<td><?php echo esc_html( $action->last_attempt_gmt ); ?></td>
							<td class="status-<?php echo $action->status === 'complete' ? 'good' : 'error'; ?>">
								<?php echo esc_html( ucfirst( $action->status ) ); ?>
							</td>
							<td>
								<?php
								if ( $action->last_attempt_gmt && $action->scheduled_date_gmt ) {
									$duration = strtotime( $action->last_attempt_gmt ) - strtotime( $action->scheduled_date_gmt );
									echo esc_html( $duration > 0 ? $duration . 's' : '—' );
								} else {
									echo '—';
								}
								?>
							</td>
							<td><?php echo esc_html( $action->message ?: 'No message' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render feed files status section
	 *
	 * @since 3.6.0
	 */
	private function render_feed_files_status() {
		$language_feed = facebook_for_woocommerce()->get_language_override_feed();

		if ( ! $language_feed ) {
			echo '<p>' . esc_html__( 'Language override feed not available.', 'facebook-for-woocommerce' ) . '</p>';
			return;
		}

		$feed_handler = $language_feed->get_feed_handler();
		if ( ! $feed_handler || ! property_exists( $feed_handler, 'language_feed_data' ) ) {
			echo '<p>' . esc_html__( 'Language feed data not available.', 'facebook-for-woocommerce' ) . '</p>';
			return;
		}

		$language_feed_data = $feed_handler->language_feed_data;
		if ( ! $language_feed_data->has_active_localization_plugin() ) {
			echo '<p>' . esc_html__( 'No active localization plugin found.', 'facebook-for-woocommerce' ) . '</p>';
			return;
		}

		$languages = $language_feed_data->get_available_languages();
		$upload_dir = wp_upload_dir();
		$feed_dir = $upload_dir['basedir'] . '/facebook_for_woocommerce/language_feeds/';

		?>
		<table class="files-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Language', 'facebook-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'File Status', 'facebook-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'File Size', 'facebook-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Last Modified', 'facebook-for-woocommerce' ); ?></th>
					<th><?php echo esc_html__( 'Feed URL', 'facebook-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $languages as $language_code ): ?>
					<?php
					// Create a temporary writer to get file path
					try {
						$header_row = $language_feed_data->get_csv_header_for_columns(['id', 'override']);
						$writer = new \WooCommerce\Facebook\Feed\Localization\LanguageOverrideFeedWriter( $language_code, $header_row );
						$file_path = $writer->get_file_path( $language_code );
						$file_exists = file_exists( $file_path );
						$file_size = $file_exists ? size_format( filesize( $file_path ) ) : '—';
						$file_modified = $file_exists ? date( 'Y-m-d H:i:s', filemtime( $file_path ) ) : '—';
						$feed_url = $language_feed->get_language_feed_url( $language_code );
					} catch ( Exception $e ) {
						$file_exists = false;
						$file_size = 'Error';
						$file_modified = 'Error';
						$feed_url = 'Error';
					}
					?>
					<tr>
						<td><?php echo esc_html( $language_code ); ?></td>
						<td class="status-<?php echo $file_exists ? 'good' : 'error'; ?>">
							<?php echo $file_exists ? esc_html__( 'Exists', 'facebook-for-woocommerce' ) : esc_html__( 'Missing', 'facebook-for-woocommerce' ); ?>
						</td>
						<td><?php echo esc_html( $file_size ); ?></td>
						<td><?php echo esc_html( $file_modified ); ?></td>
						<td>
							<?php if ( $file_exists && $feed_url !== 'Error' ): ?>
								<a href="<?php echo esc_url( $feed_url ); ?>" target="_blank">
									<?php echo esc_html__( 'View Feed', 'facebook-for-woocommerce' ); ?>
								</a>
							<?php else: ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render debug logs section
	 *
	 * @since 3.6.0
	 */
	private function render_debug_logs() {
		$log_entries = $this->get_recent_log_entries();

		?>
		<div style="max-height: 400px; overflow-y: auto;">
			<?php if ( empty( $log_entries ) ): ?>
				<p><?php echo esc_html__( 'No recent debug logs found.', 'facebook-for-woocommerce' ); ?></p>
			<?php else: ?>
				<?php foreach ( $log_entries as $entry ): ?>
					<div class="log-entry <?php echo esc_attr( $entry['level'] ); ?>">
						<strong><?php echo esc_html( $entry['timestamp'] ); ?></strong>
						[<?php echo esc_html( strtoupper( $entry['level'] ) ); ?>]
						<?php echo esc_html( $entry['message'] ); ?>
						<?php if ( ! empty( $entry['context'] ) ): ?>
							<pre style="margin-top: 5px; font-size: 11px;"><?php echo esc_html( print_r( $entry['context'], true ) ); ?></pre>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render actions section
	 *
	 * @since 3.6.0
	 */
	private function render_actions() {
		$nonce = wp_create_nonce( 'language_feed_debug_action' );
		?>
		<div class="debug-actions">
			<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'reschedule_feeds', '_wpnonce' => $nonce ) ) ); ?>"
			   class="button button-primary">
				<?php echo esc_html__( 'Reschedule Feeds', 'facebook-for-woocommerce' ); ?>
			</a>

			<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'unschedule_feeds', '_wpnonce' => $nonce ) ) ); ?>"
			   class="button button-secondary">
				<?php echo esc_html__( 'Unschedule Feeds', 'facebook-for-woocommerce' ); ?>
			</a>

			<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'trigger_generation', '_wpnonce' => $nonce ) ) ); ?>"
			   class="button button-secondary">
				<?php echo esc_html__( 'Trigger Generation Now', 'facebook-for-woocommerce' ); ?>
			</a>

			<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'clear_logs', '_wpnonce' => $nonce ) ) ); ?>"
			   class="button button-secondary">
				<?php echo esc_html__( 'Clear Debug Logs', 'facebook-for-woocommerce' ); ?>
			</a>

			<a href="<?php echo esc_url( admin_url( 'tools.php?page=action-scheduler&s=wc_facebook_regenerate_feed' ) ); ?>"
			   class="button button-secondary" target="_blank">
				<?php echo esc_html__( 'View All Scheduled Actions', 'facebook-for-woocommerce' ); ?>
			</a>
		</div>

		<p class="description">
			<?php echo esc_html__( 'Use these actions to manage language feed scheduling and troubleshoot issues. Actions are logged and can be monitored above.', 'facebook-for-woocommerce' ); ?>
		</p>
		<?php
	}

	/**
	 * Reschedule language feeds
	 *
	 * @since 3.6.0
	 */
	private function reschedule_language_feeds() {
		try {
			$language_feed = facebook_for_woocommerce()->get_language_override_feed();

			if ( ! $language_feed ) {
				throw new \Exception( 'Language override feed not available' );
			}

			// First unschedule any existing actions
			$language_feed->unschedule_feed_generation();

			// Then schedule new ones
			$language_feed->schedule_feed_generation();

			Logger::log(
				'Language
