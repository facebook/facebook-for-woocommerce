=== Facebook for WooCommerce ===
Contributors: facebook, automattic, woothemes
Tags: facebook, woocommerce, marketing, product catalog feed, pixel
Requires at least: 5.6
Tested up to: 6.7
Stable tag: 3.4.2
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
= 3.4.2 - 2025-03-13 =
* Tweak - Update README.md - Added noification for ownership transfer by @SayanPandey in #2910 and #2916

= 3.4.1 - 2025-02-27 = 
* Tweak - Removed custom field definitions by @devbodaghe in #2876
* Dev - Improved readability of function prepare_product() in fbproduct.php by @mshymon in #2889
* Dev - Enabled PHPUnit code coverage report generation by @carterbruce in #2893

= 3.4.0 - 2025-02-19 =
* Add - FB product video field to add videos. Also added products sync to support the video field with Batch API by @gurtejrehal in #2874
* Tweak - tests for #2874 by @gurtejrehal in #2888
* Tweak - tests for Product Update action as ramp up task by @nealweiMeta in #2883
* Fix - translations loading before the init hook by @iodic in #2866
* Fix - Fixed feeds by requesting a feed file upload session after feed file is generated and added missing new fields to the feed file by @mshymon in #2841

= 3.3.5 - 2025-02-12 = 
* Add - Rich Text Description to Woo Product Sync with Meta by devbodaghe in #2843

= 3.3.4 - 2025-02-11 =
* Fix - Fixing the issue with version number

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
