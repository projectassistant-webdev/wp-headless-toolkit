<?php
/**
 * Base test case for all WP Headless Toolkit tests.
 *
 * Extends WPTestCase and integrates EnvironmentTestTrait and
 * FilterCleanupTrait to provide standardized test infrastructure.
 *
 * All test classes should extend this instead of WPTestCase directly.
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * Class HeadlessToolkitTestCase
 *
 * Provides shared test infrastructure for all headless toolkit tests:
 * - Environment variable management via EnvironmentTestTrait
 * - WordPress filter/action cleanup via FilterCleanupTrait
 */
class HeadlessToolkitTestCase extends WPTestCase {

	use EnvironmentTestTrait;
	use FilterCleanupTrait;

	/**
	 * Clean up environment variables and filters after each test.
	 *
	 * Subclasses that override tear_down() MUST call parent::tear_down()
	 * to ensure proper cleanup. Place custom cleanup BEFORE the parent
	 * call so that it runs before filter/env cleanup.
	 *
	 * Example:
	 *   protected function tear_down(): void {
	 *       // Custom cleanup here.
	 *       $this->my_custom_cleanup();
	 *       parent::tear_down();
	 *   }
	 */
	protected function tear_down(): void {
		$this->clean_env_vars();
		$this->clean_filters();
		parent::tear_down();
	}
}
