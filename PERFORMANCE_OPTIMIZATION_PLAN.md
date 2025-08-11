## Facebook for WooCommerce — Performance Optimization Plan

### Objective
Reduce frontend page-load latency caused by Facebook Graph API server-side Pixel events (S2S) — notably `https://graph.facebook.com/v21.0/<pixel_id>/events` — by deferring or limiting them and scoping heavy features to admin/background, without breaking tracking or compliance.

---

### Summary of findings (root cause)
- Server-side Pixel events are fired during frontend requests via WooCommerce hooks, and are sent using blocking HTTP with long timeouts. This can add ~200–300ms per call to TTFB.
- Some events are deferred to `shutdown`, but that still runs before PHP returns the response, so it remains on the critical path.

Key code paths:
```30:150:facebook-commerce-events-tracker.php
private function add_hooks() {
  // Frontend hooks (Pixel injection + event hooks)
  add_action( 'wp_head', array( $this, 'inject_base_pixel' ) );
  add_action( 'wp_footer', array( $this, 'inject_base_pixel_noscript' ) );
  add_action( 'woocommerce_after_single_product', array( $this, 'inject_view_content_event' ) );
  add_action( 'woocommerce_after_shop_loop', array( $this, 'inject_view_category_event' ) );
  add_action( 'woocommerce_after_checkout_form', array( $this, 'inject_initiate_checkout_event' ) );
  add_action( 'woocommerce_thankyou', array( $this, 'inject_purchase_event' ) );
  // Flush pending events on shutdown (still blocks the response)
  add_action( 'shutdown', array( $this, 'send_pending_events' ) );
}
```

```1018:1033:facebook-commerce-events-tracker.php
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

```604:618:includes/API.php
public function send_pixel_events( $pixel_id, array $events ) {
  $request = new API\Pixel\Events\Request( $pixel_id, $events );
  $this->set_response_handler( Response::class );
  return $this->perform_request( $request );
}
```

```315:341:includes/Framework/Api/Base.php
protected function get_request_args() {
  $args = [
    'method'      => $this->get_request_method(),
    'timeout'     => MINUTE_IN_SECONDS, // 60s
    'redirection' => 0,
    'httpversion' => $this->get_request_http_version(),
    'sslverify'   => true,
    'blocking'    => true,
    // ...
  ];
  return apply_filters( 'wc_' . $this->get_api_id() . '_http_request_args', $args, $this );
}
```

S2S configurable flag:
```1:120:facebook-commerce-pixel-event.php
class WC_Facebookcommerce_Pixel {
  const USE_S2S_KEY = 'use_s2s';
  // ...
}
```

---

### Goals and non-goals
- **Goals**:
  - Remove S2S calls from the frontend critical path (or drastically reduce their latency).
  - Maintain tracking fidelity (browser pixel remains; optionally keep S2S for critical events like Purchase).
- **Non-goals**:
  - Rewrite the overall tracking model.
  - Change business logic around catalog sync or WhatsApp messaging.

---

### Immediate actions (no plugin edits)

- **Disable S2S globally (fastest win)**
  - In plugin settings, turn off “Server-Side Events” (`use_s2s = false`).
  - Browser pixel remains; removes frontend Graph `/events` calls.

- **Keep S2S only on high-value flows (checkout/purchase)**
  - Restrict S2S to `Purchase` / `InitiateCheckout`; avoid S2S on product/category views.

- **Cap latency with an HTTP-args filter (safe, reversible)**
  - Add a small mu-plugin to reduce timeout only for Pixel `/events`:
  ```php
  <?php
  // wp-content/mu-plugins/fbwc-http-tuning.php
  add_filter('wc_facebook_for_woocommerce_http_request_args', function($args, $api) {
    if (method_exists($api, 'get_request')) {
      $request = $api->get_request();
      if ($request instanceof \WooCommerce\Facebook\API\Pixel\Events\Request) {
        $args['timeout'] = 0.7; // was 60s
        $args['redirection'] = 0;
      }
    }
    return $args;
  }, 10, 2);
  ```

- **Optional: Gate Pixel init on lower-value routes**
  ```php
  add_filter('facebook_for_woocommerce_integration_pixel_enabled', function($enabled) {
    if (is_front_page() || is_home()) { return false; }
    return $enabled;
  });
  ```

- **Optional: Filter S2S payloads (drop non-critical events)**
  ```php
  add_filter('wc_facebook_api_pixel_event_request_data', function($data, $request) {
    if (!($request instanceof \WooCommerce\Facebook\API\Pixel\Events\Request)) { return $data; }
    $data['data'] = array_values(array_filter($data['data'] ?? [], function($event) {
      $name = $event['event_name'] ?? '';
      return in_array($name, ['Purchase', 'InitiateCheckout'], true);
    }));
    return $data;
  }, 10, 2);
  ```

---

### Admin vs frontend scoping (confirmation)

- Admin UI, REST framework, and catalog management are already admin-gated.
- Frontend performance impact stems from Pixel S2S events:
  - Keep WhatsApp/order notifications on order lifecycle hooks, not generic page views.
  - Ensure catalog sync and feed ops remain admin/cron/background only.

Key admin gating examples:
```25:52:includes/API/Plugin/InitializeRestAPI.php
public function __construct() {
  // Admin-only generation of REST framework JS
  add_action( 'admin_enqueue_scripts', [ $this, 'generate_js_request_framework' ] );
  $this->init_rest_api_framework();
}
```

```61:83:includes/Admin.php
public function __construct() {
  // ...
  add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
  // Only alter admin UI if connected & catalog present
  if ( ! $plugin->get_connection_handler()->is_connected() || ! $plugin->get_integration()->get_product_catalog_id() ) {
    return;
  }
  // ...
}
```

---

### Medium-term improvements (requires plugin edits)

1) **Asynchronous S2S using Action Scheduler**
- Collect events during request → enqueue async job → worker sends to Graph.
- Response returns immediately; no blocking HTTP on frontend.
- Preserve dedup via `event_id`.

2) **Batch events**
- Send one `/events` request per request/session instead of one-per-event.

3) **Per-event S2S toggles**
- Settings to enable S2S only for `Purchase`/`InitiateCheckout`.

4) **“Async events” setting and retry policy**
- Toggle between sync and async modes.
- Retries with exponential backoff and jitter; logs/metrics.

High-level flow:
```text
[Frontend Hooks] --(collect)--> [EventBuffer]
  -> If Sync: send immediately (blocking)  [current]
  -> If Async: schedule "fbwc_send_s2s_batch"
[Worker] --(batch + retry/backoff)--> Graph /events
```

---

### Implementation plan and milestones

- **Phase 0 (Today; no edits)**
  - Decide S2S policy (disable vs purchase-only).
  - Add HTTP-args mu-plugin if S2S remains.
  - Validate WhatsApp/catalog paths are admin/cron only.

- **Phase 1 (Code changes)**
  - Implement Async S2S (Action Scheduler), batching, per-event toggles.
  - Add settings: “Async events” + per-event S2S enable/disable.
  - Add logging/observability.

- **Phase 2 (Hardening)**
  - Tune backoff; error reporting; dashboard metrics.
  - Docs and migration guide.

---

### Measurement plan

- **Baseline**: Record TTFB on product/category pages; log `/events` durations.
- **After immediate actions**: Re-measure TTFB; expect ~200–300ms reduction per removed call. Verify Events Manager metrics.
- **After medium-term changes**: Confirm TTFB is unaffected by S2S; monitor queue health (success rate, retries, latency).

---

### Acceptance criteria

- **Performance**: TTFB reduced by ~200–300ms per `/events` call removed or deferred.
- **Tracking**: Browser pixel fires; S2S (if purchase-only) maintains acceptable conversion parity.
- **Operational**: No increase in PHP errors/timeouts; async queue stable.

---

### Risks and mitigations

- Reduced S2S may affect dedup/match quality.
  - Keep browser pixel; retain S2S for Purchase if needed.
- Async backlog/incident scenarios.
  - Implement retries with backoff; expose queue health; add kill-switch.

---

### Rollback plan

- Remove mu-plugin filters and/or re-enable S2S in settings.
- Toggle off “Async events” (when implemented).
- Revert plugin version if necessary.

---

### Appendix — Code references (pointers)

- Frontend hookup + shutdown flush:
```30:150:facebook-commerce-events-tracker.php
private function add_hooks() {
  add_action( 'wp_head', array( $this, 'inject_base_pixel' ) );
  // ...
  add_action( 'shutdown', array( $this, 'send_pending_events' ) );
}
```

- S2S send path (synchronous):
```1018:1033:facebook-commerce-events-tracker.php
protected function send_api_event( Event $event, bool $send_now = true ) {
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

- API call to Graph `/events`:
```604:618:includes/API.php
public function send_pixel_events( $pixel_id, array $events ) {
  $request = new API\Pixel\Events\Request( $pixel_id, $events );
  $this->set_response_handler( Response::class );
  return $this->perform_request( $request );
}
```

- Blocking HTTP with long timeout (filterable):
```315:341:includes/Framework/Api/Base.php
protected function get_request_args() {
  $args = [
    'timeout'     => MINUTE_IN_SECONDS,
    'blocking'    => true,
    // ...
  ];
  return apply_filters( 'wc_' . $this->get_api_id() . '_http_request_args', $args, $this );
}
```

- `/events` request builder:
```31:42:includes/API/Pixel/Events/Request.php
public function __construct( $pixel_id, array $events ) {
  $this->events = $events;
  parent::__construct( "/{$pixel_id}/events", 'POST' );
}
```

- S2S config flag:
```18:28:facebook-commerce-pixel-event.php
class WC_Facebookcommerce_Pixel {
  const USE_S2S_KEY = 'use_s2s';
  // ...
}
```

- Admin-only REST framework init:
```20:31:includes/API/Plugin/InitializeRestAPI.php
public function __construct() {
  add_action( 'admin_enqueue_scripts', [ $this, 'generate_js_request_framework' ] );
  $this->init_rest_api_framework();
}
```

- Admin gating in `includes/Admin.php`:
```61:83:includes/Admin.php
add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
if ( ! $plugin->get_connection_handler()->is_connected() || ! $plugin->get_integration()->get_product_catalog_id() ) {
  return;
}
```

- WhatsApp messaging (ensure not on general page views):
```379:420:includes/Handlers/WhatsAppUtilityConnection.php
public static function post_whatsapp_utility_messages_events_call(...) {
  $response = wp_remote_post( $base_url, $options );
  // timeout 300s; ensure triggered only on order events
}
```

---

### Owners, timelines, and comms (template)
- Tech owner: <name>
- Product owner: <name>
- Phase 0: <date range>
- Phase 1: <date range>
- Phase 2: <date range>
- Stakeholders: <teams>
- Status updates: Weekly in <channel/doc>

