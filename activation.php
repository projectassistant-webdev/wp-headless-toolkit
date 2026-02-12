<?php
/**
 * Activation Hook
 *
 * @package ProjectAssistant\HeadlessToolkit
 */

/**
 * Runs when the plugin is activated.
 */
function pa_headless_activation_callback(): callable {
	return static function (): void {
		do_action( 'pa_headless_activate' );

		// Store the current version of the plugin.
		update_option( 'pa_headless_toolkit_version', PA_HEADLESS_VERSION );
	};
}
