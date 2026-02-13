<?php
/**
 * Frontend Redirect Module
 *
 * Redirects visitors hitting the WordPress frontend URL to the Next.js
 * frontend URL. Allows wp-admin, REST API, WP-CRON, GraphQL, and asset
 * requests through.
 *
 * Heavily inspired by Headless Mode by Shelob9/Jason Bahl.
 *
 * Configuration:
 *   HEADLESS_FRONTEND_URL - The Next.js frontend URL (e.g. https://example.com)
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\FrontendRedirect
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\FrontendRedirect;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Helpers\Config;

class FrontendRedirect implements ModuleInterface {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'frontend_redirect';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'Frontend Redirect';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function is_enabled(): bool {
		if ( ! wp_headless_is_module_enabled( self::get_slug() ) ) {
			return false;
		}

		return Config::has( 'HEADLESS_FRONTEND_URL' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init(): void {
		add_action( 'template_redirect', [ $this, 'maybe_redirect' ] );
		add_filter( 'preview_post_link', [ $this, 'rewrite_preview_link' ], 10, 2 );
	}

	/**
	 * Redirect frontend requests to the Next.js URL.
	 */
	public function maybe_redirect(): void {
		// Don't redirect admin, API, or cron requests.
		if ( $this->is_passthrough_request() ) {
			return;
		}

		$frontend_url = Config::get( 'HEADLESS_FRONTEND_URL' );
		if ( empty( $frontend_url ) ) {
			return;
		}

		// Build the redirect URL preserving the path.
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$redirect_url = trailingslashit( $frontend_url ) . ltrim( $request_uri, '/' );

		/**
		 * Filter the redirect URL before sending.
		 *
		 * @param string $redirect_url The computed redirect URL.
		 * @param string $request_uri  The original request URI.
		 * @param string $frontend_url The configured frontend URL.
		 */
		$redirect_url = apply_filters( 'wp_headless_redirect_url', $redirect_url, $request_uri, $frontend_url );

		wp_redirect( $redirect_url, 301 );
		exit;
	}

	/**
	 * Rewrite preview links to point to the Next.js preview route.
	 *
	 * @param string   $preview_link The default preview link.
	 * @param \WP_Post $post         The post being previewed.
	 *
	 * @return string The rewritten preview link.
	 */
	public function rewrite_preview_link( string $preview_link, \WP_Post $post ): string {
		$frontend_url = Config::get( 'HEADLESS_FRONTEND_URL' );
		if ( empty( $frontend_url ) ) {
			return $preview_link;
		}

		$preview_url = add_query_arg(
			[
				'id'     => $post->ID,
				'status' => $post->post_status,
				'type'   => $post->post_type,
			],
			trailingslashit( $frontend_url ) . 'api/preview/'
		);

		/**
		 * Filter the rewritten preview link.
		 *
		 * @param string   $preview_url The computed preview URL.
		 * @param string   $preview_link The original preview link.
		 * @param \WP_Post $post        The post being previewed.
		 */
		return apply_filters( 'wp_headless_preview_link', $preview_url, $preview_link, $post );
	}

	/**
	 * Check if the current request should pass through without redirect.
	 *
	 * @return bool
	 */
	private function is_passthrough_request(): bool {
		// Admin area.
		if ( is_admin() ) {
			return true;
		}

		// WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		// REST API.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// AJAX requests.
		if ( wp_doing_ajax() ) {
			return true;
		}

		// Cron requests.
		if ( wp_doing_cron() ) {
			return true;
		}

		// GraphQL requests.
		if ( defined( 'GRAPHQL_HTTP_REQUEST' ) && GRAPHQL_HTTP_REQUEST ) {
			return true;
		}

		// Login/register pages.
		if ( in_array( $GLOBALS['pagenow'] ?? '', [ 'wp-login.php', 'wp-register.php' ], true ) ) {
			return true;
		}

		/**
		 * Filter whether the current request should pass through without redirect.
		 *
		 * @param bool $is_passthrough Whether this is a passthrough request.
		 */
		return (bool) apply_filters( 'wp_headless_is_passthrough_request', false );
	}
}
