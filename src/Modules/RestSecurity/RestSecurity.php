<?php
declare(strict_types=1);
/**
 * REST API Security Module
 *
 * Removes unnecessary REST API endpoints that headless doesn't need
 * (comments, password reset, user enumeration, etc.). Keeps only what
 * WPGraphQL and admin needs.
 *
 * Whitelist-based approach with filter for per-project customization.
 * Reference: FaustWP deny-public-access pattern.
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\RestSecurity
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\RestSecurity;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;

class RestSecurity implements ModuleInterface {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'rest_security';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'REST API Security';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function is_enabled(): bool {
		return wp_headless_is_module_enabled( self::get_slug() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init(): void {
		add_filter( 'rest_endpoints', [ $this, 'filter_endpoints' ], 999 );
		add_filter( 'rest_authentication_errors', [ $this, 'restrict_unauthenticated_access' ], 99 );
	}

	/**
	 * Remove unnecessary REST API endpoints.
	 *
	 * @param array $endpoints The registered REST API endpoints.
	 *
	 * @return array Filtered endpoints.
	 */
	public function filter_endpoints( array $endpoints ): array {
		// Don't filter for authenticated admin users.
		if ( current_user_can( 'manage_options' ) ) {
			return $endpoints;
		}

		/**
		 * Filter the list of REST endpoint prefixes to remove.
		 *
		 * @param string[] $blocked_prefixes Array of endpoint path prefixes to block.
		 */
		$blocked_prefixes = apply_filters( 'wp_headless_rest_blocked_prefixes', [
			'/wp/v2/comments',
			'/wp/v2/users',
			'/wp/v2/search',
		] );

		foreach ( $endpoints as $route => $handler ) {
			foreach ( $blocked_prefixes as $prefix ) {
				if ( str_starts_with( $route, $prefix ) ) {
					unset( $endpoints[ $route ] );
					break;
				}
			}
		}

		return $endpoints;
	}

	/**
	 * Restrict unauthenticated REST API access for non-whitelisted routes.
	 *
	 * @param \WP_Error|null|true $result The current authentication result.
	 *
	 * @return \WP_Error|null|true
	 */
	public function restrict_unauthenticated_access( $result ) {
		// If already authenticated or errored, pass through.
		if ( null !== $result ) {
			return $result;
		}

		// If the user is logged in, allow access.
		if ( is_user_logged_in() ) {
			return $result;
		}

		// Get the current REST route.
		$rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';

		/**
		 * Filter the list of REST route prefixes allowed for unauthenticated access.
		 *
		 * @param string[] $allowed_prefixes Array of endpoint path prefixes to allow.
		 */
		$allowed_prefixes = apply_filters( 'wp_headless_rest_allowed_prefixes', [
			'/wp-site-health/',
			'/wp/v2/settings',
			'/wpgraphql/',
			'/batch/v1',
		] );

		foreach ( $allowed_prefixes as $prefix ) {
			if ( str_starts_with( $rest_route, $prefix ) ) {
				return $result;
			}
		}

		return new \WP_Error(
			'rest_not_logged_in',
			esc_html__( 'REST API restricted to authenticated users.', 'wp-headless-toolkit' ),
			[ 'status' => 401 ]
		);
	}
}
