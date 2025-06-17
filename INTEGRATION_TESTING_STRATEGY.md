# Integration Testing Strategy for Facebook for WooCommerce

## Overview

This document outlines our strategy for implementing comprehensive integration tests for the Facebook for WooCommerce plugin. While we have established E2E tests for frontend workflows, we currently lack integration tests that validate the core business logic, data transformations, and internal API flows without relying on external Facebook API calls.

## Current State

### ✅ What We Have
- **E2E Tests**: Playwright-based tests covering product creation workflows
- **GitHub Actions CI**: Automated testing pipeline with WordPress setup
- **Unit Tests**: Basic PHPUnit setup (composer test-unit)

### ❌ What We're Missing
- **Integration Tests**: No tests validating end-to-end data flows
- **API Integration Validation**: No testing of data transformation to Facebook format
- **WordPress Integration Tests**: Missing tests for hooks, filters, and WP integrations
- **Business Logic Validation**: No comprehensive testing of sync rules and product validation

## Integration Testing Approach

### Why Not Mock Facebook APIs?
- **Rate Limits**: Facebook APIs have strict rate limiting
- **Complexity**: Mock maintenance becomes complex as APIs evolve  
- **Test Environment**: Facebook test apps have limitations
- **Speed**: External API calls slow down test execution

### Our Solution: Internal Integration Testing
Focus on testing internal data flows, business logic, and WordPress integrations without external dependencies.

## Technologies & Tools

### Core Testing Stack
- **PHPUnit**: Primary testing framework for PHP integration tests
- **WordPress Test Suite**: WP-specific testing utilities and helpers
- **WP-CLI**: Command-line tools for WordPress setup in tests
- **MySQL**: Database for integration test scenarios

### CI/CD Integration  
- **GitHub Actions**: Automated test execution
- **Docker**: Containerized test environments
- **Artifact Collection**: Test reports and failure screenshots

### Testing Categories
1. **Product Validation Tests**
2. **Data Transformation Tests** 
3. **WordPress Hook Integration Tests**
4. **REST API Endpoint Tests**
5. **Feed Generation Tests**
6. **Database Integration Tests**

## Test Implementation Plan

### 1. Product Validation & Business Logic Tests

Test the core logic that determines what gets synced to Facebook:

```php
tests/integration/ProductValidation/
├── ProductValidatorTest.php           # Core validation logic
├── ProductSyncEligibilityTest.php     # Sync enabled/disabled rules
├── CategoryExclusionTest.php          # Category-based exclusions
├── VariationValidationTest.php       # Variable product validation
└── ProductVisibilityTest.php         # Catalog visibility rules
```

**Key Test Areas:**
- Product sync eligibility based on settings
- Category and tag exclusion rules
- Product visibility and status validation
- Variable product and variation handling
- Attribute validation (max 4 attributes per variation)

### 2. Data Transformation Tests

Validate that WooCommerce product data is correctly transformed for Facebook:

```php
tests/integration/DataTransformation/
├── ProductDataPreparationTest.php    # Product data formatting
├── FeedGenerationTest.php            # CSV feed creation
├── PriceFormattingTest.php           # Price and currency handling
├── ImageUrlTransformationTest.php    # Image URL processing
└── AttributeMappingTest.php          # Attribute transformation
```

**Key Test Areas:**
- Product data structure for Facebook format
- Price formatting with currency codes
- Image URL generation and validation
- Product attribute mapping and transformation
- Feed file generation and CSV formatting

### 3. WordPress Integration Tests

Test how the plugin integrates with WordPress and WooCommerce:

```php
tests/integration/WordPressIntegration/
├── ProductHooksTest.php              # Product save/update hooks
├── CategoryHooksTest.php             # Category change handling
├── OrderHooksTest.php                # Order completion events
├── CronJobsTest.php                  # Background job scheduling
└── SettingsIntegrationTest.php       # WordPress options handling
```

**Key Test Areas:**
- Product save/update hook handling
- Category and taxonomy change events
- Background job scheduling and execution
- WordPress options and transients
- Plugin activation/deactivation flows

### 4. REST API Endpoint Tests

Test custom REST endpoints without external dependencies:

```php
tests/integration/RestAPI/
├── SettingsEndpointTest.php          # Settings update/uninstall endpoints
├── WebhookEndpointTest.php           # Webhook handling
├── ExtrasEndpointTest.php            # FBE extras endpoint
├── WhatsAppWebhookTest.php           # WhatsApp webhook handling
└── AuthenticationTest.php            # Endpoint permissions
```

**Key Test Areas:**
- `/wc-facebook/v1/settings/update` endpoint
- `/wc-facebook/v1/settings/uninstall` endpoint  
- `/wc-facebook/v1/webhook` endpoint
- Request validation and sanitization
- Permission callbacks and authentication

### 5. Feed Generation & File Handling Tests

Test product feed generation and file operations:

```php
tests/integration/FeedGeneration/
├── ProductFeedTest.php               # Complete feed generation
├── FeedFileOperationsTest.php        # File creation and management
├── BatchProcessingTest.php           # Large dataset handling
├── FeedValidationTest.php            # Feed format validation
└── UploadPreparationTest.php         # Feed upload preparation
```

**Key Test Areas:**
- Complete product feed generation
- CSV file creation and formatting
- Batch processing for large product catalogs
- Feed file validation and integrity
- Temporary file handling and cleanup

### 6. Database Integration Tests

Test data persistence and retrieval:

```php
tests/integration/Database/
├── ConnectionSettingsTest.php        # Facebook connection data
├── ProductMetaTest.php               # Product sync metadata
├── ConfigurationTest.php             # Plugin configuration storage
├── TransientHandlingTest.php         # Cache and temporary data
└── MigrationTest.php                 # Data migration scenarios
```

**Key Test Areas:**
- Facebook connection settings storage
- Product sync metadata handling
- Plugin configuration persistence
- Transient data management
- Database schema and migrations

## Test Environment Setup

### Local Development
```bash
# Setup WordPress test environment
composer install
wp-env start

# Run integration tests
composer test-integration

# Run specific test suites
composer test-integration -- --group=product-validation
composer test-integration -- --group=data-transformation
```

### CI/CD Pipeline
- **WordPress Setup**: Automated WordPress installation with MySQL
- **Plugin Installation**: Install Facebook for WooCommerce plugin
- **Test Database**: Isolated test database for each run
- **Parallel Execution**: Run test suites in parallel for speed
- **Artifact Collection**: Collect test reports and failure data

## Test Data Management

### Fixtures and Factories
```php
tests/fixtures/
├── ProductFactory.php                # Generate test products
├── CategoryFactory.php               # Generate test categories  
├── VariationFactory.php              # Generate product variations
├── SettingsFactory.php               # Generate plugin settings
└── OrderFactory.php                  # Generate test orders
```

### Test Database
- **Isolated Environment**: Each test gets fresh database state
- **Rollback Mechanism**: Automatic cleanup after each test
- **Realistic Data**: Use production-like test data
- **Performance**: Optimized for fast test execution

## Success Metrics

### Coverage Goals
- **Business Logic**: 90%+ coverage of ProductValidator and sync logic
- **Data Transformation**: 85%+ coverage of data preparation functions
- **API Endpoints**: 100% coverage of REST endpoint handlers
- **WordPress Integration**: 80%+ coverage of hook handlers

### Quality Gates
- **Test Execution**: All tests must pass in CI/CD
- **Performance**: Integration tests complete within 5 minutes
- **Reliability**: Less than 1% flaky test rate
- **Documentation**: All test scenarios documented

## Implementation Timeline

### Phase 1: Foundation (Week 1-2)
- [ ] Setup PHPUnit integration test framework
- [ ] Create test database and WordPress environment
- [ ] Implement ProductValidator integration tests
- [ ] Setup CI/CD pipeline for integration tests

### Phase 2: Core Logic (Week 3-4)
- [ ] Data transformation tests
- [ ] Feed generation tests
- [ ] WordPress hook integration tests
- [ ] REST API endpoint tests

### Phase 3: Advanced Scenarios (Week 5-6)
- [ ] Database integration tests
- [ ] Complex product scenarios (variations, bundles)
- [ ] Error handling and edge cases
- [ ] Performance and load testing

### Phase 4: Optimization (Week 7-8)
- [ ] Test suite optimization for speed
- [ ] Enhanced reporting and metrics
- [ ] Documentation and team training
- [ ] Continuous improvement processes

## Benefits

### Development Quality
- **Early Bug Detection**: Catch integration issues before production
- **Refactoring Confidence**: Safe code changes with comprehensive test coverage  
- **Regression Prevention**: Prevent old bugs from reappearing
- **Code Documentation**: Tests serve as living documentation

### Team Productivity
- **Faster Development**: Quick feedback on code changes
- **Reduced Manual Testing**: Automated validation of complex scenarios
- **Better Onboarding**: New team members understand system behavior
- **Quality Assurance**: Consistent validation across all environments

### Business Impact
- **Reduced Production Issues**: Fewer bugs reaching customers
- **Faster Feature Delivery**: Confident releases with automated validation
- **Better Customer Experience**: More reliable Facebook integration
- **Lower Support Costs**: Fewer integration-related support tickets

## Conclusion

This integration testing strategy provides comprehensive coverage of the Facebook for WooCommerce plugin's internal logic and WordPress integrations without relying on external Facebook APIs. By focusing on business logic validation, data transformation testing, and WordPress integration verification, we can ensure robust and reliable functionality while maintaining fast, predictable test execution.

The phased implementation approach allows for gradual adoption and immediate value delivery, while the comprehensive coverage ensures long-term code quality and developer confidence. 