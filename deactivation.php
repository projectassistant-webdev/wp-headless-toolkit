<?php
/**
 * Deactivation Hook
 *
 * @package ProjectAssistant\HeadlessToolkit
 */

/**
 * Runs when the plugin is deactivated.
 */
function pa_headless_deactivation_callback(): void {
	do_action( 'pa_headless_deactivate' );
}
