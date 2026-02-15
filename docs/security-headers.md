# Security Headers

## Overview

The Security Headers module adds standard security headers to all WordPress responses, including both standard page responses and REST API responses. These headers protect against common web vulnerabilities such as MIME-type sniffing, clickjacking, protocol downgrade attacks, and information leakage via referrer headers.

The module is disabled by default because many deployments rely on a CDN or reverse proxy (e.g., Cloudflare) that already handles security headers. Enabling the module at the application layer when a CDN is already adding the same headers can cause duplicate or conflicting values. Explicit opt-in ensures intentional configuration.

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `WP_HEADLESS_ENABLE_SECURITY_HEADERS` | Yes | `false` | Set to `true` to enable the module |
| `WP_HEADLESS_DISABLE_SECURITY_HEADERS` | No | `false` | Set to `true` to force-disable the module (takes precedence) |
| `WP_HEADLESS_X_CONTENT_TYPE_OPTIONS` | No | `nosniff` | Value for the `X-Content-Type-Options` header |
| `WP_HEADLESS_X_FRAME_OPTIONS` | No | `DENY` | Value for the `X-Frame-Options` header |
| `WP_HEADLESS_HSTS` | No | `max-age=31536000; includeSubDomains` | Value for the `Strict-Transport-Security` header |
| `WP_HEADLESS_REFERRER_POLICY` | No | `strict-origin-when-cross-origin` | Value for the `Referrer-Policy` header |
| `WP_HEADLESS_PERMISSIONS_POLICY` | No | `camera=(), microphone=(), geolocation=()` | Value for the `Permissions-Policy` header |

## How It Works

### Opt-In Activation

The module requires explicit opt-in via `WP_HEADLESS_ENABLE_SECURITY_HEADERS=true`. Both conditions must be met for the module to activate:

1. Not disabled via `WP_HEADLESS_DISABLE_SECURITY_HEADERS`.
2. Explicitly enabled via `WP_HEADLESS_ENABLE_SECURITY_HEADERS`.

### Default Security Headers

When enabled with default configuration, the module adds these headers to every response:

| Header | Default Value | Purpose |
|--------|---------------|---------|
| `X-Content-Type-Options` | `nosniff` | Prevents browsers from MIME-type sniffing |
| `X-Frame-Options` | `DENY` | Prevents the page from being embedded in iframes (clickjacking protection) |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Enforces HTTPS for one year including subdomains |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Limits referrer information sent with cross-origin requests |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Disables access to camera, microphone, and geolocation APIs |

### Per-Header Override

Each header can be individually overridden by setting the corresponding constant or environment variable. The module checks for a configured value and falls back to the default if none is set.

**Skipping a header:** Setting any header constant to an empty string causes that header to be omitted entirely. This is useful when a specific header is managed elsewhere (e.g., by a CDN or web server configuration).

```php
// Skip the X-Frame-Options header (e.g., because Cloudflare handles it)
define( 'WP_HEADLESS_X_FRAME_OPTIONS', '' );
```

```env
# Skip HSTS via environment variable
WP_HEADLESS_HSTS=
```

### Response Types

The module applies security headers to two types of WordPress responses:

1. **Standard WordPress responses** via the `wp_headers` filter (priority 10).
2. **REST API responses** via the `rest_post_dispatch` filter (priority 999, ensuring security headers are applied after other plugins have modified the response).

### Header Resolution

For each header, the module follows this resolution order:

1. Check if a configuration value is set via `Config::get()` (reads constants and environment variables).
2. If a configuration value exists, use it.
3. If the environment variable is explicitly set to an empty string, skip the header.
4. If no configuration is found, use the built-in default.
5. Filter the final set of headers through `wp_headless_security_headers`.

## Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_security_headers` | Modify the complete set of resolved security headers before they are applied to the response. Headers can be added, modified, or removed. | `array<string, string> $headers` -- associative array of header name to value |
| `wp_headless_module_enabled` | Control whether the module is enabled (shared across all modules). | `bool $enabled`, `string $slug` (slug: `security_headers`) |

## Usage Examples

### Enable with all defaults

```env
WP_HEADLESS_ENABLE_SECURITY_HEADERS=true
```

### Enable with a custom HSTS policy

```php
define( 'WP_HEADLESS_ENABLE_SECURITY_HEADERS', true );
define( 'WP_HEADLESS_HSTS', 'max-age=63072000; includeSubDomains; preload' );
```

### Allow framing from the same origin

```php
define( 'WP_HEADLESS_X_FRAME_OPTIONS', 'SAMEORIGIN' );
```

### Skip a specific header

```php
// Let the web server or CDN handle Permissions-Policy
define( 'WP_HEADLESS_PERMISSIONS_POLICY', '' );
```

### Add a custom header via filter

```php
add_filter( 'wp_headless_security_headers', function ( array $headers ): array {
    $headers['X-XSS-Protection'] = '1; mode=block';
    $headers['Content-Security-Policy'] = "default-src 'self'";
    return $headers;
} );
```

### Remove a header via filter

```php
add_filter( 'wp_headless_security_headers', function ( array $headers ): array {
    unset( $headers['Permissions-Policy'] );
    return $headers;
} );
```

## Disabling

Disable the module by removing the enable flag or by setting the disable constant:

```php
// Option 1: Remove or set to false
define( 'WP_HEADLESS_ENABLE_SECURITY_HEADERS', false );

// Option 2: Explicit disable (takes precedence over enable)
define( 'WP_HEADLESS_DISABLE_SECURITY_HEADERS', true );
```

```env
# Via environment variable
WP_HEADLESS_DISABLE_SECURITY_HEADERS=true
```
