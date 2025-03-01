# Facebook for WooCommerce is now an official plugin from Meta.

[![PHP Coding Standards](https://github.com/woocommerce/facebook-for-woocommerce/actions/workflows/php-cs-on-changes.yml/badge.svg)](https://github.com/woocommerce/facebook-for-woocommerce/actions/workflows/php-coding-standards.yml)

**We're excited to announce that the plugin is now owned by Meta, and we invite the developer community to join us in shaping its future through contributions.**

Grow your business on Facebook and Instagram. Easily promote your products and target accurately using powerful sales and marketing tools. Reach new customers and drive traffic to your website with seamless ad experiences, from discovery to conversion. Automatically sync your eligible products to your Meta catalog, so you can easily create ads right where your customers are.
- Help drive better ad performance by setting up a conversion pixel
- Easily set up your ads with a one-time account connection
- Sell from one inventory that automatically syncs to your catalog used for ads


### This is the development repository for the Facebook for WooCommerce plugin.
**We're excited to announce that the app is now owned by Meta, and we invite the developer community to join us in shaping its future through contributions.**

Grow your business on Facebook and Instagram. Easily promote your products and target accurately using powerful sales and marketing tools. Reach new customers and drive traffic to your website with seamless ad experiences, from discovery to conversion. Automatically sync your eligible products to your Meta catalog, so you can easily create ads right where your customers are.
- Help drive better ad performance by setting up a conversion pixel
- Easily set up your ads with a one-time account connection
- Sell from one inventory that automatically syncs to your catalog used for ads


### This is the development repository for the Facebook for WooCommerce plugin.

- [WooCommerce.com product page](https://woocommerce.com/products/facebook/)
- [WordPress.org plugin page](https://wordpress.org/plugins/facebook-for-woocommerce/)
- [User documentation](https://woocommerce.com/document/facebook-for-woocommerce/)

## Support

The best place to get support is
the [WordPress.org Facebook for WooCommerce forum](https://wordpress.org/support/plugin/facebook-for-woocommerce/).

If you have a WooCommerce.com account, you
can [search for help or submit a help request on WooCommerce.com](https://woocommerce.com/my-account/contact-support/).

### Logging

The plugin offers logging that can help debug various problems. You can enable debug mode in the main plugin settings
panel under the `Enable debug mode` section.
By default plugin omits headers in the requests to make the logs more readable. If debugging with headers is necessary
you can enable the headers in the logs by setting `wc_facebook_request_headers_in_debug_log` option to true.

## Development

### Developing

- Clone this repository into the `wp-content/plugins/` folder your WooCommerce development environment.
- Install dependencies:
	- `npm install`
	- `composer install`
- Build assets:
	- `npm start` to build a development version
- Linting:
	- `npm run lint:php` to run PHPCS linter on all PHP files
- Testing:
	- `./bin/install-wp-tests.sh <test-db-name> <db-user> <db-password> [db-host]` to set up testing environment
	- `npm run test:php` to run PHP unit tests on all PHP files
	- `./vendor/bin/phpunit --coverage-html=reports/coverage` to run PHP unit tests with coverage

#### Production build

- `npm run build` : Builds a production version.

### Releasing

Refer to
the [wiki for details of how to build and release the plugin](https://github.com/woocommerce/facebook-for-woocommerce/wiki/Build-&-Release).

### PHPCS Linting and PHP 8.1+

We currently do not support PHPCS on PHP 8.1+ versions. Please run PHPCS checks on PHP 8.0 or lower versions.
Refer [#2624 PR](https://github.com/woocommerce/facebook-for-woocommerce/pull/2624/) for additional context.
