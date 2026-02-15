# GraphQL Performance

## Overview

The GraphQL Performance module adds cache-control headers to WPGraphQL responses, integrates with WordPress object cache (Redis/Memcached) when available, and enforces query complexity limits. It serves as a lightweight complement to WPGraphQL Smart Cache, providing out-of-the-box performance improvements for headless WordPress sites using GraphQL.

The module auto-detects WPGraphQL and gracefully no-ops when WPGraphQL is not installed or active. No errors or warnings are produced if WPGraphQL is absent.

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `HEADLESS_GRAPHQL_CACHE_TTL` | No | `600` | Cache TTL in seconds for query responses (both HTTP headers and object cache) |
| `HEADLESS_GRAPHQL_COMPLEXITY_LIMIT` | No | `500` | Maximum allowed GraphQL query complexity |
| `WP_HEADLESS_DISABLE_GRAPHQL_PERFORMANCE` | No | `false` | Set to `true` to disable this module entirely |

## How It Works

### WPGraphQL Detection

On initialization, the module checks whether the `WPGraphQL` class exists. If WPGraphQL is not active, the module returns early from `init()` without registering any hooks. This means you can safely leave the module enabled even if WPGraphQL is only active on some environments.

### Cache-Control Headers

The module hooks into `graphql_response_headers_to_send` to add appropriate `Cache-Control` headers:

- **Queries** receive `public, max-age={TTL}` where TTL is the configured cache TTL (default 600 seconds / 10 minutes).
- **Mutations** receive `no-store, no-cache` to prevent caching of write operations.

Mutation detection works by inspecting the raw GraphQL query string. The module reads the query from either the POST body or `php://input` (for JSON-encoded requests) and checks whether the trimmed query starts with the keyword `mutation`.

### Object Cache Integration

The module hooks into `graphql_request_results` to cache and retrieve query results using the WordPress object cache API (`wp_cache_get` / `wp_cache_set`). This works with any persistent object cache backend (Redis, Memcached, etc.) configured in WordPress.

**Cache key generation:** The cache key is an MD5 hash of the query string concatenated with a pipe separator and the JSON-encoded variables: `md5(query + "|" + json_encode(variables))`. This ensures that identical queries with different variables produce different cache keys.

**Cache group:** All cached entries use the group `wp_headless_toolkit_graphql`.

**Cache flow:**
1. If the request is a mutation, caching is skipped entirely.
2. The module generates a cache key from the query and variables.
3. It attempts to retrieve a cached response via `wp_cache_get()`.
4. If a cached response exists, it is returned immediately (skipping GraphQL execution).
5. If no cached response exists, the original response is stored via `wp_cache_set()` with the configured TTL.

### Query Complexity Limits

The module hooks into `graphql_query_complexity_limit` to enforce a configurable complexity limit. This protects against deeply nested or excessively complex queries that could degrade server performance. The default limit is 500.

## Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_graphql_cache_headers` | Modify the Cache-Control headers before they are sent with the GraphQL response. | `array $headers` -- the response headers array including the `Cache-Control` entry |
| `wp_headless_graphql_complexity_limit` | Override the query complexity limit. | `int $configured_limit` -- the limit from configuration, `int $limit` -- the original WPGraphQL limit |
| `wp_headless_module_enabled` | Control whether the module is enabled (shared across all modules). | `bool $enabled`, `string $slug` (slug: `graphql_performance`) |

## Usage Examples

### Adjust the cache TTL

Set the cache TTL to 30 minutes via your `.env` or `wp-config.php`:

```php
// wp-config.php or .env
define( 'HEADLESS_GRAPHQL_CACHE_TTL', 1800 );
```

### Customize cache headers per request

```php
add_filter( 'wp_headless_graphql_cache_headers', function ( array $headers ): array {
    // Add s-maxage for CDN caching
    if ( isset( $headers['Cache-Control'] ) && str_starts_with( $headers['Cache-Control'], 'public' ) ) {
        $headers['Cache-Control'] .= ', s-maxage=3600';
    }
    return $headers;
} );
```

### Lower the complexity limit for a specific environment

```php
add_filter( 'wp_headless_graphql_complexity_limit', function ( int $limit ): int {
    return 200; // Stricter limit
}, 10, 1 );
```

### Set a higher complexity limit via environment variable

```env
HEADLESS_GRAPHQL_COMPLEXITY_LIMIT=1000
```

## Disabling

Disable the module by defining the constant or setting the environment variable:

```php
// wp-config.php
define( 'WP_HEADLESS_DISABLE_GRAPHQL_PERFORMANCE', true );
```

```env
# .env
WP_HEADLESS_DISABLE_GRAPHQL_PERFORMANCE=true
```

You can also disable it programmatically via the shared module filter:

```php
add_filter( 'wp_headless_module_enabled', function ( bool $enabled, string $slug ): bool {
    if ( 'graphql_performance' === $slug ) {
        return false;
    }
    return $enabled;
}, 10, 2 );
```
