# ISR Revalidation

## Overview

The ISR Revalidation module triggers Next.js on-demand Incremental Static Regeneration (ISR) when content changes in WordPress. When a post is published, updated, trashed, or deleted -- or when a taxonomy term or navigation menu is modified -- the module sends a non-blocking POST request to the Next.js revalidation endpoint with a set of cache tags identifying the affected content.

This enables the Next.js App Router tag-based revalidation pattern, where the frontend can selectively regenerate only the pages that reference the changed content rather than rebuilding the entire site.

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `NEXTJS_REVALIDATION_URL` | Yes | -- | The Next.js revalidation endpoint URL (e.g., `https://example.com/api/revalidate/`) |
| `NEXTJS_REVALIDATION_SECRET` | Yes | -- | Shared secret for authenticating revalidation requests |
| `WP_HEADLESS_DISABLE_REVALIDATION` | No | `false` | Set to `true` to disable this module entirely |

Both `NEXTJS_REVALIDATION_URL` and `NEXTJS_REVALIDATION_SECRET` must be set for the module to activate. If either is missing, the module silently disables itself even if not explicitly disabled.

## How It Works

### WordPress Hooks

The module listens on the following WordPress hooks at the default priority (10):

| Hook | Handler | Trigger |
|------|---------|---------|
| `save_post` | `handle_post_change` | Post created, updated, or published |
| `delete_post` | `handle_post_delete` | Post permanently deleted |
| `wp_trash_post` | `handle_post_delete` | Post moved to trash |
| `edited_term` | `handle_term_change` | Taxonomy term edited |
| `delete_term` | `handle_term_change` | Taxonomy term deleted |
| `wp_update_nav_menu` | `handle_menu_change` | Navigation menu updated |

### Post Change Guards

The `handle_post_change` handler applies several guards before sending a revalidation request:

1. **Autosave check**: Skips if `DOING_AUTOSAVE` is defined and true.
2. **Revision check**: Skips post revisions.
3. **Status check**: Only revalidates posts with `publish` status.
4. **Post type check**: Only revalidates allowed post types (default: `post` and `page`), configurable via the `wp_headless_revalidation_post_types` filter.

The `handle_post_delete` handler does not apply the status or post type guards, since deleted content should always trigger cache invalidation.

### Tag-Based Revalidation

Each revalidation request includes an array of cache tags that identify the affected content. The Next.js frontend uses these tags to determine which pages to regenerate.

**For posts**, the following tags are generated:

| Tag Format | Example | Purpose |
|------------|---------|---------|
| `{post_type}` | `post` | Invalidate all pages listing this post type |
| `{post_type}-{ID}` | `post-42` | Invalidate the specific post page |
| `{taxonomy}-{term_slug}` | `category-news` | Invalidate pages filtered by this term |

All taxonomies assigned to the post are included automatically.

**For taxonomy terms**, the tags are:

| Tag Format | Example | Purpose |
|------------|---------|---------|
| `{taxonomy}` | `category` | Invalidate pages listing this taxonomy |
| `term-{term_id}` | `term-15` | Invalidate the specific term archive |

**For navigation menus**, the tags are:

| Tag Format | Example | Purpose |
|------------|---------|---------|
| `menu` | `menu` | Invalidate all pages displaying menus |
| `menu-{menu_id}` | `menu-3` | Invalidate pages using this specific menu |

### Request Format

The module sends a POST request to the configured `NEXTJS_REVALIDATION_URL` with the following JSON body:

```json
{
  "tags": ["post", "post-42", "category-news"],
  "secret": "your-shared-secret"
}
```

The request is sent with `blocking => false`, making it a non-blocking (fire-and-forget) async request that does not slow down the WordPress save operation. The request timeout is 10 seconds.

## Filters & Actions

### Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_revalidation_post_types` | Modify which post types trigger revalidation. Default: `['post', 'page']`. | `string[] $post_types` |
| `wp_headless_revalidation_tags` | Modify the cache tags sent for a specific post. | `string[] $tags`, `\WP_Post $post` |
| `wp_headless_revalidation_request_args` | Modify the `wp_remote_post` arguments before the request is sent. | `array $args`, `string[] $tags`, `string $url` |

### Actions

| Action | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_revalidation_sent` | Fires after a revalidation request is dispatched. | `string[] $tags`, `string $url` |

## Usage Examples

### Add a Custom Post Type to Revalidation

```php
add_filter( 'wp_headless_revalidation_post_types', function ( array $post_types ): array {
    $post_types[] = 'product';
    $post_types[] = 'event';
    return $post_types;
} );
```

### Add Custom Tags for a Post

```php
add_filter( 'wp_headless_revalidation_tags', function ( array $tags, \WP_Post $post ): array {
    // Add a global "all-content" tag for full-site rebuilds.
    $tags[] = 'all-content';

    // Add author-specific tag.
    $tags[] = 'author-' . $post->post_author;

    return $tags;
}, 10, 2 );
```

### Modify the Revalidation Request

```php
add_filter( 'wp_headless_revalidation_request_args', function ( array $args, array $tags, string $url ): array {
    // Add a custom header.
    $args['headers']['X-Custom-Header'] = 'my-value';

    // Increase the timeout.
    $args['timeout'] = 30;

    return $args;
}, 10, 3 );
```

### Log Revalidation Events

```php
add_action( 'wp_headless_revalidation_sent', function ( array $tags, string $url ): void {
    error_log( sprintf(
        'ISR revalidation sent to %s with tags: %s',
        $url,
        implode( ', ', $tags )
    ) );
}, 10, 2 );
```

## Disabling

Set the `WP_HEADLESS_DISABLE_REVALIDATION` constant or environment variable to `true`:

```php
// In wp-config.php
define( 'WP_HEADLESS_DISABLE_REVALIDATION', true );
```

Or via environment variable:

```bash
WP_HEADLESS_DISABLE_REVALIDATION=true
```

Alternatively, remove the `NEXTJS_REVALIDATION_URL` or `NEXTJS_REVALIDATION_SECRET` configuration to prevent the module from activating.
