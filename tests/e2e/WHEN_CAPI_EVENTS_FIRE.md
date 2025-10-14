# 📋 When CAPI Events Fire - Complete Guide

## Overview

I found the code! It's in `facebook-commerce-events-tracker.php`. This file registers ALL the WordPress hooks that trigger CAPI events.

## The add_hooks() Method - Line 103

This is where EVERYTHING is registered:

```php
private function add_hooks() {
    // inject Pixel
    add_action( 'wp_head', array( $this, 'inject_base_pixel' ) );
    add_action( 'wp_footer', array( $this, 'inject_base_pixel_noscript' ) );

    // ViewContent for individual products
    add_action( 'woocommerce_after_single_product', array( $this, 'inject_view_content_event' ) );
    add_action( 'woocommerce_after_single_product', array( $this, 'maybe_inject_search