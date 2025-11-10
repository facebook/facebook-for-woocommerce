# Facebook Pixel Events - Complete End-to-End Flow

## Table of Contents
1. [Initialization Phase](#initialization-phase)
2. [Event Types & Triggers](#event-types--triggers)
3. [Event Processing Flow](#event-processing-flow)
4. [Conditions That Prevent Events](#conditions-that-prevent-events)
5. [Dual Tracking System](#dual-tracking-system)
6. [Critical Edge Cases](#critical-edge-cases)

---

## Initialization Phase

### Step 1: Plugin Initialization (`facebook-commerce.php`)
**Location:** Lines 390-394

```php
if ( $this->get_facebook_pixel_id() ) {
    $aam_settings         = $this->load_aam_settings_of_pixel();
    $user_info            = WC_Facebookcommerce_Utils::get_user_info( $aam_settings );
    $this->events_tracker = new WC_Facebookcommerce_EventsTracker( $user_info, $aam_settings );
}
```

**🚨 CONDITION #1 - Event Tracker Won't Initialize If:**
- No Facebook Pixel ID is configured
- Result: **NO EVENTS WILL FIRE AT ALL**

### Step 2: Events Tracker Constructor (`facebook-commerce-events-tracker.php`)
**Location:** Lines 66-78

```php
public function __construct( $user_info, $aam_settings ) {
    if ( ! $this->is_pixel_enabled() ) {  // ⚠️ CRITICAL CHECK
        return;
    }
    
    $this->pixel          = new \WC_Facebookcommerce_Pixel( $user_info );
    $this->aam_settings   = $aam_settings;
    $this->tracked_events = array();
    
    $this->param_builder_server_setup();
    $this->add_hooks();  // ⚠️ Hooks are added here!
}
```

**🚨 CONDITION #2 - Pixel Disabled Check:**
**Location:** Lines 148-161

```php
private function is_pixel_enabled() {
    if ( null === $this->is_pixel_enabled ) {
        // Filter can disable pixel
        $this->is_pixel_enabled = (bool) apply_filters( 
            'facebook_for_woocommerce_integration_pixel_enabled', 
            true 
        );
    }
    return $this->is_pixel_enabled;
}
```

**Ways events can be disabled:**
1. Filter `facebook_for_woocommerce_integration_pixel_enabled` returns false
2. If disabled, constructor returns early - **NO HOOKS ARE ADDED = NO EVENTS**

### Step 3: Hooks Registration
**Location:** Lines 169-216

All event tracking is set up via WordPress hooks:

```php
private function add_hooks() {
    // BASE PIXEL CODE - Most critical
    add_action( 'wp_head', array( $this, 'inject_base_pixel' ) );
    add_action( 'wp_footer', array( $this, 'inject_base_pixel_noscript' ) );
    
    // CAPI Param Builder
    add_action( 'wp_enqueue_scripts', array( $this, 'param_builder_client_setup' ) );
    
    // ViewContent for individual products
    add_action( 'woocommerce_after_single_product', array( $this, 'inject_view_content_event' ) );
    
    // ViewCategory events
    add_action( 'woocommerce_after_shop_loop', array( $this, 'inject_view_category_event' ) );
    
    // Search events
    add_action( 'pre_get_posts', array( $this, 'inject_search_event' ) );
    
    // AddToCart events
    add_action( 'woocommerce_add_to_cart', array( $this, 'inject_add_to_cart_event' ), 40, 4 );
    add_action( 'woocommerce_ajax_added_to_cart', array( $this, 'add_filter_for_add_to_cart_fragments' ) );
    
    // InitiateCheckout events
    add_action( 'woocommerce_after_checkout_form', array( $this, 'inject_initiate_checkout_event' ) );
    add_action( 'woocommerce_blocks_checkout_enqueue_data', array( $this, 'inject_initiate_checkout_event' ) );
    
    // Purchase events (multiple hooks for different checkout flows)
    add_action( 'woocommerce_new_order', array( $this, 'inject_purchase_event' ), 10 );
    add_action( 'woocommerce_process_shop_order_meta', array( $this, 'inject_purchase_event' ), 20 );
    add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'inject_purchase_event' ), 30 );
    add_action( 'woocommerce_thankyou', array( $this, 'inject_purchase_event' ), 40 );
    
    // Lead events (Contact Form 7)
    add_action( 'wpcf7_contact_form', array( $this, 'inject_lead_event_hook' ), 11 );
    
    // Flush pending events
    add_action( 'shutdown', array( $this, 'send_pending_events' ) );
}
```

---

## Event Types & Triggers

### 1. PageView Event
**Trigger:** Every page load  
**Location:** Lines 273-291

**Flow:**
1. Hook: `wp_head` → `inject_base_pixel()`
2. Checks `is_pixel_enabled()` ⚠️
3. Outputs base pixel code
4. Calls `inject_page_view_event()`

**🚨 CONDITION #3 - PageView Won't Fire If:**
- Pixel is disabled
- No Pixel ID configured (`pixel_base_code()` returns empty string at line 148)
- Already rendered (cache check at line 148)

### 2. ViewContent Event
**Trigger:** Single product page view  
**Location:** Lines 629-685

**Flow:**
```php
public function inject_view_content_event() {
    global $post;
    
    // ⚠️ CHECK 1: Pixel enabled?
    if ( ! $this->is_pixel_enabled() || ! isset( $post->ID ) ) {
        return;
    }
    
    // ⚠️ CHECK 2: Valid product?
    $product = wc_get_product( $post->ID );
    if ( ! $product instanceof \WC_Product ) {
        return;
    }
    
    // Build event data...
    $event = new Event( $event_data );
    
    // Send to Facebook API (server-side)
    $this->send_api_event( $event );
    
    // Inject JavaScript (client-side)
    $this->pixel->inject_event( 'ViewContent', $event_data );
}
```

**🚨 CONDITION #4 - ViewContent Won't Fire If:**
- Pixel disabled
- `$post->ID` not set (not in The Loop)
- Product object invalid (deleted, wrong post type, etc.)

### 3. ViewCategory Event
**Trigger:** Product category archive pages  
**Location:** Lines 297-361

**🚨 CONDITION #5 - ViewCategory Won't Fire If:**
- Pixel disabled
- Not on a product category page: `! is_product_category()`
- No products in query: `$wp_query->posts` is empty

### 4. Search Event
**Trigger:** Product search results  
**Location:** Lines 494-621

**Complex Flow with Special Session Handling:**

```php
// PHASE 1: Detect search (pre_get_posts hook)
public function inject_search_event( $query ) {
    // ⚠️ CHECK 1: Must be main query
    if ( ! $this->is_pixel_enabled() || ! $query->is_main_query() ) {
        return;
    }
    
    // ⚠️ CHECK 2: Must be product search in frontend
    if ( ! is_admin() && is_search() && '' !== get_search_query() 
         && 'product' === get_query_var( 'post_type' ) ) {
        
        // ⚠️ CHECK 3: Prevent duplicate
        if ( $this->pixel->is_last_event( 'Search' ) ) {
            return;
        }
        
        add_action( 'template_redirect', array( $this, 'send_search_event' ), 5 );
        add_action( 'woocommerce_before_shop_loop', array( $this, 'actually_inject_search_event' ) );
    }
}

// PHASE 2: Build search event
private function get_search_event() {
    global $wp_query;
    
    // ⚠️ CHECK 4: Must have results
    if ( empty( $wp_query->posts ) ) {
        return null;  // Event not created!
    }
    
    // Build event from search results...
}
```

**Special Case: Single Search Result Redirect**  
**Location:** Lines 381-389

When WooCommerce redirects to product page on single search result:
1. Hook: `woocommerce_redirect_single_search_result`
2. Store search event in session
3. On product page, check session and inject stored search event
4. Delete session data after injecting

**🚨 CONDITION #6 - Search Event Won't Fire If:**
- Pixel disabled
- Not main query
- Not a product search
- Empty search query
- No search results (`$wp_query->posts` empty)
- Is last event (duplicate prevention)
- Session not available for single-result redirect case

### 5. AddToCart Event
**Trigger:** Product added to cart  
**Location:** Lines 698-825

**Flow:**
```php
public function inject_add_to_cart_event( $cart_item_key, $product_id, $quantity, $variation_id ) {
    // ⚠️ CHECK 1: Basic validation
    if ( ! $this->is_pixel_enabled() || ! $product_id || ! $quantity ) {
        return;
    }
    
    // ⚠️ CHECK 2: Cart item must exist
    // Protection against other plugins cloning WC_Cart
    $cart = WC()->cart;
    if ( ! isset( $cart->cart_contents[ $cart_item_key ] ) ) {
        return;
    }
    
    // ⚠️ CHECK 3: Valid product object
    $product = wc_get_product( $variation_id ?: $product_id );
    if ( ! $product instanceof \WC_Product ) {
        return;
    }
    
    // Create event, send to API, inject JS
    $event = new Event( $event_data );
    $this->send_api_event( $event );
    
    // Store event ID in session for AJAX deduplication
    WC()->session->set( 'facebook_for_woocommerce_add_to_cart_event_id', $event->get_id() );
    
    $this->pixel->inject_event( 'AddToCart', $event_data );
}
```

**AJAX AddToCart Handling** (Lines 765-825):
- Hook: `woocommerce_ajax_added_to_cart`
- Adds fragment filter to inject event code in AJAX response
- Uses stored event ID from session to prevent duplication

**Redirect to Cart Handling** (Lines 194-197):
- If `woocommerce_cart_redirect_after_add` is 'yes'
- Events are deferred and rendered on next page load

**🚨 CONDITION #7 - AddToCart Won't Fire If:**
- Pixel disabled
- No product ID or quantity
- Cart item doesn't exist in cart contents
- Product object is invalid
- WooCommerce session not available (for AJAX)
- Redirect enabled but deferred events fail to save

### 6. InitiateCheckout Event
**Trigger:** Customer reaches checkout page  
**Location:** Lines 888-932

**Flow:**
```php
public function inject_initiate_checkout_event() {
    // ⚠️ CHECK 1: Multiple conditions
    if ( ! $this->is_pixel_enabled() 
         || null === WC()->cart 
         || WC()->cart->get_cart_contents_count() === 0 
         || $this->pixel->is_last_event( 'InitiateCheckout' ) ) {
        return;
    }
    
    // Build event with cart data...
    // If single item, include category
    $event = new Event( $event_data );
    $this->send_api_event( $event );
    $this->pixel->inject_event( $event_name, $event_data );
}
```

**🚨 CONDITION #8 - InitiateCheckout Won't Fire If:**
- Pixel disabled
- WC()->cart is null
- Cart is empty (0 items)
- Already fired (is_last_event check prevents duplicates)

### 7. Purchase Event
**Trigger:** Order completion  
**Location:** Lines 951-1059

**Most Complex Event - Multiple Hooks:**
1. `woocommerce_new_order` (priority 10)
2. `woocommerce_process_shop_order_meta` (priority 20)
3. `woocommerce_checkout_update_order_meta` (priority 30)
4. `woocommerce_thankyou` (priority 40)

**Flow:**
```php
public function inject_purchase_event( $order_id ) {
    // ⚠️ CHECK 1: Not admin user
    if ( \WC_Facebookcommerce_Utils::is_admin_user() ) {
        return;
    }
    
    // ⚠️ CHECK 2: Pixel enabled
    if ( ! $this->is_pixel_enabled() ) {
        return;
    }
    
    // ⚠️ CHECK 3: Valid order
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    
    // ⚠️ CHECK 4: Order status must be valid
    $valid_purchase_order_states = array( 'processing', 'completed', 'on-hold', 'pending' );
    $order_state = $order->get_status();
    if ( ! in_array( $order_state, $valid_purchase_order_states, true ) ) {
        return;
    }
    
    // ⚠️ CHECK 5: Prevent duplicate tracking
    $purchase_tracked_flag = '_wc_' . facebook_for_woocommerce()->get_id() 
                           . '_purchase_tracked_' . $order_id;
    
    if ( 'yes' === get_transient( $purchase_tracked_flag ) 
         || $order->meta_exists( '_meta_purchase_tracked' ) ) {
        return;  // Already tracked!
    }
    
    // Mark as tracked
    set_transient( $purchase_tracked_flag, 'yes', 45 * MINUTE_IN_SECONDS );
    $order->add_meta_data( '_meta_purchase_tracked', true, true );
    $order->save();
    
    // Log which hook triggered it
    $hook_name = current_action();
    Logger::log( 'Purchase event fired for order ' . $order_id . ' by hook ' . $hook_name );
    
    // Build event, send to API, inject JS
    $event = new Event( $event_data );
    $this->send_api_event( $event );
    $this->pixel->inject_event( $event_name, $event_data );
    
    // Also check for subscription events
    $this->inject_subscribe_event( $order_id );
}
```

**🚨 CONDITION #9 - Purchase Event Won't Fire If:**
- Admin user is creating/editing order
- Pixel disabled
- Order doesn't exist
- Order status not in: processing, completed, on-hold, pending
- Already tracked (transient or meta exists)
- User data extraction fails

### 8. Subscribe Event
**Trigger:** Order contains subscription products  
**Location:** Lines 1071-1101

**🚨 CONDITION #10 - Subscribe Event Won't Fire If:**
- WooCommerce Subscriptions plugin not active: `! function_exists( 'wcs_get_subscriptions_for_order' )`
- Pixel disabled
- Already is last event (duplicate prevention)
- Order has no subscriptions

### 9. Lead Event
**Trigger:** Contact Form 7 submission  
**Location:** Lines 1105-1118

**🚨 CONDITION #11 - Lead Event Won't Fire If:**
- Not Contact Form 7
- Is admin page
- Event listener not properly set up

---

## Event Processing Flow

### Client-Side (Browser JavaScript)

**Base Pixel Code Injection** (`facebook-commerce-pixel-event.php` lines 143-179):

```php
public function pixel_base_code() {
    $pixel_id = self::get_pixel_id();
    
    // ⚠️ CHECK: Must have pixel ID and not already rendered
    if ( empty( $pixel_id ) || ! empty( self::$render_cache[ self::PIXEL_RENDER ] ) ) {
        return '';  // Nothing injected!
    }
    
    self::$render_cache[ self::PIXEL_RENDER ] = true;  // Prevent duplicate
    
    // Output FB Pixel base script
    // Output pixel init with user_info (Advanced Matching)
    // Add event placeholder for AJAX events
}
```

**Event Injection Methods:**

1. **Direct Injection** (line 294):
   ```php
   public function inject_event( $event_name, $params, $method = 'track' ) {
       // For redirect-after-add scenarios
       if ( $is_redirect && $is_add_to_cart ) {
           WC_Facebookcommerce_Utils::add_deferred_event( $code );
       } else {
           WC_Facebookcommerce_Utils::wc_enqueue_js( $code );
       }
   }
   ```

2. **Conditional Event** (line 370):
   - Listens for JavaScript events
   - Used for Contact Form 7

3. **One-Time Event** (line 391):
   - jQuery-based
   - Removes listener after first trigger
   - Used for AJAX add-to-cart

### Server-Side (Conversions API - CAPI)

**Event Sending** (lines 1129-1141):

```php
protected function send_api_event( Event $event, bool $send_now = true ) {
    $this->tracked_events[] = $event;  // Keep track
    
    if ( $send_now ) {
        try {
            facebook_for_woocommerce()
                ->get_api()
                ->send_pixel_events( 
                    facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(), 
                    array( $event ) 
                );
        } catch ( ApiException $exception ) {
            facebook_for_woocommerce()->log( 'Could not send Pixel event: ' . $exception->getMessage() );
        }
    } else {
        $this->pending_events[] = $event;  // Send later
    }
}
```

**Event Object Creation** (`includes/Events/Event.php`):

```php
public function __construct( $data = array() ) {
    $this->prepare_data( $data );
}

protected function prepare_data( $data ) {
    $this->data = wp_parse_args(
        $data,
        array(
            'action_source'    => 'website',
            'event_time'       => time(),
            'event_id'         => $this->generate_event_id(),  // UUID for deduplication
            'event_source_url' => $this->get_current_url(),
            'custom_data'      => array(),
            'user_data'        => array(),
        )
    );
    
    // Add referrer if available
    if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
        $this->data['referrer_url'] = ...;
    }
    
    $this->prepare_user_data( $this->data['user_data'] );
}
```

**User Data Preparation** (includes PII hashing):

```php
protected function prepare_user_data( $data ) {
    $this->data['user_data'] = wp_parse_args(
        $data,
        array(
            'client_ip_address' => $this->get_client_ip(),
            'client_user_agent' => $this->get_client_user_agent(),
            'click_id'          => $this->get_click_id(),     // _fbc cookie
            'browser_id'        => $this->get_browser_id(),   // _fbp cookie
        )
    );
    
    // Normalize and hash PII data
    $this->data['user_data'] = Normalizer::normalize_array( ... );
    $this->data['user_data'] = $this->hash_pii_data( ... );  // SHA256
}
```

---

## Conditions That Prevent Events

### Critical Blocking Conditions (Affect ALL Events)

1. **No Pixel ID Configured**
   - EventsTracker not initialized
   - No hooks added
   - Result: TOTAL FAILURE

2. **Pixel Disabled via Filter**
   - Filter: `facebook_for_woocommerce_integration_pixel_enabled`
   - Returns false
   - Result: Constructor returns early, no hooks

3. **Pixel ID Empty in Pixel Class**
   - `pixel_base_code()` returns empty
   - Base FB script not loaded
   - Result: JavaScript events fail (fbq not defined)

4. **Already Rendered Cache**
   - Prevents duplicate base code injection
   - If something clears cache incorrectly, might block rendering

### Event-Specific Blocking Conditions

#### PageView
- Pixel disabled
- Already rendered
- No pixel ID

#### ViewContent
- Pixel disabled
- Not in WordPress Loop (`$post->ID` not set)
- Invalid product object
- Product deleted/trashed

#### ViewCategory
- Pixel disabled
- Not on category page
- No products in query

#### Search
- Pixel disabled
- Not main query
- Not product search
- Empty search query
- No results
- Duplicate event
- Session not available (single result redirect case)

#### AddToCart
- Pixel disabled
- No product ID
- No quantity
- Cart item doesn't exist in cart
- Invalid product
- WC session not available
- Deferred event save fails

#### InitiateCheckout
- Pixel disabled
- Cart is null
- Cart is empty
- Duplicate event

#### Purchase
- Admin user creating order
- Pixel disabled
- Invalid order
- Order status not valid (failed, cancelled, refunded, etc.)
- Already tracked (most important!)
- User data extraction issues

#### Subscribe
- WooCommerce Subscriptions not active
- Pixel disabled
- Duplicate event
- No subscriptions in order

### Advanced Matching Conditions

**User Data Filtering** (lines 1263-1286):

```php
private function get_user_data_from_billing_address( $order ) {
    // ⚠️ CHECK 1: AAM enabled?
    if ( null === $this->aam_settings 
         || ! $this->aam_settings->get_enable_automatic_matching() ) {
        return array();  // No user data sent!
    }
    
    // Extract user data from order
    $user_data = $this->pixel->get_user_info();
    // ... update with billing data
    
    // ⚠️ CHECK 2: Filter by enabled fields
    foreach ( $user_data as $field => $value ) {
        if ( null === $value || '' === $value ||
            ! in_array( $field, $this->aam_settings->get_enabled_automatic_matching_fields(), true )
        ) {
            unset( $user_data[ $field ] );  // Field removed!
        }
    }
    
    return $user_data;
}
```

**🚨 CONDITION #12 - Advanced Matching Won't Work If:**
- AAM Settings not loaded
- `get_enable_automatic_matching()` returns false
- Specific fields not in enabled fields list
- Data is null or empty

### CAPI Parameter Builder Conditions

**Setup** (lines 112-138):

```php
public function param_builder_server_setup() {
    try {
        $cookie_to_set = self::get_param_builder()->getCookiesToSet();
        
        // ⚠️ CHECK: Headers not sent?
        if ( ! headers_sent() ) {
            foreach ( $cookie_to_set as $cookie ) {
                setcookie( ... );
            }
        }
    } catch ( \Exception $exception ) {
        Logger::log( 'Error setting up server side CAPI Parameter Builder: ...' );
    }
}
```

**🚨 CONDITION #13 - CAPI Parameters Won't Work If:**
- Headers already sent (cookies can't be set)
- Exception in parameter builder
- Network issues with Facebook

### Client-Side Script Loading

**Location:** Lines 248-268

```php
public function param_builder_client_setup() {
    // ⚠️ CHECK: Must be connected
    if ( ! facebook_for_woocommerce()->get_connection_handler()->is_connected() ) {
        return;  // Script not loaded!
    }
    
    wp_enqueue_script(
        'facebook-capi-param-builder',
        'https://capi-automation.s3.us-east-2.amazonaws.com/public/client_js/capiParamBuilder/clientParamBuilder.bundle.js',
        array(),
        null,
        true
    );
}
```

**🚨 CONDITION #14 - Client Script Won't Load If:**
- Not connected to Facebook
- Network issues loading script
- Script blocked by ad blockers
- CSP policies block external scripts

---

## Dual Tracking System

Events are tracked in **TWO PLACES SIMULTANEOUSLY**:

### 1. Client-Side (Browser Pixel)
- JavaScript `fbq()` calls
- Runs in user's browser
- Tracked via cookies (_fbp, _fbc)
- Can be blocked by ad blockers
- Subject to GDPR/privacy controls

### 2. Server-Side (Conversions API)
- Direct API call to Facebook
- Runs on server
- More reliable (can't be blocked)
- Better for conversion tracking
- Uses same event_id for deduplication

**Deduplication Strategy:**

Both methods use the **same event_id** (UUID):
1. Event object created with unique ID
2. Server-side sends event with ID
3. Client-side sends event with same ID
4. Facebook deduplicates using event_id

**Example** (lines 680-684):
```php
$event = new Event( $event_data );
$this->send_api_event( $event );  // Server-side with event_id

$event_data['event_id'] = $event->get_id();  // Add ID
$this->pixel->inject_event( 'ViewContent', $event_data );  // Client-side with same ID
```

---

## Critical Edge Cases

### 1. WooCommerce Cart Redirect Enabled
When `woocommerce_cart_redirect_after_add` is 'yes':
- AddToCart events deferred
- Saved and rendered on next page
- Special hooks added (lines 194-197)
- Can fail if session/transient issues

### 2. AJAX Add to Cart
- Fragment system injects event code
- Uses session to share event_id
- jQuery event listener for one-time trigger
- Can fail if jQuery not loaded or fragments not working

### 3. Single Search Result Redirect
- WooCommerce redirects to product page
- Can't inject event on redirect response
- Must store in session
- Inject on product page
- Requires session to be available

### 4. Multiple Purchase Hooks
Four different hooks can trigger Purchase event:
- Each checks duplicate flag
- First one to fire wins
- Others are blocked by transient/meta
- Transient expires in 45 minutes
- Can cause issues if hooks fire in unexpected order

### 5. Admin Order Creation
- Explicitly blocked with `is_admin_user()` check
- Prevents tracking manual admin orders
- Can be confusing during testing

### 6. Failed/Cancelled Orders
- Purchase only tracks: processing, completed, on-hold, pending
- Failed/cancelled/refunded are ignored
- No refund events are fired

### 7. Variable Products
- Content type switches to 'product_group'
- Different product IDs used
- Parent vs. variation ID handling

### 8. WooCommerce Blocks
- Separate hook for checkout block
- Different initialization path
- Might need special handling

### 9. Cookie/Session Dependencies
Multiple features depend on sessions:
- AddToCart event_id sharing
- Search event storage
- _fbp and _fbc cookies
- Can fail if sessions disabled or cookies blocked

### 10. Pending Events System
- Events can be queued with `$send_now = false`
- Sent on `shutdown` hook
- Can be lost if script terminates early
- Currently only PageView uses this (line 286)

---

## Testing Checklist

To verify events are firing, check:

### Prerequisites
- [ ] Pixel ID configured
- [ ] `is_pixel_enabled()` returns true
- [ ] Not filtered by `facebook_for_woocommerce_integration_pixel_enabled`
- [ ] Connection to Facebook established

### Per Event Type
- [ ] Relevant WordPress/WooCommerce hook fires
- [ ] All conditional checks pass
- [ ] Valid objects exist (product, order, cart)
- [ ] Duplicate prevention not triggered
- [ ] Session/cookies available if needed
- [ ] Not admin user (for Purchase)
- [ ] Valid status (for Purchase)

### Output Verification
- [ ] Base pixel code in `<head>`
- [ ] Event code in page HTML
- [ ] Network request to Facebook (F12 Network tab)
- [ ] CAPI server-side request logged
- [ ] No JavaScript errors
- [ ] Correct event_id used for deduplication

---

## Summary of ALL Blocking Conditions

### Global Blockers (Affect Everything)
1. No Pixel ID configured
2. Pixel disabled via filter
3. EventsTracker not initialized
4. Base pixel code not rendered
5. Facebook connection failed

### Event-Specific Blockers
6. Invalid product/order/cart object
7. Wrong page type (category page, product page, etc.)
8. Duplicate event detection
9. Empty results (search, category)
10. Cart empty (InitiateCheckout)
11. Invalid order status (Purchase)
12. Already tracked (Purchase)
13. Admin user (Purchase)
14. Missing dependencies (WC Subscriptions for Subscribe)
15. Session not available (various)
16. Headers already sent (CAPI cookies)
17. Not main query (Search)
18. Cart item doesn't exist (AddToCart)
19. AAM disabled (Advanced Matching data)
20. Network/API errors

This is the complete end-to-end flow of how Facebook Pixel events work in this WooCommerce integration!
