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

// Ensure the plugin's access functions are available.
require_once dirname( __DIR__, 2 ) . '/access-functions.php';

// Ensure activation/deactivation files are available.
require_once dirname( __DIR__, 2 ) . '/activation.php';
require_once dirname( __DIR__, 2 ) . '/deactivation.php';
