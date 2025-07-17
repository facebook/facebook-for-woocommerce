# Product Attribute Mapping Analytics - Engineering Implementation Plan

## Problem Statement

We need to track and send analytics data to Meta about:
1. **Products with mapped attributes**: Count of products that have at least one mapped attribute with a value
2. **Total attribute mappings**: Total count of all mapped attributes across all products

This data should be sent to Meta automatically as background telemetry to understand mapping usage patterns.

## Requirements

### Functional Requirements
- Track number of products using mapped attributes
- Track total count of attribute mappings across all products
- Send analytics data to Meta automatically
- No UI required - pure background functionality
- Minimal performance impact on existing operations

### Non-Functional Requirements
- Run only when necessary (not on every product edit)
- Use existing plugin infrastructure
- Maintain data accuracy
- Handle large product catalogs efficiently

## Solution Analysis

### Option 1: Batch API Integration ❌
**Approach**: Hook into individual product sync operations via batch API.

**Pros**:
- Real-time tracking
- Incremental updates

**Cons**:
- Only captures products being actively edited
- Incomplete data (doesn't cover all products)
- Would require complex accumulation logic
- Performance impact on every product edit

**Verdict**: Rejected - doesn't provide complete store-wide analytics

### Option 2: Scheduled Background Job ⚠️
**Approach**: Daily/weekly scheduled job to scan all products.

**Pros**:
- Complete data coverage
- Predictable timing
- No impact on user operations

**Cons**:
- Additional scheduling complexity
- Duplicate logic with existing product processing
- Potential performance issues with large catalogs

**Verdict**: Viable but not optimal

### Option 3: Feed Generation Hook ✅ (Chosen)
**Approach**: Hook into existing feed generation process.

**Pros**:
- Complete data coverage (all products)
- Leverages existing product processing
- Efficient timing (when feed is generated)
- Uses existing infrastructure
- No additional scheduling needed

**Cons**:
- Tied to feed generation frequency
- Slightly delays feed completion

**Verdict**: Optimal solution

## Implementation Plan

### Phase 1: Core Analytics Calculation

```php
class ProductAttributeAnalytics {
    /**
     * Calculate store-wide attribute mapping statistics
     */
    public static function calculate_mapping_analytics() {
        $products_with_mappings = 0;
        $total_attribute_mappings = 0;
        
        // Use same product query as feed generation
        $product_ids = WC_Facebookcommerce_Utils::get_all_product_ids_for_sync();
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            // Get mapped attributes for this product
            $mapped_attributes = ProductAttributeMapper::get_mapped_attributes($product);
            
            if (!empty($mapped_attributes)) {
                $products_with_mappings++;
                $total_attribute_mappings += count($mapped_attributes);
            }
        }
        
        return [
            'products_with_mapped_attributes' => $products_with_mappings,
            'total_attribute_mappings' => $total_attribute_mappings,
            'total_products_processed' => count($product_ids),
            'timestamp' => current_time('mysql')
        ];
    }
}
```

### Phase 2: Integration with Feed Generation

```php
// Hook into feed generation completion
add_action('wc_facebook_feed_generation_completed', 'send_attribute_analytics_to_meta');

function send_attribute_analytics_to_meta() {
    // Optional: Throttle to max once per day
    $last_sent = get_option('wc_facebook_last_analytics_sent', 0);
    if (time() - $last_sent < DAY_IN_SECONDS) {
        return;
    }
    
    // Calculate analytics
    $analytics = ProductAttributeAnalytics::calculate_mapping_analytics();
    
    // Send via existing logging system
    Logger::log(
        'Product attribute mapping analytics',
        $analytics,
        array(
            'should_send_log_to_meta' => true,
            'should_save_log_in_woocommerce' => true,
            'woocommerce_log_level' => \WC_Log_Levels::DEBUG,
        )
    );
    
    // Update last sent timestamp
    update_option('wc_facebook_last_analytics_sent', time());
}
```

### Phase 3: Error Handling & Optimization

```php
function send_attribute_analytics_to_meta() {
    try {
        $analytics = ProductAttributeAnalytics::calculate_mapping_analytics();
        
        Logger::log(
            'Product attribute mapping analytics',
            $analytics,
            array(
                'should_send_log_to_meta' => true,
                'should_save_log_in_woocommerce' => true,
                'woocommerce_log_level' => \WC_Log_Levels::DEBUG,
            )
        );
        
        update_option('wc_facebook_last_analytics_sent', time());
        
    } catch (Exception $e) {
        Logger::log(
            'Failed to send attribute analytics: ' . $e->getMessage(),
            [],
            array(
                'should_send_log_to_meta' => false,
                'should_save_log_in_woocommerce' => true,
                'woocommerce_log_level' => \WC_Log_Levels::ERROR,
            )
        );
    }
}
```

## Technical Tradeoffs

### Data Transmission Method

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Batch API | Product-focused | Wrong use case | ❌ |
| Custom API | Full control | Additional complexity | ❌ |
| Existing Logger | Proven, integrated | Limited to log format | ✅ |

**Chosen**: Existing Logger system
- Already handles Meta authentication
- Consistent with plugin patterns
- Proven reliability

### Timing Strategy

| Option | Pros | Cons | Decision |
|--------|------|------|----------|
| Real-time | Immediate | Incomplete data | ❌ |
| Scheduled | Predictable | Additional complexity | ❌ |
| Feed Hook | Complete data | Tied to feed timing | ✅ |

**Chosen**: Feed Generation Hook
- Guarantees complete data coverage
- Efficient resource usage
- No additional scheduling overhead

### Performance Considerations

| Aspect | Impact | Mitigation |
|--------|--------|------------|
| Product Query | Moderate | Reuse existing feed query logic |
| Attribute Calculation | Low | Leverage existing `get_mapped_attributes()` |
| Timing | Minimal | Runs only during feed generation |

## Implementation Timeline

### Week 1: Core Development
- [ ] Create `ProductAttributeAnalytics` class
- [ ] Implement `calculate_mapping_analytics()` method
- [ ] Add basic error handling

### Week 2: Integration
- [ ] Hook into feed generation completion
- [ ] Implement throttling mechanism
- [ ] Add logging integration

### Week 3: Testing & Optimization
- [ ] Test with large product catalogs
- [ ] Verify data accuracy
- [ ] Performance testing

## Monitoring & Validation

### Success Metrics
- Analytics data successfully sent to Meta
- Minimal performance impact on feed generation
- Data accuracy verified against manual counts

### Failure Scenarios
- Feed generation disabled → No analytics sent (acceptable)
- Product query failure → Logged error, no analytics sent
- Logger failure → Logged locally, retry on next feed generation

## Future Considerations

### Potential Enhancements
- Historical trend tracking
- More granular attribute usage statistics
- Integration with other sync methods (if needed)

### Maintenance
- Monitor for changes in feed generation process
- Update if `ProductAttributeMapper` API changes
- Adjust throttling based on usage patterns

## Dependencies

### Existing Systems
- `ProductAttributeMapper::get_mapped_attributes()`
- `WC_Facebookcommerce_Utils::get_all_product_ids_for_sync()`
- Plugin Logger system
- Feed generation process

### WordPress/WooCommerce
- WordPress Options API (`get_option`, `update_option`)
- WooCommerce product system
- Action hooks (`wc_facebook_feed_generation_completed`)

## Conclusion

The chosen approach provides complete, accurate analytics data with minimal system impact by leveraging existing infrastructure. The feed generation hook ensures we capture all products in the store while maintaining efficiency through strategic timing and throttling.
