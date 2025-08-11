## Description

This PR removes an unnecessary `error_log` statement that was generating excessive log entries for every product variation that has a WooCommerce-generated attribute summary. The log line was being triggered during the short description generation process and was filling up the error logs with non-critical information.

The change simplifies the conditional logic in the `get_fb_short_description()` method by removing the verbose logging while maintaining the same functionality - the code still properly skips WooCommerce auto-generated attribute summaries, just without the log noise.

**Issue:** The error log was being flooded with entries like:
```
FB Short Description: Skipping WooCommerce attribute summary for variation 123: 'Size: Large, Color: Blue'
```

This was happening for every variation product during catalog sync operations, creating unnecessary log volume and making it harder to identify actual issues.

### Type of change

- [x] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [x] Syntax change (non-breaking change which fixes code modularity, linting or phpcs issues)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)

## Checklist

- [x] I have commented my code, particularly in hard-to-understand areas.
- [ ] I have confirmed that my changes do not introduce any new PHPCS warnings or errors. 
- [ ] I have checked plugin debug logs that my changes do not introduce any new PHP warnings or FATAL errors. 
- [x] I followed general Pull Request best practices. Meta employees to follow this [wiki]([url](https://fburl.com/wiki/2cgfduwc)).
- [ ] I have added tests (if necessary) and all the new and existing unit tests pass locally with my changes.
- [x] I have completed dogfooding and QA testing, or I have conducted thorough due diligence to ensure that it does not break existing functionality.
- [ ] I have updated or requested update to plugin documentations (if necessary). Meta employees to follow this [wiki]([url](https://fburl.com/wiki/nhx73tgs)).

## Changelog entry

Remove unnecessary logging that was filling up error logs during product variation processing

## Test Plan

1. **Before the fix:**
   - Enable WordPress debug logging (`WP_DEBUG_LOG = true`)
   - Sync products with variations that have WooCommerce-generated attribute summaries
   - Observe error log filling up with "FB Short Description: Skipping WooCommerce attribute summary" messages

2. **After the fix:**
   - Apply the changes
   - Sync the same products with variations
   - Verify that the unnecessary log messages are no longer generated
   - Confirm that the short description functionality still works correctly (attribute summaries are still skipped)

3. **Functional testing:**
   - Verify that product variations still get appropriate short descriptions
   - Confirm that WooCommerce auto-generated attribute summaries are still properly excluded
   - Test with both variation products and simple products to ensure no regression

## Screenshots

### Before
Error logs showing repeated entries:
```
[DD-MMM-YYYY HH:MM:SS UTC] FB Short Description: Skipping WooCommerce attribute summary for variation 123: 'Size: Large, Color: Blue'
[DD-MMM-YYYY HH:MM:SS UTC] FB Short Description: Skipping WooCommerce attribute summary for variation 124: 'Size: Medium, Color: Red'
[DD-MMM-YYYY HH:MM:SS UTC] FB Short Description: Skipping WooCommerce attribute summary for variation 125: 'Size: Small, Color: Green'
...
```

### After
Clean error logs with only relevant error messages, no unnecessary logging for normal operation of skipping attribute summaries.