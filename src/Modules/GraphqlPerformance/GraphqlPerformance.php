<?php
declare(strict_types=1);
/**
 * GraphQL Performance Module
 *
 * Adds cache-control headers to GraphQL responses, integrates with object
 * cache (Redis/Memcached) when available, and provides query complexity
 * limits. Lightweight complement to WPGraphQL Smart Cache.
 *
 * Auto-detects WPGraphQL; gracefully no-ops when absent.
 *
 * Configuration:
 *   HEADLESS_GRAPHQL_CACHE_TTL        - Cache TTL in seconds (default: 600)
 *   HEADLESS_GRAPHQL_COMPLEXITY_LIMIT  - Max query complexity (default: 500)
 *   NEXTJS_REVALIDATION_SECRET        - Shared secret for cache-flush endpoint auth
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\GraphqlPerformance
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\GraphqlPerformance;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Helpers\Config;

class GraphqlPerformance implements ModuleInterface {

	/**
	 * Default cache TTL in seconds (10 minutes).
	 *
	 * @var int
	 */
	private const DEFAULT_CACHE_TTL = 600;

	/**
	 * Default query complexity limit.
	 *
	 * @var int
	 */
	private const DEFAULT_COMPLEXITY_LIMIT = 500;

	/**
	 * Object cache group name.
	 *
	 * @var string
	 */
	private const CACHE_GROUP = 'wp_headless_toolkit_graphql';

	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'graphql_performance';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'GraphQL Performance';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function is_enabled(): bool {
		return wp_headless_is_module_enabled( self::get_slug() );
	}

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'wp-headless-toolkit/v1';

	/**
	 * {@inheritDoc}
	 *
	 * Only registers hooks if WPGraphQL is active.
	 */
	public function init(): void {
		if ( ! $this->is_wpgraphql_active() ) {
			return;
		}

		add_filter( 'graphql_response_headers_to_send', [ $this, 'add_cache_headers' ], 10, 1 );
		add_filter( 'graphql_query_complexity_limit', [ $this, 'set_complexity_limit' ], 10, 1 );
		add_filter( 'graphql_request_results', [ $this, 'maybe_cache_response' ], 10, 5 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register REST API routes for cache management.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/flush-graphql-cache',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_flush_cache' ],
				'permission_callback' => [ $this, 'rest_flush_cache_permissions' ],
			]
		);
	}

	/**
	 * Permission callback for the flush-cache endpoint.
	 *
	 * Validates the shared secret from the request body.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function rest_flush_cache_permissions( \WP_REST_Request $request ) {
		$secret = Config::get( 'NEXTJS_REVALIDATION_SECRET' );

		if ( empty( $secret ) ) {
			return new \WP_Error(
				'rest_forbidden',
				'Cache flush endpoint is not configured.',
				[ 'status' => 403 ]
			);
		}

		$provided = $request->get_param( 'secret' );

		if ( empty( $provided ) || ! hash_equals( $secret, $provided ) ) {
			return new \WP_Error(
				'rest_forbidden',
				'Invalid or missing secret.',
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Handle the flush-cache REST request.
	 *
	 * Flushes the GraphQL object cache group. This is used by the
	 * Next.js frontend during builds to self-correct when stale
	 * cached responses are detected.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_flush_cache( \WP_REST_Request $request ): \WP_REST_Response {
		$flushed = wp_cache_flush_group( self::CACHE_GROUP );

		// Fallback: if group flush is not supported (non-Redis), flush entire cache.
		if ( false === $flushed ) {
			wp_cache_flush();
			$flushed = true;
		}

		/**
		 * Fires after the GraphQL cache group has been flushed via REST.
		 *
		 * @param bool $flushed Whether the flush was successful.
		 */
		do_action( 'wp_headless_graphql_cache_flushed', $flushed );

		return new \WP_REST_Response(
			[
				'flushed' => $flushed,
				'group'   => self::CACHE_GROUP,
				'now'     => time(),
			],
			200
		);
	}

	/**
	 * Check whether WPGraphQL is active.
	 *
	 * @return bool
	 */
	public function is_wpgraphql_active(): bool {
		return class_exists( 'WPGraphQL' );
	}

	/**
	 * Add Cache-Control headers to GraphQL responses.
	 *
	 * Sets public caching for queries and no-store for mutations.
	 *
	 * @param array $headers The response headers.
	 *
	 * @return array Modified headers.
	 */
	public function add_cache_headers( array $headers ): array {
		if ( $this->is_mutation_request() ) {
			$headers['Cache-Control'] = 'no-store, no-cache';
		} else {
			$ttl                      = $this->get_cache_ttl();
			$headers['Cache-Control'] = "public, max-age={$ttl}";
		}

		/**
		 * Filter the GraphQL Cache-Control headers.
		 *
		 * @param array $headers The response headers including Cache-Control.
		 */
		return apply_filters( 'wp_headless_graphql_cache_headers', $headers );
	}

	/**
	 * Set the query complexity limit.
	 *
	 * @param int $limit The current complexity limit.
	 *
	 * @return int The configured complexity limit.
	 */
	public function set_complexity_limit( int $limit ): int {
		$configured_limit = (int) Config::get( 'HEADLESS_GRAPHQL_COMPLEXITY_LIMIT', self::DEFAULT_COMPLEXITY_LIMIT );

		/**
		 * Filter the GraphQL query complexity limit.
		 *
		 * @param int $configured_limit The configured complexity limit.
		 * @param int $limit            The original WPGraphQL limit.
		 */
		return (int) apply_filters( 'wp_headless_graphql_complexity_limit', $configured_limit, $limit );
	}

	/**
	 * Maybe cache GraphQL response data using the object cache.
	 *
	 * Hooks into graphql_request_results to cache/retrieve query results.
	 *
	 * @param mixed  $response   The GraphQL response.
	 * @param mixed  $schema     The schema.
	 * @param string $operation  The operation type.
	 * @param string $query      The GraphQL query string.
	 * @param mixed  $variables  The query variables.
	 *
	 * @return mixed The response (potentially from cache).
	 */
	public function maybe_cache_response( $response, $schema, $operation, $query, $variables ) {
		// Don't cache mutations.
		if ( $this->is_mutation_query( $query ) ) {
			return $response;
		}

		$cache_key = $this->generate_cache_key( $query, $variables );
		$ttl       = $this->get_cache_ttl();

		// Try to get from cache first.
		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		// Sanitize response before caching: WPGraphQL response objects may contain
		// Closures or other non-serializable values that crash Object Cache Pro's
		// Redis serialization. Converting through JSON safely strips these.
		$safe = json_decode( wp_json_encode( $response ), true );

		if ( null !== $safe ) {
			wp_cache_set( $cache_key, $safe, self::CACHE_GROUP, $ttl );
		}

		return $response;
	}

	/**
	 * Get the configured cache TTL.
	 *
	 * @return int Cache TTL in seconds.
	 */
	public function get_cache_ttl(): int {
		return (int) Config::get( 'HEADLESS_GRAPHQL_CACHE_TTL', self::DEFAULT_CACHE_TTL );
	}

	/**
	 * Generate a cache key from a query and variables.
	 *
	 * @param string $query     The GraphQL query string.
	 * @param mixed  $variables The query variables.
	 *
	 * @return string The cache key.
	 */
	public function generate_cache_key( string $query, $variables = null ): string {
		$variables_string = is_array( $variables ) || is_object( $variables )
			? wp_json_encode( $variables )
			: (string) $variables;

		return md5( $query . '|' . $variables_string );
	}

	/**
	 * Check if the current request appears to be a mutation.
	 *
	 * Inspects both POST body and the query string for mutation indicators.
	 *
	 * @return bool
	 */
	private function is_mutation_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

		if ( empty( $query ) ) {
			$input = file_get_contents( 'php://input' );
			if ( ! empty( $input ) ) {
				$data  = json_decode( $input, true );
				$query = $data['query'] ?? '';
			}
		}

		return $this->is_mutation_query( $query );
	}

	/**
	 * Determine if a query string is a mutation.
	 *
	 * @param string $query The GraphQL query string.
	 *
	 * @return bool
	 */
	public function is_mutation_query( string $query ): bool {
		if ( empty( $query ) ) {
			return false;
		}

		$trimmed = ltrim( $query );

		return str_starts_with( $trimmed, 'mutation' );
	}
}
