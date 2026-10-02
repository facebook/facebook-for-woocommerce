# Offline (physical store) events

This plugin can report sales rung up at a physical point of sale to Meta, so in-store conversions can be attributed to online ad spend. This document covers
how an order is recognised as an in-store sale, what is sent, and how to add support for another point-of-sale plugin.

For Meta's own documentation, see [Offline Events in the Conversions API](https://developers.facebook.com/documentation/ads-commerce/conversions-api/offline-events)
for the feature itself, and the [Omni Optimal Setup Guide](https://developers.facebook.com/documentation/ads-commerce/marketing-api/best-practices/omni-optimal-setup-guide) for how in-store signals fit into an omni-channel setup.

## What gets sent, and how

Offline Purchase events are sent as an ordinary `Purchase` event through the **Conversions API**, with `action_source` set to `physical_store`, so one send path serves every event the plugin emits.

There is no browser pixel counterpart. A sale at a till has no browser session, so there is nothing to deduplicate against and no shared `event_id` to maintain.

The point of sale's own pages — the till, and checkout screens it opens — are used by staff, not shoppers, so they send no storefront events at all: no pixel, no PageView, and no website Purchase. Offline events are unaffected, including one reported while such a page takes payment.

## Turning it on

The feature is **off by default** and is never enabled by an upgrade.

| | |
|---|---|
| Setting | **Enable Offline Events**, on the *Configuration* tab |
| Option | `wc_facebook_enable_offline_purchase_events` (`yes` / `no`) |
| Accessor | `WC_Facebookcommerce_Integration::is_offline_purchase_events_enabled()` |
| Filter | `wc_facebook_is_offline_purchase_events_enabled` |

The checkbox is rendered but not operable when no supported POS plugin is active — the setting would have no effect, and hiding it would make the feature undiscoverable. An existing opt-in is kept while the POS plugin is inactive: saving the *Configuration* tab leaves the setting, and its opt-in time, as they were. Switching the feature on follows the same rule whether it is done from the settings screen or over AJAX.

On a store connected to Meta, admins with a supported POS plugin active see a banner on the plugin's admin page introducing the feature, with a one-click link to the setting. It disappears once the store opts in, never returns after an opt-out, and each admin can dismiss it for themselves.

It can also be toggled over `admin-ajax.php` by a user with `manage_woocommerce`, with a nonce for the matching action:

- `wc_facebook_enable_offline_events`
- `wc_facebook_disable_offline_events`
- `wc_facebook_get_offline_events_status`

Enabling is refused when no supported POS plugin is active. Disabling always succeeds, so the setting can be cleared after a POS plugin is deactivated.

## How an order is detected

Detection runs from two places in `facebook-commerce-events-tracker.php`:

- **`inject_purchase_event()`**, on `woocommerce_new_order` and the other purchase hooks. This catches orders that are already paid when they are created, such as orders a till syncs after the fact.
- **`inject_offline_purchase_event_on_status_change()`**, on `woocommerce_order_status_changed`. Point-of-sale orders are usually created before payment is taken (WCPOS opens them as `pos-open`), so this is what reports them once they become paid.

Both call `is_offline_event()`, which asks `POS_Integration_Registry::match()` for the first **active** integration that claims the order.

If an integration claims it, the order **never** takes the web path, whether or not the merchant has opted in. With the opt-in it goes to `track_offline_purchase_event()`; without it, it is not reported at all. A till sale is not a website purchase, and reporting it as one would send it from the till's browser and IP.

### Order-level gates

Before anything is sent, the order must pass:

- **Paid** — `$order->is_paid()` is true: `processing` or `completed` by default, extendable through WooCommerce's `woocommerce_order_is_paid_statuses` filter. Waiting for payment means the event carries the order's final customer, billing details and totals. Open, partially paid, pending and on-hold orders are reported later, when they become paid.
- **Paid after opting in** — sales paid before the merchant turned the feature on are never reported, so enabling it does not backfill history. Turning the feature off and on again starts a new window.
- **Not already reported** — the `_meta_offline_purchase_tracked` order meta is absent.
- **Matchable** — the order has enough customer information for Meta to accept the event. See [When an event is not sent](#when-an-event-is-not-sent).
- **Not in flight** — a 45-minute transient guards against concurrent processes reporting the same order twice.

The transient is set just before sending, to stop a concurrent request sending the same order. The meta is written only **after Meta accepts the event**: if the request fails or Meta rejects it, the transient is released and the order is left unmarked, so the next status change tries again. An order held back by any gate is likewise left unmarked, so it can still be reported later.

### The admin-user gate does not apply

The web path skips users with `manage_woocommerce` so that staff browsing the storefront do not pollute pixel data. That reasoning does not apply to a
server-side sale at a till, so offline events bypass the check.

This matters in practice: WCPOS's dedicated `cashier` role does **not** hold `manage_woocommerce`, but `shop_manager` and `administrator` do, and both can
operate the POS. Without the bypass, whether a sale was reported would depend on who was signed in.

### The signals hold does not apply

`FacebookSignalsState` can hold CAPI sends while a web visitor's consent state is unresolved. Offline events are exempt, alongside the existing admin and cron
carve-outs: a sale at a till has no browser session that consent could describe, and a held event would be stranded, because the queue is released by a storefront
AJAX call a POS terminal never makes.

## What the event contains

The event carries the order's contents and value, the order ID, and — where the POS records one — the store it was sold in (`store_data`). Its time is when the order was paid, not when it was reported. The aim is to capture the data points Meta describes in the
[Omni Optimal Setup Guide](https://developers.facebook.com/documentation/ads-commerce/marketing-api/best-practices/omni-optimal-setup-guide).

An integration can contribute additional `custom_data` fields by returning them from `get_event_data()`, and the store through `get_store_data()`. Store data is validated before sending: only `store_page_id` and `brand_page_id` (Facebook Page IDs) and `store_code` (up to 64 characters) are kept, and an invalid value is dropped rather than altered.

Signals that only make sense for a web visit are deliberately omitted. On a POS order created over REST they would describe the cashier's terminal rather than the
customer, and Meta flags them as invalid on a `physical_store` event.

## When an event is not sent

Meta only accepts an event it can match to a person. An event carrying too little customer information — a walk-in sale with no customer attached, where the only detail on the order is the store's own country — is rejected as low quality. See Meta's [Offline Events in the Conversions API](https://developers.facebook.com/documentation/ads-commerce/conversions-api/offline-events).

So the plugin checks first, and holds back an offline event unless the order has an email address, a phone number or a customer account. When it holds one back:

- **Nothing is sent**, rather than a request Meta is certain to reject.
- **The order is not marked as reported.** If a customer is attached afterwards, the event is sent on the order's next status change.
- **It is logged** in the WooCommerce logs, naming the order, so a merchant can see why a sale was not reported.

The same applies when an event is sent but **not delivered** — the request fails, or Meta rejects it. The order is not marked, a warning is logged, and the event is retried on the order's next status change. With the `wc_facebook_pixel_events_non_blocking` filter on, immediate transport errors also leave the order unmarked. Otherwise, the order is marked once the request is dispatched without waiting for Meta's answer, so a later rejection cannot be detected.

Some in-store sales will never be reported: a walk-in customer who gives no details cannot be matched, by this plugin or anything else. Capturing an email or phone number at the till, for example for a digital receipt, is what makes those sales count.

## Supported point-of-sale systems

[WCPOS](https://wordpress.org/plugins/woocommerce-pos/) is the first supported system, not the only intended one. Support for others is being added over time.

**The plugin is point-of-sale agnostic by design.** The registry and the `POS_Integration_Interface` contract exist so that no POS is privileged: every integration is detected the same way, and every order produces the same event regardless of which system rang it up. Being supported is not an endorsement, a partnership, or a statement about any vendor's merits, and the order of entries in `POS_Integration_Registry::INTEGRATIONS` is not a ranking — `match()` returns the
first integration that *claims* an order, and integrations are not expected to overlap.

Which systems are bundled next reflects practical considerations, among them the integration effort a given system involves and how many merchants it would reach.
That is not a fixed formula, a commitment, or a published roadmap, and no ordering or preference should be inferred from it.

In any case, nothing here needs to change for a system to be supported: `wc_facebook_pos_integrations` lets a POS vendor, an agency, or a single store register an integration from their own code, with exactly the same capabilities as the bundled one. If you maintain a point of sale and want it supported here, opening a pull request with an integration class is welcome.

## Adding a point-of-sale integration

Implement `POS_Integration_Interface` under `includes/Events/POS/` and add the class to `POS_Integration_Registry::INTEGRATIONS`.

```php
namespace WooCommerce\Facebook\Events\POS;

class My_POS_Integration implements POS_Integration_Interface {

    public function get_slug(): string {
        return 'my-pos';
    }

    public function get_name(): string {
        return __( 'My POS', 'facebook-for-woocommerce' );
    }

    public function is_supported(): bool {
        return function_exists( 'my_pos_is_pos_order' );
    }

    // Whether this request renders one of your POS's own pages (the till, its
    // checkout screens). Pages only: your POS's API calls must not match.
    public function is_pos_page_request(): bool {
        return my_pos_is_pos_page();
    }

    public function is_pos_order( \WC_Order $order ): bool {
        return $this->is_supported() && my_pos_is_pos_order( $order );
    }

    // The staff member who rang up the order, or 0 if your POS doesn't record one.
    // Used to avoid reporting the cashier's details as the customer's.
    public function get_cashier_id( \WC_Order $order ): int {
        return (int) $order->get_meta( '_my_pos_cashier', true );
    }

    // The physical store the order was sold in, as Meta's store_data (e.g.
    // store_code), or an empty array if your POS doesn't record one.
    public function get_store_data( \WC_Order $order ): array {
        return array();
    }

    public function get_event_data( \WC_Order $order ): array {
        return array();
    }
}
```

Third parties can register an integration without modifying the plugin, via the `wc_facebook_pos_integrations` filter. Entries that do not implement the interface are discarded.

```php
add_filter( 'wc_facebook_pos_integrations', function ( array $integrations ) {
    $integrations[] = new My_POS_Integration();
    return $integrations;
} );
```

`match()` returns the **first active integration that claims the order**, so ordering matters only if two integrations could both claim the same order — which they should not.

## Best practices

**Detect through the POS plugin's own public API.** The bundled WCPOS integration calls `wcpos_is_pos_order()` rather than reading the `_pos` meta or comparing
`created_via` itself. If the POS changes how it flags orders, the integration keeps working. Reach for raw meta only when no public helper exists.

**Keep `is_supported()` and `is_pos_page_request()` cheap and side-effect free.** They run on every order that reaches the purchase hooks, on every order status change, and on every storefront page load, to tell point-of-sale pages apart. A `function_exists()` or `class_exists()` check, or reading a query var, is the right shape. Do not query the database, hit the network, or write options.

**Never write from `is_pos_order()`.** It is a predicate. All state changes belong in the tracker, which already orders them correctly against the send.

**Check the plugin is loaded, not merely installed.** `is_supported()` should confirm the API you are about to call actually exists. An activated-but-half-loaded
plugin should report as unsupported rather than fatal later.

**Claim every order your POS creates.** The tracker never sends an order your integration claims down the web path, but an order `is_pos_order()` misses is treated as a web order and reported as a website Purchase. If your POS also drives a web checkout for the same order, verify only one of `_meta_offline_purchase_tracked` and `_meta_purchase_tracked_server` ends up on it.

**Exclude training and test-mode sales.** Many POS systems have a practice mode. Those orders should not reach Meta; filter them out in `is_pos_order()`.

**Pass through whatever customer identifiers the POS captured.** Match quality depends on them more than on anything else an integration can do. Meta's [Omni Optimal Setup Guide](https://developers.facebook.com/documentation/ads-commerce/marketing-api/best-practices/omni-optimal-setup-guide) covers which identifiers are worth capturing and how they should be handled. Do not invent them, and do not pass the cashier's details — they are not the customer.

**Report the store and the cashier through their own methods, never as customer data.** They describe where a sale happened and who rang it up, not who bought it. Return the store from `get_store_data()` and the cashier's user ID from `get_cashier_id()`; the tracker uses the latter to avoid reporting staff details as the customer's. The bundled WCPOS integration reads them from `_pos_store` and `_pos_user`.

**Treat in-store events as one input to an omni-channel setup, not a standalone feature.** The guide linked above also covers how offline signals combine with web events, catalog, and campaign configuration. Reporting in-store purchases is necessary but not sufficient — the value shows up once the rest of that setup is in place.

## Testing

Unit tests stub detection through the `wc_facebook_pos_integrations` filter rather than requiring a POS plugin, so they run in CI where none is installed — your integration's `is_supported()` should return `false` there without erroring. They live in:

- `tests/Unit/Events/POS/` — the registry and the WCPOS integration
- `tests/Unit/Events/` — when events are reported (`OfflinePurchaseTriggerTest`), their user data (`OfflineEventUserDataTest`), the signals hold (`OfflineEventSignalsHoldTest`) and point-of-sale pages (`PosPageEventsTest`)
- `tests/Unit/` — the opt-in setting's AJAX actions (`OfflineEventsAjaxTest`) and opt-in time (`OfflineEventsOptInTimeTest`)

`tests/integration/WordPressIntegration/WCPOSContractTest.php` checks the WCPOS integration against the real WCPOS plugin, so an upstream change fails a test rather than silently changing what is reported. It is skipped unless WCPOS is installed and loaded: install it with `WCPOS_VERSION=latest-stable ./bin/install-wp-tests.sh …` and run the integration suite with `FB_TEST_PLUGIN=wcpos`. The integration workflow does both.

For an end-to-end check, enable the feature, then ring up and pay for a sale on a real terminal with a customer attached, and confirm:

1. `_meta_offline_purchase_tracked` is set on the order once it is paid, and not before.
2. `_meta_purchase_tracked_server` is **not** set.
3. The event arrives in Events Manager with `action_source: physical_store` and an event time matching the payment.
4. A walk-in sale with no customer details is not sent, and the WooCommerce logs say why.
5. A normal web order is still reported as before.

## Reference

- [Meta — Offline Events in the Conversions API](https://developers.facebook.com/documentation/ads-commerce/conversions-api/offline-events)
- [Meta — Omni Optimal Setup Guide](https://developers.facebook.com/documentation/ads-commerce/marketing-api/best-practices/omni-optimal-setup-guide) — Meta's own best practices for omni-channel setup, worth reading alongside the guidance above
- [Meta — Conversions API parameters](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters)
- [WCPOS (WooCommerce POS)](https://wordpress.org/plugins/woocommerce-pos/)
