# Frontend Redirect

## Overview

The Frontend Redirect module issues 301 permanent redirects from the WordPress frontend to the Next.js frontend URL. In a headless architecture, WordPress serves as a content API while the Next.js application handles all public-facing rendering. When a visitor or search engine crawler hits a WordPress frontend URL directly, this module redirects them to the equivalent path on the Next.js site, preserving the request URI.

The module also rewrites WordPress preview links so that clicking "Preview" in the editor opens the post on the Next.js preview route instead of the WordPress frontend.

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `HEADLESS_FRONTEND_URL` | Yes | -- | The Next.js frontend URL (e.g., `https://example.com`) |
| `WP_HEADLESS_DISABLE_FRONTEND_REDIRECT` | No | `false` | Set to `true` to disable this module |

The `HEADLESS_FRONTEND_URL` must be set for the module to activate. If it is not configured, the module silently disables itself.

## How It Works

### Redirect Behavior

The module hooks into `template_redirect` to intercept public frontend requests. When a request reaches the WordPress frontend:

1. The module checks if the request is a passthrough request (admin, API, cron, etc.).
2. If not a passthrough, it constructs a redirect URL by combining the configured `HEADLESS_FRONTEND_URL` with the current request URI path.
3. It applies the `wp_headless_redirect_url` filter, allowing customization.
4. It issues a `301 Moved Permanently` redirect and exits.

**Example**: A request to `https://wp.example.com/about-us/` redirects to `https://example.com/about-us/`.

### Passthrough Conditions

The following request types pass through without redirect:

| Condition | Detection Method |
|-----------|------------------|
| Admin area | `is_admin()` |
| WP-CLI | `WP_CLI` constant |
| REST API | `REST_REQUEST` constant |
| AJAX | `wp_doing_ajax()` |
| Cron | `wp_doing_cron()` |
| GraphQL | `GRAPHQL_HTTP_REQUEST` constant |
| Login/Register pages | `$pagenow` is `wp-login.php` or `wp-register.php` |
| Custom passthrough | `wp_headless_is_passthrough_request` filter returns `true` |

### Preview Link Rewriting

The module hooks into `preview_post_link` (priority 10) to rewrite the preview URL. Instead of pointing to the WordPress frontend, preview links point to:

```
{HEADLESS_FRONTEND_URL}/api/preview/?id={post_id}&status={post_status}&type={post_type}
```

**Example**: Previewing a draft post with ID 42 generates:

```
https://example.com/api/preview/?id=42&status=draft&type=post
```

The Next.js `/api/preview/` route handler is responsible for enabling Next.js draft mode and redirecting to the correct page.

## Filters & Actions

### Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_is_passthrough_request` | Add custom conditions for requests that should not be redirected. Return `true` to bypass the redirect. Default: `false`. | `bool $is_passthrough` |
| `wp_headless_redirect_url` | Modify the redirect URL before the 301 is issued. | `string $redirect_url`, `string $request_uri`, `string $frontend_url` |
| `wp_headless_preview_link` | Modify the rewritten preview link. | `string $preview_url`, `string $preview_link`, `\WP_Post $post` |

## Usage Examples

### Add a Custom Passthrough Condition

```php
add_filter( 'wp_headless_is_passthrough_request', function ( bool $is_passthrough ): bool {
    // Allow requests to /custom-webhook/ to pass through.
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( str_starts_with( $request_uri, '/custom-webhook/' ) ) {
        return true;
    }
    return $is_passthrough;
} );
```

### Customize the Redirect URL

```php
add_filter( 'wp_headless_redirect_url', function ( string $redirect_url, string $request_uri, string $frontend_url ): string {
    // Redirect /blog/* paths to a subdomain.
    if ( str_starts_with( $request_uri, '/blog/' ) ) {
        return 'https://blog.example.com' . $request_uri;
    }
    return $redirect_url;
}, 10, 3 );
```

### Customize the Preview Link

```php
add_filter( 'wp_headless_preview_link', function ( string $preview_url, string $preview_link, \WP_Post $post ): string {
    // Use a custom preview route for specific post types.
    if ( 'product' === $post->post_type ) {
        return add_query_arg(
            [ 'id' => $post->ID, 'type' => 'product' ],
            'https://example.com/api/preview-product/'
        );
    }
    return $preview_url;
}, 10, 3 );
```

## Disabling

Set the `WP_HEADLESS_DISABLE_FRONTEND_REDIRECT` constant or environment variable to `true`:

```php
// In wp-config.php
define( 'WP_HEADLESS_DISABLE_FRONTEND_REDIRECT', true );
```

Or via environment variable:

```bash
WP_HEADLESS_DISABLE_FRONTEND_REDIRECT=true
```

Alternatively, removing the `HEADLESS_FRONTEND_URL` configuration will prevent the module from activating.
