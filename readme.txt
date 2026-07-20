=== Meta for WooCommerce ===
Contributors: facebook
Tags: meta, facebook, whatsapp, conversions api, catalog sync
Requires at least: 5.6
Tested up to: 7.0.1
Stable tag: 3.7.4
Requires PHP: 7.4
MySQL: 5.6 or greater
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Reach more customers and drive sales on Facebook, Instagram and WhatsApp with the official Meta for WooCommerce plugin.

== Description ==

This is the official Meta for WooCommerce plugin that connects your WooCommerce website to Facebook, Instagram and WhatsApp. With this plugin, you can install the Facebook pixel, upload your online store catalog, enabling you to easily run dynamic ads and connect your WhatsApp Business account to automatically update customers about their orders.


Marketing on Meta platforms helps your business build lasting relationships with people, find new customers, and increase sales for your online store. With this Facebook ad extension, reaching the people who matter most to your business is simple. This extension will track the results of your advertising across devices. It will also help you:

* Maximize your campaign performance. By setting up the Facebook pixel and building your audience, you will optimize your ads for people likely to buy your products, and reach people with relevant ads on Facebook after they’ve visited your website.
* Find more customers. Connecting your product catalog automatically creates carousel ads that showcase the products you sell and attract more shoppers to your website.
* Generate sales among your website visitors. When you set up the Facebook pixel and connect your product catalog, you can use dynamic ads to reach shoppers when they're on Facebook with ads for the products they viewed on your website. This will be included in a future release of Meta for WooCommerce.
* Engage with customers on WhatsApp by updating your customers about their orders at every step, freeing up more time for you to focus on your business.

== Installation ==

Visit the Facebook Help Center [here](https://www.facebook.com/business/help/900699293402826).

== Support ==

Before raising a question with Meta Support, please first take a look at the Meta [helpcenter docs](https://www.facebook.com/business/help), by searching for keywords like 'WooCommerce' here. If you didn't find what you were looking for, you can go to [Meta Direct Support](https://www.facebook.com/business-support-home) and ask your question.

When reporting an issue on Meta Direct Support, please give us as many details as possible.
* Symptoms of your problem
* Screenshot, if possible
* Your Facebook page URL
* Your website URL
* Current version of Facebook-for-WooCommerce, WooCommerce, Wordpress, PHP

To suggest technical improvements, you can raise an issue on our [Github repository](https://github.com/facebook/facebook-for-woocommerce/issues).

== Known limitations ==

Crash recovery uses a shutdown handler to write a disable flag and queue a sanitized crash report.
In rare PHP memory-exhaustion fatals, there may be too little memory left for the shutdown handler to run.
When that happens, the site still recovers on the next request, but the disable flag and crash report may be skipped for that request.

== Changelog ==

= 3.7.5 - 2026-07-14 =
* Fix - ci(release): grant actions:write so Prepare Release can dispatch the build by @vahidkay-meta in #3973
* Doc - Update the contributor's guide by @iodic in #3820
* Tweak - Remove deprecated WhatsApp consent checkbox from checkout by @ukilla in #3886
* Add - Feature CF7 Lead server tracking by @ukilla in #3944
* Fix - Block CAPI unconditionally on invalid token; remove rollout switch by @rafael-curran in #3966
* Fix - Fix intermittent connection save issue in /settings/update flow for missing page/ad account IDs by @ukilla in #3903
* Fix - Release readme/stable-tag guardrails + fix WPFactory COGS deprecation by @vahidkay-meta in #3967
* Update - Gate WhatsApp customer_events on onboarding completion (gate + rollout switch + onboarding-complete writers) by @ceciliazeng-wa in #3969
* Fix - Fix/subscriptions renewal purchase capi by @ukilla in #3946
* Dev - ci(release): release-workflow guardrails — block overlapping releases + clearer Set Stable Tag errors by @vahidkay-meta in #3961
* Add - Add cache detection flag to Pixel agent string by @vahidkay-meta in #3948
* Fix - Prevent orphan variation sync-field fatal during trash flows by @ukilla in #3963
* Dev - ci: fail Prepare New Release if an open PR exists for the current stable version by @vahidkay-meta in #3965
* Dev - ci: fix flaky E2E login, spread CI runners, auto-label Dependabot PRs (+ bump capi-param-builder) by @vahidkay-meta in #3960
* Add - E2E test expansion by @iodic in #3917
* Add - Optimize Dependabot checks by @iodic in #3925
* Add - Add crash recovery support by @iodic in #3941

[See changelog for all versions](https://raw.githubusercontent.com/facebook/facebook-for-woocommerce/refs/heads/main/changelog.txt).
