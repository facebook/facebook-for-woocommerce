# ProductAttributeMapper Feature Testing Guide

## Overview

The **ProductAttributeMapper** feature in Facebook for WooCommerce provides an intelligent system for mapping WooCommerce product attributes to Facebook catalog fields. This enhanced system automatically maps standard attributes and provides flexible options for custom attribute mappings.

## Key Features

- **Automatic Standard Field Mapping**: Maps common WooCommerce attributes to Facebook fields (color, size, material, etc.)
- **Custom Attribute Mapping**: Support for mapping unusual or custom attribute names to Facebook fields
- **Value Normalization**: Intelligent normalization of values for gender, age group, and condition fields
- **Priority-Based Conflict Resolution**: Smart handling when multiple attributes could map to the same Facebook field
- **Unmapped Attribute Handling**: Automatically adds unmapped attributes as custom data

## Test Scenarios

### Scenario 1: Basic Standard Attribute Mapping

**Goal**: Test automatic mapping of standard WooCommerce attributes to Facebook fields.

**Setup Steps**:
1. Create a new WooCommerce product (Simple Product)
2. Navigate to the **Attributes** tab in the product editor
3. Add the following attributes:

| Attribute Name | Values | Type |
|----------------|--------|------|
| Color | Red, Blue | Custom attribute |
| Size | Large, Medium | Custom attribute |
| Material | Cotton | Custom attribute |
| Brand | Nike | Custom attribute |

4. Save the attributes
5. Switch to the **Facebook** tab
6. Observe the synced fields

**Expected Result**:
- Color field should automatically populate with "Red | Blue"
- Size field should show "Large | Medium"
- Material field should display "Cotton"
- Brand field should show "Nike"
- All fields should be disabled with a sync indicator icon

**Testing Notes**:
- Fields should be grayed out and show "Synced from the Attributes tab" tooltip
- Manual editing should be disabled for synced fields

---

### Scenario 2: Taxonomy Attribute Mapping

**Goal**: Test mapping of global WooCommerce taxonomy attributes.

**Setup Steps**:
1. Go to **Products > Attributes** in WordPress admin
2. Create a new global attribute:
   - Name: `Color`
   - Slug: `color` (or let it auto-generate as `pa_color`)
3. Add terms: `Red`, `Green`, `Blue`
4. Create a new product
5. In the **Attributes** tab, add the global `Color` attribute
6. Select `Red` and `Blue` as values
7. Save and check the **Facebook** tab

**Expected Result**:
- Color field in Facebook tab should show "Red | Blue"
- Attribute should be properly recognized despite `pa_` prefix

---

### Scenario 3: Alternative Naming Variations

**Goal**: Test mapping of attributes with common naming variations.

**Setup Steps**:
1. Create a product with these attributes:

| Attribute Name | Values | Expected Mapping |
|----------------|--------|------------------|
| Product Color | Navy Blue | Color |
| Item Size | XL | Size |
| Product Material | Polyester | Material |
| Target Gender | Female | Gender |
| Product Brand | Adidas | Brand |

2. Save attributes and check Facebook tab

**Expected Result**:
- All attributes should correctly map to their respective Facebook fields
- Values should appear in the correct Facebook fields despite alternative naming

---

### Scenario 4: Value Normalization Testing

**Goal**: Test automatic normalization of gender, age group, and condition values.

**Setup Steps**:
1. Create a product with these attributes:

| Attribute Name | Values | Expected Normalized Value |
|----------------|--------|---------------------------|
| Gender | Men | male |
| Gender | Women | female |
| Gender | Unisex | unisex |
| Age Group | Kids | kids |
| Age Group | Teen | teen |
| Age Group | Adult | adult |
| Condition | Brand New | new |
| Condition | Pre-owned | used |
| Condition | Refurbished | refurbished |

2. Test each combination separately
3. Check Facebook tab after each save

**Expected Result**:
- Values should be automatically normalized to Facebook's expected format
- Dropdown fields should show the normalized values

---

### Scenario 5: Numeric Slug Attributes

**Goal**: Test handling of attributes with numeric slugs (edge case).

**Setup Steps**:
1. Create a global attribute with numeric slug:
   - Go to **Products > Attributes**
   - Add attribute with Name: `Material Type` but manually set Slug to `123`
2. Add terms: `Cotton`, `Polyester`
3. Create a product and assign this attribute
4. Check if it maps correctly

**Expected Result**:
- System should handle numeric slugs gracefully
- Attribute should still map based on its display name or configured mappings

---

### Scenario 6: Mixed Mapped and Unmapped Attributes

**Goal**: Test handling of both mapped and unmapped attributes.

**Setup Steps**:
1. Create a product with these attributes:

| Attribute Name | Values | Should Map? |
|----------------|--------|-------------|
| Color | Blue | Yes (standard) |
| Weight | 2kg | No (custom data) |
| Size | Large | Yes (standard) |
| Style | Modern | No (custom data) |
| Care Instructions | Machine wash | No (custom data) |

2. Save and check Facebook tab

**Expected Result**:
- Color and Size should appear in Facebook fields
- Weight, Style, and Care Instructions should be handled as custom data
- No unmapped attributes should appear in standard Facebook fields

---

### Scenario 7: Priority and Conflict Resolution

**Goal**: Test priority handling when multiple attributes could map to the same field.

**Setup Steps**:
1. Create a product with these potentially conflicting attributes:

| Attribute Name | Values | Priority Level |
|----------------|--------|----------------|
| Color | Red | Direct match (highest) |
| Product Color | Blue | Mapping variation |
| pa_color | Green | Taxonomy attribute |

2. Save and observe which value takes precedence

**Expected Result**:
- Only one value should appear in the Color field
- Higher priority mappings should override lower priority ones
- Direct matches should take precedence

---

### Scenario 8: Empty and Invalid Values

**Goal**: Test handling of empty or invalid attribute values.

**Setup Steps**:
1. Create attributes with various edge cases:

| Attribute Name | Values |
|----------------|--------|
| Color | (empty) |
| Size | "" (empty string) |
| Material | Valid Value |
| Brand | (empty) |

2. Save and check Facebook tab

**Expected Result**:
- Empty values should not appear in Facebook fields
- Only attributes with valid values should be mapped
- System should handle empty values gracefully without errors

---

### Scenario 9: Variable Product Attribute Mapping

**Goal**: Test attribute mapping for variable products and their variations.

**Setup Steps**:
1. Create a Variable Product
2. Add variation attributes:
   - Color: Red, Blue, Green
   - Size: Small, Medium, Large
3. Create variations for different combinations
4. Check Facebook tab for parent product
5. Check individual variation Facebook settings

**Expected Result**:
- Parent product should show all possible attribute values
- Individual variations should inherit or override parent attributes
- Variation-specific attributes should map correctly

---

### Scenario 10: Bulk Product Testing

**Goal**: Test performance with multiple products having various attribute combinations.

**Setup Steps**:
1. Create 10-20 products with different attribute combinations
2. Use a mix of:
   - Standard attributes (color, size, material)
   - Custom attributes 
   - Global taxonomy attributes
   - Custom non-mapped attributes
3. Bulk edit or check several products

**Expected Result**:
- All products should process attribute mappings correctly
- No performance issues with multiple products
- Consistent mapping behavior across all products

---

## Verification Checklist

For each scenario, verify:

- [ ] **Correct Mapping**: Attributes map to expected Facebook fields
- [ ] **UI Indicators**: Synced fields show proper visual indicators
- [ ] **Value Format**: Values appear in correct format (single values vs. pipe-separated)
- [ ] **Field State**: Synced fields are properly disabled
- [ ] **Tooltip Display**: Sync indicators show informative tooltips
- [ ] **Manual Override**: Ability to manually override when needed
- [ ] **Save Persistence**: Mappings persist after save/reload
- [ ] **No Errors**: No JavaScript or PHP errors in logs

## Troubleshooting Common Issues

### Issue: Attributes Not Mapping
**Check**:
- Attribute has non-empty values
- Attribute name matches expected patterns
- No conflicting higher-priority mappings

### Issue: Wrong Values Showing
**Check**:
- Multiple attributes mapping to same field (priority conflict)
- Value normalization for gender/age_group/condition
- Custom mappings overriding standard mappings

### Issue: UI Not Updating
**Check**:
- JavaScript errors in browser console
- AJAX responses from sync_facebook_attributes endpoint
- Page cache or browser cache issues

### Issue: Performance Problems
**Check**:
- Number of attributes per product
- Database query efficiency
- Custom mapping configurations

## Advanced Testing

### Custom Mapping Configuration
Test custom mappings through WordPress filters:

```php
add_filter('wc_facebook_product_attribute_mappings', function($mappings, $product) {
    return array_merge($mappings, [
        'custom_field_name' => 'material',
        'product_weight' => 'size'
    ]);
}, 10, 2);
```

### Debug Information
Enable debug logging to see detailed mapping process:
1. Check `wp-content/uploads/fb-product-debug.log`
2. Look for ProductAttributeMapper entries
3. Verify mapping phases and decisions

## Expected Benefits

After testing, users should observe:

1. **Reduced Manual Work**: Automatic attribute mapping reduces manual field population
2. **Consistent Data**: Standardized mapping ensures consistent Facebook catalog data
3. **Better SEO**: Properly mapped attributes improve product discoverability
4. **Time Savings**: Bulk attribute handling saves significant time
5. **Fewer Errors**: Automated mapping reduces human errors in data entry

## Reporting Issues

When reporting issues, please include:
- WordPress and WooCommerce versions
- Product attribute configuration
- Expected vs. actual mapping results
- Screenshots of Facebook tab
- Browser console errors
- Debug log entries (if available) 