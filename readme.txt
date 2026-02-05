=== Facebook for WooCommerce ===
Contributors: facebook
Tags: meta, facebook, conversions api, catalog sync, ads
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 3.5.15
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

= 3.5.16 - 2026-02-03 =
* Fix - Fix/collection page bugs - Batch 1 by @vahidkay-meta in #3837
* Fix - Fixed composer autoload warnings. also fixed a bug regarding fbcollection page registration. by @vahidkay-meta in #3834
* Fix - Replace deprecated wc_enqueue_js with wp_add_inline_script by @ukilla in #3836
* Dev - fix unsafe domain check warning on capture.js by @ashiqursuperfly in #3829
* Tweak - fix: recursion call bug by @rithikb24 in #3824
* Dev - retry adding helpers/js/facebook by @ashiqursuperfly in #3831
* Dev - organize E2E test helpers by @ashiqursuperfly in #3821
* Update - update parambuiler library to 1.2.0 by @chc421 in #3825
* Fix - Fix TypeError: array_map(): Argument #2 must be of type array, string given in fbproduct.php:1775 by @ashiqursuperfly in #3822
* Add - fix for unused variable by @rithikb24 in #3776
* Tweak - Restrict E2E tests to facebook repo and add daily schedule (#3799) by @jarretth in #3800
* Tweak - Add param_builder_server_setup test (#3790) by @jarretth in #3795
* Dev - add additional unit tests for facebook-commerce.php by @ashiqursuperfly in #3815
* Add - Add unit tests for Admin.php and WCFacebookProductFeedTest.php by @immadhavv in #3816
* Add - Added unit tests for Feed, Products , WC_Facebookcommerce_EventsTracker by @immadhavv in #3813
* Dev - make exactSearchSelect2Container E2E test helper more robust by @ashiqursuperfly in #3809
* Add - Added User consent check for events tracking test by @immadhavv in #3789
* Dev - remove tests.describe.serial by @ashiqursuperfly in #3788
* Add - Add test to check plugin compatibility with other major plugins by @immadhavv in #3785
* Dev - add E2E test for product attribute mapping and syncing by @ashiqursuperfly in #3784
* Dev - Catalog rotation by @ashiqursuperfly in #3781
* Add - DevmateWooCommerceGenerateTests: Add tests for facebook-commerce-events-tracker.php by @immadhavv in #3783
* Fix - Fix bug - Remove "Delete all products" button as it calls an unimplemented method by @immadhavv in #3782
* Add - Plugin level e2e tests remaining by @immadhavv in #3780
* Add - Add workflow to auto-label internal PRs by @immadhavv in #3769
* Add - e2e test for csv import update by @rithikb24 in #3762
* Dev - fix batch api E2E test category leak bug by @ashiqursuperfly in #3763
* Add - Adding Profit Margin signal to AddToCart and Purchase events by @vahidkay-meta in #3730
* Dev - add E2E test for product sync batch api monitoring  by @ashiqursuperfly in #3760
* Add - Catalog api e2e tests plugin level by @immadhavv in #3761
* Dev - add E2E test to create product from feed file by @ashiqursuperfly in #3754
* Add - CAPI/Pixel e2e tests added for remaining events, framework updated. by @immadhavv in #3755
* Tweak - standarize tests by @rithikb24 in #3745
* Dev - add E2E tests to verify category/product_set update and delete by @ashiqursuperfly in #3744
* Dev - add E2E test for product set creation by @ashiqursuperfly in #3740

[See changelog for all versions](https://raw.githubusercontent.com/facebook/facebook-for-woocommerce/refs/heads/main/changelog.txt).
