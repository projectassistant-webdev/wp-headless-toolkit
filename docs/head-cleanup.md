# Head Cleanup

## Overview

The Head Cleanup module strips unnecessary tags from the WordPress `wp_head` output. In a headless architecture, the WordPress frontend is not rendered to visitors -- the Next.js application handles all public-facing HTML. However, WordPress still generates `<head>` output for the admin area and for any direct access to the WordPress site. This module removes meta tags, discovery links, emoji scripts, and feed links that serve no purpose in a headless context, keeping the WordPress instance clean and reducing unnecessary output.

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `WP_HEADLESS_DISABLE_HEAD_CLEANUP` | No | `false` | Set to `true` to disable this module |

No additional configuration is required. The module activates by default and removes a standard set of `wp_head` items.

## How It Works

When the module initializes, it immediately calls `remove_action` on the following `wp_head` callbacks:

| Item Removed | WordPress Function | Priority | Purpose |
|-------------|-------------------|----------|---------|
| Generator meta tag | `wp_generator` | 10 | Removes `<meta name="generator" content="WordPress X.Y.Z" />` which discloses the WordPress version |
| Windows Live Writer manifest | `wlwmanifest_link` | 10 | Removes the `wlwmanifest.xml` link used by the deprecated Windows Live Writer |
| RSD link | `rsd_link` | 10 | Removes the Really Simple Discovery link used by XML-RPC clients |
| Shortlink | `wp_shortlink_wp_head` | 10 | Removes the `<link rel="shortlink">` tag |
| REST API link | `rest_output_link_wp_head` | 10 | Removes the `<link rel="https://api.w.org/">` discovery link (the REST API endpoint remains accessible) |
| oEmbed discovery links | `wp_oembed_add_discovery_links` | * | Removes oEmbed `<link>` tags (handles any registered priority) |
| Emoji detection script | `print_emoji_detection_script` | 7 | Removes the inline emoji detection JavaScript from `wp_head` |
| Emoji styles | `print_emoji_styles` | -- | Removes emoji CSS from `wp_print_styles` |
| Admin emoji script | `print_emoji_detection_script` | -- | Removes emoji JavaScript from `admin_print_scripts` |
| Admin emoji styles | `print_emoji_styles` | -- | Removes emoji CSS from `admin_print_styles` |
| Feed links | `feed_links` | 2 | Removes RSS feed `<link>` tags |
| Extra feed links | `feed_links_extra` | 3 | Removes additional RSS feed links (comments, categories, etc.) |

The oEmbed discovery link removal uses a `while` loop with `has_action()` to handle cases where the callback may be registered at non-standard priorities.

### Extensibility

After removing the default set of items, the module applies the `wp_headless_head_cleanup_removals` filter. This allows themes or plugins to specify additional `wp_head` items to remove without modifying the module directly.

## Filters & Actions

### Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_head_cleanup_removals` | Add additional `wp_head` items to remove. Return an array of `[action_name, callback, priority]` arrays. Default: `[]` (empty array). | `array $additional_removals` |

## Usage Examples

### Remove Additional wp_head Items

```php
add_filter( 'wp_headless_head_cleanup_removals', function ( array $removals ): array {
    // Remove the canonical link tag.
    $removals[] = [ 'wp_head', 'rel_canonical' ];

    // Remove a theme's custom meta tag registered at priority 5.
    $removals[] = [ 'wp_head', 'my_theme_meta_tag', 5 ];

    return $removals;
} );
```

Each entry in the returned array should be an indexed array with:
- `[0]` (string): The action hook name (typically `wp_head`).
- `[1]` (callable): The callback function to remove.
- `[2]` (int, optional): The priority at which the callback was registered. Defaults to 10 if omitted.

### Prevent a Specific Item from Being Removed

If you need to keep one of the default removals (for example, feed links for RSS functionality), you can re-add the action after the module initializes:

```php
add_action( 'wp_headless_modules_loaded', function (): void {
    // Re-add feed links that the Head Cleanup module removed.
    add_action( 'wp_head', 'feed_links', 2 );
    add_action( 'wp_head', 'feed_links_extra', 3 );
} );
```

## Disabling

Set the `WP_HEADLESS_DISABLE_HEAD_CLEANUP` constant or environment variable to `true`:

```php
// In wp-config.php
define( 'WP_HEADLESS_DISABLE_HEAD_CLEANUP', true );
```

Or via environment variable:

```bash
WP_HEADLESS_DISABLE_HEAD_CLEANUP=true
```

When disabled, WordPress outputs all standard `wp_head` tags as normal.
