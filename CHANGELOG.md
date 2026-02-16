# Changelog

All notable changes to the WP Headless Toolkit plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.6.0] - 2026-02-17

### Added
- **GraphQL cache flush endpoint** -- `POST /wp-json/wp-headless-toolkit/v1/flush-graphql-cache` secured with `NEXTJS_REVALIDATION_SECRET`
  - Flushes only the `wp_headless_toolkit_graphql` Redis cache group (surgical, not full cache flush)
  - Falls back to `wp_cache_flush()` on non-Redis environments
  - Enables Next.js frontend builds to self-correct when stale cached GraphQL responses are detected
  - Fires `wp_headless_graphql_cache_flushed` action after flush

---

## [1.5.0] - 2026-02-16

### Fixed
- **Module initialization** -- Plugin now correctly calls `Main::instance()` during bootstrap, ensuring all modules load
- **Autoloader guard** -- `includes()` now checks `file_exists()` before requiring `vendor/autoload.php`, preventing fatal errors on Bedrock installations where the root Composer handles autoloading
- **REST Security self-blocking** -- Added `/wp-headless-toolkit/` to the unauthenticated access whitelist so the plugin's own REST endpoints (e.g., preview token verification) are not blocked by the REST Security module

### Changed
- **README** -- Added Compatibility section documenting hosting requirements; added CloudflarePurge module to module reference; clarified WP Migrate DB requires Pro version; clarified env config works beyond Bedrock
- **CHANGELOG** -- Fixed inaccurate "Cloudflare Enterprise" reference (module works with any Cloudflare plan via Breeze)

---

## [1.4.0] - 2026-02-15

### Added
- **Cloudflare Purge module** -- Full-domain Cloudflare CDN cache purge on content changes via Breeze integration
  - Hooks into `save_post`, `delete_post`, `wp_trash_post`, `edited_term`, `delete_term`, `wp_update_nav_menu`
  - Runs at priority 20 (after ISR Revalidation at priority 10)
  - Debounced: only one purge per PHP request regardless of bulk operations
  - Auto-disables when the Breeze plugin is not active or Cloudflare is not enabled in Breeze
  - Also flushes GraphQL object cache group on content change
  - Fires `wp_headless_cloudflare_purged` action for extensibility

---

## [1.3.0] - 2026-02-13

### Changed
- Renamed Composer package from `projectassistant/wordpress-headless-toolkit` to `projectassistant/wp-headless-toolkit`
- Updated all internal references to match new package name

### Fixed
- CI pipeline compatibility improvements for Bitbucket Pipelines

---

## [1.2.0] - 2026-02-12

### Added
- Tiered test execution with `@group smoke` markers for fast CI feedback
- Coverage reporting with configurable threshold enforcement
- PR smoke test step in CI pipeline

### Changed
- Extracted shared test infrastructure into base class and traits
- Added `@group` annotations to all 16 test classes
- Added `declare(strict_types=1)` to all source files (17 files total)

### Fixed
- Coverage threshold now fails closed on missing or unparseable `coverage.xml`

---

## [1.1.0] - 2026-02-11

### Added
- Bitbucket Pipelines CI configuration with parallel lint, analyze, and test steps
- Release tagging automation in CI

### Fixed
- CI compatibility: REQUEST_URI, WP cron shutdown, ANSI color handling, pcov detection
- oEmbed discovery link removal now detects hook priority for cross-version WordPress compatibility

---

## [1.0.0] - 2026-02-10

### Added
- **Security Headers module** -- X-Content-Type-Options, X-Frame-Options, HSTS, Referrer-Policy, Permissions-Policy (disabled by default)
- **Preview Mode module** -- JWT-based preview link rewriting for authenticated draft access
- **WP Migrate DB Compatibility module** -- Ensures WP Migrate DB endpoints bypass REST API filtering
- **CORS module** -- Configurable CORS headers with origin allowlist
- **GraphQL Performance module** -- Cache-control headers, object cache integration, query complexity limits
- **Admin Settings page** -- Display-only settings page showing module status and environment configuration
- Deployment documentation and installation validation script (`bin/validate-installation.php`)
- Comprehensive WPUnit test suite (337 tests across 16 test classes)

### Security
- REST API returns `WP_Error` for blocked routes instead of empty array (TD-SEC-001)
- CORS module sanitizes `HTTP_ORIGIN` and `REQUEST_URI` (TD-SEC-002, TD-SEC-003)
- Settings page enforces `manage_options` capability check (TD-SEC-005)
- `do_action` calls moved inside singleton guard to prevent double-firing (TD-QA-004)

---

## [0.1.0] - 2026-02-08

### Added
- Initial plugin scaffold with modular architecture
- **ISR Revalidation module** -- Triggers Next.js on-demand revalidation on content changes
- **REST API Security module** -- Filters REST API to only expose headless-required endpoints
- **Head Cleanup module** -- Removes emoji scripts, oEmbed, RSD, WLW manifest, shortlinks
- **Frontend Redirect module** -- Redirects public visitors to headless frontend
- Environment-variable-based configuration with zero database writes
- `ModuleInterface` contract for consistent module architecture
- `Config` helper supporting `.env`, `wp-config.php` constants, and filter overrides
- Plugin lifecycle hooks: `wp_headless_modules_loaded`, `wp_headless_init`
- Module control filters: `wp_headless_module_enabled`, `wp_headless_module_classes`
- Docker-based development and testing environment
