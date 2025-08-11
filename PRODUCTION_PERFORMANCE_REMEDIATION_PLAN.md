*** Begin Patch
*** Update File: /Users/david/Local Sites/site19/app/public/wp-content/plugins/facebook-for-woocommerce/PRODUCTION_PERFORMANCE_REMEDIATION_PLAN.md
@@
 ### Medium-term improvements (plugin edits)
@@
 Backward compatibility:
 - Default remains synchronous until “Async events” is enabled.
 - All existing filters continue to apply to async payloads.
 
 ---
 
+### Concrete code migration examples (before → after)
+
+This section shows exactly what code can be moved off the frontend request path and how S2S code is shifted to async. Examples are illustrative and designed to fit the plugin’s structure.
+
+#### 1) Replace synchronous S2S sends with async queue
+
+- Before (synchronous send on the critical path):
+  ```php
+  // facebook-commerce-events-tracker.php
+  protected function send_api_event( Event $event, bool $send_now = true ) {
+    $this->tracked_events[] = $event;
+
+    if ( $send_now ) {
+      facebook_for_woocommerce()->get_api()->send_pixel_events(
+        facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(),
+        array( $event )
+      );
+    } else {
+      $this->pending_events[] = $event;
+    }
+  }
+  ```
+
+- After (async dispatch with Action Scheduler):
+  ```php
+  // facebook-commerce-events-tracker.php
+  protected function send_api_event( Event $event, bool $send_now = true ) {
+    $this->tracked_events[] = $event;
+
+    $async_enabled = (bool) get_option( 'fbwc_async_s2s_enabled', false );
+    if ( $async_enabled ) {
+      $this->get_async_buffer()->collect_event( $event );
+      return;
+    }
+
+    if ( $send_now ) {
+      facebook_for_woocommerce()->get_api()->send_pixel_events(
+        facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(),
+        array( $event )
+      );
+    } else {
+      $this->pending_events[] = $event;
+    }
+  }
+
+  private function get_async_buffer() {
+    if ( empty( $this->async_buffer ) ) {
+      $this->async_buffer = new \WooCommerce\Facebook\Events\AsyncEventBuffer();
+    }
+    return $this->async_buffer;
+  }
+
+  // On shutdown, schedule the async job instead of sending HTTP
+  add_action( 'shutdown', function() {
+    $async_enabled = (bool) get_option( 'fbwc_async_s2s_enabled', false );
+    if ( ! $async_enabled || empty( $this->async_buffer ) ) { return; }
+    $events = $this->async_buffer->drain();
+    if ( ! empty( $events ) ) {
+      as_enqueue_async_action( 'fbwc_process_s2s_batch', [ 'events' => $events ] );
+    }
+  }, 99 );
+  ```
+
+#### 2) New request-scoped buffer to collect events (moved off critical path)
+
+```php
+// includes/Events/AsyncEventBuffer.php
+namespace WooCommerce\Facebook\Events;
+
+class AsyncEventBuffer {
+  private $events = [];
+
+  public function collect_event( $event ) {
+    // Optionally normalize here; must preserve event_id for dedup
+    $this->events[] = $event;
+  }
+
+  public function drain() {
+    $drained = $this->events;
+    $this->events = [];
+    return $drained;
+  }
+}
+```
+
+#### 3) Async worker that performs the actual HTTP (shifted to background)
+
+```php
+// includes/Jobs/ProcessPixelEvents.php
+namespace WooCommerce\Facebook\Jobs;
+
+class ProcessPixelEvents {
+  public function __construct() {
+    add_action( 'fbwc_process_s2s_batch', [ $this, 'process_batch' ], 10, 1 );
+  }
+
+  public function process_batch( array $payload ) {
+    $events   = $payload['events'] ?? [];
+    $api      = facebook_for_woocommerce()->get_api();
+    $pixel_id = facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id();
+
+    if ( empty( $events ) || ! $pixel_id ) { return; }
+
+    $this->send_with_retry( $api, $pixel_id, $events );
+  }
+
+  private function send_with_retry( $api, $pixel_id, array $events ) {
+    $max_retries = (int) get_option( 'fbwc_async_max_retries', 3 );
+    $attempt = 0;
+
+    while ( $attempt < $max_retries ) {
+      $attempt++;
+      $response = null;
+      try {
+        $response = $api->send_pixel_events( $pixel_id, $events );
+        if ( $response && ! is_wp_error( $response ) ) {
+          do_action( 'fbwc_s2s_event_sent', $events, true, 0 );
+          return;
+        }
+      } catch ( \Throwable $e ) {
+        // log error
+      }
+
+      // backoff with jitter
+      $delay = min( 300, pow( 2, $attempt ) + wp_rand( 1, 5 ) );
+      sleep( $delay );
+    }
+
+    do_action( 'fbwc_s2s_event_sent', $events, false, 0 );
+  }
+}
+```
+
+#### 4) Batch events per request/session (fewer HTTP calls)
+
+```php
+// includes/Events/RequestBatcher.php
+namespace WooCommerce\Facebook\Events;
+
+class RequestBatcher {
+  private $buffer = [];
+  private $max_batch_size;
+
+  public function __construct( $max_batch_size = 50 ) {
+    $this->max_batch_size = $max_batch_size;
+  }
+
+  public function add( $event ) {
+    $this->buffer[] = $event;
+    if ( count( $this->buffer ) >= $this->max_batch_size ) {
+      return $this->flush();
+    }
+    return [];
+  }
+
+  public function flush() {
+    $batch = $this->buffer;
+    $this->buffer = [];
+    return $batch;
+  }
+}
+```
+
+Usage (in the tracker while collecting events):
+```php
+$batcher = isset( $this->batcher ) ? $this->batcher : ( $this->batcher = new \WooCommerce\Facebook\Events\RequestBatcher( 50 ) );
+$ready_batch = $batcher->add( $event );
+if ( ! empty( $ready_batch ) ) {
+  as_enqueue_async_action( 'fbwc_process_s2s_batch', [ 'events' => $ready_batch ] );
+}
+```
+
+#### 5) Restrict S2S to critical events only (shift non-critical off S2S)
+
+```php
+// wp-content/mu-plugins/fbwc-selective-s2s.php
+add_filter( 'wc_facebook_api_pixel_event_request_data', function( $data, $request ) {
+  if ( ! ( $request instanceof \WooCommerce\Facebook\API\Pixel\Events\Request ) ) { return $data; }
+  $allowed = (array) get_option( 'fbwc_s2s_enabled_events', [ 'Purchase', 'InitiateCheckout' ] );
+  $data['data'] = array_values( array_filter( $data['data'] ?? [], function( $event ) use ( $allowed ) {
+    return in_array( $event['event_name'] ?? '', $allowed, true );
+  } ) );
+  return $data;
+}, 10, 2 );
+```
+
+#### 6) Ensure WhatsApp and other heavy calls run on order lifecycle hooks only
+
+```php
+// wp-content/mu-plugins/fbwc-whatsapp-scope.php
+add_action( 'woocommerce_order_status_completed', function( $order_id ) {
+  // Only send WhatsApp messages on order completion, not on general page views
+  if ( ! $order_id ) { return; }
+  \WooCommerce\Facebook\Handlers\WhatsAppUtilityConnection::post_whatsapp_utility_messages_events_call( /* args */ );
+}, 10 );
+```
+
+This relocates heavy network operations strictly to order lifecycle events, away from generic page requests.
+
+---
+
 ### Implementation milestones and deliverables
*** End Patchtegorized error handling (HTTP vs Graph).
- Kill-switch: immediate pause of async dispatch; queue inspection via Admin UI and WP-CLI.

Proposed components:
- `EventBuffer` (new): Collects S2S events during the request; validates and tags with `event_id`.
- `AsyncEventDispatcher` (new): Translates buffer to an Action Scheduler job.
- `SendS2SBatchJob` (new): Batches by pixel/session, calls Graph `/events`, implements retry/backoff.

Admin UI:
- Checkbox: “Enable async server-side events”.
- Toggles: “Enable S2S for…” [Purchase, InitiateCheckout, AddToCart, ViewContent, …].
- Advanced: “Max retries”, “Initial backoff (ms)”, “Backoff multiplier”.
- Health: queue depth, last error, last success, success rate (24h), average delivery latency.

Observability:
- Structured logs for enqueue, batch sizes, durations, retries, error categories.
- Admin status panel summarizing queue health.

Backward compatibility:
- Default remains synchronous until “Async events” is enabled.
- All existing filters continue to apply to async payloads.

---

### Concrete code migration examples (before → after)

This section shows exactly what code can be moved off the frontend request path and how S2S code is shifted to async. Examples are illustrative and designed to fit the plugin’s structure.

#### 1) Replace synchronous S2S sends with async queue

- Before (synchronous send on the critical path):
  ```php
  // facebook-commerce-events-tracker.php
  protected function send_api_event( Event $event, bool $send_now = true ) {
    $this->tracked_events[] = $event;

    if ( $send_now ) {
      facebook_for_woocommerce()->get_api()->send_pixel_events(
        facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(),
        array( $event )
      );
    } else {
      $this->pending_events[] = $event;
    }
  }
  ```

- After (async dispatch with Action Scheduler):
  ```php
  // facebook-commerce-events-tracker.php
  protected function send_api_event( Event $event, bool $send_now = true ) {
    $this->tracked_events[] = $event;

    $async_enabled = (bool) get_option( 'fbwc_async_s2s_enabled', false );
    if ( $async_enabled ) {
      $this->get_async_buffer()->collect_event( $event );
      return;
    }

    if ( $send_now ) {
      facebook_for_woocommerce()->get_api()->send_pixel_events(
        facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id(),
        array( $event )
      );
    } else {
      $this->pending_events[] = $event;
    }
  }

  private function get_async_buffer() {
    if ( empty( $this->async_buffer ) ) {
      $this->async_buffer = new \WooCommerce\Facebook\Events\AsyncEventBuffer();
    }
    return $this->async_buffer;
  }

  // On shutdown, schedule the async job instead of sending HTTP
  add_action( 'shutdown', function() {
    $async_enabled = (bool) get_option( 'fbwc_async_s2s_enabled', false );
    if ( ! $async_enabled || empty( $this->async_buffer ) ) { return; }
    $events = $this->async_buffer->drain();
    if ( ! empty( $events ) ) {
      as_enqueue_async_action( 'fbwc_process_s2s_batch', [ 'events' => $events ] );
    }
  }, 99 );
  ```

#### 2) New request-scoped buffer to collect events (moved off critical path)

```php
// includes/Events/AsyncEventBuffer.php
namespace WooCommerce\Facebook\Events;

class AsyncEventBuffer {
  private $events = [];

  public function collect_event( $event ) {
    // Optionally normalize here; must preserve event_id for dedup
    $this->events[] = $event;
  }

  public function drain() {
    $drained = $this->events;
    $this->events = [];
    return $drained;
  }
}
```

#### 3) Async worker that performs the actual HTTP (shifted to background)

```php
// includes/Jobs/ProcessPixelEvents.php
namespace WooCommerce\Facebook\Jobs;

class ProcessPixelEvents {
  public function __construct() {
    add_action( 'fbwc_process_s2s_batch', [ $this, 'process_batch' ], 10, 1 );
  }

  public function process_batch( array $payload ) {
    $events   = $payload['events'] ?? [];
    $api      = facebook_for_woocommerce()->get_api();
    $pixel_id = facebook_for_woocommerce()->get_integration()->get_facebook_pixel_id();

    if ( empty( $events ) || ! $pixel_id ) { return; }

    $this->send_with_retry( $api, $pixel_id, $events );
  }

  private function send_with_retry( $api, $pixel_id, array $events ) {
    $max_retries = (int) get_option( 'fbwc_async_max_retries', 3 );
    $attempt = 0;

    while ( $attempt < $max_retries ) {
      $attempt++;
      $response = null;
      try {
        $response = $api->send_pixel_events( $pixel_id, $events );
        if ( $response && ! is_wp_error( $response ) ) {
          do_action( 'fbwc_s2s_event_sent', $events, true, 0 );
          return;
        }
      } catch ( \Throwable $e ) {
        // log error
      }

      // backoff with jitter
      $delay = min( 300, pow( 2, $attempt ) + wp_rand( 1, 5 ) );
      sleep( $delay );
    }

    do_action( 'fbwc_s2s_event_sent', $events, false, 0 );
  }
}
```

#### 4) Batch events per request/session (fewer HTTP calls)

```php
// includes/Events/RequestBatcher.php
namespace WooCommerce\Facebook\Events;

class RequestBatcher {
  private $buffer = [];
  private $max_batch_size;

  public function __construct( $max_batch_size = 50 ) {
    $this->max_batch_size = $max_batch_size;
  }

  public function add( $event ) {
    $this->buffer[] = $event;
    if ( count( $this->buffer ) >= $this->max_batch_size ) {
      return $this->flush();
    }
    return [];
  }

  public function flush() {
    $batch = $this->buffer;
    $this->buffer = [];
    return $batch;
  }
}
```

Usage (in the tracker while collecting events):
```php
$batcher = isset( $this->batcher ) ? $this->batcher : ( $this->batcher = new \WooCommerce\Facebook\Events\RequestBatcher( 50 ) );
$ready_batch = $batcher->add( $event );
if ( ! empty( $ready_batch ) ) {
  as_enqueue_async_action( 'fbwc_process_s2s_batch', [ 'events' => $ready_batch ] );
}
```

#### 5) Restrict S2S to critical events only (shift non-critical off S2S)

```php
// wp-content/mu-plugins/fbwc-selective-s2s.php
add_filter( 'wc_facebook_api_pixel_event_request_data', function( $data, $request ) {
  if ( ! ( $request instanceof \WooCommerce\Facebook\API\Pixel\Events\Request ) ) { return $data; }
  $allowed = (array) get_option( 'fbwc_s2s_enabled_events', [ 'Purchase', 'InitiateCheckout' ] );
  $data['data'] = array_values( array_filter( $data['data'] ?? [], function( $event ) use ( $allowed ) {
    return in_array( $event['event_name'] ?? '', $allowed, true );
  } ) );
  return $data;
}, 10, 2 );
```

#### 6) Ensure WhatsApp and other heavy calls run on order lifecycle hooks only

```php
// wp-content/mu-plugins/fbwc-whatsapp-scope.php
add_action( 'woocommerce_order_status_completed', function( $order_id ) {
  // Only send WhatsApp messages on order completion, not on general page views
  if ( ! $order_id ) { return; }
  \WooCommerce\Facebook\Handlers\WhatsAppUtilityConnection::post_whatsapp_utility_messages_events_call( /* args */ );
}, 10 );
```

This relocates heavy network operations strictly to order lifecycle events, away from generic page requests.

---

### Implementation milestones and deliverables

- **Phase 0 (Today; no edits)**
  - Decide S2S policy: disable globally vs purchase-only.
  - Install mu-plugins for timeout capping and optional event filtering.
  - Verify heavy features are admin/cron-only.

- **Phase 1 (Plugin edits)**
  - Implement Action Scheduler-based async S2S with batching.
  - Add settings: “Async events” and per-event S2S toggles.
  - Implement retry/backoff and structured logging.

- **Phase 2 (Hardening)**
  - Tune backoff; add dashboards/metrics.
  - Documentation and migration guide.
  - WP-CLI: inspect/flush queue; toggle kill-switch.

---

### Measurement plan

- **Baseline**
  - Record TTFB across product/category/home/cart/checkout with S2S enabled.
  - Instrument durations for each `/events` call (via WP debug log or server profiling).

- **After immediate mitigations**
  - Expect ~200–300 ms reduction per removed call.
  - Validate browser pixel events; if purchase-only S2S, confirm acceptable conversion parity.

- **After async rollout**
  - Confirm TTFB unaffected by S2S across all routes.
  - Monitor queue health: depth, success rate, retries, P95 delivery latency.

---

### Acceptance criteria

- **Performance**
  - TTFB reduced by ~200–300 ms per `/events` call eliminated from the critical path.
  - No TTFB regression on browsing routes (product/category/home).
  - Checkout/thank-you TTFB within target SLO even when S2S is active.

- **Tracking fidelity**
  - Browser pixel fires reliably across routes.
  - If purchase-only S2S: conversion reporting parity remains acceptable.

- **Operational stability**
  - No increase in PHP errors/timeouts.
  - Async queue maintains high success rate and bounded retries.
  - Clear observability and simple rollback.

---

### Risks and mitigations

- **Reduced dedup/match if S2S disabled**
  - Keep browser pixel and retain S2S for high-value events.
  - Preserve consistent `event_id` across browser and S2S.

- **Async backlog during incidents**
  - Exponential backoff with jitter and max caps.
  - Health checks for queue depth/failures; kill-switch; WP-CLI to drain.

- **Third-party network variance**
  - Immediate timeout capping via filter; long-term async processing removes HTTP from request path.

---

### Rollback plan

- Remove mu-plugins (`fbwc-http-tuning.php`, `fbwc-s2s-filter.php`) and/or re-enable S2S settings.
- Toggle off “Async events” to revert to synchronous mode.
- Revert plugin version if necessary.

---

### Appendix — Ready-to-use snippets

- **Cap `/events` HTTP latency**
  ```php
  // wp-content/mu-plugins/fbwc-http-tuning.php
  <?php
  add_filter('wc_facebook_for_woocommerce_http_request_args', function($args, $api) {
    if (method_exists($api, 'get_request')) {
      $request = $api->get_request();
      if ($request instanceof \WooCommerce\Facebook\API\Pixel\Events\Request) {
        $args['timeout'] = 0.7;
        $args['redirection'] = 0;
      }
    }
    return $args;
  }, 10, 2);
  ```

- **Keep only critical S2S events**
  ```php
  // wp-content/mu-plugins/fbwc-s2s-filter.php
  <?php
  add_filter('wc_facebook_api_pixel_event_request_data', function($data, $request) {
    if (!($request instanceof \WooCommerce\Facebook\API\Pixel\Events\Request)) { return $data; }
    $data['data'] = array_values(array_filter($data['data'] ?? [], function($event) {
      $name = $event['event_name'] ?? '';
      return in_array($name, ['Purchase', 'InitiateCheckout'], true);
    }));
    return $data;
  }, 10, 2);
  ```

- **Gate pixel init on home/front page (optional)**
  ```php
  add_filter('facebook_for_woocommerce_integration_pixel_enabled', function($enabled) {
    if (is_front_page() || is_home()) { return false; }
    return $enabled;
  });
  ```

---

### Appendix — Support response template (optional)

Hi <name>,

Thanks for the detailed profiling. We see the added latency originates from synchronous calls to Facebook Graph `/events` made during page render. You have options that do not require modifying the plugin:

- Disable server-side events globally in the plugin settings to remove these calls.
- Keep S2S only for Purchase/InitiateCheckout and cap the HTTP timeout to <1s using a small mu-plugin (snippets included in our plan).
- For a long-term solution, we recommend enabling the new async S2S mode (once available) which moves all server calls off the request path.

These changes preserve the browser pixel and can dramatically reduce TTFB (200–300 ms per `/events` call). We included rollback steps in case you’d like to revert quickly. We’re happy to assist with rollout and testing.

---

### References (pointers)

- `includes/API/Pixel/Events/Request.php` — builds `/events` request.
- `includes/API.php` — `send_pixel_events()` path.
- `includes/Framework/Api/Base.php` — HTTP args (`timeout`, `blocking`).
- `includes/API/Plugin/InitializeRestAPI.php` — admin-scoped REST scaffolding.
- `includes/Admin.php` — admin gating.
- `facebook-commerce-pixel-event.php` — S2S flag constant pointer.

