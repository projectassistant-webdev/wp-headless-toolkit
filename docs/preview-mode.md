# Preview Mode

## Overview

The Preview Mode module rewrites WordPress preview links to point to the headless frontend application, enabling content editors to preview draft and pending posts directly on the decoupled frontend. It generates short-lived JWT tokens using HMAC-SHA256 for authenticated draft access, and provides a REST API endpoint for the frontend to verify those tokens.

The module uses a pure PHP JWT implementation with no external dependencies. It is enabled by default but gracefully falls back to the standard WordPress preview link when the required configuration (`HEADLESS_FRONTEND_URL` and `HEADLESS_PREVIEW_SECRET`) is not set.

## Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `HEADLESS_FRONTEND_URL` | Yes | *(none)* | The base URL of the headless frontend application (e.g., `https://my-nextjs-site.com`). Shared with other modules such as FrontendRedirect. |
| `HEADLESS_PREVIEW_SECRET` | Yes | *(none)* | A shared secret string used to sign and verify JWT tokens. Must match the secret configured in the frontend application. |
| `WP_HEADLESS_PREVIEW_TOKEN_EXPIRY` | No | `300` | Token expiry duration in seconds (default: 5 minutes). Minimum value is 1 second. |
| `WP_HEADLESS_DISABLE_PREVIEW_MODE` | No | `false` | Set to `true` to disable this module entirely |

## How It Works

### Preview Link Rewriting

The module hooks into the `preview_post_link` filter to intercept WordPress preview links. When a content editor clicks "Preview" in the WordPress admin, the module:

1. Checks that `HEADLESS_FRONTEND_URL` and `HEADLESS_PREVIEW_SECRET` are both configured.
2. If either is missing, returns the original WordPress preview link (graceful fallback).
3. Generates a JWT token containing the post ID, user ID, and expiry.
4. Constructs a preview URL pointing to the frontend.

**Preview URL format:**

```
{HEADLESS_FRONTEND_URL}/api/preview?secret={jwt_token}&id={post_id}
```

The preview path (`api/preview` by default) can be customized per post type via the `wp_headless_preview_url` filter.

### JWT Token Implementation

The module implements JWT (JSON Web Token) using HMAC-SHA256 in pure PHP, requiring no external libraries like `firebase/php-jwt`.

**Token structure:**

- **Header:** `{"alg": "HS256", "typ": "JWT"}`
- **Payload:**
  - `post_id` (int) -- the WordPress post ID being previewed
  - `user_id` (int) -- the ID of the logged-in WordPress user
  - `iat` (int) -- issued-at timestamp (Unix epoch)
  - `exp` (int) -- expiration timestamp (Unix epoch, defaults to `iat + 300`)
- **Signature:** HMAC-SHA256 of `{base64url_header}.{base64url_payload}` using the configured secret

All segments use URL-safe Base64 encoding (replacing `+` with `-`, `/` with `_`, and stripping trailing `=`).

### Token Verification

The module registers a public REST API endpoint for token verification:

**Endpoint:** `GET /wp-json/wp-headless-toolkit/v1/preview/verify?token={jwt_token}`

The endpoint is publicly accessible (`permission_callback` returns `true`) because the JWT itself serves as the authentication mechanism.

**Verification process:**

1. Validates that the token parameter is present (returns `400` with `missing_token` error if absent).
2. Splits the token into its three JWT segments.
3. Recomputes the signature using the configured secret and compares it to the provided signature using `hash_equals()` for timing-safe comparison.
4. Decodes the payload and checks that `exp` has not passed.
5. Returns a JSON response:

**Success (200):**
```json
{
    "valid": true,
    "post_id": 42
}
```

**Invalid token (401):**
```json
{
    "valid": false,
    "error": "invalid_token"
}
```

**Missing token (400):**
```json
{
    "valid": false,
    "error": "missing_token"
}
```

### Graceful Fallback

When `HEADLESS_FRONTEND_URL` or `HEADLESS_PREVIEW_SECRET` is not configured, the module returns the original WordPress preview link without modification. This means the module can be left enabled during initial setup without breaking the preview experience.

## Filters

| Filter | Description | Parameters |
|--------|-------------|------------|
| `wp_headless_preview_url` | Customize the preview path per post type. For example, route page previews to a different frontend path than post previews. | `string $path` -- default: `'api/preview'`, `\WP_Post $post` -- the post being previewed, `string $post_type` -- the post type slug |
| `wp_headless_module_enabled` | Control whether the module is enabled (shared across all modules). | `bool $enabled`, `string $slug` (slug: `preview_mode`) |

## Usage Examples

### Basic setup

```env
HEADLESS_FRONTEND_URL=https://my-nextjs-site.com
HEADLESS_PREVIEW_SECRET=my-super-secret-key-change-this
```

### Customize the token expiry to 15 minutes

```php
define( 'WP_HEADLESS_PREVIEW_TOKEN_EXPIRY', 900 );
```

### Custom preview paths per post type

```php
add_filter( 'wp_headless_preview_url', function ( string $path, \WP_Post $post, string $post_type ): string {
    return match ( $post_type ) {
        'page'    => 'api/preview/pages',
        'product' => 'api/preview/products',
        default   => $path,
    };
}, 10, 3 );
```

This would produce preview URLs like:
- Posts: `https://my-site.com/api/preview?secret=...&id=42`
- Pages: `https://my-site.com/api/preview/pages?secret=...&id=10`
- Products: `https://my-site.com/api/preview/products?secret=...&id=7`

### Frontend token verification (Next.js example)

```javascript
// pages/api/preview.js (Next.js API route)
export default async function handler(req, res) {
    const { secret, id } = req.query;

    const response = await fetch(
        `${process.env.WORDPRESS_URL}/wp-json/wp-headless-toolkit/v1/preview/verify?token=${secret}`
    );
    const data = await response.json();

    if (!data.valid) {
        return res.status(401).json({ message: 'Invalid token' });
    }

    res.setPreviewData({ postId: data.post_id });
    res.redirect(`/posts/${id}`);
}
```

## Disabling

Disable the module by defining the constant or setting the environment variable:

```php
// wp-config.php
define( 'WP_HEADLESS_DISABLE_PREVIEW_MODE', true );
```

```env
# .env
WP_HEADLESS_DISABLE_PREVIEW_MODE=true
```
