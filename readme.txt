=== Facebook for WooCommerce ===
Contributors: facebook
Tags: meta, facebook, conversions api, catalog sync, ads
Requires at least: 5.6
Tested up to: 6.8.1
Stable tag: 3.5.2
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

= 3.5.2 - 2025-06-04 =
*  Tweak - Update ALL_PRODUCTS_PLUGIN_VERSION to 3.5.3 @tzahgr

= 3.5.0 - 2025-05-28 =
*  Add - Create basic checkout permalink w/ products and coupon support by @ajello-meta in #2887
*  Add - Common Feed Upload Framework by @jmencab in #2875
*  Fix - Fix bug where templates were not loading correctly by @ajello-meta in #2915
*  Tweak - Change MICE to use base site url instead of shop url by @carterbuce in #2934
*  Tweak - Improve custom checkout UI by @ajello-meta in #2930
*  Tweak - Make custom checkout UI mobile compatible by @ajello-meta in #2942
*  Fix - Update parsing for Checkout URL Product IDs by @carterbuce in #2935
*  Add - Implement dummy logging util by @nealweiMeta in #2920
*  Add - Setup cron job for batch logging with global message queue by @nealweiMeta in #2924
*  Add - Error log request api activate by @nealweiMeta in #2933
*  Add - Log locally with debug mode enabled by @nealweiMeta in #2939
*  Add - Ratings and reviews feed upload by @nrostrow-meta in #2937
*  Tweak - Feed upload skip logic and logging calls by @nrostrow-meta in #2964
*  Add - Add function to fetch feed upload instance by @nrostrow-meta in #2970
*  Tweak - Have feed uploads always use feed generator by @nrostrow-meta in #2971
*  Tweak - Trigger metadata feed uploads on CPI ID change (post onboarding) by @nrostrow-meta in #2995
*  Add - Shipping profile feed upload button by @nrostrow-meta in #3140
*  Add - Navigation menu feed upload logic by @nrostrow-meta in #3159
*  Fix - Fixing some fclose and logging gaps in the feed upload logic by @nrostrow-meta in #3192
*  Add - Enabling navigation menu feed upload and adding manual sync button by @nrostrow-meta in #3223
*  Add - Promotions feed upload by @carterbuce in #2941
*  Add - Plugin AJAX API Framework by @sol-loup in #2928
*  Tweak - Test Infrastructure Enhancement by @sol-loup in #2944
*  Add - Implement telemetry logs api by @nealweiMeta in #2940
*  Fix -  Make error logging event configurable by @nealweiMeta in #2954
*  Add - Implement logging toggle by @nealweiMeta in #2959
*  Fix - auto products sync by @nealweiMeta in #2978
*  Tweak - Sync products with restriction by @nealweiMeta in #2983
*  Fix - Fix use_enhanced_onboarding for legacy connections by @carterbuce in #2986
*  Add - Create enhanced settings UI by @ajello-meta in #2968
*  Add - Create new troubleshooting drawer from legacy debug settings by @ajello-meta in #2977
*  Add - Add manual product and coupon sync buttons by @ajello-meta in #2984
*  Tweak - Make page title in enhanced settings static by @ajello-meta in #2985
*  Tweak - Align finalized content for logging toggle by @nealweiMeta in #2992
*  Tweak - Improve local log by @nealweiMeta in #3009
*  Fix - Fix free shipping coupon sync by @carterbuce in #2993
*  Tweak - Add logging for feed generation scheduling failure by @carterbuce in #2994
*  Tweak - Add logging in checkout for coupon code by @ajello-meta in #2991
*  Tweak - Clean up CSS in enhanced settings UI by @ajello-meta in #2996
*  Tweak - Remove the "Advertise" tab by @ajello-meta in #3024
*  Tweak - Sync "Usage Count" in Promos Feed by @carterbuce in #3036
*  Tweak - Disable mini_shops product capability for unsupported items by @carterbuce in #3084
*  Add - Add usage logging for enhanced settings tabs by @ajello-meta in #3202
*  Tweak - Remove UI of a checkbox that controls enablement of the new style feed generation by @mshymon in #3056
*  Fix - Fix linter errors for ./includes/fbutils.php files by @ajello-meta in #3075
*  Fix - Hotfix for Rollout Switches by @vinkmeta in #3236
*  Add - Opt out sync experience. by @SayanPandey in #3220
*  Fix - Added a transient flag to avoid flooding of product set api requests by @vinkmeta in #3245
*  Fix - Additional check for the opt-out banner by @SayanPandey in #3259
*  Fix - Bump up GraphAPI version to 21 by @vahidkay-meta in #3219
*  Fix - fix linter errors for ./class-wc-facebookcommerce.php by @ajello-meta in #3255
*  Fix - fix linter errors for ./facebook-commerce-events-tracker.php by @ajello-meta in #3254
*  Fix - fix linter errors for ./includes/Admin/Settings_Screens/Advertise.php by @ajello-meta in #3237
*  Fix - fix linter errors for ./includes/Admin/Settings_Screens/Product_Sync.php by @ajello-meta in #3239
*  Fix - fix function return typing for get_settings() by @ajello-meta in #3257
*  Tweak - Addition check for opt out by @SayanPandey in #3259
*  Tweak - Update the GraphAPI version to 21 by @vahidkay-meta in #3219
*  Fix - Enabled rollout switch only for plugin admins by @vinkmeta in #3242
*  Add - reset connection functionality by @jczhuoMeta in #3262
*  Fix - fixing the non static method called as static issue by @SayanPandey in #3263
*  Fix - Fix linter errors for ./facebook-commerce.php by @ajello-meta in #3251
*  Fix - updating banners to show the latest version as 3.5.1 by @SayanPandey in #3265
*  Tweak - enforced product sets functionality for plugin admins by @vinkmeta in #3243
*  Tweak - removing the product set functionality migration to Admin by @vinkmeta in #3267
*  Tweak - removing the migration of rollout switch to Admin by @vinkmeta in #3268
*  Fix - fix to remove html encoding in fb product sets names by @mshymon in #3266
*  Add - trigger product sets sync in Handler on catalog ID update (new SAOff MBE onboarding flow) by @mshymon in #3270
*  Fix - fix linter errors for ./facebook-commerce-pixel-event.php by @ajello-meta in #3248
*  Fix - fix /variation field persistence & rich text description handling for variants by @devbodaghe in #3269
*  Tweak - update product validation logic for checkout by @ajello-meta in #3271
*  Fix - Adding precautionary bug fix by @SayanPandey in #3275
*  Add - Added Loading States to WAUM flows by @woo-ardsouza in #3272
*  Add - Add logging for coupon in checkout by @ajello-meta in #3279
*  Fix - fix inconsistency between enhanced and legacy flow by @jczhuoMeta in #3285
*  Fix - Removing the tag to get rid of warnings by @SayanPandey in #3287

[See changelog for all versions](https://raw.githubusercontent.com/facebook/facebook-for-woocommerce/refs/heads/main/changelog.txt).
