# Plugin Architecture

## Overview

WP Headless Toolkit is a unified headless WordPress plugin designed for Next.js frontend projects. It consolidates the functionality of multiple standalone mu-plugins into a single, modular plugin with environment-based configuration, zero database writes, and a consistent API surface.

The plugin follows a modular architecture where each feature is encapsulated in its own module class implementing a shared `ModuleInterface` contract. Modules are loaded through a singleton `Main` class, individually toggleable via environment variables or constants, and require no wp-admin configuration forms. An admin settings page exists solely to display module status and environment variable state.

## Version Requirements

| Requirement | Minimum Version |
|-------------|-----------------|
| PHP | 8.1 |
| WordPress | 6.4 |
| WPGraphQL | 2.0.0 |

The plugin declares WPGraphQL as a required dependency via the `Requires Plugins: wp-graphql` header. If WPGraphQL is not active, the plugin displays an admin notice and does not initialize.

## Plugin Constants

The bootstrap file (`wp-headless-toolkit.php`) defines the following constants during initialization:

| Constant | Description |
|----------|-------------|
| `WP_HEADLESS_VERSION` | Current plugin version (e.g., `1.5.0`) |
| `WP_HEADLESS_PLUGIN_DIR` | Absolute path to the plugin directory |
| `WP_HEADLESS_PLUGIN_URL` | URL to the plugin directory |
| `WP_HEADLESS_PLUGIN_FILE` | Path to the main plugin file |
| `WP_HEADLESS_AUTOLOAD` | Whether Composer autoload is active (default: `true`) |

## Modular Architecture

### ModuleInterface Contract

Every module must implement `ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface`, which defines four methods:

| Method | Return Type | Purpose |
|--------|-------------|---------|
| `get_slug()` | `string` | Unique slug for configuration lookup (e.g., `revalidation`) |
| `get_name()` | `string` | Human-readable name (e.g., `ISR Revalidation`) |
| `is_enabled()` | `bool` | Whether the module should load |
| `init()` | `void` | Register WordPress hooks (only called if enabled) |

### Enabling and Disabling Modules

All modules are enabled by default. To disable a module, set a constant or environment variable following the naming convention:

```
WP_HEADLESS_DISABLE_{SLUG_UPPERCASE}
```

For example, to disable the `rest_security` module:

```php
// In wp-config.php
define( 'WP_HEADLESS_DISABLE_REST_SECURITY', true );
```

Or via environment variable:

```bash
WP_HEADLESS_DISABLE_REST_SECURITY=true
```

The `wp_headless_is_module_enabled()` function checks for the disable constant/env var and applies the `wp_headless_module_enabled` filter, allowing programmatic control.

Some modules have additional prerequisites beyond the disable toggle. For example, the Revalidation module requires both `NEXTJS_REVALIDATION_URL` and `NEXTJS_REVALIDATION_SECRET` to be configured. The Cloudflare Purge module requires the Breeze plugin with Cloudflare enabled.

### Registered Modules

The plugin ships with the following modules loaded in order:

| Module | Slug | Description |
|--------|------|-------------|
| ISR Revalidation | `revalidation` | Triggers Next.js on-demand ISR revalidation |
| Cloudflare Purge | `cloudflare_purge` | Purges Cloudflare CDN and GraphQL object cache |
| REST API Security | `rest_security` | Filters REST API to headless-needed endpoints |
| Frontend Redirect | `frontend_redirect` | 301 redirects public visitors to Next.js |
| WP Migrate DB Compat | `migrate_db_compat` | Compatibility layer for WP Migrate DB |
| Head Cleanup | `head_cleanup` | Strips unnecessary tags from wp_head output |
| GraphQL Performance | `graphql_performance` | GraphQL query performance optimizations |
| CORS | `cors` | Cross-origin resource sharing headers |
| Security Headers | `security_headers` | HTTP security headers |
| Preview Mode | `preview_mode` | Headless preview mode support |

## Module Loading Lifecycle

1. WordPress fires the `graphql_init` action (ensuring WPGraphQL is loaded).
2. `wp_headless_init()` runs: defines plugin constants and checks that WPGraphQL is active.
3. `Main::instance()` is called (singleton), which:
   a. Loads Composer autoloader via `includes()`.
   b. Calls `load_modules()`:
      - Applies the `wp_headless_module_classes` filter to the default module list.
      - For each module class: validates it implements `ModuleInterface`, checks `is_enabled()`, instantiates, calls `init()`, and stores the instance keyed by slug.
      - Fires `wp_headless_modules_loaded` action with all loaded modules.
   c. Calls `load_admin()`: instantiates `SettingsPage` if in admin context.
   d. Fires `wp_headless_init` action with the `Main` instance.

## Configuration System

Configuration is entirely environment-based with zero database writes. The `Config` helper class provides a static API:

| Method | Description |
|--------|-------------|
| `Config::get($key, $default)` | Get a value (env var > constant > filter > default) |
| `Config::get_bool($key, $default)` | Get a boolean value (understands `true`, `1`, `yes`, `on`) |
| `Config::get_list($key, $default)` | Get a comma-separated string as an array |
| `Config::has($key)` | Check if a key is set in env or constants |

The lookup priority is:

1. Environment variable (`getenv($key)`) -- Bedrock `.env` compatible
2. PHP constant (`defined($key)` / `constant($key)`) -- traditional `wp-config.php`
3. `wp_headless_config_value` filter -- programmatic override
4. Default value

## Admin Settings Page

The plugin registers a display-only settings page under **Settings > WP Headless Toolkit**. It contains no form inputs and performs no database writes.

The page displays:

- **Module Status Table**: Lists all registered modules (including disabled ones) with their name, slug, and enabled/disabled status.
- **Environment Configuration Table**: Shows all recognized environment variables, whether each is set, and its current value. Secret values (keys containing "SECRET") are masked with `********`.

## Global Access Functions

| Function | Description |
|----------|-------------|
| `wp_headless_get_config($key, $default)` | Retrieve a configuration value (env > constant > filter > default) |
| `wp_headless_is_module_enabled($slug)` | Check if a module is enabled (respects disable constants and `wp_headless_module_enabled` filter) |

## Filter and Hook System Overview

### Initialization Hooks

| Hook | Type | Description |
|------|------|-------------|
| `wp_headless_init` | Action | Fires after the plugin singleton is fully initialized. Receives the `Main` instance. |
| `wp_headless_modules_loaded` | Action | Fires after all modules have been loaded. Receives the array of loaded module instances. |
| `wp_headless_module_classes` | Filter | Modify the list of module classes before loading. Add or remove module class names. |
| `wp_headless_module_enabled` | Filter | Control whether a specific module is enabled. Receives `bool $enabled` and `string $slug`. |
| `wp_headless_config_value` | Filter | Override a config value before it falls back to the default. Receives `mixed $value` and `string $key`. |

### Accessing Modules at Runtime

```php
// Get the plugin instance
$plugin = \ProjectAssistant\HeadlessToolkit\Main::instance();

// Get a specific loaded module
$revalidation = $plugin->get_module( 'revalidation' );

// Get all loaded modules
$modules = $plugin->get_modules();

// Get all registered module classes (including disabled)
$classes = $plugin->get_registered_module_classes();
```

## Adding a Custom Module

To add a custom module, create a class implementing `ModuleInterface` and register it via the filter:

```php
add_filter( 'wp_headless_module_classes', function ( array $modules ): array {
    $modules[] = \MyPlugin\CustomModule::class;
    return $modules;
} );
```

The class must implement `get_slug()`, `get_name()`, `is_enabled()`, and `init()`. The standard pattern for `is_enabled()` is:

```php
public static function is_enabled(): bool {
    return wp_headless_is_module_enabled( static::get_slug() );
}
```

This gives the module automatic support for the `WP_HEADLESS_DISABLE_{SLUG}` toggle convention.
