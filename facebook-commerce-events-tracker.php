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

use WooCommerce\Facebook\Events\Event;
use WooCommerce\Facebook\Framework\Api\Exception as ApiException;
use WooCommerce\Facebook\Framework\Helper;

if ( ! class_exists( 'WC_Facebookcommerce_EventsTracker' ) ) :

	if ( ! class_exists( 'WC_Facebookcommerce_Utils' ) ) {
		include_once 'includes/fbutils.php';
	}

	if ( ! class_exists( 'WC_Facebookcommerce_Pixel' ) ) {
		include_once 'facebook-commerce-pixel-event.php';
	}

	class WC_Facebookcommerce_EventsTracker {

		/** @var \WC_Facebookcommerce_Pixel instance */
		private $pixel;

		/** @var string name of the session variable used to store search event data */
		private $search_event_data_session_variable = 'wc_facebook_search_event_data';

		/** @var Event search event instance */
		private $search_event;

		/** @var array with events tracked */
		private $tracked_events;

		/** @var array array with epnding events */
		private $pending_events = [];

		/** @var AAMSettings aam settings instance, used to filter advanced matching fields*/
		private $aam_settings;

		/** @var bool whether the pixel should be enabled */
		private $is_pixel_enabled;


		/**
		 * Events tracker constructor.
		 *
		 * @param $user_info
		 * @param $aam_settings
		 */
		public function __construct( $user_info, $aam_settings ) {

			if ( ! $this->is_pixel_enabled() ) {
				return;
			}

			$this->pixel          = new \WC_Facebookcommerce_Pixel( $user_info );
			$this->aam_settings   = $aam_settings;
			$this->tracked_events = array();

			$this->add_hooks();
		}


		/**
		 * Determines whether the Pixel should be enabled.
		 *
		 * @since 2.2.0
		 *
		 * @return bool
		 */
		private function is_pixel_enabled() {

			if ( null === $this->is_pixel_enabled ) {

				/**
				 * Filters whether the Pixel should be enabled.
				 *
				 * @param bool $enabled default true
				 */
				$this->is_pixel_enabled = (bool) apply_filters( 'facebook_for_woocommerce_integration_pixel_enabled', true );
			}

			return $this->is_pixel_enabled;
		}


		/**
		 * Add events tracker hooks.
		 *
		 * @since 2.2.0
		 */
		private function add_hooks() {

			// inject Pixel
			add_action( 'wp_head', array( $this, 'inject_base_pixel' ) );
			add_action( 'wp_footer', array( $this, 'inject_base_pixel_noscript' ) );

			// ViewContent for individual products
			add_action( 'woocommerce_after_single_product', array( $this, 'inject_view_content_event' ) );
			add_action( 'woocommerce_after_single_product', array( $this, 'maybe_inject_search_event' ) );

			// ViewCategory events
			add_action( 'woocommerce_after_shop_loop', array( $this, 'inject_view_category_event' ) );

			// Search events
			add_action( 'pre_get_posts', array( $this, 'inject_search_event' ) );
			add_filter( 'woocommerce_redirect_single_search_result', array( $this, 'maybe_add_product_search_event_to_session' ) );

			// AddToCart events
			add_action( 'woocommerce_add_to_cart', array( $this, 'inject_add_to_cart_event' ), 40, 4 );
			// AddToCart while AJAX is enabled
			add_action( 'woocommerce_ajax_added_to_cart', array( $this, 'add_filter_for_add_to_cart_fragments' ) );
			// AddToCart while using redirect to cart page
			if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add', 'no' ) ) {
				add_action( 'wp_head', array( WC_Facebookcommerce_Utils::class, 'print_deferred_events' ) );
				add_action( 'shutdown', array( WC_Facebookcommerce_Utils::class, 'save_deferred_events' ) );
			}

			// InitiateCheckout events
			add_action( 'woocommerce_after_checkout_form', array( $this, 'inject_initiate_checkout_event' ) );

			// InitiateCheckout events for checkout block.
			add_action( 'woocommerce_blocks_checkout_enqueue_data', array( $this, 'inject_initiate_checkout_event' ) );
			
			// Purchase and Subscribe events
			add_action( 'woocommerce_new_order', array( $this, 'inject_purchase_event' ) );
			add_action( 'woocommerce_payment_complete', array( $this, 'inject_purchase_event' ), 10 );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'inject_purchase_event' ), 20 );
			add_action( 'woocommerce_thankyou', array( $this, 'inject_purchase_event' ), 40 );
			add_action( 'woocommerce_order_status_processing', array( $this, 'inject_purchase_event' ), 50 );
			add_action( 'woocommerce_order_status_completed', array( $this, 'inject_purchase_event' ), 50 );
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'inject_purchase_event' ), 60 );

			// Lead events through Contact Form 7
			add_action( 'wpcf7_contact_form', array( $this, 'inject_lead_event_hook' ), 11 );

			// Flush pending events on shutdown
			add_action( 'shutdown', array( $this, 'send_pending_events' ) );
		}


		/**
		 * Prints the base JavaScript pixel code.
		 */
		public function inject_base_pixel() {

			if ( $this->is_pixel_enabled() ) {
				// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
				echo $this->pixel->pixel_base_code();
			}
		}


		/**
		 * Prints the base <noscript> pixel code.
		 *
		 * This is necessary to avoid W3 validation errors.
		 */
		public function inject_base_pixel_noscript() {

			if ( $this->is_pixel_enabled() ) {
				// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
				echo $this->pixel->pixel_base_code_noscript();
			}
		}


		/**
		 * Triggers ViewCategory for product category listings
		 */
		public function inject_view_category_event() {
			global $wp_query;

			if ( ! $this->is_pixel_enabled() || ! is_product_category() ) {
				return;
			}

			$products = array_values(
				array_map(
					function( $post ) {
						return wc_get_product( $post );
					},
					$wp_query->posts
				)
			);

			// if any product is a variant, fire the pixel with
			// content_type: product_group
			$content_type = 'product';
			$product_ids  = array();
			$contents     = array();

			foreach ( $products as $product ) {

				if ( ! $product ) {
					continue;
				}

				$contents[] = array(
					'id'       => \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product ),
					'quantity' => 1, // consider category results a quantity of 1
				);

				$product_ids = array_merge(
					$product_ids,
					WC_Facebookcommerce_Utils::get_fb_content_ids( $product )
				);
				if ( WC_Facebookcommerce_Utils::is_variable_type( $product->get_type() ) ) {
					$content_type = 'product_group';
				}
			}

			$category = get_queried_object();

			$event_name = 'ViewCategory';
			$event_data = array(
				'event_name'  => $event_name,
				'custom_data' => array(
					'content_name'     => $category->name,
					'content_category' => $category->name,
					'content_ids'      => json_encode( array_slice( $product_ids, 0, 10 ) ),
					'content_type'     => $content_type,
					'contents'         => $contents,
				),
				'user_data'   => $this->pixel->get_user_info(),
			);

			$event = new Event( $event_data );

			$this->send_api_event( $event, false );

			$event_data['event_id'] = $event->get_id();

			$this->pixel->inject_event( $event_name, $event_data, 'trackCustom' );
		}


		/**
		 * Attempts to add a session variable to indicate that a product search event occurred.
		 *
		 * The session variable is used to catch search events that have a single result.
		 * In those cases WooCommerce redirects customers to the product page instead of showing the search results.
		 *
		 * The plugin can't inject a Pixel event code on redirect responses, but it can check for the presence of the variable on the product page.
		 *
		 * This method is hooked to woocommerce_redirect_single_search_result which is triggered right before redirecting.
		 *
		 * @internal
		 *
		 * @since 2.1.2
		 *
		 * @param bool $redirect whether to redirect to the product page
		 * @return bool
		 */
		public function maybe_add_product_search_event_to_session( $redirect ) {

			if ( $redirect && $this->search_event && $this->is_single_search_result() ) {

				$this->add_product_search_event_to_session( $this->search_event );
			}

			return $redirect;
		}


		/**
		 * Determines whether the current request is a product search with a single result.
		 *
		 * @since 2.1.2
		 *
		 * @return bool
		 */
		private function is_single_search_result() {
			global $wp_query;

			return is_search() && 1 === absint( $wp_query->found_posts ) && is_post_type_archive( 'product' );
		}


		/**
		 * Adds search event data to the session.
		 *
		 * This does nothing if there is no session set.
		 *
		 * @since 2.1.2
		 *
		 * @return void
		 */
		private function add_product_search_event_to_session( Event $event ) {

			if ( isset( WC()->session ) && is_callable( array( WC()->session, 'has_session' ) ) && WC()->session->has_session() ) {
				WC()->session->set( $this->search_event_data_session_variable, $event->get_data() );
			}
		}


		/**
		 * Injects a frontend search event if the session has stored event data.
		 *
		 * @internal
		 *
		 * @since 2.1.2
		 */
		public function maybe_inject_search_event() {

			if ( ! $this->is_pixel_enabled() ) {
				return;
			}

			$this->search_event = $this->get_product_search_event_from_session();

			if ( ! $this->search_event ) {
				return;
			}

			$this->delete_session_data( $this->search_event_data_session_variable );
			$this->actually_inject_search_event();
		}


		/**
		 * Attempts to create an Event instance for a product search event using session data.
		 *
		 * @since 2.1.2
		 *
		 * @return Event|null
		 */
		private function get_product_search_event_from_session() {

			if ( ! isset( WC()->session ) || ! is_callable( array( WC()->session, 'get' ) ) ) {
				return null;
			}

			$data = WC()->session->get( $this->search_event_data_session_variable );

			if ( ! is_array( $data ) || empty( $data ) ) {
				return null;
			}

			return new Event( $data );
		}


		/**
		 * Deletes a session variable.
		 *
		 * @since 2.1.2
		 *
		 * @param string $key name of the variable to delete
		 */
		private function delete_session_data( $key ) {

			if ( isset( WC()->session->$key ) ) {
				unset( WC()->session->$key );
			}
		}


		/**
		 * Triggers Search for result pages (deduped)
		 *
		 * @internal
		 *
		 * @param WP_Query $query the query object
		 */
		public function inject_search_event( $query ) {

			if ( ! $this->is_pixel_enabled() || ! $query->is_main_query() ) {
				return;
			}

			if ( ! is_admin() && is_search() && '' !== get_search_query() && 'product' === get_query_var( 'post_type' ) ) {

				if ( $this->pixel->is_last_event( 'Search' ) ) {
					return;
				}

				// needs to run before wc_template_redirect, normally hooked with priority 10
				add_action( 'template_redirect', array( $this, 'send_search_event' ), 5 );
				add_action( 'woocommerce_before_shop_loop', array( $this, 'actually_inject_search_event' ) );
			}
		}


		/**
		 * Sends a server-side Search event.
		 *
		 * @internal
		 *
		 * @since 2.0.0
		 */
		public function send_search_event() {

			$this->send_api_event( $this->get_search_event() );
		}


		/**
		 * Creates an Event instance to track a search request.
		 *
		 * The event instance is stored in memory to return a single instance per request.
		 *
		 * @since 2.0.0
		 *
		 * @return Event
		 */
		private function get_search_event() {
			global $wp_query;

			if ( null === $this->search_event ) {

				// if any product is a variant, fire the pixel with content_type: product_group
				$content_type = 'product';
				$product_ids  = array();
				$contents     = array();
				$total_value  = 0.00;

				foreach ( $wp_query->posts as $post ) {

					$product = wc_get_product( $post );

					if ( ! $product instanceof \WC_Product ) {
						continue;
					}

					$product_ids = array_merge( $product_ids, WC_Facebookcommerce_Utils::get_fb_content_ids( $product ) );

					$contents[] = array(
						'id'       => \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product ),
						'quantity' => 1, // consider the search results a quantity of 1
					);

					$total_value += (float) $product->get_price();

					if ( WC_Facebookcommerce_Utils::is_variable_type( $product->get_type() ) ) {
						$content_type = 'product_group';
					}
				}

				$event_data = array(
					'event_name'  => 'Search',
					'custom_data' => array(
						'content_type'  => $content_type,
						'content_ids'   => json_encode( array_slice( $product_ids, 0, 10 ) ),
						'contents'      => $contents,
						'search_string' => get_search_query(),
						'value'         => Helper::number_format( $total_value ),
						'currency'      => get_woocommerce_currency(),
					),
					'user_data'   => $this->pixel->get_user_info(),
				);

				$this->search_event = new Event( $event_data );
			}

			return $this->search_event;
		}


		/**
		 * Injects a Search event on result pages.
		 *
		 * @internal
		 */
		public function actually_inject_search_event() {

			$event = $this->get_search_event();

			$this->pixel->inject_event(
				$event->get_name(),
				array(
					'event_id'    => $event->get_id(),
					'event_name'  => $event->get_name(),
					'custom_data' => $event->get_custom_data(),
				)
			);
		}


		/**
		 * Triggers ViewContent event on product pages
		 *
		 * @internal
		 */
		public function inject_view_content_event() {
			global $post;

			if ( ! $this->is_pixel_enabled() || ! isset( $post->ID ) ) {
				return;
			}

			$product = wc_get_product( $post->ID );

			if ( ! $product instanceof \WC_Product ) {
				return;
			}

			// if product is variable or grouped, fire the pixel with content_type: product_group
			if ( $product->is_type( array( 'variable', 'grouped' ) ) ) {
				$content_type = 'product_group';
			} else {
				$content_type = 'product';
			}

			if ( WC_Facebookcommerce_Utils::is_variable_type( $product->get_type() ) ) {
                            $product_price = $product->get_variation_price( 'min' );
                        } else {
                            $product_price = $product->get_price();
                        }

			$categories = \WC_Facebookcommerce_Utils::get_product_categories( $product->get_id() );

			$event_data = array(
				'event_name'  => 'ViewContent',
				'custom_data' => array(
					'content_name'     => \WC_Facebookcommerce_Utils::clean_string( $product->get_title() ),
					'content_ids'      => wp_json_encode( \WC_Facebookcommerce_Utils::get_fb_content_ids( $product ) ),
					'content_type'     => $content_type,
					'contents'         => wp_json_encode(
						array(
							array(
								'id'       => \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product ),
								'quantity' => 1,
							),
						)
					),
					'content_category' => $categories['name'],
					'value'            => $product_price,
					'currency'         => get_woocommerce_currency(),
				),
				'user_data'   => $this->pixel->get_user_info(),
			);

			$event = new Event( $event_data );

			$this->send_api_event( $event, false );

			$event_data['event_id'] = $event->get_id();

			$this->pixel->inject_event( 'ViewContent', $event_data );
		}


		/**
		 * Triggers an AddToCart event when a product is added to cart.
		 *
		 * @internal
		 *
		 * @param string $cart_item_key the cart item key
		 * @param int    $product_id the product identifier
		 * @param int    $quantity the added product quantity
		 * @param int    $variation_id the product variation identifier
		 */
		public function inject_add_to_cart_event( $cart_item_key, $product_id, $quantity, $variation_id ) {

			// bail if pixel tracking disabled or invalid variables
			if ( ! $this->is_pixel_enabled() || ! $product_id || ! $quantity ) {
				return;
			}

			/**
			 * Make sure to proceed only if the cart item exists.
			 * Some other plugins may clone the WC_Cart object. Calling clone APIs may trigger the 'woocommerce_add_to_cart' action.
			 * We want to make sure to proceed only if the cart item exists inside the original WC_Cart object.
			 */
			$cart = WC()->cart;
			if ( ! isset( $cart->cart_contents[ $cart_item_key ] ) ) {
				return;
			}

			$product = wc_get_product( $variation_id ?: $product_id );

			// bail if invalid product or error
			if ( ! $product instanceof \WC_Product ) {
				return;
			}

			$event_data = array(
				'event_name'  => 'AddToCart',
				'custom_data' => array(
					'content_ids'  => wp_json_encode( \WC_Facebookcommerce_Utils::get_fb_content_ids( $product ) ),
					'content_name' => \WC_Facebookcommerce_Utils::clean_string( $product->get_title() ),
					'content_type' => 'product',
					'contents'     => wp_json_encode(
						array(
							array(
								"id"	   => \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product ),
								"quantity" =>  $quantity,
							),
						)
					),
					'value'        => (float) $product->get_price() * $quantity,
					'currency'     => get_woocommerce_currency(),
				),
				'user_data'   => $this->pixel->get_user_info(),
			);

			$event = new WooCommerce\Facebook\Events\Event( $event_data );

			$this->send_api_event( $event, false );

			// send the event ID to prevent duplication
			$event_data['event_id'] = $event->get_id();

			// store the ID in the session to be sent in AJAX JS event tracking as well
			WC()->session->set( 'facebook_for_woocommerce_add_to_cart_event_id', $event->get_id() );

			$this->pixel->inject_event( 'AddToCart', $event_data );
		}


		/**
		 * Setups a filter to add an add to cart fragment whenever a product is added to the cart through Ajax.
		 *
		 * @see \WC_Facebookcommerce_EventsTracker::add_add_to_cart_event_fragment
		 *
		 * @internal
		 *
		 * @since 1.10.2
		 */
		public function add_filter_for_add_to_cart_fragments() {

			if ( 'no' === get_option( 'woocommerce_cart_redirect_after_add', 'no' ) ) {
				add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'add_add_to_cart_event_fragment' ) );
			}
		}


		/**
		 * Adds an add to cart fragment to trigger an AddToCart event.
		 *
		 * @internal
		 *
		 * @since 1.10.2
		 *
		 * @param array $fragments add to cart fragments
		 * @return array
		 */
		public function add_add_to_cart_event_fragment( $fragments ) {

			$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : '';
			$quantity   = isset( $_POST['quantity']) ? (int) $_POST['quantity'] : '';
			$product 	= wc_get_product($product_id);

			if ( ! $product instanceof \WC_Product || empty( $quantity ) ) {
				return $fragments;
			}


			if ( $this->is_pixel_enabled() ) {

				$params = array(
					'content_ids'  => wp_json_encode( \WC_Facebookcommerce_Utils::get_fb_content_ids( $product ) ),
					'content_name' => \WC_Facebookcommerce_Utils::clean_string( $product->get_title() ),
					'content_type' => 'product',
					'contents'     => wp_json_encode(
						array(
							array(
								'id'       => \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product ),
								'quantity' => $quantity,
							),
						)
					),
					'value'        => (float) $product->get_price() * $quantity,
					'currency'     => get_woocommerce_currency(),
				);

				// send the event ID to prevent duplication
				if ( ! empty( $event_id = WC()->session->get( 'facebook_for_woocommerce_add_to_cart_event_id' ) ) ) {
					$params['event_id'] = $event_id;
				}

				$script = $this->pixel->get_event_script( 'AddToCart', $params );

				$fragments['div.wc-facebook-pixel-event-placeholder'] = '<div class="wc-facebook-pixel-event-placeholder">' . $script . '</div>';
			}

			return $fragments;
		}


		/**
		 * Setups a filter to add an add to cart fragment to trigger an AddToCart event on added_to_cart JS event.
		 *
		 * This method is used by code snippets and should not be removed.
		 *
		 * @see \WC_Facebookcommerce_EventsTracker::add_conditional_add_to_cart_event_fragment
		 *
		 * @internal
		 *
		 * @since 1.10.2
		 */
		public function add_filter_for_conditional_add_to_cart_fragment() {

			if ( 'no' === get_option( 'woocommerce_cart_redirect_after_add', 'no' ) ) {
				add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'add_conditional_add_to_cart_event_fragment' ) );
			}
		}


		/**
		 * Adds an add to cart fragment to trigger an AddToCart event on added_to_cart JS event.
		 *
		 * @internal
		 *
		 * @since 1.10.2
		 *
		 * @param array $fragments add to cart fragments
		 * @return array
		 */
		public function add_conditional_add_to_cart_event_fragment( $fragments ) {

			if ( $this->is_pixel_enabled() ) {

				$params = array(
					'content_ids'  => $this->get_cart_content_ids(),
					'content_name' => $this->get_cart_content_names(),
					'content_type' => 'product',
					'contents'     => $this->get_cart_contents(),
					'value'        => $this->get_cart_total(),
					'currency'     => get_woocommerce_currency(),
				);

				// send the event ID to prevent duplication
				if ( ! empty( $event_id = WC()->session->get( 'facebook_for_woocommerce_add_to_cart_event_id' ) ) ) {

					$params['event_id'] = $event_id;
				}

				$script = $this->pixel->get_conditional_one_time_event_script( 'AddToCart', $params, 'added_to_cart' );

				$fragments['div.wc-facebook-pixel-event-placeholder'] = '<div class="wc-facebook-pixel-event-placeholder">' . $script . '</div>';
			}

			return $fragments;
		}

		/**
		 * Triggers an InitiateCheckout event when customer reaches checkout page.
		 *
		 * @internal
		 */
		public function inject_initiate_checkout_event() {

			if ( ! $this->is_pixel_enabled() || null === WC()->cart || WC()->cart->get_cart_contents_count() === 0 || $this->pixel->is_last_event( 'InitiateCheckout' ) ) {
				return;
			}

			$event_name = 'InitiateCheckout';
			$event_data = array(
				'event_name'  => $event_name,
				'custom_data' => array(
					'num_items'    => $this->get_cart_num_items(),
					'content_ids'  => $this->get_cart_content_ids(),
					'content_name' => $this->get_cart_content_names(),
					'content_type' => 'product',
					'contents'     => $this->get_cart_contents(),
					'value'        => $this->get_cart_total(),
					'currency'     => get_woocommerce_currency(),
				),
				'user_data'   => $this->pixel->get_user_info(),
			);

			// if there is only one item in the cart, send its first category
			if ( ( $cart = WC()->cart ) && count( $cart->get_cart() ) === 1 ) {

				$item = current( $cart->get_cart() );

				if ( isset( $item['data'] ) && $item['data'] instanceof \WC_Product ) {

					$categories = \WC_Facebookcommerce_Utils::get_product_categories( $item['data']->get_id() );

					if ( ! empty( $categories['name'] ) ) {
						$event_data['custom_data']['content_category'] = $categories['name'];
					}
				}
			}

			$event = new Event( $event_data );

			$this->send_api_event( $event, false );

			$event_data['event_id'] = $event->get_id();

			$this->pixel->inject_event( $event_name, $event_data );
		}


		/**
		 * Triggers a Purchase event when checkout is completed.
		 *
		 * This may happen either when:
		 * - WooCommerce signals a payment transaction complete (most gateways)
		 * - The order status is changed through the Woo dashboard to Processing or Completed
		 * - The Payment Completed event is fired, which happens in case of some external payment gateways.
		 * - Customer reaches Thank You page skipping payment (for gateways that do not require payment, e.g. Cheque, BACS, Cash on delivery...)
		 *
		 * The method checks if the event was not triggered already avoiding a duplicate.
		 * Finally, if the order contains subscriptions, it will also track an associated Subscription event.
		 *
		 * @internal
		 *
		 * @param int $order_id order identifier
		 */
		public function inject_purchase_event( $order_id ) {

			$event_name = 'Purchase';

			$valid_purchase_order_states = array( 'processing', 'completed' );

			if ( ! $this->is_pixel_enabled() ) {
				return;
			}

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				return;
			}
			
			// Get the status of the order to ensure we track the actual purchases and not the ones that have a failed payment.
			$order_state = $order->get_status();

			// use a session flag to ensure this Purchase event is not tracked multiple times
			$purchase_tracked_flag = '_wc_' . facebook_for_woocommerce()->get_id() . '_purchase_tracked_' . $order_id;

			// Return if this Purchase event has already been tracked
			if ( 'yes' === get_transient( $purchase_tracked_flag ) || $order->meta_exists( '_meta_purchase_tracked' ) || ! in_array( $order_state, $valid_purchase_order_states ) ) {
				return;
			}

			// Mark the order as tracked for the session
			set_transient( $purchase_tracked_flag, 'yes', 15 * MINUTE_IN_SECONDS );

			// Set a flag to ensure this Purchase event is not going to be sent across different sessions
			$order->add_meta_data( '_meta_purchase_tracked', true, true );

			// Save the metadata
			$order->save();

			$content_type  = 'product';
			$contents      = array();
			$product_ids   = array( array() );
			$product_names = array();

			foreach ( $order->get_items() as $item ) {

				$product = $item->get_product();

				if ( $product ) {
					$product_ids[]   = \WC_Facebookcommerce_Utils::get_fb_content_ids( $product );
					$product_names[] = \WC_Facebookcommerce_Utils::clean_string( $product->get_title() );

					if ( 'product_group' !== $content_type && $product->is_type( 'variable' ) ) {
						$content_type = 'product_group';
					}

					$quantity = $item->get_quantity();
					$content  = new \stdClass();

					$content->id       = \WC_Facebookcommerce_Utils::get_fb_retailer_id( $product );
					$content->quantity = $quantity;

					$contents[] = $content;
				}
			}
			// Advanced matching information is extracted from the order
			$event_data = array(
				'event_name'  => $event_name,
				'custom_data' => array(
					'content_ids'  => wp_json_encode( array_merge( ... $product_ids ) ),
					'content_name' => wp_json_encode( $product_names ),
					'contents'     => wp_json_encode( $contents ),
					'content_type' => $content_type,
					'value'        => $order->get_total(),
					'currency'     => get_woocommerce_currency(),
				),
				'user_data'   => $this->get_user_data_from_billing_address( $order ),
			);

			$event = new Event( $event_data );

			$this->send_api_event( $event );

			$event_data['event_id'] = $event->get_id();

			$this->pixel->inject_event( $event_name, $event_data );

			$this->inject_subscribe_event( $order_id );

		}


		/**
		 * Triggers a Subscribe event when a given order contains subscription products.
		 *
		 * @see \WC_Facebookcommerce_EventsTracker::inject_purchase_event()
		 *
		 * @internal
		 *
		 * @param int $order_id order identifier
		 */
		public function inject_subscribe_event( $order_id ) {

			if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) || ! $this->is_pixel_enabled() || $this->pixel->is_last_event( 'Subscribe' ) ) {
				return;
			}

			foreach ( wcs_get_subscriptions_for_order( $order_id ) as $subscription ) {

				// TODO consider 'StartTrial' event for free trial Subscriptions, which is the same as here (minus sign_up_fee) and tracks "when a person starts a free trial of a product or service" {FN 2020-03-20}
				$event_name = 'Subscribe';

				// TODO consider including (int|float) 'predicted_ltv': "Predicted lifetime value of a subscriber as defined by the advertiser and expressed as an exact value." {FN 2020-03-20}
				$event_data = array(
					'event_name'  => $event_name,
					'custom_data' => array(
						'sign_up_fee' => $subscription->get_sign_up_fee(),
						'value'       => $subscription->get_total(),
						'currency'    => get_woocommerce_currency(),
					),
					'user_data'   => $this->pixel->get_user_info(),
				);

				$event = new Event( $event_data );

				$this->send_api_event( $event );

				$event_data['event_id'] = $event->get_id();

				$this->pixel->inject_event( $event_name, $event_data );
			}
		}


		/** Contact Form 7 Support **/
		public function inject_lead_event_hook() {
			add_action( 'wp_footer', array( $this, 'inject_lead_event' ), 11 );
		}

		public function inject_lead_event() {
			if ( ! is_admin() ) {
				$this->pixel->inject_conditional_event(
					'Lead',
					array(),
					'wpcf7submit',
					'{ em: event.detail.inputs.filter(ele => ele.name.includes("email"))[0].value }'
				);
			}
		}


		/**
		 * Sends an API event.
		 *
		 * @since 2.0.0
		 *
		 * @param Event $event event object
		 * @param bool $send_now optional, defaults to true
		 */
		protected function send_api_event( Event $event, bool $send_now = true ) {
			$this->tracked_events[] = $event;

			if ( $send_now ) {
				try {
					facebook_for_woocommerce()->get_api()->send_pixel_events( facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(), array( $event ) );
				} catch ( ApiException $exception ) {
					facebook_for_woocommerce()->log( 'Could not send Pixel event: ' . $exception->getMessage() );
				}
			} else {
				$this->pending_events[] = $event;
			}
		}


		/**
		 * Gets the cart content items count.
		 *
		 * @since 1.10.2
		 *
		 * @return int
		 */
		private function get_cart_num_items() {

			return WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
		}


		/**
		 * Gets all content IDs from cart.
		 *
		 * @since 1.10.2
		 *
		 * @return string JSON data
		 */
		private function get_cart_content_ids() {

			$product_ids = array( array() );

			if ( $cart = WC()->cart ) {

				foreach ( $cart->get_cart() as $item ) {

					if ( isset( $item['data'] ) && $item['data'] instanceof \WC_Product ) {

						$product_ids[] = \WC_Facebookcommerce_Utils::get_fb_content_ids( $item['data'] );
					}
				}
			}

			return wp_json_encode( array_unique( array_merge( ... $product_ids ) ) );
		}


		/**
		 * Gets all content names from cart.
		 *
		 * @since 2.0.0
		 *
		 * @return string JSON data
		 */
		private function get_cart_content_names() {

			$product_names = array();

			if ( $cart = WC()->cart ) {

				foreach ( $cart->get_cart() as $item ) {

					if ( isset( $item['data'] ) && $item['data'] instanceof \WC_Product ) {

						$product_names[] = \WC_Facebookcommerce_Utils::clean_string( $item['data']->get_title() );
					}
				}
			}

			return wp_json_encode( array_unique( $product_names ) );
		}


		/**
		 * Gets the cart content data.
		 *
		 * @since 1.10.2
		 *
		 * @return string JSON data
		 */
		private function get_cart_contents() {

			$cart_contents = array();

			if ( $cart = WC()->cart ) {

				foreach ( $cart->get_cart() as $item ) {

					if ( ! isset( $item['data'], $item['quantity'] ) || ! $item['data'] instanceof \WC_Product ) {
						continue;
					}

					$content = new \stdClass();

					$content->id       = \WC_Facebookcommerce_Utils::get_fb_retailer_id( $item['data'] );
					$content->quantity = $item['quantity'];

					$cart_contents[] = $content;
				}
			}

			return wp_json_encode( $cart_contents );
		}


		/**
		 * Gets the cart total.
		 *
		 * @return float|int
		 */
		private function get_cart_total() {

			return WC()->cart ? WC()->cart->total : 0;
		}

		/**
		 * Gets advanced matching information from a given order
		 *
		 * @since 2.0.3
		 *
		 * @return array
		 */
		private function get_user_data_from_billing_address( $order ) {
			if ( $this->aam_settings == null || ! $this->aam_settings->get_enable_automatic_matching() ) {
				return array();
			}
			$user_data       = array();
			$user_data['fn'] = $order->get_billing_first_name();
			$user_data['ln'] = $order->get_billing_last_name();
			$user_data['em'] = $order->get_billing_email();
			// get_user_id() returns 0 if the current user is a guest
			$user_data['external_id'] = $order->get_user_id() === 0 ? null : strval( $order->get_user_id() );
			$user_data['zp']          = $order->get_billing_postcode();
			$user_data['st']          = $order->get_billing_state();
			// We can use country as key because this information is for CAPI events only
			$user_data['country'] = $order->get_billing_country();
			$user_data['ct']      = $order->get_billing_city();
			$user_data['ph']      = $order->get_billing_phone();
			// The fields contain country, so we do not need to add a condition
			foreach ( $user_data as $field => $value ) {
				if ( $value === null || $value === '' ||
					! in_array( $field, $this->aam_settings->get_enabled_automatic_matching_fields() )
				) {
					unset( $user_data[ $field ] );
				}
			}
			return $user_data;
		}

		/**
		 * Gets the events tracked by this object
		 *
		 * @return array
		 */
		public function get_tracked_events() {
			return $this->tracked_events;
		}

		/**
		 * Gets the pending events awaiting to be sent
		 *
		 * @return array
		 */
		public function get_pending_events() {
			return $this->pending_events;
		}

		/**
		 * Send pending events.
		 */
		public function send_pending_events() {

			$pending_events = $this->get_pending_events();

			if ( empty( $pending_events ) ) {
				return;
			}

			foreach ( $pending_events as $event ) {

				$this->send_api_event( $event );
			}
		}

	}

endif;
