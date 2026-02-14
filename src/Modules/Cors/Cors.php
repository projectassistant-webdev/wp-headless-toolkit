<?php
declare(strict_types=1);
/**
 * CORS Module
 *
 * Sets CORS headers on GraphQL and REST API responses for browser-side
 * requests. Disabled by default (most SSR/ISR projects don't need it).
 *
 * Configuration:
 *   HEADLESS_CORS_ORIGINS  - Comma-separated allowed origins
 *                            (e.g. "https://example.com,https://staging.example.com")
 *
 * The module only activates when HEADLESS_CORS_ORIGINS is configured.
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\Cors
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\Cors;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Helpers\Config;

class Cors implements ModuleInterface {

	/**
	 * Default allowed HTTP methods for CORS.
	 *
	 * @var string
	 */
	private const DEFAULT_METHODS = 'GET, POST, OPTIONS';

	/**
	 * Default allowed HTTP headers for CORS.
	 *
	 * @var string
	 */
	private const DEFAULT_HEADERS = 'Content-Type, Authorization';

	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'cors';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'CORS';
	}

	/**
	 * {@inheritDoc}
	 *
	 * CORS is disabled by default. It only activates when:
	 * 1. Not explicitly disabled via WP_HEADLESS_DISABLE_CORS, AND
	 * 2. HEADLESS_CORS_ORIGINS is configured with at least one origin.
	 */
	public static function is_enabled(): bool {
		if ( ! wp_headless_is_module_enabled( self::get_slug() ) ) {
			return false;
		}

		// Require explicit opt-in via HEADLESS_CORS_ORIGINS.
		$origins = Config::get_list( 'HEADLESS_CORS_ORIGINS' );

		return ! empty( $origins );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init(): void {
		// Handle preflight OPTIONS requests early.
		add_action( 'init', [ $this, 'handle_preflight' ], 1 );

		// Apply CORS headers to REST API responses.
		add_filter( 'rest_pre_serve_request', [ $this, 'add_rest_cors_headers' ], 10, 1 );

		// Apply CORS headers to GraphQL responses (if WPGraphQL is active).
		if ( class_exists( 'WPGraphQL' ) ) {
			add_filter( 'graphql_response_headers_to_send', [ $this, 'add_graphql_cors_headers' ], 10, 1 );
		}
	}

	/**
	 * Handle preflight OPTIONS requests.
	 *
	 * Sends CORS headers and exits for OPTIONS requests to the REST API
	 * or GraphQL endpoint.
	 */
	public function handle_preflight(): void {
		if ( 'OPTIONS' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		if ( ! $this->is_api_request() ) {
			return;
		}

		$origin = $this->get_request_origin();
		if ( ! $this->is_origin_allowed( $origin ) ) {
			return;
		}

		$this->send_cors_headers( $origin );
		header( 'Access-Control-Max-Age: 86400' );
		header( 'Content-Length: 0' );
		header( 'Content-Type: text/plain' );
		status_header( 204 );

		/**
		 * Fires after CORS preflight headers have been sent.
		 *
		 * @param string $origin The allowed origin.
		 */
		do_action( 'wp_headless_cors_preflight_sent', $origin );

		exit;
	}

	/**
	 * Add CORS headers to REST API responses.
	 *
	 * @param bool $served Whether the request has already been served.
	 *
	 * @return bool The original $served value (unmodified).
	 */
	public function add_rest_cors_headers( bool $served ): bool {
		$origin = $this->get_request_origin();

		if ( $this->is_origin_allowed( $origin ) ) {
			$this->send_cors_headers( $origin );
		}

		return $served;
	}

	/**
	 * Add CORS headers to GraphQL responses.
	 *
	 * @param array $headers The response headers.
	 *
	 * @return array Modified headers with CORS headers added.
	 */
	public function add_graphql_cors_headers( array $headers ): array {
		$origin = $this->get_request_origin();

		if ( $this->is_origin_allowed( $origin ) ) {
			$headers['Access-Control-Allow-Origin']      = $origin;
			$headers['Access-Control-Allow-Methods']     = $this->get_allowed_methods();
			$headers['Access-Control-Allow-Headers']     = $this->get_allowed_headers();
			$headers['Access-Control-Allow-Credentials'] = 'true';
		}

		/**
		 * Filter the GraphQL CORS headers.
		 *
		 * @param array  $headers The response headers including CORS headers.
		 * @param string $origin  The request origin.
		 */
		return apply_filters( 'wp_headless_cors_graphql_headers', $headers, $origin );
	}

	/**
	 * Get the request Origin header.
	 *
	 * @return string The origin or empty string if not present.
	 */
	public function get_request_origin(): string {
		$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

		if ( '' === $origin ) {
			return '';
		}

		return esc_url_raw( wp_unslash( $origin ) );
	}

	/**
	 * Check if an origin is in the allowed origins list.
	 *
	 * @param string $origin The origin to check.
	 *
	 * @return bool True if the origin is allowed.
	 */
	public function is_origin_allowed( string $origin ): bool {
		if ( empty( $origin ) ) {
			return false;
		}

		$allowed_origins = $this->get_allowed_origins();

		/**
		 * Filter whether an origin is allowed for CORS.
		 *
		 * @param bool     $allowed Whether the origin is allowed.
		 * @param string   $origin  The request origin.
		 * @param string[] $allowed_origins The configured allowed origins.
		 */
		return (bool) apply_filters(
			'wp_headless_cors_origin_allowed',
			in_array( $origin, $allowed_origins, true ),
			$origin,
			$allowed_origins
		);
	}

	/**
	 * Get the configured allowed origins.
	 *
	 * @return string[]
	 */
	public function get_allowed_origins(): array {
		return Config::get_list( 'HEADLESS_CORS_ORIGINS' );
	}

	/**
	 * Get the allowed HTTP methods.
	 *
	 * @return string
	 */
	public function get_allowed_methods(): string {
		/**
		 * Filter the allowed CORS methods.
		 *
		 * @param string $methods The allowed methods string.
		 */
		return (string) apply_filters( 'wp_headless_cors_allowed_methods', self::DEFAULT_METHODS );
	}

	/**
	 * Get the allowed HTTP headers.
	 *
	 * @return string
	 */
	public function get_allowed_headers(): string {
		/**
		 * Filter the allowed CORS headers.
		 *
		 * @param string $headers The allowed headers string.
		 */
		return (string) apply_filters( 'wp_headless_cors_allowed_headers', self::DEFAULT_HEADERS );
	}

	/**
	 * Send CORS headers via PHP header() function.
	 *
	 * @param string $origin The allowed origin.
	 */
	private function send_cors_headers( string $origin ): void {
		header( "Access-Control-Allow-Origin: {$origin}" );
		header( 'Access-Control-Allow-Methods: ' . $this->get_allowed_methods() );
		header( 'Access-Control-Allow-Headers: ' . $this->get_allowed_headers() );
		header( 'Access-Control-Allow-Credentials: true' );
	}

	/**
	 * Check if the current request is to an API endpoint.
	 *
	 * Checks for REST API and GraphQL endpoints.
	 *
	 * @return bool
	 */
	private function is_api_request(): bool {
		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

		// Check for REST API.
		$rest_prefix = rest_get_url_prefix();
		if ( false !== strpos( $request_uri, "/{$rest_prefix}/" ) ) {
			return true;
		}

		// Check for GraphQL endpoint.
		if ( false !== strpos( $request_uri, '/graphql' ) ) {
			return true;
		}

		return false;
	}
}
