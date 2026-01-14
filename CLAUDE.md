# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Smartling Connector** WordPress plugin - a translation and localization platform integration that seamlessly translates WordPress content through the Smartling API. The plugin handles content upload, translation management, and automatic download of completed translations.

## Key Development Commands

### Testing
- **Unit tests**: `./inc/third-party/phpunit/phpunit/phpunit --configuration ./phpunit.xml.dist`

### Build & Release
- **Build plugin**: `./build.sh` - Creates production-ready `smartling-connector.zip`
- **Composer dependencies**: `./composer update` (dev), `./composer update --no-dev` (production)

### Dependency Management
- Dependencies are installed to `inc/third-party/` (configured in composer.json)
- Scoped namespacing prevents conflicts with other WordPress plugins, installed to `inc/lib/` (https://github.com/vsolovei-smartling/namespacer)

## Architecture Overview

### Core Components

**Bootstrap Process** (`inc/Smartling/Bootstrap.php`):
- Main entry point that initializes the plugin
- Handles dependency injection container setup
- Registers WordPress hooks and services
- Manages plugin activation/deactivation

**Content Management**:
- `ContentTypes/` - Handles different WordPress content types (posts, pages, taxonomies, menus, widgets)
- `ExternalContent*` - Specialized handlers for third-party plugins (Elementor, Yoast, ACF, Gravity Forms, Beaver Builder)
- `Submissions/` - Core translation workflow management

**Translation Pipeline**:
1. **Upload**: Content serialization → Smartling API upload
2. **Processing**: Translation occurs in Smartling dashboard  
3. **Download**: Completed translations → WordPress content application

**Key Services**:
- `ApiWrapper` - Smartling API communication layer with retry logic
- `SubmissionManager` - Translation job lifecycle management
- `ContentHelper` - WordPress content serialization/deserialization
- `FieldsFilterHelper` - Field filtering and transformation rules

### Database Layer
- `DbAl/` - Database abstraction layer
- `Migrations/` - Schema versioning and updates
- `WordpressContentEntities/` - WordPress-specific entity handling

### Extensibility
- `Extensions/` - Plugin extension system for third-party integrations
- `Tuner/` - Content filtering and rule management
- `Replacers/` - Content transformation during translation

## Testing Structure

**Unit Tests** (`tests/`):
- Test individual components in isolation
- Mock dependencies for fast execution
- Run with: `./inc/third-party/phpunit/phpunit/phpunit --configuration ./phpunit.xml.dist`

**Integration Tests** (`tests/IntegrationTests/`):
- Full WordPress environment testing
- Real database operations
- Third-party plugin compatibility testing
- Run with: `phpunit -c tests/phpunit.xml`

**Docker Testing**:
- Complete isolated environment with MySQL
- Automated via `Buildplan/test.sh`
- Includes WordPress multisite configuration

## Configuration

**Service Configuration** (`inc/config/`):
- `services.yml` - Dependency injection container setup
- `boot.yml` - Bootstrap configuration
- `field-processor.yml` - Content processing rules
- `media-attachment-rules.yml` - Media handling rules

**Key Settings**:
- Content type auto-discovery
- Field filtering rules
- Translation workflow configuration
- Third-party plugin integration settings

## Development Guidelines

### Code Structure
- PSR-0 autoloading with `Smartling\` namespace
- Dependency injection throughout the codebase
- Extensive use of interfaces for testability
- WordPress hooks for extensibility

### Content Processing
- All content goes through serialization/deserialization pipeline
- Field-level filtering for translation control
- Relationship tracking between original and translated content
- Gutenberg block-level translation support

### API Integration
- Robust error handling and retry mechanisms
- Job-based translation workflow
- Progress tracking and status reporting
- Webhook support for real-time updates

### Third-party Compatibility  
- Modular extension system for popular WordPress plugins
- Page builder support (Elementor, Beaver Builder)
- SEO plugin integration (Yoast, All in One SEO)
- Form plugin support (Gravity Forms)
- Advanced Custom Fields (ACF) support

## Common Workflows

When working on content type support, examine existing `ContentTypes/` implementations. For API changes, check both `ApiWrapper` and related tests. For field processing modifications, review `FieldsFilterHelper` and corresponding configuration files.

### Elementor Widget Development

When adding or modifying Elementor widget support, refer to the comprehensive guide:
- **Elementor Development Guide**: `docs/ELEMENTOR_DEVELOPMENT.md`
- Contains patterns for processing related content, testing strategies, and real-world examples
- Essential reading for WP-* tickets involving Elementor widgets
