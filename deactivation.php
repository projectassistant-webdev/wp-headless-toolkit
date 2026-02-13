<?php
/**
 * Deactivation Hook
 *
 * @package ProjectAssistant\HeadlessToolkit
 */

/**
 * Runs when the plugin is deactivated.
 */
function wp_headless_deactivation_callback(): void {
	do_action( 'wp_headless_deactivate' );
}
