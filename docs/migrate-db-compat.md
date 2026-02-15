# WP Migrate DB Compatibility

## Overview

The WP Migrate DB Compatibility module resolves compatibility issues between WP Migrate DB Pro and WordPress installations using the Bedrock directory structure. Bedrock places the `wp-content` directory (named `app` by default) outside the standard WordPress root, which causes WP Migrate DB Pro to miscalculate upload paths and content directories during migrations.

This module was originally a standalone must-use plugin (`wp-migrate-db-pro-compatibility.php`) that has been folded into the WP Headless Toolkit. It auto-detects both the presence of WP Migrate DB Pro and the Bedrock directory layout, applying fixes only when both conditions are met. No manual configuration is required.

## Configuration

This module requires no configuration. It activates automatically when:

1. The module is not disabled via `WP_HEADLESS_DISABLE_MIGRATE_DB_COMPAT`.
2. WP Migrate DB Pro is active (detected via the `\DeliciousBrains\WPMDB\Pro\Plugin\ProPlugin` class or the `WPMDB_PRO_VERSION` constant).

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `WP_HEADLESS_DISABLE_MIGRATE_DB_COMPAT` | No | `false` | Set to `true` to disable this module |

## How It Works

### Dependency Detection

The module's `is_enabled()` method checks for the presence of WP Migrate DB Pro using two detection strategies:

1. **Class check:** `class_exists( '\DeliciousBrains\WPMDB\Pro\Plugin\ProPlugin' )`
2. **Constant check:** `defined( 'WPMDB_PRO_VERSION' )`

If neither condition is met, the module does not register any hooks.

### Upload Directory Fix

The module hooks into the `wpmdb_upload_dir` filter to correct the upload directory paths. In a Bedrock installation, `WP_CONTENT_DIR` points to a location outside the standard WordPress directory tree (e.g., `/srv/www/app/web/app` instead of `/srv/www/app/web/wp/wp-content`). The module:

1. Checks that both `WP_CONTENT_DIR` and `ABSPATH` are defined.
2. Constructs the correct upload path as `WP_CONTENT_DIR . '/uploads'`.
3. Verifies the path exists on disk via `is_dir()`.
4. Updates the `basedir` and `path` entries in the upload directory array.

### Upload Info Fix

The `wpmdb_upload_info` filter receives the same array structure as `wpmdb_upload_dir`. The module delegates to the same `fix_upload_dir()` method to apply identical corrections.

### Content Path Fix

The module hooks into `wpmdb_get_path` with two parameters: the path string and a type identifier. When the type is `'content'` and `WP_CONTENT_DIR` is defined, the module returns `WP_CONTENT_DIR` instead of the path that WP Migrate DB Pro calculated. This ensures the plugin correctly locates themes, plugins, and other content directory assets during migration.

## Filters

This module does not define its own filters. It hooks into WP Migrate DB Pro's filters:

| Filter (WP Migrate DB Pro) | Description | Parameters |
|----------------------------|-------------|------------|
| `wpmdb_upload_dir` | Corrects the upload directory base path and current path for Bedrock layouts. | `array $upload_dir` -- upload directory info array with `basedir`, `path`, and `subdir` keys |
| `wpmdb_upload_info` | Applies the same upload directory corrections (delegates to `fix_upload_dir()`). | `array $upload_info` -- upload info array |
| `wpmdb_get_path` | Returns `WP_CONTENT_DIR` when the requested path type is `'content'`. | `string $path` -- the computed content path, `string $type` -- the path type (e.g., `'content'`) |

The shared module filter is also available:

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_module_enabled` | Control whether the module is enabled (shared across all modules). | `bool $enabled`, `string $slug` (slug: `migrate_db_compat`) |

## Usage Examples

No usage examples are needed for this module. It works automatically when both WP Migrate DB Pro and Bedrock are detected. Simply ensure the WP Headless Toolkit plugin is active alongside WP Migrate DB Pro.

### Verifying the module is active

You can confirm the module is loaded by checking the module slug:

```php
if ( wp_headless_is_module_enabled( 'migrate_db_compat' ) ) {
    // Module is active and WP Migrate DB Pro is detected.
}
```

## Disabling

Disable the module by defining the constant or setting the environment variable:

```php
// wp-config.php
define( 'WP_HEADLESS_DISABLE_MIGRATE_DB_COMPAT', true );
```

```env
# .env
WP_HEADLESS_DISABLE_MIGRATE_DB_COMPAT=true
```

The module also self-disables when WP Migrate DB Pro is not active, so no action is needed if you uninstall WP Migrate DB Pro.
