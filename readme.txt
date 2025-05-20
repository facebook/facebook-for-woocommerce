=== Facebook for WooCommerce ===
Contributors: facebook
Tags: meta, facebook, conversions api, catalog sync, ads
Requires at least: 5.6
Tested up to: 6.8.1
Stable tag: 3.4.9
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

= 3.4.9 - 2025-05-14 =
* Add - Support for rollout switches in the plugin to control feature rollouts from meta side @francorisso in #3126
* Fix - Tests in the rollout switches file by @francorisso in #3146
* Fix - RolloutSwitches Init by @carterbuce in #3157
* Add - Integrate Whatsapp Utility Messaging for WooCommerce Order Update Notifications by @sharunaanandraj in #3164
* Tweak - Improve Test Filter Management with AbstractWPUnitTestWithSafeFiltering by @sol-loup in #2944
* Fix - Namespacing issue causing some tests to be skipped @sol-loup in #3037
* Tweak - Additional logs and timeout for Utility Message Flows by @woo-ardsouza in #3171
* Fix - The WAUM payment progress to only Show Up after Consent Collection is Enabled by @sharunaanandraj in #3175
* Tweak - Update language dropdown based on supported_languages in GET api response by @woo-ardsouza in #3178
* Add - Error notice to gracefully handle errors in Manage Events view by @woo-ardsouza in #3179
* Fix - The Status on the Whatsapp Consent Collection Pill and Button @sharunaanandraj in #3183
* Tweak - Update Message Sending API from Messages to Message Events by @woo-ardsouza in #3182
* Tweak - Update the Authentication mechanism for Whatsapp Webhook by @sharunaanandraj #3186
* Tweak - Minor design updates to Utility Event Settings card by @woo-ardsouza in #3193
* Add - Admin notice for WhatsApp utility messaging recruitment @iodic in #3177
* Fix - The product sync button showing up twice by @sharunaanandraj in #3199
* Tweak - Bump WooCommerce and WordPress compatibility by @iodic in #3200
* Add - An automated process that synchronizes all WooCommerce product categories with Meta, creating catalog product sets for each category. The synchronization process ensures that any changes made to the WooCommerce product categories are reflected in the corresponding Meta catalog product sets by @mshymon in #3168
* Add - A banner in product sets tab to explain recent changes to product sets sync by @mshymon in #3207
* Add - Admin notice for WhatsApp utility messaging recruitment by @iodic in #3211

[See changelog for all versions](https://raw.githubusercontent.com/facebook/facebook-for-woocommerce/refs/heads/releases/changelog.txt).
