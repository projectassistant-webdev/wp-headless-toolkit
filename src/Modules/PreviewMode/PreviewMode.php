<?php
/**
 * Preview Mode Module
 *
 * Rewrites WordPress preview links to point to the Next.js preview route.
 * Generates JWT tokens for authenticated draft access.
 *
 * References the HeadstartWP approach for preview URL rewriting and
 * HWP Previews for per-post-type preview URL configuration.
 *
 * Configuration:
 *   HEADLESS_FRONTEND_URL            - Frontend application URL (required, shared with FrontendRedirect)
 *   HEADLESS_PREVIEW_SECRET          - Shared secret for JWT signing (required)
 *   WP_HEADLESS_PREVIEW_TOKEN_EXPIRY - Token expiry in seconds (default: 300)
 *
 * Preview URL format:
 *   {HEADLESS_FRONTEND_URL}/api/preview?secret={jwt_token}&id={post_id}
 *
 * JWT token payload:
 *   { "post_id": int, "user_id": int, "iat": timestamp, "exp": timestamp }
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\PreviewMode
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\PreviewMode;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Helpers\Config;

class PreviewMode implements ModuleInterface {

	/**
	 * Default token expiry in seconds (5 minutes).
	 *
	 * @var int
	 */
	private const DEFAULT_TOKEN_EXPIRY = 300;

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'wp-headless-toolkit/v1';

	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'preview_mode';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'Preview Mode';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Preview Mode is enabled by default. It only deactivates when:
	 * 1. Explicitly disabled via WP_HEADLESS_DISABLE_PREVIEW_MODE, OR
	 * 2. Disabled via the wp_headless_module_enabled filter.
	 */
	public static function is_enabled(): bool {
		return wp_headless_is_module_enabled( self::get_slug() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init(): void {
		// Rewrite preview post links to point to the headless frontend.
		add_filter( 'preview_post_link', [ $this, 'rewrite_preview_link' ], 10, 2 );

		// Register REST API endpoint for token verification.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Rewrite the preview post link to point to the headless frontend.
	 *
	 * @param string   $preview_link The original preview link.
	 * @param \WP_Post $post         The post object.
	 *
	 * @return string The rewritten preview link or the original if configuration is missing.
	 */
	public function rewrite_preview_link( string $preview_link, \WP_Post $post ): string {
		$frontend_url = $this->get_frontend_url();
		$secret       = $this->get_secret();

		// Graceful fallback: return original link if configuration is missing.
		if ( empty( $frontend_url ) || empty( $secret ) ) {
			return $preview_link;
		}

		$token = $this->generate_jwt( [
			'post_id' => $post->ID,
			'user_id' => get_current_user_id(),
			'iat'     => time(),
			'exp'     => time() + $this->get_token_expiry(),
		] );

		$preview_path = $this->get_preview_path( $post );

		$url = rtrim( $frontend_url, '/' ) . '/' . ltrim( $preview_path, '/' );

		return add_query_arg(
			[
				'secret' => $token,
				'id'     => $post->ID,
			],
			$url
		);
	}

	/**
	 * Get the preview path for a given post.
	 *
	 * Applies the wp_headless_preview_url filter to allow per-post-type
	 * preview URL customization.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return string The preview path (e.g. "api/preview" or "api/preview/pages").
	 */
	public function get_preview_path( \WP_Post $post ): string {
		$default_path = 'api/preview';

		/**
		 * Filter the preview URL path per post type.
		 *
		 * @param string   $path      The default preview path.
		 * @param \WP_Post $post      The post being previewed.
		 * @param string   $post_type The post type slug.
		 */
		return (string) apply_filters(
			'wp_headless_preview_url',
			$default_path,
			$post,
			$post->post_type
		);
	}

	/**
	 * Register REST API routes for preview token verification.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/preview/verify',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_verify_token' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'token' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * REST API callback to verify a preview token.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response The response with token validity.
	 */
	public function rest_verify_token( \WP_REST_Request $request ): \WP_REST_Response {
		$token = $request->get_param( 'token' );

		if ( empty( $token ) ) {
			return new \WP_REST_Response(
				[ 'valid' => false, 'error' => 'missing_token' ],
				400
			);
		}

		$payload = $this->verify_jwt( $token );

		if ( null === $payload ) {
			return new \WP_REST_Response(
				[ 'valid' => false, 'error' => 'invalid_token' ],
				401
			);
		}

		return new \WP_REST_Response(
			[
				'valid'   => true,
				'post_id' => $payload['post_id'],
			],
			200
		);
	}

	/**
	 * Generate a JWT token.
	 *
	 * Pure PHP HMAC-SHA256 implementation (no external library required).
	 *
	 * @param array $payload The token payload.
	 *
	 * @return string The JWT token string.
	 */
	public function generate_jwt( array $payload ): string {
		$header          = $this->base64url_encode( wp_json_encode( [ 'alg' => 'HS256', 'typ' => 'JWT' ] ) );
		$payload_encoded = $this->base64url_encode( wp_json_encode( $payload ) );
		$signature       = $this->base64url_encode(
			hash_hmac( 'sha256', "{$header}.{$payload_encoded}", $this->get_secret(), true )
		);

		return "{$header}.{$payload_encoded}.{$signature}";
	}

	/**
	 * Verify a JWT token.
	 *
	 * Validates the signature and checks expiry.
	 *
	 * @param string $token The JWT token string.
	 *
	 * @return array|null The decoded payload or null if invalid.
	 */
	public function verify_jwt( string $token ): ?array {
		$parts = explode( '.', $token );

		if ( 3 !== count( $parts ) ) {
			return null;
		}

		[ $header, $payload, $signature ] = $parts;

		$expected_signature = $this->base64url_encode(
			hash_hmac( 'sha256', "{$header}.{$payload}", $this->get_secret(), true )
		);

		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return null;
		}

		$data = json_decode( $this->base64url_decode( $payload ), true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( ! isset( $data['exp'] ) || $data['exp'] < time() ) {
			return null;
		}

		return $data;
	}

	/**
	 * Base64url encode a string.
	 *
	 * @param string $data The data to encode.
	 *
	 * @return string The base64url encoded string.
	 */
	public function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url decode a string.
	 *
	 * @param string $data The base64url encoded string.
	 *
	 * @return string The decoded data.
	 */
	public function base64url_decode( string $data ): string {
		return base64_decode( strtr( $data, '-_', '+/' ) );
	}

	/**
	 * Get the frontend URL from configuration.
	 *
	 * @return string The frontend URL or empty string if not configured.
	 */
	public function get_frontend_url(): string {
		return (string) Config::get( 'HEADLESS_FRONTEND_URL', '' );
	}

	/**
	 * Get the preview secret from configuration.
	 *
	 * @return string The preview secret or empty string if not configured.
	 */
	public function get_secret(): string {
		return (string) Config::get( 'HEADLESS_PREVIEW_SECRET', '' );
	}

	/**
	 * Get the token expiry duration in seconds.
	 *
	 * @return int The expiry duration.
	 */
	public function get_token_expiry(): int {
		$expiry = Config::get( 'WP_HEADLESS_PREVIEW_TOKEN_EXPIRY', self::DEFAULT_TOKEN_EXPIRY );

		return max( 1, (int) $expiry );
	}
}
