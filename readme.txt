=== Facebook for WooCommerce ===
Contributors: facebook
Tags: meta, facebook, conversions api, catalog sync, ads
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 3.5.16
Requires PHP: 7.4
MySQL: 5.6 or greater
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Get the Official Facebook for WooCommerce plugin for powerful ways to help grow your business.

== Description ==

This is the official Facebook for WooCommerce plugin that connects your WooCommerce website to Facebook. With this plugin, you can install the Facebook pixel, and upload your online store catalog, enabling you to easily run dynamic ads.


Marketing on Facebook helps your business build lasting relationships with people, find new customers, and increase sales for your online store. With this Facebook ad extension, reaching the people who matter most to your business is simple. This extension will track the results of your advertising across devices. It will also help you:

* Maximize your campaign performance. By setting up the Facebook pixel and building your audience, you will optimize your ads for people likely to buy your products, and reach people with relevant ads on Facebook after they’ve visited your website.
* Find more customers. Connecting your product catalog automatically creates carousel ads that showcase the products you sell and attract more shoppers to your website.
* Generate sales among your website visitors. When you set up the Facebook pixel and connect your product catalog, you can use dynamic ads to reach shoppers when they’re on Facebook with ads for the products they viewed on your website. This will be included in a future release of Facebook for WooCommerce.

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

== Changelog ==

= 3.5.17 - 2026-02-17 =
* Fix - Fix - Removed the prefix from retailer id. by @vahidkay-meta in #3858
* Add - [WooCommernce] [Rich Order] Add rich-order payload gating and simplify per-item amount for events. by @ashutoshbondre in #3848
* Add - Fix pixel event tracking by isolating JS execution context by @cshing-meta in #3835
* Dev - Tweak - Broke down integration tests to run with PHP7.4 & PHP8.4 by @vahidkay-meta in #3855
* Fix - Tweak - Added logging to detect if we can reliably use fbcollection by @vahidkay-meta in #3854
* Fix - Fix duplicate CAPI Purchase events during checkout process by @cshing-meta in #3850
* Tweak - Optimize Release process, automate marketplace artifact verification by @vahidkay-meta in #3846
* Fix - Fix performance issue: Cache background sync job queries and skip on … by @devbodaghe in #3823
* Fix - Fix test with accurate retailer ID by @jarretth in #3852
* Fix - Pin Polylang version for PHP 7.4 in CI by @jarretth in #3851
* Tweak - Update parambuilder server version to 1.2.1 by @jarretth in #3844

[See changelog for all versions](https://raw.githubusercontent.com/facebook/facebook-for-woocommerce/refs/heads/main/changelog.txt).
