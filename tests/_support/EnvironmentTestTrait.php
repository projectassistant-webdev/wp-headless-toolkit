<?php
/**
 * Trait for managing environment variables in tests.
 *
 * Provides a set_env() helper that registers env vars for automatic cleanup
 * in tear_down(), preventing env var leakage between tests.
 *
 * Used by test classes that need to set environment variables during tests
 * (e.g., CorsTest, SecurityHeadersTest, PreviewModeTest, SettingsPageTest).
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit;

/**
 * Trait EnvironmentTestTrait
 *
 * Extracted from the identical set_env() pattern found across multiple test files.
 */
trait EnvironmentTestTrait {

	/**
	 * Environment variables to clean up in tear_down.
	 *
	 * @var string[]
	 */
	protected array $env_vars_to_clean = [];

	/**
	 * Helper to set an env var and register it for cleanup.
	 *
	 * @param string $key   The env var name.
	 * @param string $value The env var value.
	 */
	protected function set_env( string $key, string $value ): void {
		putenv( "{$key}={$value}" );
		$this->env_vars_to_clean[] = $key;
	}

	/**
	 * Clean up all registered environment variables.
	 *
	 * Call this in tear_down() to remove env vars set during the test.
	 */
	protected function clean_env_vars(): void {
		foreach ( $this->env_vars_to_clean as $key ) {
			putenv( $key );
		}
		$this->env_vars_to_clean = [];
	}
}
