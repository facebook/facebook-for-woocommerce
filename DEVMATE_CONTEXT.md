# Facebook for WooCommerce - Developer Context

## Project Overview

This is the official Facebook for WooCommerce plugin that enables WooCommerce stores to integrate with Facebook's commerce platform. The plugin allows merchants to sync their product catalogs, manage feeds, and handle localized content for international markets.

## Recent Major Development: Comprehensive Localization System

### Current Branch Context
The current branch (`localization_integration`) contains extensive new functionality for multi-language and multi-currency support, developed over 10 commits from August-September 2025. This represents a major architectural expansion to support international merchants using translation and multicurrency plugins.

### Latest Major Updates (September 2025)
- **Country Override Feeds**: Complete country-specific feed system with currency pricing
- **Enhanced Language Feeds**: Improved batch processing and multi-language generation
- **Debug & Monitoring Tools**: Comprehensive admin debug interface for feed management
- **Batch Upload Support**: Direct API synchronization for both language and country feeds

## Core Architecture

### Main Plugin File
- **`class-wc-facebookcommerce.php`** - Main plugin class and entry point
  - Handles plugin initialization, hooks, and core functionality
  - Manages integration with WooCommerce and Facebook APIs
  - Contains the main plugin lifecycle management

### Key Directory Structure

```
/includes/
├── Admin/                          # Admin interface and settings
│   ├── Settings.php               # Main settings management
│   ├── Enhanced_Settings.php      # Enhanced settings UI
│   └── Settings_Screens/
│       └── Localization_Integrations.php  # NEW: Localization settings UI
├── API/                           # Facebook API integration
│   ├── API.php                   # Main API class
│   └── ProductCatalog/
│       └── LocalizedItemsBatch/   # NEW: Localized items API
├── Feed/                          # Product feed generation
│   ├── FeedManager.php           # Central feed management
│   ├── AbstractFeed.php          # Base feed class
│   ├── AbstractFeedHandler.php   # Base feed handler
│   └── Localization/             # NEW: Language-specific feeds
├── Integrations/                  # NEW: Third-party plugin integrations
│   ├── Abstract_Localization_Integration.php
│   ├── Polylang.php              # Polylang plugin support
│   ├── WPML.php                  # WPML plugin support
│   └── IntegrationRegistry.php   # Integration management
└── Products/                      # Product sync and management
    ├── Feed.php                  # Product feed generation
    └── Sync.php                  # Product synchronization
```

## Localization Integration System (NEW)

### Core Components

#### 1. Abstract Localization Integration
**File**: `/includes/Integrations/Abstract_Localization_Integration.php`
- Base class for all localization plugin integrations
- Defines common interface for language detection and translation
- Handles integration lifecycle (activation, deactivation, availability checking)

#### 2. Specific Plugin Integrations
- **Polylang Integration** (`/includes/Integrations/Polylang.php`)
- **WPML Integration** (`/includes/Integrations/WPML.php`)
- Both extend the abstract base class and implement plugin-specific logic

#### 3. Language Feed System
**Directory**: `/includes/Feed/Localization/`

Key files:
- **`LanguageFeedData.php`** - Data structure for language-specific product information
- **`LanguageOverrideFeed.php`** - Main feed class for language overrides
- **`LanguageOverrideFeedHandler.php`** - Handles feed generation and management
- **`LanguageOverrideFeedGenerator.php`** - Generates CSV feeds for specific languages
- **`LanguageOverrideFeedWriter.php`** - Writes feed data to files
- **`LanguageOverrideFeedDirectSync.php`** - Direct API synchronization
- **`LanguageFeedManagementTrait.php`** - Shared feed management functionality

#### 4. Country Feed System (NEW - September 2025)
**Directory**: `/includes/Feed/Localization/` (Country-specific files)

Key files:
- **`CountryFeedData.php`** - Data structure for country-specific currency and pricing information
- **`CountryOverrideFeed.php`** - Main feed class for country overrides
- **`CountryOverrideFeedHandler.php`** - Handles country feed generation and management
- **`CountryOverrideFeedGenerator.php`** - Generates CSV feeds for specific countries
- **`CountryOverrideFeedWriter.php`** - Writes country feed data to files
- **`CountryFeedManagementTrait.php`** - Shared country feed management functionality

#### 5. Debug & Monitoring Tools (NEW - September 2025)
- **`Language_Feed_Debug_Page.php`** - Comprehensive admin debug interface
- Enhanced logging and monitoring capabilities
- Feed status tracking and scheduled action management

#### 6. Integration Registry
**File**: `/includes/Integrations/IntegrationRegistry.php`
- Manages registration and discovery of available localization integrations
- Handles integration availability checking and logging

### How Localization Works

#### Language Override System
1. **Detection**: The system detects available translation plugins (Polylang, WPML)
2. **Registration**: Available integrations are registered in the IntegrationRegistry
3. **Configuration**: Admin can configure which languages to sync via Settings UI
4. **Feed Generation**: Language-specific feeds are generated containing translated product data
5. **API Sync**: Feeds are either uploaded as files or synced directly via Facebook API

#### Country Override System (NEW)
1. **Multicurrency Detection**: System detects WPML with WooCommerce Multilingual (WCML)
2. **Country Analysis**: Identifies viable countries based on:
   - Shipping zones configuration
   - Currency assignments
   - Meta/Facebook supported countries
3. **Prerequisites Check**: Validates multicurrency setup and country coverage
4. **Country Feed Generation**: Creates country-specific feeds with localized pricing
5. **Batch Upload**: Uses Facebook's batch upload API for efficient processing

### API Integration

#### Localized Items Batch API
**Directory**: `/includes/API/ProductCatalog/LocalizedItemsBatch/`
- **`Create/Request.php`** - API request for creating localized item batches
- **`Create/Response.php`** - API response handling

This allows direct synchronization of translated product data to Facebook without requiring CSV file uploads.

## Testing Infrastructure

### Test Structure
```
/tests/
├── Unit/                          # Unit tests
│   ├── Feed/Localization/        # NEW: Localization feed tests
│   └── Integrations/             # Integration-specific tests
├── integration/                   # Integration tests
│   ├── Feed/Localization/        # NEW: Localization integration tests
│   └── LocalizationIntegration/  # Integration framework tests
└── bootstrap-*.php               # Test environment setup
```

### Test Environment Setup
- **`bootstrap-polylang.php`** - Sets up Polylang for testing
- **`bootstrap-wpml.php`** - Sets up WPML for testing
- **`bin/install-wp-tests-with-polylang.sh`** - Polylang test environment installer
- **`bin/install-wp-tests-with-wpml.sh`** - WPML test environment installer

## Key Classes and Interfaces

### Feed Management
- **`FeedManager`** - Central coordinator for all feed types
- **`AbstractFeed`** - Base class for all feed types
- **`AbstractFeedHandler`** - Base class for feed processing

### Integration Framework
- **`Abstract_Localization_Integration`** - Base for translation plugin integrations
- **`IntegrationRegistry`** - Manages available integrations
- **`IntegrationAvailabilityLogger`** - Logs integration status and issues

### Translation Support
- **`Facebook_Fields_Translation_Trait`** - Shared translation functionality
- **`Locale.php`** - Locale and language code management

## Development Patterns

### Integration Pattern
1. Extend `Abstract_Localization_Integration`
2. Implement required methods for language detection
3. Register integration in the registry
4. Handle plugin-specific translation logic

### Feed Generation Pattern
1. Extend `AbstractFeed` for new feed types
2. Implement `AbstractFeedHandler` for processing
3. Use traits for shared functionality
4. Register with `FeedManager`

## Configuration and Settings

### Admin Interface
- Main settings in `/includes/Admin/Settings.php`
- Localization-specific settings in `/includes/Admin/Settings_Screens/Localization_Integrations.php`
- Enhanced UI components in `/includes/Admin/Enhanced_Settings.php`

### Key Settings
- Language selection for feed generation
- Integration enable/disable toggles
- Feed generation scheduling
- API sync preferences

## Common Development Tasks

### Adding a New Translation Plugin Integration
1. Create new class extending `Abstract_Localization_Integration`
2. Implement language detection methods
3. Add integration to registry
4. Create corresponding test files
5. Update admin settings if needed

### Modifying Feed Generation
1. Update relevant classes in `/includes/Feed/Localization/`
2. Modify `LanguageFeedData` for new data fields
3. Update feed generators and writers
4. Add corresponding tests

### API Integration Changes
1. Modify classes in `/includes/API/ProductCatalog/LocalizedItemsBatch/`
2. Update request/response handling
3. Test with Facebook's API endpoints

## Debugging and Development Tools

### Debug Files (in Local Sites)
The project includes numerous debug files for testing various components:
- Language feed generation debugging
- API request debugging
- Translation detection testing
- CSV generation validation

### Key Debug Areas
- Feed generation process
- API synchronization
- Translation plugin integration
- Locale and language code handling

## Dependencies and Requirements

### WordPress Plugins
- WooCommerce (required)
- Polylang (optional, for multi-language support)
- WPML (optional, for multi-language support)

### PHP Requirements
- Modern PHP version with support for traits and namespaces
- WordPress and WooCommerce compatibility

## Country Override Feeds (NEW - September 2025)

### System Overview
A comprehensive multicurrency feed system that generates country-specific product feeds with localized pricing. Works primarily with WPML + WooCommerce Multilingual (WCML) for sophisticated currency management.

### Key Features
- **Multicurrency Support**: Integrates with WPML's currency-by-country and currency-by-language modes
- **Shipping Zone Integration**: Analyzes WooCommerce shipping zones to determine viable countries
- **Meta Country Validation**: Cross-references against Facebook's supported countries
- **Automated Feed Generation**: Creates separate CSV feeds for each viable country
- **Batch Upload API**: Uses Facebook's batch upload system for efficient processing

### Prerequisites
For country feeds to be generated, the system requires:
1. **WPML Multicurrency Enabled**: Active WPML with WooCommerce Multilingual
2. **Multiple Currencies**: At least 2 currencies configured
3. **Multiple Shipping Countries**: Shipping zones covering more than 1 country
4. **Viable Countries**: Countries that are shipped to, have currency assigned, and are Meta-supported

### Country Feed Components

#### Data Processing
- **`CountryFeedData.php`**: Core data handler for country-specific information
  - Validates multicurrency prerequisites
  - Analyzes shipping zones and currency assignments
  - Identifies viable countries for feed generation
  - Generates country-specific pricing data

#### Feed Management
- **`CountryOverrideFeed.php`**: Main feed orchestrator extending AbstractFeed
  - Manages scheduling and regeneration cycles
  - Handles multiple country feed uploads
  - Provides feed URL generation with country parameters

#### Generation & Processing
- **`CountryOverrideFeedGenerator.php`**: Batch-processes country feed generation
- **`CountryOverrideFeedHandler.php`**: Handles individual country feed creation
- **`CountryOverrideFeedWriter.php`**: Writes country-specific CSV files
- **`CountryFeedManagementTrait.php`**: Shared utilities for country feed operations

### Feed Format
Country override feeds use Facebook's country override format:
```csv
id,override,price
wc_post_id_123,GB,29.99 GBP
wc_post_id_123,FR,34.99 EUR
```

### Integration with WPML
The system deeply integrates with WPML's multicurrency features:
- **Currency Mode Detection**: Supports both "by country" and "by language" modes
- **Dynamic Currency Assignment**: Uses WPML's priority system for currency selection
- **Fallback Logic**: Handles "rest of world" scenarios and currency inheritance
- **Real-time Exchange Rates**: Leverages WPML's currency conversion system

## Debug & Administration Tools (NEW - September 2025)

### Language Feed Debug Page
**File**: `/includes/Admin/Language_Feed_Debug_Page.php`

A comprehensive admin interface providing:
- **System Status Dashboard**: Integration status and prerequisites
- **Scheduled Actions Monitor**: Real-time feed generation tracking
- **Feed Files Status**: Per-language file status and accessibility
- **Debug Logs Viewer**: Centralized logging with filtering
- **Manual Actions**: Force regeneration, rescheduling, and cleanup tools

### Enhanced Logging System
Integrated throughout both language and country feed systems:
- **Structured Logging**: Contextual information with events and metadata
- **Meta Integration**: Selective logging to Facebook for critical issues
- **WooCommerce Logs**: Local logging for debugging and monitoring
- **Performance Tracking**: Feed generation timing and success rates

### Monitoring Features
- **Prerequisite Validation**: Real-time checking of system requirements
- **Feed Health Monitoring**: File existence, size, and modification tracking
- **Action Scheduler Integration**: Deep integration with WordPress cron system
- **Error Reporting**: Comprehensive error tracking and alerting

## Enhanced API Integration

### Batch Upload System
**Directory**: `/includes/API/ProductCatalog/LocalizedItemsBatch/`

#### LocalizedItemsBatch API
- **`Create/Request.php`**: Handles batch creation requests for localized items
- **`Create/Response.php`**: Processes API responses and handles errors
- Supports both language and country override data
- Efficient bulk processing for large catalogs

### Direct Synchronization
Alternative to file-based feeds:
- **`LanguageOverrideFeedDirectSync.php`**: Direct API sync for language data
- Real-time translation updates
- Reduced server storage requirements
- Improved synchronization reliability

## Architecture Enhancements

### Feed Management Evolution
The system has evolved from simple product feeds to a sophisticated multi-feed architecture:

1. **AbstractFeed Pattern**: Common base for all feed types
2. **Specialized Handlers**: Country and language-specific processing
3. **Trait-based Functionality**: Shared behavior across feed types
4. **Registry Pattern**: Dynamic integration discovery and management

### Scheduling & Performance
- **Independent Scheduling**: Language and country feeds operate on separate schedules
- **Batched Processing**: Large catalogs processed in chunks to prevent timeouts
- **Memory Management**: Optimized for large product collections
- **Error Recovery**: Robust handling of partial failures and retries

### Extensibility Framework
The localization system is designed for future expansion:
- **Plugin-agnostic**: Easy addition of new translation/currency plugins
- **Feed Type Extensible**: Template for adding new feed formats
- **API Flexible**: Support for future Facebook API changes
- **Configuration Driven**: Admin-configurable without code changes

## Recent Changes Summary (Updated September 2025)

The localization integration represents a major architectural transformation that:

### Language Override System
1. **Translation Plugin Support**: Polylang and WPML integration
2. **Multi-language Feeds**: Separate feeds per language with translated content
3. **Direct API Sync**: Real-time synchronization alternative to file uploads
4. **Batch Processing**: Efficient handling of large multilingual catalogs

### Country Override System (NEW)
1. **Multicurrency Integration**: Deep WPML + WCML integration
2. **Geographic Targeting**: Country-specific feeds with localized pricing
3. **Shipping Zone Analysis**: Automatic country detection from WooCommerce settings
4. **Currency Fallback Logic**: Sophisticated currency assignment with priorities

### Administrative & Debug Tools
1. **Comprehensive Debug Interface**: Real-time monitoring and troubleshooting
2. **Enhanced Logging**: Structured logging with Meta integration
3. **Manual Controls**: Force regeneration, scheduling controls, and cleanup tools
4. **Performance Monitoring**: Feed generation timing and success tracking

### Technical Infrastructure
1. **Batch Upload APIs**: Efficient bulk synchronization with Facebook
2. **Robust Error Handling**: Comprehensive error recovery and reporting
3. **Memory Optimization**: Handling of large product catalogs
4. **Extensible Architecture**: Framework for future localization features

This system enables international merchants to fully leverage Facebook's commerce platform with proper localization for both content translation and currency presentation, supporting sophisticated global e-commerce operations.
