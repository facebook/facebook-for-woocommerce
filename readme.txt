=== Facebook for WooCommerce ===
Contributors: facebook, automattic, woothemes
Tags: facebook, woocommerce, marketing, product catalog feed, pixel
Requires at least: 5.6
Tested up to: 6.7
Stable tag: 3.3.3
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

= 3.3.3 - 2025-02-06 = 
* Fix - Use of recommended delete connection endpoint over delete permission endpoint by atuld123 in #2844
* Add - Expose Brand & MPN to Woocommerce UI by @devbodaghe in #2842

= 3.3.2 - 2025-01-21 =
* Fix - a bug that arose from reliance on the plugin version compatibility tag by @vinkmeta in #2856
* Fix - Update catalog link in connections tab to point to Catalog by @devbodaghe in #2848
* Fix - Product URL links now point to items tab by @vinkmeta in #2852
* Tweak - Removed sell on Instagram by @mshymon in #2854
* Tweak - Add custom fields to product data structure by @devbodaghe in #2836
* Fix - Multiple issues with Fixed FB Attribute Syncing by @vinkmeta in #2860

= 3.3.1 - 2024-12-06 = 
* Fix - Sale price effective date by @vinkmeta in #2809
* Fix - Sync stock quantity when available by @mshymon in #2811
* Fix - Sync product GTIN when available by @mshymon in #2810

= 3.3.0 - 2024-11-07 =
* Add - Extension navigation tabs on Product Sets screens.
* Tweak - Tooltips, config labels, documentation links.
* Tweak - WC 9.4 compatibility.
* Tweak - WP 6.7 compatibility.

= 3.2.10 - 2024-09-30 =
* Update -  Remove Marketing Products Sets menu entry.

= 3.2.9 - 2024-09-12 =
* Break - Removes WooCommerce navigation items.
* Tweak - WC 9.3 compatibility.

= 3.2.8 - 2024-09-03 =
* Update - Access Token retrieval procedure.
* Update - Use get_title() for content_name to match catalog name.

= 3.2.7 - 2024-08-13 =
* Dev - Updated `phpcs` ruleset.
* Fix - Update failing due to undefined constant error in Lifecycle.
* Tweak - WC 9.2 compatibility.

= 3.2.6 - 2024-07-23 =
* Add - Filter facebook_for_woocommerce_fb_product_description.
* Fix - Remove deprecated FILTER_SANITIZE_STRING usage.
* Tweak - Align PHP require version to composer.json.

= 3.2.5 - 2024-07-10 =
* Tweak - WC 9.1 compatibility.
* Tweak - WP 6.6 compatibility.

= 3.2.4 - 2024-06-13 =
* Dev - Adds support for wp-env.
* Tweak - Fully remove Facebook Messenger code references.
* Tweak - WC 9.0 compatibility.

= 3.2.3 - 2024-05-28 =
* Add - Versioning and compatibility checks to implement support policy.
* Fix - Errors and warnings while generating pot file.
* Tweak - Bump Marketing API version to v20.0.
* Tweak - Remove hidden files from build archive.

= 3.2.2 - 2024-05-14 =
* Fix - Incorrect alert for Product Sets without excluded categories.
* Tweak - WC 8.9 compatibility.

= 3.2.1 - 2024-05-07 =
* Fix - Defer only AddToCart events if applicable.
* Fix - Direct upgrade path from < 3.1.13 to ≥ 3.2.0.
* Tweak - Adds WooCommerce as a dependency to the plugin header.
* Tweak - Revert to WooCommerce.com domain.

= 3.2.0 - 2024-05-01 =
* Tweak - PHP8.3 to GitHub PHPCS and Unit Tests workflows.
* Update - Remove the sunsetted Messenger Chat feature.

= 3.1.15 - 2024-04-16 =
* Tweak - Replace the middleware URL from connect.woocommerce.com to api.woocommerce.com/integrations.
* Tweak - Test environment setup to resolve notice.

= 3.1.14 - 2024-04-03 =
* Fix - Remove facebook_messenger_deprecation_warning notice on deactivation.
* Tweak - Insert pixel-event-placeholder element via vanilla JS.
* Tweak - WC 8.8 compatibility.

= 3.1.13 - 2024-03-27 =
* Add - Messenger feature deprecation notices.

= 3.1.12 - 2024-03-19 =
* Tweak - Check if condition is set before setting a default value.
* Tweak - Updates readme.txt to meet WordPress requirements.

= 3.1.11 - 2024-03-12 =
* Fix - Add video syncs to fbproduct.
* Fix - Deprecation warnings with PHP 8.2.
* Tweak - WC 8.7 compatibility.
* Tweak - WP 6.5 compatibility.
