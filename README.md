# WP Headless Toolkit

Unified headless WordPress plugin for Next.js projects. Provides ISR revalidation, REST API security, CORS, preview mode, GraphQL performance, security headers, and more -- all managed through environment variables with zero database writes.

## Features

- **ISR Revalidation** -- Trigger Next.js on-demand revalidation when content changes in WordPress
- **REST API Security** -- Restrict REST API endpoints to only those needed by headless frontends
- **Head Cleanup** -- Remove unnecessary `<head>` elements (emoji scripts, oEmbed, etc.)
- **Frontend Redirect** -- Redirect public visitors from WordPress to your headless frontend
- **GraphQL Performance** -- Cache-control headers, object cache integration, query complexity limits
- **CORS** -- Configurable CORS headers for browser-side API requests
- **Security Headers** -- X-Content-Type-Options, HSTS, Referrer-Policy, and more
- **Preview Mode** -- JWT-based preview link rewriting for authenticated draft access
- **WP Migrate DB Compatibility** -- Ensures WP Migrate DB works alongside headless configuration

## Requirements

- PHP 8.1 or higher
- WordPress 6.4 or higher
- [WPGraphQL](https://www.wpgraphql.com/) 2.0+ (required dependency)
- Composer for installation

## Installation

### Via Composer (recommended)

Add the repository to your `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/projectassistant-webdev/wp-headless-toolkit.git"
    }
  ],
  "require": {
    "projectassistant/wp-headless-toolkit": "^1.3"
  }
}
```

Then install:

```bash
composer install
```

The plugin will be installed to `wp-content/plugins/wp-headless-toolkit/`.

### Manual Installation

1. Download or clone the repository into `wp-content/plugins/wp-headless-toolkit/`:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/projectassistant-webdev/wp-headless-toolkit.git
   ```
2. Run `composer install --no-dev` in the plugin directory
3. Activate the plugin in WordPress admin

## Quick Start

1. Install the plugin via Composer
2. Activate the plugin in WordPress admin (requires WPGraphQL to be active)
3. Add environment variables to your `.env` file or `wp-config.php`:

```bash
# Required: Your Next.js frontend URL
HEADLESS_FRONTEND_URL=https://your-frontend.com

# Required for revalidation: Next.js revalidation endpoint and secret
NEXTJS_REVALIDATION_URL=https://your-frontend.com/api/revalidate
NEXTJS_REVALIDATION_SECRET=your-shared-secret

# Required for CORS: Allowed origins (comma-separated)
HEADLESS_CORS_ORIGINS=https://your-frontend.com,http://localhost:3000

# Required for preview mode: Shared secret for JWT signing
HEADLESS_PREVIEW_SECRET=your-preview-secret
```

4. Visit **Settings > WP Headless Toolkit** in wp-admin to verify module status

## Module Reference

### 1. ISR Revalidation

Triggers Next.js on-demand ISR revalidation when posts, pages, or custom post types are published or updated.

| Variable | Description | Default |
|----------|-------------|---------|
| `NEXTJS_REVALIDATION_URL` | Full URL to your Next.js revalidation endpoint | -- |
| `NEXTJS_REVALIDATION_SECRET` | Shared secret for authenticating revalidation requests | -- |
| `WP_HEADLESS_DISABLE_REVALIDATION` | Set to `true` to disable this module | `false` |

### 2. REST API Security

Filters REST API endpoints to only expose those used by headless frontends. Blocks access to endpoints that could leak sensitive data.

| Variable | Description | Default |
|----------|-------------|---------|
| `WP_HEADLESS_DISABLE_REST_SECURITY` | Set to `true` to disable this module | `false` |

### 3. Head Cleanup

Removes unnecessary `<head>` elements: WordPress emoji scripts, oEmbed discovery links, REST API link, WLW manifest link, RSD link, and shortlink.

| Variable | Description | Default |
|----------|-------------|---------|
| `WP_HEADLESS_DISABLE_HEAD_CLEANUP` | Set to `true` to disable this module | `false` |

### 4. Frontend Redirect

Redirects public visitors from WordPress to the headless frontend. Preserves admin access and API endpoints.

| Variable | Description | Default |
|----------|-------------|---------|
| `HEADLESS_FRONTEND_URL` | URL to redirect visitors to | -- |
| `WP_HEADLESS_DISABLE_FRONTEND_REDIRECT` | Set to `true` to disable this module | `false` |

### 5. GraphQL Performance

Adds cache-control headers to GraphQL responses, integrates with object cache (Redis/Memcached), and enforces query complexity limits.

| Variable | Description | Default |
|----------|-------------|---------|
| `HEADLESS_GRAPHQL_CACHE_TTL` | Cache TTL in seconds | `600` |
| `HEADLESS_GRAPHQL_COMPLEXITY_LIMIT` | Maximum query complexity score | `500` |
| `WP_HEADLESS_DISABLE_GRAPHQL_PERFORMANCE` | Set to `true` to disable this module | `false` |

### 6. CORS

Adds CORS headers to allow browser-side requests from your frontend to the WordPress API.

| Variable | Description | Default |
|----------|-------------|---------|
| `HEADLESS_CORS_ORIGINS` | Comma-separated list of allowed origins | -- |
| `WP_HEADLESS_DISABLE_CORS` | Set to `true` to disable this module | `false` |

### 7. Security Headers

Adds security headers to all responses. Disabled by default since many deployments use Cloudflare or similar CDNs.

| Variable | Description | Default |
|----------|-------------|---------|
| `WP_HEADLESS_ENABLE_SECURITY_HEADERS` | Set to `true` to enable (disabled by default) | `false` |
| `WP_HEADLESS_DISABLE_SECURITY_HEADERS` | Set to `true` to disable this module | `false` |
| `WP_HEADLESS_X_CONTENT_TYPE_OPTIONS` | Override X-Content-Type-Options | `nosniff` |
| `WP_HEADLESS_X_FRAME_OPTIONS` | Override X-Frame-Options | `DENY` |
| `WP_HEADLESS_HSTS` | Override Strict-Transport-Security | `max-age=31536000; includeSubDomains` |
| `WP_HEADLESS_REFERRER_POLICY` | Override Referrer-Policy | `strict-origin-when-cross-origin` |
| `WP_HEADLESS_PERMISSIONS_POLICY` | Override Permissions-Policy | `camera=(), microphone=(), geolocation=()` |

### 8. Preview Mode

Rewrites WordPress preview links to point to your Next.js preview route. Generates JWT tokens for authenticated draft access.

| Variable | Description | Default |
|----------|-------------|---------|
| `HEADLESS_FRONTEND_URL` | Frontend URL for preview links | -- |
| `HEADLESS_PREVIEW_SECRET` | Shared secret for JWT signing | -- |
| `WP_HEADLESS_PREVIEW_TOKEN_EXPIRY` | Token expiry in seconds | `300` |
| `WP_HEADLESS_DISABLE_PREVIEW_MODE` | Set to `true` to disable this module | `false` |

### 9. WP Migrate DB Compatibility

Ensures WP Migrate DB works alongside the headless configuration by excluding its endpoints from REST API filtering.

| Variable | Description | Default |
|----------|-------------|---------|
| `WP_HEADLESS_DISABLE_MIGRATE_DB_COMPAT` | Set to `true` to disable this module | `false` |

## Environment Variables Reference

All configuration is done through environment variables (`.env` for Bedrock, or `wp-config.php` constants for standard WordPress). Priority: environment variable > wp-config constant > default value.

### Core Configuration

| Variable | Required | Description |
|----------|----------|-------------|
| `HEADLESS_FRONTEND_URL` | Yes | Your Next.js frontend URL |
| `NEXTJS_REVALIDATION_URL` | For revalidation | Revalidation endpoint URL |
| `NEXTJS_REVALIDATION_SECRET` | For revalidation | Revalidation shared secret |
| `HEADLESS_CORS_ORIGINS` | For CORS | Comma-separated allowed origins |
| `HEADLESS_PREVIEW_SECRET` | For preview | JWT signing secret |

### Module Toggle Variables

| Variable | Effect |
|----------|--------|
| `WP_HEADLESS_DISABLE_REVALIDATION` | Disable ISR revalidation module |
| `WP_HEADLESS_DISABLE_REST_SECURITY` | Disable REST API security module |
| `WP_HEADLESS_DISABLE_HEAD_CLEANUP` | Disable head cleanup module |
| `WP_HEADLESS_DISABLE_FRONTEND_REDIRECT` | Disable frontend redirect module |
| `WP_HEADLESS_DISABLE_GRAPHQL_PERFORMANCE` | Disable GraphQL performance module |
| `WP_HEADLESS_DISABLE_CORS` | Disable CORS module |
| `WP_HEADLESS_ENABLE_SECURITY_HEADERS` | Enable security headers (disabled by default) |
| `WP_HEADLESS_DISABLE_SECURITY_HEADERS` | Disable security headers module |
| `WP_HEADLESS_DISABLE_PREVIEW_MODE` | Disable preview mode module |
| `WP_HEADLESS_DISABLE_MIGRATE_DB_COMPAT` | Disable WP Migrate DB compat module |

### Performance Tuning

| Variable | Default | Description |
|----------|---------|-------------|
| `HEADLESS_GRAPHQL_CACHE_TTL` | `600` | GraphQL cache TTL in seconds |
| `HEADLESS_GRAPHQL_COMPLEXITY_LIMIT` | `500` | Maximum GraphQL query complexity |
| `WP_HEADLESS_PREVIEW_TOKEN_EXPIRY` | `300` | Preview token expiry in seconds |

## Hooks and Filters

### Configuration

```php
// Override any config value
add_filter( 'wp_headless_config_value', function( $value, $key ) {
    if ( 'HEADLESS_FRONTEND_URL' === $key ) {
        return 'https://custom-frontend.com';
    }
    return $value;
}, 10, 2 );
```

### Module Control

```php
// Disable a specific module programmatically
add_filter( 'wp_headless_module_enabled', function( $enabled, $slug ) {
    if ( 'frontend_redirect' === $slug && wp_get_environment_type() === 'local' ) {
        return false;
    }
    return $enabled;
}, 10, 2 );

// Modify the list of loaded modules
add_filter( 'wp_headless_module_classes', function( $modules ) {
    // Remove a module
    return array_filter( $modules, function( $class ) {
        return $class !== \ProjectAssistant\HeadlessToolkit\Modules\HeadCleanup\HeadCleanup::class;
    });
});
```

### Lifecycle

```php
// Run code after all modules are loaded
add_action( 'wp_headless_modules_loaded', function( $modules ) {
    // $modules is an array of loaded ModuleInterface instances
});

// Run code after plugin initialization
add_action( 'wp_headless_init', function( $instance ) {
    // $instance is the Main singleton
});
```

### Preview Mode

```php
// Customize preview URL per post type
add_filter( 'wp_headless_preview_url', function( $path, $post, $post_type ) {
    if ( 'page' === $post_type ) {
        return 'api/preview/pages';
    }
    return $path;
}, 10, 3 );
```

### GraphQL Performance

```php
// Customize GraphQL cache headers
add_filter( 'wp_headless_graphql_cache_headers', function( $headers ) {
    $headers['Cache-Control'] = 'public, max-age=3600, s-maxage=86400';
    return $headers;
});

// Customize query complexity limit
add_filter( 'wp_headless_graphql_complexity_limit', function( $limit ) {
    return 1000; // Allow more complex queries
}, 10, 1 );
```

### Security Headers

```php
// Modify security headers
add_filter( 'wp_headless_security_headers', function( $headers ) {
    $headers['X-Frame-Options'] = 'SAMEORIGIN';
    return $headers;
});
```

## Installation Validation

Run the validation script to verify your installation is configured correctly:

```bash
wp eval-file wp-content/plugins/wp-headless-toolkit/bin/validate-installation.php
```

Or if using WP-CLI from the plugin directory:

```bash
cd wp-content/plugins/wp-headless-toolkit
wp eval-file bin/validate-installation.php
```

The script checks PHP version, WordPress version, WPGraphQL status, module configuration, and environment variable presence.

## Testing

The test suite uses Codeception with the WPUnit module.

```bash
# Run all tests (via Docker)
docker compose run --rm wordpress vendor/bin/codecept run wpunit

# Run a specific test file
docker compose run --rm wordpress vendor/bin/codecept run wpunit tests/wpunit/Modules/Cors/CorsTest.php
```

## Architecture

```
wp-headless-toolkit/
  src/
    Admin/
      SettingsPage.php          # Display-only admin settings page
    Helpers/
      Config.php                # Environment/constant config helper
    Modules/
      ModuleInterface.php       # Contract for all modules
      Revalidation/             # ISR revalidation module
      RestSecurity/             # REST API endpoint filtering
      HeadCleanup/              # Head element cleanup
      FrontendRedirect/         # Visitor redirect module
      GraphqlPerformance/       # GraphQL caching and limits
      Cors/                     # CORS headers module
      SecurityHeaders/          # Security headers module
      PreviewMode/              # JWT preview link rewriting
      MigrateDbCompat/          # WP Migrate DB compatibility
    Main.php                    # Plugin singleton, module loader
  access-functions.php          # Global helper functions
  wp-headless-toolkit.php       # Plugin bootstrap
  bin/
    validate-installation.php   # Installation validation script
```

## License

GPL-3.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html) for details.
