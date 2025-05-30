# Quick Start: Try the Attribute Mapper Feature

## What is it?
The new **Attribute Mapper** automatically connects your WooCommerce product attributes to Facebook catalog fields, saving you time and ensuring consistent data.

## 🚀 Quick Test (5 minutes)

### Test 1: Basic Auto-Mapping
1. **Create a new product** (any type)
2. **Go to Attributes tab** and add these:
   ```
   Color: Red, Blue
   Size: Large
   Material: Cotton
   Brand: Nike
   ```
3. **Click "Save attributes"**
4. **Switch to Facebook tab** 
5. **Observe**: Fields automatically fill and become grayed out with sync icons! ✨

### Test 2: Smart Value Recognition
1. **Create another product**
2. **Add these attributes**:
   ```
   Gender: Women
   Age Group: Kids  
   Condition: Brand New
   ```
3. **Save and check Facebook tab**
4. **Notice**: Values get automatically normalized (Women → female, Kids → kids, etc.)

### Test 3: Custom vs Mapped Attributes
1. **Add both types**:
   ```
   Color: Green          (will map to Facebook)
   Weight: 2kg           (stays as custom data)
   Care Instructions: Machine wash  (stays as custom data)
   ```
2. **Result**: Only `Color` appears in Facebook fields, others handled as custom data

## 💡 What You'll See

### ✅ Mapped Attributes
- **Auto-fill** Facebook fields
- **Grayed out** appearance  
- **Sync icon** with tooltip
- **Cannot edit** manually (unless you remove the attribute)

### ✅ Smart Mappings
- `Product Color` → Color field
- `Item Size` → Size field  
- `Target Gender` → Gender field
- `Product Material` → Material field

### ✅ Value Normalization
- `Men/Man/Boys` → `male`
- `Women/Woman/Girls` → `female` 
- `Kids/Children` → `kids`
- `Brand New` → `new`

## 🔧 Pro Tips

1. **Multiple values**: Separate with commas in attributes → Shows as "Red | Blue" in Facebook
2. **Priority matters**: If multiple attributes could map to same field, direct matches win
3. **Global attributes**: Work just like custom ones (pa_color, pa_size, etc.)
4. **Empty values**: Ignored automatically
5. **Variable products**: Parent shows all variations, children inherit/override

## 🚨 Quick Troubleshooting

**Attribute not mapping?**
- Check it has a value
- Try more specific names (Color vs Colour)
- Remove conflicting attributes

**Wrong value showing?**
- Multiple attributes mapping to same field
- Check for typos in attribute names

**UI not updating?**
- Refresh the page
- Check browser console for errors

## 🎯 Best Practices

1. **Use clear attribute names**: `Color`, `Size`, `Material` work better than vague names
2. **Consistent naming**: Stick to standard terms across products
3. **Clean up unused attributes**: Remove empty or irrelevant ones
4. **Test with variations**: Make sure parent/child relationships work as expected

## 📊 Expected Benefits

After setup, you should see:
- ⚡ **Faster product setup** (no manual field filling)
- 🎯 **Consistent Facebook data** (automated mapping)
- 🔄 **Bulk efficiency** (works across all products)
- ✅ **Fewer errors** (no manual data entry mistakes)

## 📝 Quick Success Checklist

- [ ] Created test product with standard attributes  
- [ ] Saw automatic field population in Facebook tab
- [ ] Noticed grayed out synced fields with icons
- [ ] Tested gender/age normalization
- [ ] Tried both mapped and unmapped attributes
- [ ] Verified different attribute naming variations

**🎉 Success!** You've experienced the key benefits of automatic attribute mapping!

---

*Need more detailed testing? Check out the full `ATTRIBUTE_MAPPER_TESTING_GUIDE.md` for comprehensive scenarios.* 