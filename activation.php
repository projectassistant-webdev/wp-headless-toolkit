<?php
declare(strict_types=1);
/**
 * Activation Hook
 *
 * @package ProjectAssistant\HeadlessToolkit
 */

/**
 * Runs when the plugin is activated.
 */
function wp_headless_activation_callback(): void {
	// Ensure constants are defined (activation fires before graphql_init).
	if ( function_exists( 'wp_headless_constants' ) ) {
		wp_headless_constants();
	}

	do_action( 'wp_headless_activate' );

	// Store the current version of the plugin.
	if ( defined( 'WP_HEADLESS_VERSION' ) ) {
		update_option( 'wp_headless_toolkit_version', WP_HEADLESS_VERSION );
	}
}
