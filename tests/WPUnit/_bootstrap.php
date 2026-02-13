<?php
/**
 * WPUnit Test Suite Bootstrap
 *
 * This file is loaded by Codeception before running WPUnit tests.
 * It ensures the plugin files are available for testing.
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit
 */

// Ensure the plugin's access functions are available.
require_once dirname( __DIR__, 2 ) . '/access-functions.php';

// Ensure activation/deactivation files are available.
require_once dirname( __DIR__, 2 ) . '/activation.php';
require_once dirname( __DIR__, 2 ) . '/deactivation.php';
