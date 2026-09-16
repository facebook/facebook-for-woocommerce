# Offline (physical store) events

This plugin can report sales rung up at a physical point of sale to Meta, so
in-store conversions can be attributed to online ad spend. This document covers
how an order is recognised as an in-store sale, what is sent, and how to add
support for another point-of-sale plugin.

For Meta's own documentation, see
[Offline Events in the Conversions API](https://developers.facebook.com/documentation/ads-commerce/conversions-api/offline-events)
for the feature itself, and the
[Omni Optimal Setup Guide](https://developers.facebook.com/documentation/ads-commerce/marketing-api/best-practices/omni-optimal-setup-guide)
for how in-store signals fit into an omni-channel setup.

## What gets sent, and how

Offline Purchase events are sent as an ordinary `Purchase` event through the
**Conversions API**, with `action_source` set to `physical_store`, so one send
path serves every event the plugin emits.

There is no browser pixel counterpart. A sale at a till has no browser session,
so there is nothing to deduplicate against and no shared `event_id` to maintain.

## Turning it on

The feature is **off by default** and is never enabled by an upgrade.

| | |
|---|---|
| Setting | **Enable Offline Events**, on the *Configuration* tab |
| Option | `wc_facebook_enable_offline_purchase_events` (`yes` / `no`) |
| Accessor | `WC_Facebookcommerce_Integration::is_offline_purchase_events_enabled()` |
| Filter | `wc_facebook_is_offline_purchase_events_enabled` |

The checkbox is rendered but not operable when no supported POS plugin is
active — the setting would have no effect, and hiding it would make the feature
undiscoverable.

It can also be toggled over `admin-ajax.php` by a user with `manage_woocommerce`,
with a nonce for the matching action:

- `wc_facebook_enable_offline_events`
- `wc_facebook_disable_offline_events`
- `wc_facebook_get_offline_events_status`

Enabling is refused when no supported POS plugin is active. Disabling always
succeeds, so the setting can be cleared after a POS plugin is deactivated.

## How an order is detected

Detection runs inside the normal Purchase path in
`facebook-commerce-events-tracker.php`. On `woocommerce_new_order` and the other
purchase hooks, `inject_purchase_event()` asks `is_offline_event()`, which:

1. Returns `false` immediately if the merchant has not opted in. The POS
   integrations are never consulted on a store that has the feature off.
2. Otherwise asks `POS_Integration_Registry::match()` for the first **active**
   integration that claims the order.

If an integration claims it, the order goes to `track_offline_purchase_event()`
and never to the web path. The two are mutually exclusive.

### Order-level gates

Before anything is sent, the order must pass:

- **Status** — one of `processing`, `completed`, `on-hold`, `pending`. This is
  the same list the web Purchase path uses, and it keeps failed payments out.
- **Not already reported** — the `_meta_offline_purchase_tracked` order meta is
  absent.
- **Not in flight** — a 45-minute transient guards against concurrent processes
  reporting the same order twice.

The meta and transient are written *before* the send, matching the web path's
ordering.

### The admin-user gate does not apply

The web path skips users with `manage_woocommerce` so that staff browsing the
storefront do not pollute pixel data. That reasoning does not apply to a
server-side sale at a till, so offline events bypass the check.

This matters in practice: WCPOS's dedicated `cashier` role does **not** hold
`manage_woocommerce`, but `shop_manager` and `administrator` do, and both can
operate the POS. Without the bypass, whether a sale was reported would depend on
who was signed in.

### The signals hold does not apply

`FacebookSignalsState` can hold CAPI sends while a web visitor's consent state is
unresolved. Offline events are exempt, alongside the existing admin and cron
carve-outs: a sale at a till has no browser session that consent could describe,
and a held event would be stranded, because the queue is released by a storefront
AJAX call a POS terminal never makes.

## What the event contains

The event carries the order's contents and value, shaped by the same code the web
Purchase path uses. The aim is to capture the data points Meta describes in the
[Omni Optimal Setup Guide](https://developers.facebook.com/documentation/ads-commerce/marketing-api/best-practices/omni-optimal-setup-guide).

An integration can contribute additional fields by returning them from
`get_event_data()`.

Signals that only make sense for a web visit are deliberately omitted. On a POS
order created over REST they would describe the cashier's terminal rather than the
customer, and Meta flags them as invalid on a `physical_store` event.

## Supported point-of-sale systems

[WCPOS](https://wordpress.org/plugins/woocommerce-pos/) is the first supported
system, not the only intended one. Support for others is being added over time.

**The plugin is point-of-sale agnostic by design.** The registry and the
`POS_Integration_Interface` contract exist so that no POS is privileged: every
integration is detected the same way, and every order produces the same event
regardless of which system rang it up. Being supported is not an endorsement, a
partnership, or a statement about any vendor's merits, and the order of entries in
`POS_Integration_Registry::INTEGRATIONS` is not a ranking — `match()` returns the
first integration that *claims* an order, and integrations are not expected to
overlap.

Which systems are bundled next reflects practical considerations, among them the
integration effort a given system involves and how many merchants it would reach.
That is not a fixed formula, a commitment, or a published roadmap, and no ordering
or preference should be inferred from it.

In any case, nothing here needs to change for a system to be supported:
`wc_facebook_pos_integrations` lets a POS vendor, an agency, or a single store
register an integration from their own code, with exactly the same capabilities
as the bundled one. If you maintain a point of sale and want it supported here,
opening a pull request with an integration class is welcome.

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

    public function is_pos_order( \WC_Order $order ): bool {
        return $this->is_supported() && my_pos_is_pos_order( $order );
    }

    public function get_event_data( \WC_Order $order ): array {
        return array();
    }
}
```

Third parties can register an integration without modifying the plugin, via the
`wc_facebook_pos_integrations` filter. Entries that do not implement the
interface are discarded.

```php
add_filter( 'wc_facebook_pos_integrations', function ( array $integrations ) {
    $integrations[] = new My_POS_Integration();
    return $integrations;
} );
```

`match()` returns the **first active integration that claims the order**, so
ordering matters only if two integrations could both claim the same order — which
they should not.

## Best practices

**Detect through the POS plugin's own public API.** The bundled WCPOS integration
calls `wcpos_is_pos_order()` rather than reading the `_pos` meta or comparing
`created_via` itself. If the POS changes how it flags orders, the integration
keeps working. Reach for raw meta only when no public helper exists.

**Keep `is_supported()` cheap and side-effect free.** It is called on every order
that reaches the purchase hooks. A `function_exists()` or `class_exists()` check
is the right shape. Do not query the database, hit the network, or write options.

**Never write from `is_pos_order()`.** It is a predicate. All state changes belong
in the tracker, which already orders them correctly against the send.

**Check the plugin is loaded, not merely installed.** `is_supported()` should
confirm the API you are about to call actually exists. An activated-but-half-loaded
plugin should report as unsupported rather than fatal later.

**Make sure POS orders cannot also travel the web path.** The tracker's branch is
exclusive, but if your POS also drives a web checkout for the same order, verify
only one of `_meta_offline_purchase_tracked` and `_meta_purchase_tracked_server`
ends up on it.

**Exclude training and test-mode sales.** Many POS systems have a practice mode.
Those orders should not reach Meta; filter them out in `is_pos_order()`.

**Pass through whatever customer identifiers the POS captured.** Match quality
depends on them more than on anything else an integration can do. Meta's
[Omni Optimal Setup Guide](https://developers.facebook.com/documentation/ads-commerce/marketing-api/best-practices/omni-optimal-setup-guide)
covers which identifiers are worth capturing and how they should be handled. Do
not invent them, and do not pass the cashier's details — they are not the
customer.

**Surface store and till as attributes of the transaction, not the customer.**
They describe where a sale happened, not who made it. WCPOS records these as
`_pos_store` and `_pos_user` if you need them.

**Treat in-store events as one input to an omni-channel setup, not a standalone
feature.** The guide linked above also covers how offline signals combine with web
events, catalog, and campaign configuration. Reporting in-store purchases is
necessary but not sufficient — the value shows up once the rest of that setup is
in place.

## Testing

Unit tests live in `tests/Unit/Events/POS/`. They stub detection through the
`wc_facebook_pos_integrations` filter rather than requiring a POS plugin, so they
run in CI where none is installed — your integration's `is_supported()` should
return `false` there without erroring.

For an end-to-end check, ring up a sale on a real terminal and confirm:

1. `_meta_offline_purchase_tracked` is set on the order.
2. `_meta_purchase_tracked_server` is **not** set.
3. The event arrives in Events Manager with `action_source: physical_store`.
4. A normal web order is still reported as before.

## Reference

- [Meta — Offline Events in the Conversions API](https://developers.facebook.com/documentation/ads-commerce/conversions-api/offline-events)
- [Meta — Omni Optimal Setup Guide](https://developers.facebook.com/documentation/ads-commerce/marketing-api/best-practices/omni-optimal-setup-guide) — Meta's own best practices for omni-channel setup, worth reading alongside the guidance above
- [Meta — Conversions API parameters](https://developers.facebook.com/docs/marketing-api/conversions-api/parameters)
- [WCPOS (WooCommerce POS)](https://wordpress.org/plugins/woocommerce-pos/)
