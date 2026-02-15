# Next.js Caching & ISR Revalidation Guide

Reference guide for configuring Next.js frontends that consume WordPress via WPGraphQL and use wp-headless-toolkit for on-demand ISR revalidation.

---

## Architecture Overview

```
WordPress (Headless CMS)
    |
    +-- Content changes (publish, update, delete)
    |       |
    |       v
    |   wp-headless-toolkit (Revalidation Module)
    |       |
    |       v  POST /api/revalidate/ { secret, tags }
    |
Next.js Frontend (Vercel)
    |
    +-- Route Handler: /api/revalidate/route.ts
    |       |
    |       +-- revalidateTag('wordpress', { expire: 0 })  <- Purges Data Cache
    |       +-- revalidatePath('/', 'layout')               <- Purges Route Cache
    |
    +-- Data Cache (Vercel)
    |       |
    |       +-- Stores fetch() responses tagged with 'wordpress'
    |           Cache key = fetch URL + options
    |           Populated when cache: 'force-cache' is set on fetch()
    |
    +-- Full Route Cache (Vercel CDN edge)
    |       |
    |       +-- Stores pre-rendered HTML for static/ISR pages
    |           Purged by revalidatePath()
    |
    +-- GraphQL Client (fetchGraphQL)
            |
            +-- GET https://wp.example.com/graphql?query=...&variables=...
                With: { cache: 'force-cache', next: { revalidate: 3600, tags: ['wordpress'] } }
```

### How a page request flows

1. User requests `/blog/`
2. If Full Route Cache has a valid entry -> serve cached HTML (static pages only)
3. If dynamic page (uses `searchParams`, `cookies`, etc.) -> render fresh on every request
4. During rendering, `fetchGraphQL()` runs GraphQL queries
5. Each `fetch()` checks the Data Cache for a matching entry (by URL + options)
6. Cache HIT -> return cached response. Cache MISS -> fetch from WordPress, store in Data Cache
7. Rendered HTML returned to user

### How revalidation flows

1. Author publishes/updates a post in WordPress
2. wp-headless-toolkit fires `POST /api/revalidate/` with `{ secret, tags }`
3. Route Handler calls `revalidateTag('wordpress', { expire: 0 })` -> Data Cache entries tagged `'wordpress'` are expired immediately
4. Route Handler calls `revalidatePath('/', 'layout')` -> Full Route Cache is purged for all pages
5. Next page request triggers fresh `fetch()` calls -> fresh data from WordPress

---

## Next.js Frontend Setup

### 1. GraphQL Client

The central GraphQL client must configure caching defaults for all WordPress queries.

```typescript
export const DEFAULT_CACHE_CONFIG: CacheConfig = {
  cache: 'force-cache' as const,
  next: { revalidate: 3600, tags: ['wordpress'] },
};
```

**What each option does:**

| Option | Purpose |
|--------|---------|
| `cache: 'force-cache'` | Tells Next.js to store this fetch response in the Data Cache |
| `next.revalidate: 3600` | Time-based revalidation fallback -- re-fetch after 1 hour even without a webhook |
| `next.tags: ['wordpress']` | Tags this cache entry so `revalidateTag('wordpress')` can purge it |

**CRITICAL (Next.js 16+):** The `cache: 'force-cache'` option is **required**. Next.js 16 changed the default fetch behavior from `force-cache` to no caching. Without this, the Data Cache is never populated and `revalidateTag` has nothing to invalidate.

### 2. Revalidation Route Handler (`app/api/revalidate/route.ts`)

```typescript
import { revalidatePath, revalidateTag } from 'next/cache';
import { NextRequest, NextResponse } from 'next/server';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const secret = body?.secret;
    const expectedSecret = process.env.REVALIDATION_SECRET;

    if (!expectedSecret || !secret || secret !== expectedSecret) {
      return NextResponse.json({ message: 'Invalid token' }, { status: 401 });
    }

    // Purge Data Cache -- immediate expiration for webhook use case
    revalidateTag('wordpress', { expire: 0 });

    // Purge Full Route Cache (ISR / CDN edge cache)
    revalidatePath('/', 'layout');

    return NextResponse.json({ revalidated: true, now: Date.now() }, { status: 200 });
  } catch {
    return NextResponse.json({ message: 'Error revalidating' }, { status: 500 });
  }
}
```

### 3. Environment Variables (Frontend)

```bash
# .env.production / .env.staging
REVALIDATION_SECRET=your-shared-secret-here
```

This must match the `NEXTJS_REVALIDATION_SECRET` configured in WordPress.

---

## revalidateTag Profiles (Next.js 16+)

Next.js 16 introduced a second argument to `revalidateTag` that controls expiration behavior.

| Profile | Syntax | Behavior | Use When |
|---------|--------|----------|----------|
| **Immediate** | `revalidateTag('tag', { expire: 0 })` | Cache entry expires immediately. Next request is a blocking cache miss -- fetches fresh data. | **Webhooks from external systems** (WordPress, CMS, etc.) where you need the next visit to show fresh content. |
| **SWR (max)** | `revalidateTag('tag', 'max')` | Cache entry marked stale. Next request serves stale data while fetching fresh in background. Request *after that* serves fresh data. | Server Actions where a slight delay is acceptable (e.g., user-initiated actions within the Next.js app). |
| **No argument** | `revalidateTag('tag')` | Same as `{ expire: 0 }` but **deprecated** in Next.js 16. Will be removed in a future version. | Do not use. Migrate to `{ expire: 0 }` or `'max'`. |

### Which profile for wp-headless-toolkit webhooks?

**Always use `{ expire: 0 }`.**

The plugin sends revalidation requests from WordPress (an external system). The `'max'` profile would cause the first visitor after a content update to still see old content -- defeating the purpose of on-demand revalidation.

### updateTag vs revalidateTag

Next.js 16 also introduced `updateTag` for Server Actions that need read-your-own-writes consistency. This is **not applicable** to webhook-based revalidation since webhooks use Route Handlers, not Server Actions.

---

## Next.js Version-Specific Notes

### Next.js 16 Breaking Changes (Critical)

1. **Default fetch cache changed**: `fetch()` no longer caches by default. You **must** add `cache: 'force-cache'` to any fetch call you want cached and revalidatable.

2. **revalidateTag requires second argument**: The single-argument form `revalidateTag('tag')` is deprecated. Use `revalidateTag('tag', { expire: 0 })` for webhooks.

3. **dynamicIO flag**: Next.js 16 introduced `dynamicIO` for the `use cache` directive. This is a separate system from `fetch()` caching and is **not required** for tag-based revalidation of fetch requests.

### Next.js 15 and Earlier

- `fetch()` defaults to `force-cache` -- Data Cache is populated automatically
- `revalidateTag('tag')` works with a single argument (immediate expiration)
- No profile argument needed

### Migration Checklist (Next.js 15 -> 16)

- [ ] Add `cache: 'force-cache'` to all GraphQL/WordPress fetch calls
- [ ] Update `revalidateTag('tag')` calls to `revalidateTag('tag', { expire: 0 })` in Route Handlers
- [ ] Verify Data Cache is being populated (check `x-vercel-cache` headers)
- [ ] Test revalidation end-to-end after migration

---

## Vercel Cache Layers

Understanding the two cache layers on Vercel is essential for debugging.

### Data Cache

- **What**: Stores individual `fetch()` responses
- **Scope**: Global across all Vercel edge regions (eventually consistent)
- **Cache key**: URL + fetch options
- **Populated when**: `cache: 'force-cache'` is set on the fetch call
- **Invalidated by**: `revalidateTag()` (tag-based) or `revalidate` timer (time-based)
- **Persists across**: Deployments (survives redeployments)

### Full Route Cache (Edge/CDN)

- **What**: Stores pre-rendered HTML for entire pages
- **Scope**: Per Vercel edge region
- **Populated when**: Page is statically rendered or ISR-rendered
- **Not populated when**: Page is dynamic (uses `searchParams`, `cookies`, `headers`, etc.)
- **Invalidated by**: `revalidatePath()` or new deployment

### Dynamic vs Static Pages

| Page Type | Full Route Cache | Data Cache | Behavior |
|-----------|-----------------|------------|----------|
| Static | YES | YES | Fastest -- serves cached HTML from edge |
| ISR | YES (with TTL) | YES | Re-renders periodically, serves cached between |
| Dynamic | NO | YES | Renders fresh HTML every request, but individual fetches can still be cached |

**Important**: A dynamic page (like `/blog/` with `searchParams`) still benefits from the Data Cache. The page re-renders on every request, but the GraphQL fetch calls inside it return cached data from the Data Cache.

---

## Troubleshooting

### Revalidation webhook returns 200 but content doesn't update

1. **Check the revalidateTag profile**: Must be `{ expire: 0 }` for webhooks. The `'max'` profile serves stale data on the first visit.

2. **Check `cache: 'force-cache'` is set**: Without this in Next.js 16, the Data Cache is never populated. `revalidateTag` has nothing to invalidate.

3. **Check tags match**: The tag in `revalidateTag('wordpress', ...)` must match the tag in `fetch(url, { next: { tags: ['wordpress'] } })`.

4. **Wait for propagation**: Vercel's Data Cache is eventually consistent across edge regions. Wait 5-10 seconds after revalidation before testing.

5. **Check if page is dynamic**: If the page uses `searchParams`, it's dynamic -- the Full Route Cache won't apply. But Data Cache still works for individual fetch calls.

### Content updates appear on some pages but not others

- Different pages may use different fetch functions with different cache configs
- Check that ALL GraphQL fetch calls include `tags: ['wordpress']` in their cache config
- Ensure no fetch call overrides the default cache config with `cache: 'no-store'`

### Revalidation webhook returns 401

- The `REVALIDATION_SECRET` on the frontend doesn't match `NEXTJS_REVALIDATION_SECRET` in WordPress
- Check both the frontend env file and WordPress env config

### Revalidation webhook returns 500

- Check Vercel function logs for the `/api/revalidate` route
- Common cause: `revalidateTag` or `revalidatePath` throwing due to incorrect arguments

### How to manually test revalidation

```bash
# 1. Trigger revalidation (replace with your actual frontend URL and secret)
curl -s -X POST 'https://your-frontend.com/api/revalidate/' \
  -H 'Content-Type: application/json' \
  -d '{"secret": "your-revalidation-secret"}'

# Expected: {"revalidated":true,"now":1234567890}

# 2. Wait a few seconds for cache propagation
sleep 5

# 3. Load the page and check for updated content
curl -s -I 'https://your-frontend.com/blog/' | grep -i 'x-vercel-cache'
```

### How to verify Data Cache is working

Check the `x-vercel-cache` response header:

| Value | Meaning |
|-------|---------|
| `HIT` | Served from Full Route Cache (static/ISR page) |
| `MISS` | Not in Full Route Cache (dynamic page or first request) |
| `STALE` | Served stale while revalidating in background |

Note: This header reflects the Full Route Cache, not the Data Cache. The Data Cache is internal to Vercel's serverless functions and not directly observable via HTTP headers.

---

## WordPress Side Configuration

### wp-headless-toolkit Environment Variables

```bash
# Required for revalidation
NEXTJS_REVALIDATION_URL=https://your-frontend.com/api/revalidate
NEXTJS_REVALIDATION_SECRET=your-shared-secret-here
```

### What the plugin sends

When content changes, the plugin sends:

```json
POST https://your-frontend.com/api/revalidate/
Content-Type: application/json

{
  "secret": "your-shared-secret-here",
  "tags": ["post", "post-123", "category-web-development"]
}
```

The recommended Next.js route handler uses the `secret` field for authentication. The `tags` field from the plugin is available for future granular per-tag revalidation but is not currently used -- the route handler invalidates all content tagged `'wordpress'` on any change.

### Supported WordPress triggers

The plugin fires revalidation on:

| WordPress Action | Trigger |
|-----------------|---------|
| `save_post` | Post/page published or updated |
| `delete_post` | Post/page deleted |
| `wp_trash_post` | Post/page trashed |
| `edited_term` | Category/tag edited |
| `delete_term` | Category/tag deleted |
| `wp_update_nav_menu` | Navigation menu updated |

---

## Quick Reference

### Required fetch configuration (Next.js 16+)

```typescript
fetch(url, {
  cache: 'force-cache',                    // REQUIRED: enables Data Cache
  next: { revalidate: 3600, tags: ['wordpress'] }  // Tags for on-demand invalidation
})
```

### Required route handler (webhook)

```typescript
revalidateTag('wordpress', { expire: 0 });  // Immediate expiration for webhooks
revalidatePath('/', 'layout');               // Purge Full Route Cache
```

### Required environment variables

| Location | Variable | Purpose |
|----------|----------|---------|
| WordPress | `NEXTJS_REVALIDATION_URL` | Frontend webhook endpoint |
| WordPress | `NEXTJS_REVALIDATION_SECRET` | Shared authentication secret |
| Next.js | `REVALIDATION_SECRET` | Same shared secret (must match) |
