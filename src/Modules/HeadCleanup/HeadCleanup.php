<?php
/**
 * Head Cleanup Module
 *
 * Strips unnecessary tags from WordPress <head> output:
 * wp_generator, wlwmanifest, rsd_link, wp_shortlink, emoji scripts.
 *
 * Not critical for headless (the frontend doesn't render WP's head),
 * but keeps the WordPress instance clean and reduces unnecessary output.
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\HeadCleanup
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\HeadCleanup;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;

class HeadCleanup implements ModuleInterface {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'head_cleanup';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'Head Cleanup';
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
		// Remove WordPress version meta tag.
		remove_action( 'wp_head', 'wp_generator' );

		// Remove Windows Live Writer manifest link.
		remove_action( 'wp_head', 'wlwmanifest_link' );

		// Remove RSD link (Really Simple Discovery).
		remove_action( 'wp_head', 'rsd_link' );

		// Remove shortlink.
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );

		// Remove REST API link from head (still accessible via endpoint).
		remove_action( 'wp_head', 'rest_output_link_wp_head' );

		// Remove oEmbed discovery links (may be registered at multiple priorities).
		while ( false !== ( $oembed_priority = has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) ) ) {
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links', $oembed_priority );
		}

		// Remove emoji scripts and styles.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		// Remove feed links.
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );

		/**
		 * Filter the list of additional wp_head items to remove.
		 * Return an array of [action_name, callback, priority] arrays.
		 *
		 * @param array $additional_removals Additional items to remove.
		 */
		$additional = apply_filters( 'wp_headless_head_cleanup_removals', [] );

		foreach ( $additional as $removal ) {
			if ( isset( $removal[0], $removal[1] ) ) {
				remove_action( $removal[0], $removal[1], $removal[2] ?? 10 );
			}
		}
	}
}
