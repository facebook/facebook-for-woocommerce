=== Facebook for WooCommerce ===
Contributors: facebook
Tags: meta, facebook, conversions api, catalog sync, ads
Requires at least: 5.6
Tested up to: 6.7
Stable tag: 3.4.7
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

If you believe you have found a security vulnerability on Facebook, we encourage you to let us know right away. We investigate all legitimate reports and do our best to quickly fix the problem. Before reporting, please review [this page](https://www.facebook.com/whitehat), which includes our responsible disclosure policy and reward guideline. You can submit bugs [here](https://github.com/facebookincubator/facebook-for-woocommerce/issues) or contact advertising support [here](https://www.facebook.com/business/help/900699293402826).

When opening a bug on GitHub, please give us as many details as possible.

* Symptoms of your problem
* Screenshot, if possible
* Your Facebook page URL
* Your website URL
* Current version of Facebook-for-WooCommerce, WooCommerce, Wordpress, PHP

== Changelog ==

= 3.4.7 - 2025-04-17 =
* Tweak - Added external_variant_id to the feed file by @mshymon in #2998
* Tweak - Added support for syncing product type by @vinkmeta in #3013
* Tweak - Relocating bulk actions by @SayanPandey in #2943
* Tweak - Filtration on All Products page | Synced and Not Synced by @SayanPandey in #2999
* Tweak - Updated PR Template by @vinkmeta in #3019
* Fix - Null check exceptions by @vinkmeta in #3015
* Tweak - Relaxing sync validations by @raymon1 in #2969
* Tweak - Truncates extra characters from title and description by @raymon1 in #3023
* Tweak - Updated PR template by @vinkmeta in #3053
* Fix - The item not found error by using filter in the product endpoint @vinkmeta in #3054
* Fix - Bug where MPN input box had no tooltip by @devbodaghe in #3034
* Tweak - Investigation: WooCommerce to Facebook Product Attribute Syncing by @devbodaghe in #3033
* Fix - Add parent product material inheritance for variations by @devbodaghe in #3035
* Fix - Tooltip Messages for Skirt Length and Sleeve Length by @devbodaghe in #3039
* Fix - Typo in Admin.php by @SayanPandey in #3063
* Add - Add separate short_description field to Facebook product data by @devbodaghe in #3029
* Tweak - Sync short description remove dropdown by @devbodaghe in #3031
* Tweak - Short Description Fallback by @devbodaghe in #3048
* Fix - A problem where Purchase event was not firing if thankyou page was not shown or Purchase state updated through Woo dashboard by @vahidkay-meta in #3060
* Tweak - Remove type casting for gpc to int by @devbodaghe in 3078
* Tweak - Disable unmapped fields to batch api by @devbodaghe in #3079
* Fix - Product variation fields not saving correctly by @devbodaghe in #3090
* Fix - Removed failing test due to merge conflicts @vinkmeta in #3103

[See changelog for all versions](https://raw.githubusercontent.com/facebook/facebook-for-woocommerce/refs/heads/main/changelog.txt).
