<?php
/**
 * Tests for access-functions.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use Tests\ProjectAssistant\HeadlessToolkit\HeadlessToolkitTestCase;

/**
 * Tests for the global access functions.
 */
class AccessFunctionsTest extends HeadlessToolkitTestCase {

	/**
	 * {@inheritDoc}
	 */
	protected function get_filters_to_clean(): array {
		return [
			'wp_headless_config_value',
			'wp_headless_module_enabled',
		];
	}

	/**
	 * Clean up environment variables after each test.
	 */
	protected function tear_down(): void {
		putenv( 'PA_TEST_ACCESS_CONFIG_KEY' );
		putenv( 'WP_HEADLESS_DISABLE_TEST_MODULE' );

		parent::tear_down();
	}

	/**
	 * Test that wp_headless_get_config() prioritizes env var over constant and default.
	 */
	public function test_get_config_env_priority(): void {
		if ( ! defined( 'PA_TEST_ACCESS_CONFIG_KEY' ) ) {
			define( 'PA_TEST_ACCESS_CONFIG_KEY', 'from_constant' );
		}
		putenv( 'PA_TEST_ACCESS_CONFIG_KEY=from_env' );

		$result = wp_headless_get_config( 'PA_TEST_ACCESS_CONFIG_KEY', 'from_default' );

		$this->assertSame( 'from_env', $result, 'wp_headless_get_config() must prioritize env var over constant and default.' );
	}

	/**
	 * Test that wp_headless_get_config() falls back to constant when no env var.
	 */
	public function test_get_config_constant_fallback(): void {
		if ( ! defined( 'PA_TEST_CONST_FALLBACK_KEY' ) ) {
			define( 'PA_TEST_CONST_FALLBACK_KEY', 'from_constant' );
		}

		$result = wp_headless_get_config( 'PA_TEST_CONST_FALLBACK_KEY', 'from_default' );

		$this->assertSame( 'from_constant', $result, 'wp_headless_get_config() must fall back to constant when env var not set.' );
	}

	/**
	 * Test that wp_headless_get_config() applies wp_headless_config_value filter on default.
	 */
	public function test_get_config_filter(): void {
		add_filter(
			'wp_headless_config_value',
			static function ( $value, $key ) {
				if ( 'PA_TEST_FILTER_KEY_12345' === $key ) {
					return 'from_filter';
				}
				return $value;
			},
			10,
			2
		);

		$result = wp_headless_get_config( 'PA_TEST_FILTER_KEY_12345', 'from_default' );

		$this->assertSame( 'from_filter', $result, 'wp_headless_config_value filter must modify the default value.' );
	}

	/**
	 * Test that wp_headless_is_module_enabled() returns true by default.
	 */
	public function test_is_module_enabled_default_true(): void {
		$result = wp_headless_is_module_enabled( 'test_module' );

		$this->assertTrue( $result, 'Modules must be enabled by default.' );
	}

	/**
	 * Test that wp_headless_is_module_enabled() returns false when disable constant is set.
	 */
	public function test_is_module_enabled_disable_constant(): void {
		if ( ! defined( 'WP_HEADLESS_DISABLE_CONST_DISABLED_MOD' ) ) {
			define( 'WP_HEADLESS_DISABLE_CONST_DISABLED_MOD', true );
		}

		$result = wp_headless_is_module_enabled( 'const_disabled_mod' );

		$this->assertFalse( $result, 'Module must be disabled when WP_HEADLESS_DISABLE_{SLUG} constant is truthy.' );
	}

	/**
	 * Test that wp_headless_is_module_enabled() returns false when disable env var is set.
	 */
	public function test_is_module_enabled_disable_env(): void {
		putenv( 'WP_HEADLESS_DISABLE_TEST_MODULE=true' );

		$result = wp_headless_is_module_enabled( 'test_module' );

		$this->assertFalse( $result, 'Module must be disabled when WP_HEADLESS_DISABLE_{SLUG} env var is truthy.' );
	}

	/**
	 * Test that wp_headless_is_module_enabled() respects wp_headless_module_enabled filter.
	 */
	public function test_is_module_enabled_filter(): void {
		add_filter(
			'wp_headless_module_enabled',
			static function ( $enabled, $slug ) {
				if ( 'filter_disabled_mod' === $slug ) {
					return false;
				}
				return $enabled;
			},
			10,
			2
		);

		$this->assertFalse(
			wp_headless_is_module_enabled( 'filter_disabled_mod' ),
			'wp_headless_module_enabled filter must be able to disable a module.'
		);
		$this->assertTrue(
			wp_headless_is_module_enabled( 'other_mod' ),
			'wp_headless_module_enabled filter must not affect other modules.'
		);
	}
}
