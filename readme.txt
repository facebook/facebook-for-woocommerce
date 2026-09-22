=== Meta for WooCommerce ===
Contributors: facebook
Tags: meta, facebook, whatsapp, conversions api, catalog sync
Requires at least: 5.6
Tested up to: 7.1
Stable tag: 3.7.6
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

= 3.7.7 - 2026-09-22 =
* Dev - ci(e2e): run E2E tests on the latest PHP by @vahidkay-meta in #3982
* Dev - test(e2e): assert exact mapped value on Facebook color field by @vahidkay-meta in #3985
* Dev - test(e2e): harden weak assertions and modernize CI database by @vahidkay-meta in #3984
* Dev - Add copyright headers and surface license/legal info by @vahidkay-meta in #3996
* Dev - Remove unused legacy connection methods by @jczhuoMeta in #4001
* Dev - Remove legacy page access token storage by @jczhuoMeta in #4002
* Dev - Bump postcss from 8.5.6 to 8.5.26 by @app/dependabot in #4010
* Dev - test(e2e): cover Search event on classic + block theme projects by @vahidkay-meta in #3995
* Dev - ci(e2e): fix every known cause of the red E2E pipeline by @jczhuoMeta in #4016
* Fix - Fix - Stop calling the deprecated WooCommerce Admin marketing feature flag (WC 11.1.0) by @jczhuoMeta in #4026
* Breaking - Remove the retired WooCommerce connection bridge by @jczhuoMeta in #4014
* Add - Use finalize-install for enhanced onboarding by @jczhuoMeta in #4032
* Tweak - Remove the retired FBE install webhook by @jczhuoMeta in #4037
* Fix - Align finalize-install request schema with its payload by @jczhuoMeta in #4038
* Tweak - Tweak - Stop writing redundant legacy connection options by @jczhuoMeta in #4035
* Fix - Clamp out-of-range quantities before reporting pixel events by @vahidkay-meta in #4039
* Fix - Redact credentials from API request logs and CI artifacts by @rafael-curran in #4029
* Update - Use delegated access tokens for Shops management URLs by @jczhuoMeta in #4030
* Fix - Stop requiring and collecting the Facebook Page ID by @jczhuoMeta in #4044
* Tweak - Remove the hourly business-configuration refresh by @jczhuoMeta in #4046
* Tweak - Retire the connection options nothing reads by @jczhuoMeta in #4045
* Dev - Add Param Builder CDN fallback by @pinahar in #4050
* Fix - [Easy] Derive the Authorization header from the current access token by @jczhuoMeta in #4043
* Add - Gate Value Optimization behind a rollout switch, and fix its net revenue calculation by @vahidkay-meta in #4042
* Fix - Sync products to Meta catalog on WooCommerce REST API saves by @vahidkay-meta in #3997
* Fix - Make the log throttle group cap a fixed 24 hour window by @vahidkay-meta in #4053

[See changelog for all versions](https://raw.githubusercontent.com/facebook/facebook-for-woocommerce/refs/heads/main/changelog.txt).

== Terms of Use ==

Your use of this project is governed by [Meta's Terms of Use](https://opensource.fb.com/legal/terms).

== Privacy Policy ==

For information about how Meta collects and uses data, please review [Meta's Privacy Policy](https://opensource.fb.com/legal/privacy).

== Copyright ==

Copyright (c) Meta Platforms, Inc. and affiliates. All Rights Reserved.

Meta for WooCommerce is distributed under the terms of the GNU General Public License v2.0 (or later). See the LICENSE file in the plugin's source repository for the full license text.
