<?php
/**
 * Activation Hook
 *
 * @package ProjectAssistant\HeadlessToolkit
 */

/**
 * Runs when the plugin is activated.
 */
function pa_headless_activation_callback(): void {
	// Ensure constants are defined (activation fires before graphql_init).
	if ( function_exists( 'pa_headless_constants' ) ) {
		pa_headless_constants();
	}

	do_action( 'pa_headless_activate' );

	// Store the current version of the plugin.
	if ( defined( 'PA_HEADLESS_VERSION' ) ) {
		update_option( 'pa_headless_toolkit_version', PA_HEADLESS_VERSION );
	}
}
