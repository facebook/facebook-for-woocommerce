=== Facebook for WooCommerce ===
Contributors: facebook
Tags: meta, facebook, conversions api, catalog sync, ads
Requires at least: 5.6
Tested up to: 6.8.1
Stable tag: 3.5.3
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

= 3.5.3 - 2025-06-17 =
*   Tweak - Add comprehensive unit tests for Locale class by @sol-loup in #3362
*   Tweak - Add unit tests for Idempotent_Request trait by @sol-loup in #3380
*   Tweak - Add comprehensive unit tests for MetaLog Response by @sol-loup in #3376
*   Tweak - Add comprehensive unit tests for ProductSets Delete Response class by @sol-loup in #3398
*   Fix - Since tags by @sol-loup in #3397
*   Tweak - Add comprehensive unit tests for Pages Read Response by @sol-loup in #3378
*   Tweak - Add comprehensive unit tests for User Response to improve code coverage by @sol-loup in #3377
*   Fix - Linter errors for ./includes/fbproductfeed.php by @ajello-meta in #3396
*   Tweak - Add unit tests for Events AAMSettings class by @sol-loup in #3369
*   Tweak - Improve CAPI event handling & add logging for Purchase events by @iodic in #3360
*   Fix - Adding links to the updated banner by @SayanPandey in #3388
*   Fix - Removing deprecated tag from Product Sync tab by @SayanPandey in #3387
*   Fix - Transmit opted_out_woo_all_products timestamp by @vinkmeta in #3386
*   Tweak - Product sync tab deprecation using Rollout Switches by @SayanPandey in #3364
*   Tweak - Excluded categories and tags from Rollout Switches by @SayanPandey in #3382
*   Tweak - Add unit tests for ProductExcludedException and ProductInvalidException by @sol-loup in #3357
*   Tweak - Global sync check for Rollout Switches by @SayanPandey in #3367
*   Fix - Limiting BatchLogHandler processing for performance reasons by @vinkmeta in #3381
*   Tweak - Add unit tests for Framework Api Exception class by @sol-loup in #3370
*   Tweak - Add unit tests for Framework Plugin Exception class by @sol-loup in #3371
*   Fix - Updated since tags across a few files by @sol-loup in #3374
*   Fix - Deprecated the concept of tip info by @vinkmeta in #3373
*   Fix - Removed instance of deprecated logger by @vinkmeta in #3372
*   Fix - the process to share ExternalVersionUpdate with Meta by @vinkmeta in #3365
*   Fix - Failing tests by @vinkmeta in #3366
*   Fix - Deprecated legacy logging apis by @vinkmeta in #3351
*   Fix - Issue with productSetSyncTest causing warnings during PHPUnit by @sol-loup in #3355
*   Tweak - Add comprehensive unit tests for Events\Normalizer class by @sol-loup in #3359
*   Tweak - Add unit tests for API Response classes to improve code coverage by @sol-loup in #3358
*   Fix - Deprecated the concept of fblog by @vinkmeta in #3354
*   Fix - Deprecated tool tip event concepts by @vinkmeta in #3349
*   Fix - Removed unnecessary logs being sent to Meta servers by @vinkmeta in #3347
*   Fix - Clean up info Banner releated class and components by @vinkmeta in #3348
*   Fix - Sending backgroung syncing jobs action logs to Meta by @vinkmeta in #3346
*   Fix - Tracking error messages being displayed to Admins by @vinkmeta in #3345
*   Tweak - Workflow for Releasing Plugin by @tzahgr in #3309
*   Fix - Deleted wp_ajax_ajax_sync_all_fb_products action by @vinkmeta in #3343
*   Fix - Deprecated log_with_debug_mode_enabled by @vinkmeta in #3342
*   Fix - Removed unnecessary logs by @vinkmeta in #3341
*   Fix - removing duplicate logging by @vinkmeta in #3337
*   Fix - Updated exception logs to centralized logger by @vinkmeta in #3338
*   Fix - Migrated Feed Logger by @vinkmeta in #3340
*   Fix - Deprecated log_to_meta functionality by @vinkmeta in #3336
*   Fix - Enabled fbproduct for code sniffing in #3328
*   Fix - Enabled phpcs for plugin banners by @vinkmeta in #3332
*   Fix - Updated loggers for Feeds by @vinkmeta in #3335
*   Fix - Updated loggers for checkout flow by @vinkmeta in #3334
*   Fix - Logging background product syncing debug logs by @vinkmeta in #3329
*   Fix - Enabled Pixel Normalizer for CS by @vinkmeta in #3333
*   Fix - Update to Feed Loggers by @vinkmeta in #3330
*   Fix - Debug logs for background product syncing by @vinkmeta in #3327
*   Add - Sync all products if user has opted-in for Woo All Products by @SayanPandey in #3281
*   Fix - PHPUnit warnings by renaming test files to match their class names by @sol-loup in #3311
*   Tweak - Removing exclusion of sync of excluded categories and tags by @SayanPandey in #3244
*   Fix - Lints and phpcs issues with fbproduct by @vinkmeta in #3324
*   Fix - Code sniffing fixes for fbproduct by @vinkmeta in #3323
*   Fix - Migrated Product Feed Progress Logging Mechanism by @vinkmeta in #3321
*   Fix - Added critical log when user attempting non-permissible actions by @vinkmeta in #3322
*   Fix - Handling edge case with BatchLogHandler in #3320
*   Fix - Product Group Logging by @vinkmeta in #3317
*   Fix - PHPCBF for fbproduct by @vinkmeta in #3319
*   Fix - Linting for WC_Facebookcommerce_Background_Process by @vinkmeta in #3318
*   Tweak - Adding tags on excluded categories and tags by @SayanPandey in #3308
*   Tweak - Removing unnecessary modal for excluded categories by @SayanPandey in #3307
*   Tweak - Removing delete on stock check by @SayanPandey in #3306
*   Add - Showing a banner to the user about the changes by @SayanPandey in #3256
*   Fix - Updated location for Product Feeds by @vinkmeta in #3316
*   Tweak - Removing internal wiki links by @vinkmeta in #3315
*   Add - Introducing a centralized logger by @vinkmeta in #3313
*   Fix - Update ratings and reviews query logic and fix tests by @nrostrow-meta in #3312
*   Fix - Restoring the product sync tagging with integers by @SayanPandey in #3305
*   Tweak - Removing Enable Sync checkbox by @SayanPandey in #3241
*   Fix - Flush rewrite rules to ensure /fb-checkout endpoint is working correctly by @ajello-meta in #3301
*   Tweak - Deprecating Product Sync tab by @SayanPandey in #3273
*   Tweak - Updated GitHub PR template by @vinkmeta in #3304
*   Fix - Changed json_encode to wp_json_encode to avoid phpcs issues by @vinkmeta in #3303
*   Fix - Replaced json_encode with wp_json_encode by @vinkmeta in #3276
*   Fix - Linting issues resolved for includes/ProductSets/Sync.phpby @sol-loup in #3188
*   Fix - Linting issues resolved for includes/Utilities/Background_Remove_Duplicate_Visibility_Meta.php by @sol-loup in #3187
*   Fix - Linting issues resolved for includes/Events/Event.php by @sol-loup in #3189
*   Tweak - Enable Video sync at Variable Product Level by @gurtejrehal in #3291
*   Fix - Revert #3295 by @vinkmeta in #3299

[See changelog for all versions](https://raw.githubusercontent.com/facebook/facebook-for-woocommerce/refs/heads/main/changelog.txt).
