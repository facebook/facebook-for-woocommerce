<?php
/**
 * Test script for Facebook product data generation
 *
 * This script will load a product and run the prepare_product method
 * to test our attribute mapper without sending data to Facebook.
 */

// Ensure WP is loaded
require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php';

// Make sure we have WooCommerce
if ( ! function_exists( 'WC' ) ) {
	die( 'WooCommerce is not active' );
}

// Check if we're logged in as admin
if ( ! current_user_can( 'manage_options' ) ) {
	die( 'You need to be logged in as an administrator to run this test' );
}

// Product ID to test (default to first product if not specified)
$product_id = null;
if ( isset( $_GET['product_id'], $_GET['_wpnonce'] ) &&
	wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'test_product_sync' ) ) {
	$product_id = intval( $_GET['product_id'] );
} else {
	$product_id = get_option( 'woocommerce_product_to_test', null );
}

if ( ! $product_id ) {
	// Get first product
	$products = wc_get_products( array( 'limit' => 1 ) );
	if ( ! empty( $products ) ) {
		$product_id = $products[0]->get_id();
		update_option( 'woocommerce_product_to_test', $product_id );
	} else {
		die( 'No products found in your store.' );
	}
}

// Output the heading with proper escaping
echo wp_kses(
	sprintf(
		'<h1>Testing Facebook Product Sync for Product #%s</h1>',
		esc_html( $product_id )
	),
	array(
		'h1' => array(),
	)
);

// Load the product
$product = wc_get_product( $product_id );
if ( ! $product ) {
	die( 'Product #' . esc_html( $product_id ) . ' not found' );
}

echo '<p>Product: ' . esc_html( $product->get_name() ) . '</p>';

// Log product attributes information
echo '<h2>Product Attributes</h2>';
$attributes = $product->get_attributes();
if ( empty( $attributes ) ) {
	echo '<p>This product has no attributes. Try a different product that has attributes like color, size, etc.</p>';
} else {
	echo '<ul>';
	foreach ( $attributes as $attribute_name => $attribute ) {
		echo '<li><strong>' . esc_html( $attribute_name ) . '</strong>: ';
		$values = array();

		if ( $attribute->is_taxonomy() ) {
			$attribute_values = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'names' ) );
			if ( ! empty( $attribute_values ) ) {
				$values = $attribute_values;
			}
		} else {
			$values = $attribute->get_options();
		}

		echo esc_html( implode( ', ', $values ) ) . '</li>';
	}
	echo '</ul>';
}

// Create Facebook product
$fb_product = new WC_Facebook_Product( $product->get_id() );

// Initialize WP_Filesystem
global $wp_filesystem;
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

// Log path to help with debugging
$log_file = WP_CONTENT_DIR . '/uploads/fb-product-debug.log';
echo '<p>Debug log will be written to: ' . esc_html( $log_file ) . '</p>';

// Clear previous log
$wp_filesystem->put_contents( $log_file, "=== START FACEBOOK PRODUCT TEST ===\n\n" );

// Test the attribute mapper directly
if ( class_exists( '\\WooCommerce\\Facebook\\ProductAttributeMapper' ) ) {
	echo '<h2>Testing ProductAttributeMapper Directly</h2>';

	// Write test header to log
	$wp_filesystem->put_contents( $log_file, "DIRECT ATTRIBUTE MAPPER TEST\n", FILE_APPEND );

	// Get mapped attributes
	$mapped_attributes = \WooCommerce\Facebook\ProductAttributeMapper::get_mapped_attributes( $product );
	$wp_filesystem->put_contents(
		$log_file,
		'Mapped attributes: ' . wp_json_encode( $mapped_attributes, JSON_PRETTY_PRINT ) . "\n\n",
		FILE_APPEND
	);

	if ( empty( $mapped_attributes ) ) {
		echo '<p>No attributes mapped. Check if you have defined attribute mappings in Facebook for WooCommerce settings.</p>';
	} else {
		echo '<p>' . count( $mapped_attributes ) . ' attributes mapped.</p>';
	}

	// Get unmapped attributes
	$unmapped_attributes = \WooCommerce\Facebook\ProductAttributeMapper::get_unmapped_attributes( $product );
	$wp_filesystem->put_contents(
		$log_file,
		'Unmapped attributes: ' . wp_json_encode( $unmapped_attributes, JSON_PRETTY_PRINT ) . "\n\n",
		FILE_APPEND
	);

	if ( empty( $unmapped_attributes ) ) {
		echo '<p>No unmapped attributes found.</p>';
	} else {
		echo '<p>' . count( $unmapped_attributes ) . ' unmapped attributes found.</p>';
	}
}

// Run prepare_product for different types to test attributes
$retailer_id = \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product );

echo '<h2>Testing normal product preparation</h2>';
$normal_data = $fb_product->prepare_product( $retailer_id, \WC_Facebook_Product::PRODUCT_PREP_TYPE_NORMAL );
// Display the full normal data
echo '<details>';
echo '<summary>View full normal data</summary>';
echo '<pre>' . esc_html( wp_json_encode( $normal_data, JSON_PRETTY_PRINT ) ) . '</pre>';
echo '</details>';

echo '<h2>Testing items batch product preparation</h2>';
$batch_data = $fb_product->prepare_product( $retailer_id, \WC_Facebook_Product::PRODUCT_PREP_TYPE_ITEMS_BATCH );
// Display the full batch data
echo '<details>';
echo '<summary>View full items_batch data (API CALL FORMAT)</summary>';
echo '<pre>' . esc_html( wp_json_encode( $batch_data, JSON_PRETTY_PRINT ) ) . '</pre>';
echo '</details>';

echo '<h2>Testing feed product preparation</h2>';
$feed_data = $fb_product->prepare_product( $retailer_id, \WC_Facebook_Product::PRODUCT_PREP_TYPE_FEED );
// Display the full feed data
echo '<details>';
echo '<summary>View full feed data</summary>';
echo '<pre>' . esc_html( wp_json_encode( $feed_data, JSON_PRETTY_PRINT ) ) . '</pre>';
echo '</details>';

// Let's directly call the apply_enhanced_catalog_fields_from_attributes method using reflection
echo '<h2>Directly testing attribute mapping</h2>';
$wp_filesystem->put_contents( $log_file, "\nDIRECT ATTRIBUTE MAPPING TEST\n", FILE_APPEND );

try {
	// Use reflection to access the private method
	$reflection_class = new ReflectionClass( $fb_product );
	$method           = $reflection_class->getMethod( 'apply_enhanced_catalog_fields_from_attributes' );
	$method->setAccessible( true );

	// Call the method directly with a fake Google category
	$fake_google_category = '1234'; // Just a placeholder to make sure the method runs
	$test_data            = array(
		'name' => $product->get_name(),
		'id'   => $product->get_id(),
	);
	$result               = $method->invokeArgs( $fb_product, array( $test_data, $fake_google_category ) );

	$wp_filesystem->put_contents( $log_file, "Direct method call completed successfully\n", FILE_APPEND );
	if ( $result !== $test_data ) {
		$wp_filesystem->put_contents( $log_file, "Result differs from input data - mapping worked!\n", FILE_APPEND );
		echo '<p>Attribute mapping test successful!</p>';
	} else {
		$wp_filesystem->put_contents( $log_file, "Result same as input - no mapping occurred\n", FILE_APPEND );
		echo '<p>No attribute mapping occurred in direct test.</p>';
	}
} catch ( Exception $e ) {
	$wp_filesystem->put_contents( $log_file, 'ERROR in direct method call: ' . $e->getMessage() . "\n", FILE_APPEND );
	echo '<p>Error testing attribute mapping: ' . esc_html( $e->getMessage() ) . '</p>';
}

// Write log footer
$wp_filesystem->put_contents( $log_file, "=== END FACEBOOK PRODUCT TEST ===\n", FILE_APPEND );

echo '<p>Test complete! Check the debug log for detailed information.</p>';
echo '<p>Log File: <code>' . esc_html( $log_file ) . '</code></p>';
echo '<p><a href="' . esc_url(
	add_query_arg(
		array(
			'product_id' => $product_id + 1,
			'_wpnonce'   => wp_create_nonce( 'test_product_sync' ),
		)
	)
) . '">Test next product</a></p>';

// Add a button to view the log file content
echo '<h2>Log File Content</h2>';
echo '<textarea style="width: 100%; height: 300px;">';
echo esc_textarea( $wp_filesystem->get_contents( $log_file ) );
echo '</textarea>';
