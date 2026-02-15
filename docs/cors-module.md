# CORS Module

## Overview

The CORS module sets cross-origin resource sharing headers on GraphQL and REST API responses, enabling browser-side JavaScript to communicate with the WordPress backend. This is essential for headless architectures where the frontend application is served from a different domain than the WordPress API.

The module is opt-in by design. Most server-side rendered (SSR) and incremental static regeneration (ISR) setups make API calls from the server, not the browser, so CORS headers are unnecessary. The module only activates when `HEADLESS_CORS_ORIGINS` is explicitly configured with at least one allowed origin.

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `HEADLESS_CORS_ORIGINS` | Yes | *(none)* | Comma-separated list of allowed origins (e.g., `https://example.com,https://staging.example.com`). Module stays inactive without this. |
| `WP_HEADLESS_DISABLE_CORS` | No | `false` | Set to `true` to disable this module entirely |

## How It Works

### Opt-In Activation

The module's `is_enabled()` method checks two conditions:

1. The module is not explicitly disabled via `WP_HEADLESS_DISABLE_CORS`.
2. `HEADLESS_CORS_ORIGINS` contains at least one origin.

If either condition fails, the module does not register any hooks.

### Origin Validation

When a request arrives with an `Origin` header, the module checks whether that origin appears in the configured allow list. The comparison is an exact string match against the comma-separated values in `HEADLESS_CORS_ORIGINS`. The `wp_headless_cors_origin_allowed` filter allows custom logic (e.g., regex matching or dynamic origin lists).

### Preflight OPTIONS Handling

The module registers an early `init` action (priority 1) to intercept OPTIONS preflight requests. When an OPTIONS request targets the REST API or GraphQL endpoint:

1. The request origin is validated against the allow list.
2. If allowed, the module sends CORS headers along with:
   - `Access-Control-Max-Age: 86400` (24-hour preflight cache)
   - `Content-Length: 0`
   - `Content-Type: text/plain`
   - HTTP status `204 No Content`
3. The `wp_headless_cors_preflight_sent` action fires.
4. Execution terminates via `exit`.

If the origin is not allowed, the request proceeds without CORS headers.

### API Endpoint Detection

The module determines whether a request targets an API endpoint by checking the request URI for:

- The WordPress REST API prefix (obtained via `rest_get_url_prefix()`)
- The `/graphql` path

Non-API requests are not affected by the CORS module.

### REST API Headers

For REST API responses, the module hooks into `rest_pre_serve_request` and adds CORS headers via PHP's `header()` function when the origin is allowed.

### GraphQL Headers

For WPGraphQL responses, the module hooks into `graphql_response_headers_to_send` and adds CORS headers to the response headers array. This hook is only registered if WPGraphQL is active (`class_exists( 'WPGraphQL' )`).

### Default Headers and Methods

| Setting | Default Value |
|---------|---------------|
| Allowed Methods | `GET, POST, OPTIONS` |
| Allowed Headers | `Content-Type, Authorization` |
| Allow Credentials | `true` |

## Filters & Actions

### Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_cors_allowed_methods` | Customize the allowed HTTP methods string. | `string $methods` -- default: `'GET, POST, OPTIONS'` |
| `wp_headless_cors_allowed_headers` | Customize the allowed HTTP headers string. | `string $headers` -- default: `'Content-Type, Authorization'` |
| `wp_headless_cors_origin_allowed` | Override origin validation logic. Return `true` to allow an origin that is not in the configured list, or `false` to reject one that is. | `bool $allowed`, `string $origin`, `string[] $allowed_origins` |
| `wp_headless_cors_graphql_headers` | Modify the full set of GraphQL response headers after CORS headers have been added. | `array $headers`, `string $origin` |
| `wp_headless_module_enabled` | Control whether the module is enabled (shared across all modules). | `bool $enabled`, `string $slug` (slug: `cors`) |

### Actions

| Action | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_cors_preflight_sent` | Fires after CORS preflight headers have been sent and before execution terminates. Useful for logging or analytics. | `string $origin` -- the allowed origin |

## Usage Examples

### Basic setup with a single frontend origin

```env
HEADLESS_CORS_ORIGINS=https://my-nextjs-site.com
```

### Allow multiple origins (e.g., production and staging)

```env
HEADLESS_CORS_ORIGINS=https://my-nextjs-site.com,https://staging.my-nextjs-site.com
```

### Add additional HTTP methods

```php
add_filter( 'wp_headless_cors_allowed_methods', function ( string $methods ): string {
    return 'GET, POST, PUT, DELETE, OPTIONS';
} );
```

### Add custom headers

```php
add_filter( 'wp_headless_cors_allowed_headers', function ( string $headers ): string {
    return 'Content-Type, Authorization, X-Custom-Header';
} );
```

### Allow origins dynamically (e.g., all subdomains)

```php
add_filter( 'wp_headless_cors_origin_allowed', function ( bool $allowed, string $origin ): bool {
    if ( str_ends_with( $origin, '.example.com' ) ) {
        return true;
    }
    return $allowed;
}, 10, 2 );
```

### Log preflight requests

```php
add_action( 'wp_headless_cors_preflight_sent', function ( string $origin ): void {
    error_log( "CORS preflight handled for origin: {$origin}" );
} );
```

## Disabling

Disable the module by defining the constant or setting the environment variable:

```php
// wp-config.php
define( 'WP_HEADLESS_DISABLE_CORS', true );
```

```env
# .env
WP_HEADLESS_DISABLE_CORS=true
```

Alternatively, simply remove or leave `HEADLESS_CORS_ORIGINS` unconfigured -- the module will remain inactive without it.
