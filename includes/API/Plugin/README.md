# Meta for WooCommerce REST API

This document outlines how to work with the REST API system in the Meta for WooCommerce plugin.

## Architecture Overview

The REST API is built on these key components:

- `Controller`: Central registry for endpoints and JS-enabled requests
- `AbstractRESTEndpoint`: Base class for endpoint handlers
- `Request`: Base class for request validation
- `JS_Exposable`: Trait for exposing endpoints to JavaScript

## Adding a New REST Endpoint

### 1. Create Request Class

```php
namespace WooCommerce\Facebook\API\Plugin\YourFeature\YourAction;

use WooCommerce\Facebook\API\Plugin\Request as RESTRequest;
use WooCommerce\Facebook\API\Plugin\Traits\JS_Exposable;

class Request extends RESTRequest {
    use JS_Exposable;
    
    /**
     * Gets the API endpoint for this request.
     *
     * @return string
     */
    public function get_endpoint() {
        return 'your-feature/your-action';
    }
    
    /**
     * Gets the HTTP method for this request.
     *
     * @return string
     */
    public function get_method() {
        return 'POST'; // or GET, PUT, DELETE
    }
    
    /**
     * Gets the parameter schema for this request.
     *
     * @return array
     */
    public function get_param_schema() {
        return [
            'param_name' => [
                'type'     => 'string', // string, int, bool, array
                'required' => true,
            ],
            // Add more parameters as needed
        ];
    }
    
    /**
     * Gets the JavaScript function name for this request.
     *
     * @return string
     */
    public function get_js_function_name() {
        return 'YourActionName';
    }
    
    /**
     * Validate the request.
     *
     * @return true|\WP_Error
     */
    public function validate() {
        if (empty($this->get_param('param_name'))) {
            return new \WP_Error(
                'missing_param',
                __('Missing required parameter', 'facebook-for-woocommerce'),
                ['status' => 400]
            );
        }
        
        return true;
    }
}
```

### 2. Create Handler Class

```php
namespace WooCommerce\Facebook\API\Plugin\YourFeature;

use WooCommerce\Facebook\API\Plugin\AbstractRESTEndpoint;
use WooCommerce\Facebook\API\Plugin\YourFeature\YourAction\Request as YourActionRequest;

class Handler extends AbstractRESTEndpoint {
    /**
     * Register routes for this endpoint.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            $this->get_namespace(),
            '/your-feature/your-action',
            [
                'methods'             => \WP_REST_Server::CREATABLE, // or READABLE, EDITABLE, DELETABLE
                'callback'            => [$this, 'handle_your_action'],
                'permission_callback' => [$this, 'permission_callback'],
            ]
        );
        
        // Register additional routes as needed
    }
    
    /**
     * Handle the action request.
     *
     * @param \WP_REST_Request $wp_request
     * @return \WP_REST_Response
     */
    public function handle_your_action(\WP_REST_Request $wp_request) {
        try {
            $request = new YourActionRequest($wp_request);
            $validation_result = $request->validate();
            
            if (is_wp_error($validation_result)) {
                return $this->error_response(
                    $validation_result->get_error_message(),
                    400
                );
            }
            
            // Process the request
            $param_value = $request->get_param('param_name');
            
            // Do something with the parameters
            
            return $this->success_response([
                'message' => __('Action completed successfully', 'facebook-for-woocommerce'),
                'data'    => ['processed' => true],
            ]);
            
        } catch (\Exception $e) {
            return $this->error_response(
                $e->getMessage(),
                500
            );
        }
    }
}
```

### 3. Register in Controller

Add your new endpoint handler and request class to the `Controller` constants:

```php
class Controller {
    /** @var array Endpoint handler classes */
    const ENDPOINT_HANDLERS = [
        Settings\Handler::class,
        YourFeature\Handler::class, // Add your handler here
    ];

    /** @var array JS-enabled request classes */
    const JS_ENABLED_REQUESTS = [
        'WooCommerce\Facebook\API\Plugin\Settings\Update\Request',
        'WooCommerce\Facebook\API\Plugin\Settings\Uninstall\Request',
        'WooCommerce\Facebook\API\Plugin\YourFeature\YourAction\Request', // Add your request here
    ];
    
    // Rest of the class...
}
```

## Consuming the API in JavaScript

### 1. Ensure the API Script is Loaded

First, make sure the API script is loaded on your page:

```php
wp_enqueue_script(
    'plugin-api-client',
    facebook_for_woocommerce()->get_plugin_url() . '/assets/js/admin/plugin-api-client.js',
    ['jquery'],
    \WC_Facebookcommerce::VERSION,
    true
);
```

### 2. Set Up Event Listeners (if needed)

For features like the Facebook iframe integration, set up event listeners to handle messages.

**Security:** Always validate `event.origin` against an allowlist and verify `event.data` is a non-null object before dereferencing. Without both checks, any third-party page can drive your REST endpoints against the admin's session.

The plugin centralizes the validator in `WooCommerce\Facebook\Admin\Postmessage_Origin_Validation`. Use it instead of hand-rolling the check — it ships with the static allowlist plus support for Facebook on-demand (OD) instances in two label shapes (both validated label-by-label against the parsed URL):

| Shape    | Example                                                    | Constraint on the dynamic label                               |
| -------- | ---------------------------------------------------------- | ------------------------------------------------------------- |
| Format A | `https://www.<digits>.od.commercepartnerhub.com`           | `<digits>` matches `^[0-9]+$`                                 |
| Format B | `https://www.my-od.commercepartnerhub.com` and `https://www.my-od-<N>.commercepartnerhub.com` | Literal `my-od` prefix, optional `-<N>` where `<N>` is `^[0-9]+$` |

#### What NOT to do

The validator is **not** simply a string comparison. Two patterns have caused real production incidents (see Meta SEV S649287) and must be avoided when matching iframe origins:

* `event.origin.endsWith('.commercepartnerhub.com')` — an attacker who registers `evilcommercepartnerhub.com` (no leading dot) trivially bypasses this with `evil.commercepartnerhub.com.attacker.com` or a subdomain like `xxx.evilcommercepartnerhub.com` once the suffix-matching is combined with any reconstruction step.
* `event.origin.match(/^https:\/\/www\..*\.od\.commercepartnerhub\.com$/)` — `.*` greedy matching is fine for one specific origin shape but trivially broken when paired with any subsequent string slicing of the matched group; prefer label-aware checks.

Use the parsed `URL` object and walk the hostname labels explicitly. The shipped helper does exactly this.

#### Recommended pattern (using the shipped helper)

```php
use WooCommerce\Facebook\Admin\Postmessage_Origin_Validation;

/**
 * Renders the message handler script in the footer.
 */
public function render_message_handler() {
    if ( ! $this->is_current_screen_page() ) {
        return;
    }

    $origin_validator_js = Postmessage_Origin_Validation::generate_inline_js();
    ?>
    <script type="text/javascript">
        <?php echo $origin_validator_js; // emits `isAllowedOrigin(origin)` ?>
        window.addEventListener('message', function(event) {
            if (!isAllowedOrigin(event.origin)) {
                return;
            }
            const message = event.data;
            if (!message || typeof message !== 'object') {
                return;
            }
            const messageEvent = message.event;

            if (messageEvent === 'SomeEvent::ACTION') {
                FacebookWooCommerceAPI.yourActionName({
                    param_name: message.some_data
                })
                .then(function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        console.error('Error:', response);
                    }
                })
                .catch(function(error) {
                    console.error('Error:', error);
                });
            }
        }, false);
    </script>
    <?php
}
```

#### Extending the allowlist

To add additional origins (e.g. a private staging deployment) hook the
`wc_facebook_commerce_partner_allowed_origins` filter:

```php
add_filter( 'wc_facebook_commerce_partner_allowed_origins', function ( $config ) {
    // Add an exact-match origin.
    $config['exact'][]    = 'https://staging.commercepartnerhub.example.com';

    // Add a Format-A base (`www.<digits>.od.<base>`).
    $config['od_bases'][] = 'commercepartnerhub-staging.example.com';

    // Add a Format-B alias (`www.<prefix>[-<N>].<base>`).
    $config['od_aliases'][] = [
        'prefix' => 'staging-od',
        'base'   => 'commercepartnerhub-staging.example.com',
    ];

    return $config;
} );
```

Anything you add via this filter is subjected to the same strict validation — entries with characters outside `[a-z0-9-]` in OD base or alias labels (or whose alias prefix is not exactly one DNS label) are silently dropped to prevent a misconfigured filter from injecting JSON into the inline script.

### 3. Direct API Calls

For direct API calls from your JavaScript code:

```php
/**
 * Adds a button that triggers an API call when clicked.
 */
public function render_action_button() {
    ?>
    <button id="trigger-action" class="button button-primary">
        <?php esc_html_e('Perform Action', 'facebook-for-woocommerce'); ?>
    </button>
    
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#trigger-action').on('click', function() {
                // Show loading state
                $(this).prop('disabled', true).text('Processing...');
                
                // Call the API
                FacebookWooCommerceAPI.yourActionName({
                    param_name: 'value'
                })
                .then(function(response) {
                    if (response.success) {
                        alert('Action completed successfully!');
                    } else {
                        alert('Error: ' + response.message);
                    }
                })
                .catch(function(error) {
                    alert('Error: ' + error.message);
                })
                .finally(function() {
                    // Reset button state
                    $('#trigger-action').prop('disabled', false)
                        .text('<?php esc_html_e('Perform Action', 'facebook-for-woocommerce'); ?>');
                });
            });
        });
    </script>
    <?php
}
```

### 4. Real-World Example (from Shops.php)

How the Shops screen handles Commerce Extension events (see `Shops.php::generate_inline_enhanced_onboarding_script()`). This is the canonical implementation — note how it uses the centralized validator rather than hand-rolling an `endsWith` check.

```php
use WooCommerce\Facebook\Admin\Postmessage_Origin_Validation;

/**
 * Generates the inline script for the enhanced onboarding flow.
 *
 * @return string
 */
public function generate_inline_enhanced_onboarding_script() {
    $nonce               = wp_json_encode( wp_create_nonce( 'wp_rest' ) );
    $origin_validator_js = Postmessage_Origin_Validation::generate_inline_js();

    return <<<JAVASCRIPT
        const fbAPI = GeneratePluginAPIClient({$nonce});
        {$origin_validator_js}
        window.addEventListener('message', function(event) {
            if (!isAllowedOrigin(event.origin)) {
                return;
            }
            const message = event.data;
            if (!message || typeof message !== 'object') {
                return;
            }
            const messageEvent = message.event;

            if (messageEvent === 'CommerceExtension::INSTALL' && message.success) {
                const requestBody = {
                    access_token: message.access_token,
                    merchant_access_token: message.access_token,
                    page_access_token: message.access_token,
                    product_catalog_id: message.catalog_id,
                    pixel_id: message.pixel_id,
                    page_id: message.page_id,
                    // Additional parameters...
                };

                fbAPI.updateSettings(requestBody)
                    .then(function(response) {
                        if (response.success) {
                            window.location.reload();
                        } else {
                            console.error('Error updating Facebook settings:', response);
                        }
                    })
                    .catch(function(error) {
                        console.error('Error during settings update:', error);
                    });
            }

            if (messageEvent === 'CommerceExtension::RESIZE') {
                const iframe = document.getElementById('facebook-commerce-iframe-enhanced');
                if (iframe && message.height) {
                    iframe.height = message.height;
                }
            }

            if (messageEvent === 'CommerceExtension::UNINSTALL') {
                fbAPI.uninstallSettings()
                    .then(function(response) {
                        if (response.success) {
                            window.location.reload();
                        }
                    })
                    .catch(function(error) {
                        console.error('Error during uninstall:', error);
                        window.location.reload();
                    });
            }
        });
    JAVASCRIPT;
}
```

## How It Works

1. `InitializeRestAPI` is instantiated when the plugin loads
2. It registers the `Controller` which handles REST API route registration
3. During page load, `generate_js_request_framework()` is called to generate JS API definitions and localize them to the script