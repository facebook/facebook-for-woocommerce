<?php
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * Template Name: Checkout Template
 *
 * @package FacebookCommerce
 */

namespace WooCommerce\Facebook\Templates;

defined( 'ABSPATH' ) || exit;

get_header(); ?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<?php
		// Display the WooCommerce cart
		echo do_shortcode( '[woocommerce_checkout]' );
		?>
	</main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>
