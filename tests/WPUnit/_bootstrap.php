<?php
/**
 * WPUnit Test Suite Bootstrap
 *
 * This file is loaded by Codeception before running WPUnit tests.
 * It ensures the plugin files are available for testing.
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit
 */

// Prevent WP cron shutdown fatal: $_SERVER['REQUEST_URI'] is undefined in CLI.
// DISABLE_WP_CRON causes _wp_cron() to return early, avoiding the access.
if ( ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}
if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
	$_SERVER['REQUEST_URI'] = '/';
}

// Register a shutdown function BEFORE WordPress registers shutdown_action_hook.
// This fires first during PHP shutdown (FIFO order). Two defenses:
// 1. restore_error_handler() removes Codeception's strict ErrorHandler so that
//    the E_WARNING from _wp_cron() accessing $_SERVER['REQUEST_URI'] in CLI
//    is handled by PHP's default handler (non-fatal) instead of being converted
//    to Codeception\Exception\Warning (fatal).
// 2. Set $_SERVER['REQUEST_URI'] in case restore_error_handler alone is not enough.
register_shutdown_function(
	function () {
		restore_error_handler();
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = '/';
		}
	}
);

// Ensure the plugin's access functions are available.
require_once dirname( __DIR__, 2 ) . '/access-functions.php';

// Ensure activation/deactivation files are available.
require_once dirname( __DIR__, 2 ) . '/activation.php';
require_once dirname( __DIR__, 2 ) . '/deactivation.php';
