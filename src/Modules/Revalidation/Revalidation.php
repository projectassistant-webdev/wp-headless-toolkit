<?php
declare(strict_types=1);
/**
 * ISR Revalidation Module
 *
 * Triggers Next.js ISR revalidation when content changes in WordPress.
 * Sends a POST to the Next.js /api/revalidate/ endpoint with the
 * revalidation secret on save_post, delete_post, and related hooks.
 * Supports tag-based revalidation (App Router).
 *
 * Folded in from production mu-plugin: pa-nextjs-revalidation.php v2.0.0
 *
 * Configuration:
 *   NEXTJS_REVALIDATION_URL    - The Next.js revalidation endpoint URL
 *   NEXTJS_REVALIDATION_SECRET - Shared secret for authentication
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\Revalidation
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\Revalidation;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Helpers\Config;

class Revalidation implements ModuleInterface {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'revalidation';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'ISR Revalidation';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function is_enabled(): bool {
		if ( ! wp_headless_is_module_enabled( self::get_slug() ) ) {
			return false;
		}

		// Requires both URL and secret to be configured.
		return Config::has( 'NEXTJS_REVALIDATION_URL' ) && Config::has( 'NEXTJS_REVALIDATION_SECRET' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init(): void {
		add_action( 'save_post', [ $this, 'handle_post_change' ], 10, 2 );
		add_action( 'delete_post', [ $this, 'handle_post_delete' ], 10, 1 );
		add_action( 'wp_trash_post', [ $this, 'handle_post_delete' ], 10, 1 );
		add_action( 'edited_term', [ $this, 'handle_term_change' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'handle_term_change' ], 10, 3 );
		add_action( 'wp_update_nav_menu', [ $this, 'handle_menu_change' ], 10, 1 );
	}

	/**
	 * Handle post save/update.
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

		// Only revalidate published content.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		/**
		 * Filter which post types trigger revalidation.
		 *
		 * @param string[] $post_types Array of post type slugs.
		 */
		$post_types = apply_filters( 'wp_headless_revalidation_post_types', [ 'post', 'page' ] );

		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$tags = $this->get_revalidation_tags_for_post( $post );
		$this->send_revalidation( $tags );
	}

	/**
	 * Handle post deletion.
	 *
	 * @param int $post_id The post ID.
	 */
	public function handle_post_delete( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$tags = $this->get_revalidation_tags_for_post( $post );
		$this->send_revalidation( $tags );
	}

	/**
	 * Handle term changes (category/tag edits).
	 *
	 * @param int    $term_id  The term ID.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy slug.
	 */
	public function handle_term_change( int $term_id, int $tt_id, string $taxonomy ): void {
		$tags = [ $taxonomy, "term-{$term_id}" ];
		$this->send_revalidation( $tags );
	}

	/**
	 * Handle menu changes.
	 *
	 * @param int $menu_id The menu ID.
	 */
	public function handle_menu_change( int $menu_id ): void {
		$this->send_revalidation( [ 'menu', "menu-{$menu_id}" ] );
	}

	/**
	 * Get revalidation tags for a post.
	 *
	 * @param \WP_Post $post The post object.
	 *
	 * @return string[]
	 */
	private function get_revalidation_tags_for_post( \WP_Post $post ): array {
		$tags = [
			$post->post_type,
			"{$post->post_type}-{$post->ID}",
		];

		// Add taxonomy terms as tags.
		$taxonomies = get_object_taxonomies( $post->post_type );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'slugs' ] );
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term_slug ) {
					$tags[] = "{$taxonomy}-{$term_slug}";
				}
			}
		}

		/**
		 * Filter the revalidation tags for a post.
		 *
		 * @param string[] $tags The revalidation tags.
		 * @param \WP_Post $post The post object.
		 */
		return apply_filters( 'wp_headless_revalidation_tags', $tags, $post );
	}

	/**
	 * Send revalidation request to Next.js.
	 *
	 * @param string[] $tags The cache tags to revalidate.
	 */
	private function send_revalidation( array $tags ): void {
		$url    = Config::get( 'NEXTJS_REVALIDATION_URL' );
		$secret = Config::get( 'NEXTJS_REVALIDATION_SECRET' );

		if ( empty( $url ) || empty( $secret ) ) {
			return;
		}

		$body = wp_json_encode( [
			'tags'   => $tags,
			'secret' => $secret,
		] );

		$args = [
			'body'        => $body,
			'headers'     => [
				'Content-Type' => 'application/json',
			],
			'timeout'     => 10,
			'blocking'    => false,
			'data_format' => 'body',
		];

		/**
		 * Filter the revalidation request arguments.
		 *
		 * @param array    $args The wp_remote_post arguments.
		 * @param string[] $tags The revalidation tags.
		 * @param string   $url  The revalidation URL.
		 */
		$args = apply_filters( 'wp_headless_revalidation_request_args', $args, $tags, $url );

		wp_remote_post( $url, $args );

		/**
		 * Fires after a revalidation request is sent.
		 *
		 * @param string[] $tags The revalidation tags.
		 * @param string   $url  The revalidation URL.
		 */
		do_action( 'wp_headless_revalidation_sent', $tags, $url );
	}
}
