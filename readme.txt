=== Facebook for WooCommerce ===
Contributors: facebook
Tags: meta, facebook, conversions api, catalog sync, ads
Requires at least: 5.6
Tested up to: 6.8.1
Stable tag: 3.5.4
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

= 3.5.5 - 2025-07-23 =
* Fix - Updated the links to Meta Support by @vahidkay-meta in #3523
* Fix - Improving AAM settings params being captured + Purchase events captured by Pixel by @vahidkay-meta in #3512
* Fix - Prevent cleanupAllUIElements from resetting selects with one option to maintain Composite Products compatibility by @ukilla in #3515
* Tweak - Updated the agent string to be more accurate by @vahidkay-meta in #3514
* Fix - Add surface catalog id display with link to commerce manager in produ… by @devbodaghe in #3517
* Fix - Restrict Facebook banner to only show on attributes page and Facebook settings page by @devbodaghe in #3516
* Fix - [WAUM] Add country_code to Message Events API by @woo-ardsouza in #3510
* Fix - Updating promotions feed upload utils tests to fix failure by @nrostrow-meta in #3511
* Update - Update Node.js & NPM versions used in the plugin by @iodic in #3361
* Fix - Adding better queries for Filters & removing variant dependencies by @SayanPandey in #3496
* Add - [facebook-for-woocommerce][PR] Add default brand name in Facebook tab by @Rebeca-Reis in #3505
* Fix - Updated logger for JsonFeedFileWriter by @vinkmeta in #3503
* Fix - Migrated Handler exceptions to centralisedLogger by @vinkmeta in #3498
* Fix - Migrated Abstract Feed Meta loggers to centralised Logger by @vinkmeta in #3499
* Fix - Moved AbstractFeedFileWriter Meta logs to centralised logger by @vinkmeta in #3500
* Fix - Migrated CsvFeedFileWriter logs by @vinkmeta in #3501
* Fix - Updated FeedUploadUtils loggers by @vinkmeta in #3502
* Fix - Added support for exceptions in Logger by @vinkmeta in #3497
* Fix - Fix UTF-8 character encoding in normalization method by @devbodaghe in #3495
* Fix - Fixes add new attribute mapping row selection by @yuriferretti in #3490
* Tweak - Remove tax attribute mapping option by @juan-goncalves in #3488
* Fix - Rolling back out of stock change by @SayanPandey in #3492
* Tweak - [BUG] fix: Product description is a different font size from the rest of the fields on the fb product page  by @mangalutsav in #3493
* Fix - [Bug] fix: 'Facebook Product Video' field placement by @sarthakpandeymeta in #3491
* Fix - Adding visibility hidden check for out of stock products by @SayanPandey in #3489
* Fix - Fix exclude_sale_price Value Type in Discount Syncing by @mradmeta in #3487
* Dev - Create unit tests for ./Framework/Plugin/Dependancies.php by @ajello-meta in #3485
* Dev - Create unit tests for ./Framework/Plugin/Compatibility.php by @ajello-meta in #3484
* Dev - Create unit tests for ./Framework/Api/JSONResponse.php by @ajello-meta in #3481
* Dev - Create unit tests for ./Framework/Api/Request.php by @ajello-meta in #3479
* Dev - Improve unit tests for ./Admin/Settings_Screens/Shops.php by @ajello-meta in #3477
* Dev - Create unit tests for ./Framework/Api/Response.php by @ajello-meta in #3480
* Dev - Create unit tests for ./Admin/Abstract_Settings_Screen.php by @ajello-meta in #3478
* Dev - Improve unit tests for ./Admin/Settings_Screens/Connection.php by @ajello-meta in #3476
* Add - Validate PR has single changelog label by @tzahgr in #3470
* Add - Set stable tag manual workflow by @tzahgr in #3454

[See changelog for all versions](https://raw.githubusercontent.com/facebook/facebook-for-woocommerce/refs/heads/main/changelog.txt).
