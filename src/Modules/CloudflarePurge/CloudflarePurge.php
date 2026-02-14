<?php
declare(strict_types=1);
/**
 * Cloudflare Purge Module
 *
 * Purges Cloudflare CDN cache and GraphQL object cache when content
 * changes in WordPress. Piggybacks on the Breeze plugin's Cloudflare
 * integration to issue full-domain purges, ensuring that cached
 * GraphQL responses served by Cloudflare are invalidated alongside
 * traditional page URLs.
 *
 * Runs at priority 20 so it fires AFTER the Revalidation module
 * (priority 10) — Next.js ISR revalidation happens first, then
 * the CDN layer is cleared.
 *
 * Prerequisites:
 *   - Breeze plugin installed and active
 *   - Cloudflare enabled in Breeze (CDN_SITE_ID + CDN_SITE_TOKEN defined)
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\CloudflarePurge
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\CloudflarePurge;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;

class CloudflarePurge implements ModuleInterface {
	/**
	 * Whether a purge has already been executed in this request.
	 *
	 * Prevents duplicate Cloudflare API calls during bulk operations
	 * (e.g., bulk post updates, quick-edit saves).
	 *
	 * @var bool
	 */
	private static bool $purge_requested = false;

	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'cloudflare_purge';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'Cloudflare Purge';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function is_enabled(): bool {
		if ( ! wp_headless_is_module_enabled( self::get_slug() ) ) {
			return false;
		}

		// Requires Breeze plugin with Cloudflare integration enabled.
		if ( ! class_exists( 'Breeze_CloudFlare_Helper' ) ) {
			return false;
		}

		return \Breeze_CloudFlare_Helper::is_cloudflare_enabled();
	}

	/**
	 * {@inheritDoc}
	 */
	public function init(): void {
		// Priority 20: run AFTER Revalidation module (priority 10).
		add_action( 'save_post', [ $this, 'handle_post_change' ], 20, 2 );
		add_action( 'delete_post', [ $this, 'handle_post_delete' ], 20, 1 );
		add_action( 'wp_trash_post', [ $this, 'handle_post_delete' ], 20, 1 );
		add_action( 'edited_term', [ $this, 'handle_term_change' ], 20, 3 );
		add_action( 'delete_term', [ $this, 'handle_term_change' ], 20, 3 );
		add_action( 'wp_update_nav_menu', [ $this, 'handle_menu_change' ], 20, 1 );
	}

	/**
	 * Handle post save/update.
	 *
	 * Applies the same guards as the Revalidation module: skips
	 * autosaves, revisions, non-published posts, and respects the
	 * wp_headless_revalidation_post_types filter.
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post    The post object.
	 */
	public function handle_post_change( int $post_id, \WP_Post $post ): void {
		// Skip autosaves and revisions.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only purge for published content.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		/**
		 * Filter which post types trigger cache purge.
		 *
		 * Reuses the Revalidation module's filter for consistency —
		 * any post type that triggers ISR revalidation should also
		 * trigger a Cloudflare purge.
		 *
		 * @param string[] $post_types Array of post type slugs.
		 */
		$post_types = apply_filters( 'wp_headless_revalidation_post_types', [ 'post', 'page' ] );

		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$this->purge_caches();
	}

	/**
	 * Handle post deletion.
	 *
	 * @param int $post_id The post ID.
	 */
	public function handle_post_delete( int $post_id ): void {
		$this->purge_caches();
	}

	/**
	 * Handle term changes (category/tag edits or deletions).
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy slug.
	 */
	public function handle_term_change( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->purge_caches();
	}

	/**
	 * Handle menu changes.
	 *
	 * @param int $menu_id The menu ID.
	 */
	public function handle_menu_change( int $menu_id ): void {
		$this->purge_caches();
	}

	/**
	 * Purge Cloudflare CDN cache and GraphQL object cache.
	 *
	 * Debounced: only executes once per PHP request regardless of
	 * how many hooks fire (e.g., bulk post updates).
	 */
	private function purge_caches(): void {
		if ( self::$purge_requested ) {
			return;
		}

		self::$purge_requested = true;

		// 1. Full-domain Cloudflare cache purge via Breeze.
		\Breeze_CloudFlare_Helper::reset_all_cache();

		// 2. Flush GraphQL object cache group (WP 6.1+).
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'wp_headless_toolkit_graphql' );
		}

		/**
		 * Fires after Cloudflare and GraphQL caches have been purged.
		 *
		 * Allows other modules or plugins to perform additional
		 * cleanup when a full CDN purge occurs.
		 */
		do_action( 'wp_headless_cloudflare_purged' );
	}
}
