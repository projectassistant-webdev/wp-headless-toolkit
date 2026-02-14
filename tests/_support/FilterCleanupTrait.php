<?php
/**
 * Trait for cleaning up WordPress filters and actions in tests.
 *
 * Provides a centralized mechanism for test classes to declare which
 * filters/actions they register during tests, ensuring proper cleanup
 * in tear_down() to prevent filter leakage between tests.
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit;

/**
 * Trait FilterCleanupTrait
 *
 * Extracted from the remove_all_filters() pattern found across 13 test files.
 * Each test class declares its filters via get_filters_to_clean().
 */
trait FilterCleanupTrait {

	/**
	 * Return the list of filter/action hooks to clean up in tear_down.
	 *
	 * Override this method in each test class to declare which filters
	 * and actions the test may register. Both filters and actions use
	 * the same WordPress hook system, so remove_all_filters() works
	 * for both.
	 *
	 * @return string[] Array of hook names to clean up.
	 */
	protected function get_filters_to_clean(): array {
		return [];
	}

	/**
	 * Remove all registered callbacks from declared filters/actions.
	 *
	 * Call this in tear_down() to clean up all hooks declared by
	 * get_filters_to_clean().
	 */
	protected function clean_filters(): void {
		foreach ( $this->get_filters_to_clean() as $hook ) {
			remove_all_filters( $hook );
		}
	}
}
