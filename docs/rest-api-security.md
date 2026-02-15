# REST API Security

## Overview

The REST API Security module restricts WordPress REST API access to only the endpoints needed for headless operation. In a headless architecture, public-facing content is served through WPGraphQL, so standard REST endpoints like comments, user enumeration, and search become unnecessary attack surfaces. This module removes those endpoints for unauthenticated users and restricts general REST API access to a whitelist of allowed route prefixes.

The approach is inspired by the FaustWP deny-public-access pattern: block by default, allow by exception.

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `WP_HEADLESS_DISABLE_REST_SECURITY` | No | `false` | Set to `true` to disable this module |

No additional configuration is required. The module activates by default with sensible blocked and allowed prefix lists that can be customized via filters.

## How It Works

The module applies two layers of REST API protection:

### Layer 1: Endpoint Removal

The `filter_endpoints` method hooks into the `rest_endpoints` filter at priority 999 (late, to catch all registered endpoints) and removes routes matching blocked prefixes from the endpoint registry entirely.

**Default blocked prefixes:**

| Prefix | Reason |
|--------|--------|
| `/wp/v2/comments` | Comments are not used in headless frontends |
| `/wp/v2/users` | Prevents user enumeration attacks |
| `/wp/v2/search` | Search is handled by the frontend or WPGraphQL |

**Admin bypass**: Authenticated users with the `manage_options` capability see all endpoints unfiltered. This ensures wp-admin functionality (Gutenberg editor, block API calls, etc.) remains fully operational.

### Layer 2: Authentication Restriction

The `restrict_unauthenticated_access` method hooks into the `rest_authentication_errors` filter at priority 99 and returns a `401 Unauthorized` error for any unauthenticated REST API request that does not match an allowed route prefix.

**Default allowed prefixes for unauthenticated access:**

| Prefix | Reason |
|--------|--------|
| `/wp-site-health/` | WordPress site health checks |
| `/wp/v2/settings` | Public settings endpoint |
| `/wpgraphql/` | WPGraphQL IDE and introspection |
| `/batch/v1` | WordPress batch API |

**Authenticated user bypass**: Any logged-in user (regardless of role) has full REST API access. The authentication check only applies to completely unauthenticated requests.

### Request Flow

```
REST API Request
    |
    v
Is user logged in? -- Yes --> Allow (full access)
    |
    No
    |
    v
Does route match allowed prefix? -- Yes --> Allow
    |
    No
    |
    v
Return 401 Unauthorized
```

For the endpoint removal layer:

```
REST Endpoints Registration
    |
    v
Has 'manage_options' capability? -- Yes --> Show all endpoints
    |
    No
    |
    v
Remove routes matching blocked prefixes
```

## Filters & Actions

### Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_rest_blocked_prefixes` | Modify the list of REST endpoint prefixes to remove from the registry. Default: `['/wp/v2/comments', '/wp/v2/users', '/wp/v2/search']`. | `string[] $blocked_prefixes` |
| `wp_headless_rest_allowed_prefixes` | Modify the list of REST route prefixes allowed for unauthenticated access. Default: `['/wp-site-health/', '/wp/v2/settings', '/wpgraphql/', '/batch/v1']`. | `string[] $allowed_prefixes` |

## Usage Examples

### Block Additional REST Endpoints

```php
add_filter( 'wp_headless_rest_blocked_prefixes', function ( array $prefixes ): array {
    // Also block media and tag endpoints for unauthenticated users.
    $prefixes[] = '/wp/v2/media';
    $prefixes[] = '/wp/v2/tags';
    return $prefixes;
} );
```

### Allow a Custom REST Route for Unauthenticated Access

```php
add_filter( 'wp_headless_rest_allowed_prefixes', function ( array $prefixes ): array {
    // Allow a custom plugin's public API.
    $prefixes[] = '/my-plugin/v1/public';
    return $prefixes;
} );
```

### Unblock a Default Blocked Endpoint

```php
add_filter( 'wp_headless_rest_blocked_prefixes', function ( array $prefixes ): array {
    // Allow the search endpoint (remove it from blocked list).
    return array_filter( $prefixes, fn( $p ) => $p !== '/wp/v2/search' );
} );
```

## Disabling

Set the `WP_HEADLESS_DISABLE_REST_SECURITY` constant or environment variable to `true`:

```php
// In wp-config.php
define( 'WP_HEADLESS_DISABLE_REST_SECURITY', true );
```

Or via environment variable:

```bash
WP_HEADLESS_DISABLE_REST_SECURITY=true
```

When disabled, all REST API endpoints remain accessible as in a standard WordPress installation.
