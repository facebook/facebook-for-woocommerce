# Facebook for WooCommerce REST API

This directory contains the REST API implementation for the Facebook for WooCommerce plugin.

## Structure

The REST API is organized into the following components:

- `Controller`: Central registration point for all REST API endpoints
- `Endpoint`: Base class for all endpoint handlers
- `Request`: Base class for handling and validating requests
- `Response`: Base class for formatting responses
- `Traits`: Reusable traits for API components

Endpoints are organized by functionality:

- `Settings`: Endpoints for managing plugin settings

## Implementation

The REST API is automatically initialized when the plugin loads. The initialization happens in the bootstrap file:

```php
// Initialize the REST API
require_once( __DIR__ . '/includes/API/REST/bootstrap.php' );
\WooCommerce\Facebook\API\REST\init();
```

## JavaScript API

The REST API endpoints are automatically exposed to JavaScript through a dynamically generated API. The JavaScript API is created based on the PHP endpoint definitions, ensuring that the PHP and JavaScript APIs stay in sync.

### How It Works

1. Each endpoint handler class defines methods that handle REST API requests
2. These methods are annotated with special docblock tags:
   - `@http_method`: The HTTP method (GET, POST, etc.)
   - `@description`: A description of what the endpoint does
3. The request class for each endpoint must use the `JS_Exposable` trait to indicate it should be exposed to JavaScript
4. The `get_js_api_definitions()` method in the `Endpoint` class uses reflection to extract these annotations
5. The API helper functions collect all endpoint definitions and pass them to JavaScript
6. The JavaScript API dynamically creates methods based on these definitions

### Using the JavaScript API

The JavaScript API is automatically available on pages where the Facebook for WooCommerce settings are displayed. You can use it like this:

```javascript
// Update settings
FacebookWooCommerceAPI.updateSettings({
    merchant_access_token: 'your-token',
    access_token: 'your-token',
    pixel_id: 'your-pixel-id'
})
.then(function(response) {
    console.log('Settings updated:', response);
})
.catch(function(error) {
    console.error('Error updating settings:', error);
});

// Uninstall
FacebookWooCommerceAPI.uninstall()
.then(function(response) {
    console.log('Uninstalled:', response);
})
.catch(function(error) {
    console.error('Error uninstalling:', error);
});
```

## Adding New Endpoints

To add a new endpoint:

1. Create a new directory under `includes/API/REST` for your endpoint group
2. Create a `Handler.php` file that extends `Endpoint`
3. Implement the `register_routes()` method
4. Add methods that handle requests, with names starting with `handle_`
5. Annotate your methods with `@http_method` and `@description` tags
6. Create request classes for validating input
7. Add the `JS_Exposable` trait to request classes that should be exposed to JavaScript
8. Add your handler to the `get_api_definitions()` function in `api-helpers.php`

Example handler method:

```php
/**
 * Handle the example request.
 *
 * @since 2.3.5
 * @http_method POST
 * @description Example endpoint
 *
 * @param \WP_REST_Request $wp_request The WordPress request object.
 * @param string $example_param Example parameter.
 * @return \WP_REST_Response
 */
public function handle_example(\WP_REST_Request $wp_request) {
    // Implementation
}
```

Example request class:

```php
namespace WooCommerce\Facebook\API\REST\Settings\Example;

use WooCommerce\Facebook\API\REST\Request as RESTRequest;
use WooCommerce\Facebook\API\REST\Traits\JS_Exposable;

class Request extends RESTRequest {
    // Use this trait to expose the endpoint to JavaScript
    use JS_Exposable;

    public function validate() {
        // Validation logic
        return true;
    }
}
```

This will automatically create a JavaScript method called `example` that can be called like:

```javascript
FacebookWooCommerceAPI.example('param-value')
.then(function(response) {
    console.log('Example response:', response);
})
.catch(function(error) {
    console.error('Error:', error);
});
``` 