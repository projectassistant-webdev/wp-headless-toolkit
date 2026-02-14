<?php
declare(strict_types=1);
/**
 * Security Headers Module
 *
 * Adds security headers to all WordPress responses: X-Content-Type-Options,
 * X-Frame-Options, Strict-Transport-Security, Referrer-Policy, and
 * Permissions-Policy. Disabled by default (many deployments use Cloudflare
 * or similar CDN that handles security headers).
 *
 * Enable via constant or environment variable:
 *   WP_HEADLESS_ENABLE_SECURITY_HEADERS=true
 *
 * Override individual headers via constants:
 *   WP_HEADLESS_X_CONTENT_TYPE_OPTIONS  - Default: "nosniff"
 *   WP_HEADLESS_X_FRAME_OPTIONS         - Default: "DENY"
 *   WP_HEADLESS_HSTS                    - Default: "max-age=31536000; includeSubDomains"
 *   WP_HEADLESS_REFERRER_POLICY         - Default: "strict-origin-when-cross-origin"
 *   WP_HEADLESS_PERMISSIONS_POLICY      - Default: "camera=(), microphone=(), geolocation=()"
 *
 * Set any header constant to an empty string to skip that header.
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\SecurityHeaders
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\SecurityHeaders;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Helpers\Config;

class SecurityHeaders implements ModuleInterface {

	/**
	 * Default security headers.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULTS = [
		'X-Content-Type-Options'    => 'nosniff',
		'X-Frame-Options'           => 'DENY',
		'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
		'Referrer-Policy'           => 'strict-origin-when-cross-origin',
		'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=()',
	];

	/**
	 * Map of header names to configuration constants.
	 *
	 * @var array<string, string>
	 */
	private const CONFIG_KEYS = [
		'X-Content-Type-Options'    => 'WP_HEADLESS_X_CONTENT_TYPE_OPTIONS',
		'X-Frame-Options'           => 'WP_HEADLESS_X_FRAME_OPTIONS',
		'Strict-Transport-Security' => 'WP_HEADLESS_HSTS',
		'Referrer-Policy'           => 'WP_HEADLESS_REFERRER_POLICY',
		'Permissions-Policy'        => 'WP_HEADLESS_PERMISSIONS_POLICY',
	];

	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'security_headers';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'Security Headers';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Security Headers is disabled by default. It only activates when:
	 * 1. Not explicitly disabled via WP_HEADLESS_DISABLE_SECURITY_HEADERS, AND
	 * 2. Explicitly enabled via WP_HEADLESS_ENABLE_SECURITY_HEADERS.
	 */
	public static function is_enabled(): bool {
		if ( ! wp_headless_is_module_enabled( self::get_slug() ) ) {
			return false;
		}

		// Require explicit opt-in via WP_HEADLESS_ENABLE_SECURITY_HEADERS.
		return Config::get_bool( 'WP_HEADLESS_ENABLE_SECURITY_HEADERS', false );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init(): void {
		// Apply security headers to standard WordPress responses.
		add_filter( 'wp_headers', [ $this, 'add_security_headers' ], 10, 1 );

		// Apply security headers to REST API responses.
		add_filter( 'rest_post_dispatch', [ $this, 'add_rest_security_headers' ], 999, 1 );
	}

	/**
	 * Add security headers to standard WordPress responses.
	 *
	 * @param array<string, string> $headers The WordPress response headers.
	 *
	 * @return array<string, string> Modified headers with security headers added.
	 */
	public function add_security_headers( array $headers ): array {
		$security_headers = $this->get_headers();

		foreach ( $security_headers as $name => $value ) {
			$headers[ $name ] = $value;
		}

		return $headers;
	}

	/**
	 * Add security headers to REST API responses.
	 *
	 * @param \WP_REST_Response $response The REST API response object.
	 *
	 * @return \WP_REST_Response Modified response with security headers added.
	 */
	public function add_rest_security_headers( \WP_REST_Response $response ): \WP_REST_Response {
		$security_headers = $this->get_headers();

		foreach ( $security_headers as $name => $value ) {
			$response->header( $name, $value );
		}

		return $response;
	}

	/**
	 * Get the resolved security headers.
	 *
	 * Merges defaults with any configured overrides, filters out
	 * headers set to an empty string, and applies the filter.
	 *
	 * @return array<string, string> Resolved security headers.
	 */
	public function get_headers(): array {
		$headers = [];

		foreach ( self::DEFAULTS as $header => $default ) {
			$config_key = self::CONFIG_KEYS[ $header ];

			// Check if a custom value is configured via env var or constant.
			// Config::has() treats empty strings as "not set", so also check
			// getenv() directly to support setting a header to empty string
			// (which means "skip this header").
			$env_value = getenv( $config_key );
			if ( Config::has( $config_key ) ) {
				$value = (string) Config::get( $config_key, $default );
			} elseif ( false !== $env_value ) {
				// Env var is set to empty string — user wants to skip this header.
				$value = '';
			} else {
				$value = $default;
			}

			// Skip headers explicitly set to empty string.
			if ( '' === $value ) {
				continue;
			}

			$headers[ $header ] = $value;
		}

		/**
		 * Filter the security headers before they are applied.
		 *
		 * @param array<string, string> $headers The resolved security headers.
		 */
		return (array) apply_filters( 'wp_headless_security_headers', $headers );
	}
}
