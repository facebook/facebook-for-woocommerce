# Advanced ProductAttributeMapper Features

## Overview for Developers & Power Users

The ProductAttributeMapper introduces sophisticated attribute mapping capabilities with priority-based resolution, custom filtering, and extensive normalization features.

## Architecture & Priority System

### Mapping Phases (In Order of Priority)

1. **Custom Mappings (UI/Filter)** - Priority: 90
   - Explicit mappings via settings or filters
   - `wc_facebook_product_attribute_mappings` filter

2. **Direct Standard Field Matches** - Priority: 100  
   - Exact matches: `color` → `color`, `brand` → `brand`
   - Display name matches: `Material` → `material`

3. **Slug Matches** - Priority: 80
   - Pre-defined slug mappings
   - `age-group` → `age_group`, `colour` → `color`

4. **Mapped via check_attribute_mapping** - Priority: 60
   - Built-in attribute name variations
   - `product_color` → `color`, `item_size` → `size`

5. **Meta Values** - Priority: 20
   - Fallback to `_wc_facebook_enhanced_catalog_attributes_*` meta

## Custom Filter Integration

### Basic Custom Mapping
```php
add_filter('wc_facebook_product_attribute_mappings', function($mappings, $product) {
    return array_merge($mappings, [
        'fabric_composition' => 'material',
        'target_demographic' => 'gender',
        'product_line' => 'brand',
        'item_state' => 'condition'
    ]);
}, 10, 2);
```

### Conditional Mapping by Product
```php
add_filter('wc_facebook_product_attribute_mappings', function($mappings, $product) {
    // Map differently based on product category
    if (has_term('clothing', 'product_cat', $product->get_id())) {
        $mappings['fabric'] = 'material';
        $mappings['fit'] = 'size';
    } elseif (has_term('electronics', 'product_cat', $product->get_id())) {
        $mappings['brand_name'] = 'brand';
        $mappings['warranty'] = 'condition';
    }
    
    return $mappings;
}, 10, 2);
```

## Advanced Testing Scenarios

### Scenario A: Complex Priority Resolution
Test multiple conflicting attributes to verify priority system:

```php
// Product attributes setup
$attributes = [
    'color' => 'Red',                    // Direct match (Priority 100)
    'product_color' => 'Blue',           // Mapping variation (Priority 60)  
    'item_color' => 'Green',             // Mapping variation (Priority 60)
    'custom_color' => 'Yellow'           // Custom mapping (Priority 90)
];

// With custom filter:
add_filter('wc_facebook_product_attribute_mappings', function($mappings) {
    return array_merge($mappings, ['custom_color' => 'color']);
}, 10, 2);

// Expected result: 'Red' (direct match wins)
// Without 'color' attribute: 'Yellow' (custom mapping wins)
```

### Scenario B: Normalization Edge Cases
Test value normalization with unusual inputs:

```php
$test_cases = [
    'gender' => [
        'M' => 'male',
        'F' => 'female', 
        'Boys Size' => 'male',
        'Ladies' => 'female',
        'Unisex' => 'unisex',
        'Invalid' => 'Invalid' // Pass through
    ],
    'age_group' => [
        'Grown-ups' => 'adult',
        'Teenagers' => 'teen',
        'Little ones' => 'kids',
        'Babies' => 'infant',
        'Invalid' => 'Invalid' // Pass through
    ],
    'condition' => [
        'BNIB' => 'new',
        'Mint' => 'new',
        'Lightly used' => 'used',
        'Factory refurb' => 'refurbished',
        'Invalid' => 'Invalid' // Pass through
    ]
];
```

### Scenario C: Performance Testing
Test with high-volume product scenarios:

```php
// Create test products with various attribute combinations
for ($i = 0; $i < 100; $i++) {
    $product = wc_get_product($product_ids[$i]);
    
    // Measure mapping performance
    $start = microtime(true);
    $mappings = ProductAttributeMapper::get_mapped_attributes($product);
    $duration = microtime(true) - $start;
    
    // Should complete in < 0.1 seconds per product
    assert($duration < 0.1, "Mapping took too long: {$duration}s");
}
```

## Custom Attribute Mapping Storage

### Database Options
The mapper supports custom mappings via WordPress options:

```php
// Save custom mappings
$custom_mappings = [
    'product_weight' => 'size',
    'manufacturing_date' => 'custom_label_0',
    'supplier' => 'brand'
];
update_option('wc_facebook_custom_attribute_mappings', $custom_mappings);

// Retrieve custom mappings  
$mappings = get_option('wc_facebook_custom_attribute_mappings', []);
```

### Programmatic API
```php
// Add mapping programmatically
ProductAttributeMapper::add_custom_attribute_mapping('fabric_type', 'material');

// Remove mapping
ProductAttributeMapper::remove_custom_attribute_mapping('fabric_type');

// Set multiple mappings
ProductAttributeMapper::set_custom_attribute_mappings([
    'item_weight' => 'size',
    'brand_name' => 'brand'
]);

// Get all custom mappings
$mappings = ProductAttributeMapper::get_custom_attribute_mappings();
```

## Debug & Monitoring

### Enable Debug Logging
```php
// In wp-config.php
define('WC_FACEBOOK_DEBUG', true);

// Or via filter
add_filter('wc_facebook_enable_debug', '__return_true');
```

### Log Analysis
Check `wp-content/uploads/fb-product-debug.log` for entries like:
```
ProductAttributeMapper: Starting attribute mapping for product 123
Available attributes: ["pa_color", "size", "custom_material"]
PHASE 0: Direct match for standard field 'size' with attribute 'size'
PHASE 2: Mapped 'pa_color' to 'color'
Final mapped attributes: {"size":"Large","color":"Red"}
```

### Performance Monitoring
```php
add_action('wc_facebook_product_mapped_attributes', function($mappings, $product) {
    if (count($mappings) === 0) {
        error_log("No attributes mapped for product {$product->get_id()}");
    }
    
    if (count($mappings) > 10) {
        error_log("High attribute count for product {$product->get_id()}: " . count($mappings));
    }
}, 10, 2);
```

## Edge Cases & Limitations

### Known Edge Cases
1. **Numeric Slugs**: Attributes with numeric slugs (e.g., `123`) require special handling
2. **Unicode Characters**: Non-ASCII attribute names may need sanitization  
3. **Very Long Values**: Facebook has field length limits (typically 100-255 chars)
4. **Taxonomy vs Custom**: Different behavior between `pa_*` and custom attributes

### Limitation Workarounds
```php
// Handle numeric slugs
add_filter('wc_facebook_product_attribute_mappings', function($mappings, $product) {
    // Map numeric attribute ID to meaningful field
    $mappings['123'] = 'material'; // Where '123' is a numeric attribute slug
    return $mappings;
}, 10, 2);

// Handle long values
add_filter('wc_facebook_integration_prepare_product', function($product_data) {
    foreach (['color', 'size', 'material', 'pattern'] as $field) {
        if (isset($product_data[$field]) && strlen($product_data[$field]) > 100) {
            $product_data[$field] = substr($product_data[$field], 0, 97) . '...';
        }
    }
    return $product_data;
});
```

## Integration with Other Plugins

### WPML/Multilingual Support
```php
add_filter('wc_facebook_product_attribute_mappings', function($mappings, $product) {
    // Adjust mappings based on language
    if (defined('ICL_LANGUAGE_CODE')) {
        switch (ICL_LANGUAGE_CODE) {
            case 'de':
                $mappings['farbe'] = 'color';
                $mappings['größe'] = 'size';
                break;
            case 'fr':
                $mappings['couleur'] = 'color';
                $mappings['taille'] = 'size';
                break;
        }
    }
    
    return $mappings;
}, 10, 2);
```

### Custom Fields Plugin Integration
```php
// Map ACF fields to Facebook attributes
add_filter('wc_facebook_product_attribute_mappings', function($mappings, $product) {
    if (function_exists('get_field')) {
        $custom_brand = get_field('custom_brand', $product->get_id());
        if ($custom_brand) {
            // Create virtual mapping for ACF field
            $mappings['custom_brand'] = 'brand';
        }
    }
    
    return $mappings;
}, 10, 2);
```

## Testing Utilities

### Automated Test Suite
```php
class AttributeMapperTestSuite {
    
    public function test_all_standard_mappings() {
        $standard_fields = ['color', 'size', 'material', 'brand', 'gender', 'age_group'];
        
        foreach ($standard_fields as $field) {
            $product = $this->create_test_product([$field => 'test_value']);
            $mappings = ProductAttributeMapper::get_mapped_attributes($product);
            
            $this->assertEquals('test_value', $mappings[$field] ?? null);
        }
    }
    
    public function test_priority_resolution() {
        $product = $this->create_test_product([
            'color' => 'direct_match',
            'product_color' => 'variation_match'
        ]);
        
        $mappings = ProductAttributeMapper::get_mapped_attributes($product);
        $this->assertEquals('direct_match', $mappings['color']);
    }
    
    public function test_normalization() {
        $test_cases = [
            ['gender', 'Women', 'female'],
            ['age_group', 'Kids', 'kids'],
            ['condition', 'Brand New', 'new']
        ];
        
        foreach ($test_cases as [$field, $input, $expected]) {
            $product = $this->create_test_product([$field => $input]);
            $mappings = ProductAttributeMapper::get_mapped_attributes($product);
            
            $this->assertEquals($expected, $mappings[$field]);
        }
    }
}
```

### Manual Testing Helpers
```php
// Quick test function for console/debugging
function test_attribute_mapping($product_id, $attributes = []) {
    $product = wc_get_product($product_id);
    
    if (!empty($attributes)) {
        // Set test attributes
        $attr_objects = [];
        foreach ($attributes as $name => $value) {
            $attr = new WC_Product_Attribute();
            $attr->set_name($name);
            $attr->set_options([$value]);
            $attr_objects[] = $attr;
        }
        $product->set_attributes($attr_objects);
        $product->save();
    }
    
    // Get mappings
    $mappings = ProductAttributeMapper::get_mapped_attributes($product);
    $unmapped = ProductAttributeMapper::get_unmapped_attributes($product);
    
    return [
        'mapped' => $mappings,
        'unmapped' => $unmapped,
        'product_id' => $product_id
    ];
}

// Usage:
// test_attribute_mapping(123, ['color' => 'Red', 'weight' => '2kg']);
```

## Best Practices for Developers

1. **Use Filters Early**: Apply custom mappings in `init` or earlier hooks
2. **Cache Mappings**: For high-volume sites, consider caching mapping results
3. **Validate Inputs**: Always validate attribute names and values
4. **Monitor Performance**: Log slow mapping operations in production
5. **Test Edge Cases**: Verify numeric slugs, unicode, and empty values
6. **Document Custom Mappings**: Keep track of site-specific mapping rules

## Migration from Legacy System

### Upgrading from Previous Versions
```php
// Migrate old custom mapping format
$old_mappings = get_option('wc_facebook_legacy_mappings', []);
$new_mappings = [];

foreach ($old_mappings as $old_format) {
    // Convert old format to new format
    $new_mappings[$old_format['wc_attr']] = $old_format['fb_field'];
}

update_option('wc_facebook_custom_attribute_mappings', $new_mappings);
```

This advanced guide provides the technical depth needed for developers and power users to fully leverage the ProductAttributeMapper system. 