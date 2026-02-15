# Cloudflare Purge

## Overview

The Cloudflare Purge module clears the Cloudflare CDN cache and the WordPress GraphQL object cache whenever content changes in WordPress. It works in tandem with the ISR Revalidation module, running at hook priority 20 (after Revalidation at priority 10) so that Next.js ISR revalidation fires first, and then the CDN layer is invalidated.

This module depends on the Breeze plugin's Cloudflare integration. It delegates the actual Cloudflare API call to `Breeze_CloudFlare_Helper::reset_all_cache()`, which performs a full-domain cache purge using the Cloudflare credentials configured in Breeze.

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `WP_HEADLESS_DISABLE_CLOUDFLARE_PURGE` | No | `false` | Set to `true` to disable this module |

### Prerequisites

The module has two runtime prerequisites beyond the standard disable toggle. If either is not met, the module silently disables itself:

1. **Breeze plugin**: The `Breeze_CloudFlare_Helper` class must exist (Breeze plugin installed and active).
2. **Cloudflare enabled in Breeze**: `Breeze_CloudFlare_Helper::is_cloudflare_enabled()` must return `true` (Cloudflare CDN integration configured within Breeze, with `CDN_SITE_ID` and `CDN_SITE_TOKEN` defined).

## How It Works

### WordPress Hooks

The module registers on the same content change hooks as the Revalidation module, but at priority 20:

| Hook | Handler | Priority |
|------|---------|----------|
| `save_post` | `handle_post_change` | 20 |
| `delete_post` | `handle_post_delete` | 20 |
| `wp_trash_post` | `handle_post_delete` | 20 |
| `edited_term` | `handle_term_change` | 20 |
| `delete_term` | `handle_term_change` | 20 |
| `wp_update_nav_menu` | `handle_menu_change` | 20 |

### Post Change Guards

The `handle_post_change` handler applies the same guards as the Revalidation module:

1. Skips autosaves (`DOING_AUTOSAVE`).
2. Skips post revisions.
3. Only purges for posts with `publish` status.
4. Respects the `wp_headless_revalidation_post_types` filter (default: `['post', 'page']`).

Delete, trash, term, and menu handlers do not apply post type or status guards.

### Debouncing

The module uses a static boolean flag (`$purge_requested`) to ensure only a single Cloudflare purge occurs per PHP request, regardless of how many hooks fire. This prevents redundant API calls during bulk operations such as bulk post updates or quick-edit saves.

### Cache Purge Steps

When a purge is triggered, the module performs two operations:

1. **Cloudflare CDN purge**: Calls `Breeze_CloudFlare_Helper::reset_all_cache()` to issue a full-domain Cloudflare cache purge.
2. **GraphQL object cache flush**: Calls `wp_cache_flush_group('wp_headless_toolkit_graphql')` to clear the GraphQL object cache group (requires WordPress 6.1+ for group flush support).

After both operations complete, the `wp_headless_cloudflare_purged` action fires.

### Execution Order

The priority 20 registration ensures the following sequence on each content change:

1. ISR Revalidation module fires at priority 10 -- Next.js regenerates the affected pages.
2. Cloudflare Purge module fires at priority 20 -- Cloudflare serves fresh responses to users.

## Filters & Actions

### Filters

The Cloudflare Purge module reuses the Revalidation module's post type filter for consistency:

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_revalidation_post_types` | Controls which post types trigger a Cloudflare purge (shared with the Revalidation module). Default: `['post', 'page']`. | `string[] $post_types` |

### Actions

| Action | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_cloudflare_purged` | Fires after Cloudflare CDN cache and GraphQL object cache have been purged. No parameters. | -- |

## Usage Examples

### React to Cloudflare Purge Events

```php
add_action( 'wp_headless_cloudflare_purged', function (): void {
    error_log( 'Cloudflare cache and GraphQL object cache have been purged.' );
} );
```

### Add Custom Post Types to Purge Triggers

Since the module shares the `wp_headless_revalidation_post_types` filter with the Revalidation module, adding a post type to that filter automatically includes it in both ISR revalidation and Cloudflare purge triggers:

```php
add_filter( 'wp_headless_revalidation_post_types', function ( array $post_types ): array {
    $post_types[] = 'product';
    return $post_types;
} );
```

## Disabling

Set the `WP_HEADLESS_DISABLE_CLOUDFLARE_PURGE` constant or environment variable to `true`:

```php
// In wp-config.php
define( 'WP_HEADLESS_DISABLE_CLOUDFLARE_PURGE', true );
```

Or via environment variable:

```bash
WP_HEADLESS_DISABLE_CLOUDFLARE_PURGE=true
```

The module also disables itself automatically if the Breeze plugin is not active or if Cloudflare is not enabled in Breeze.
