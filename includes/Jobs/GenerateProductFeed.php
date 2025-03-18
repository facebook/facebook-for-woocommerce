<?php
namespace WooCommerce\Facebook\Jobs;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Utilities\BatchQueryOffset;
use Exception;
use WC_Facebookcommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Class GenerateProductFeed
 *
 * @since 2.5.0
 */
class GenerateProductFeed extends AbstractChainedJob {

	use BatchQueryOffset;
	use LoggingTrait;

	/**
	 * Called before starting the job.
	 */
	protected function handle_start() {
		$feed_handler = new \WC_Facebook_Product_Feed();
		$feed_handler->create_files_to_protect_product_feed_directory();
		$feed_handler->prepare_temporary_feed_file();
		facebook_for_woocommerce()->get_tracker()->reset_batch_generation_time();
	}

	/**
	 * Called after the finishing the job.
	 */
	protected function handle_end() {
		$feed_handler = new \WC_Facebook_Product_Feed();
		$feed_handler->rename_temporary_feed_file_to_final_feed_file();
		facebook_for_woocommerce()->get_tracker()->save_batch_generation_time();

		do_action( 'wc_facebook_feed_generation_completed' );
	}

	/**
	 * Get a set of items for the batch.
	 *
	 * NOTE: when using an OFFSET based query to retrieve items it's recommended to order by the item ID while
	 * ASCENDING. This is so that any newly added items will not disrupt the query offset.
	 *
	 * @param int   $batch_number The batch number increments for each new batch in the job cycle.
	 * @param array $args         The args for the job.
	 *
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		global $wpdb;

		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post.ID
				FROM {$wpdb->posts} as post
				LEFT JOIN {$wpdb->posts} as parent ON post.post_parent = parent.ID
				WHERE
					( post.post_type = 'product_variation' AND parent.post_status = 'publish' )
				OR
					( post.post_type = 'product' AND post.post_status = 'publish' )
				ORDER BY post.ID ASC
				LIMIT %d OFFSET %d",
				$this->get_batch_size(),
				$this->get_query_offset( $batch_number )
			)
		);

		return array_map( 'intval', $product_ids );
	}

	/**
	 * Processes a batch of items.
	 *
	 * @since 1.1.0
	 *
	 * @param array $items The items of the current batch.
	 * @param array $args  The args for the job.
	 *
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	protected function process_items( array $items, array $args ) {
		// Grab start time.
		$start_time = microtime( true );

		/*
		 * Pre-fetch full product objects.
		 * Variable products will be filtered out here since we don't need them for the feed. It's important to not
		 * filter out variable products in ::get_items_for_batch() because if a batch only contains variable products
		 * the job will end prematurely thinking it has nothing more to process.
		 */
		$products       = wc_get_products(
			array(
				'type'    => array( 'simple', 'variation' ),
				'include' => $items,
				'orderby' => 'none',
				'limit'   => $this->get_batch_size(),
			)
		);
		$feed_handler   = new \WC_Facebook_Product_Feed();
		$temp_feed_file = fopen( $feed_handler->get_temp_file_path(), 'a' );
		$feed_handler->write_products_feed_to_temp_file( $products, $temp_feed_file );
		if ( is_resource( $temp_feed_file ) ) {
			fclose( $temp_feed_file );
		}
		facebook_for_woocommerce()->get_tracker()->increment_batch_generation_time( microtime( true ) - $start_time );
	}

	/**
	 * Empty function to satisfy parent class requirements.
	 * We don't use it because we are processing the whole batch at once in process_items.
	 *
	 * @param mixed $item The item to process.
	 * @param array $args The args for the job.
	 */
	protected function process_item( $item, array $args ) {}

	/**
	 * Get the name/slug of the job.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'generate_feed';
	}

	/**
	 * Get the name/slug of the plugin that owns the job.
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return WC_Facebookcommerce::PLUGIN_ID;
	}

	/**
	 * Get the job's batch size.
	 *
	 * @return int
	 */
	protected function get_batch_size(): int {
		return 15;
	}
}
