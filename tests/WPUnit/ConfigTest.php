<?php
/**
 * Tests for src/Helpers/Config.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use Tests\ProjectAssistant\HeadlessToolkit\HeadlessToolkitTestCase;
use ProjectAssistant\HeadlessToolkit\Helpers\Config;

/**
 * Tests for the Config helper class.
 *
 * @group unit
 * @group config
 */
class ConfigTest extends HeadlessToolkitTestCase {

	/**
	 * Test that get() returns environment variable value when set.
	 *
	 * @group smoke
	 */
	public function test_get_returns_env_var_value(): void {
		$this->set_env( 'PA_TEST_CONFIG_KEY', 'env_value' );

		$result = Config::get( 'PA_TEST_CONFIG_KEY', 'default' );

		$this->assertSame( 'env_value', $result, 'Config::get() must return env var value when set.' );
	}

	/**
	 * Test that get() returns constant value when env var is not set.
	 */
	public function test_get_returns_constant_when_no_env(): void {
		// Use a constant that is defined but no env var is set.
		if ( ! defined( 'PA_TEST_CONST_ONLY' ) ) {
			define( 'PA_TEST_CONST_ONLY', 'constant_value' );
		}

		$result = Config::get( 'PA_TEST_CONST_ONLY', 'default' );

		$this->assertSame( 'constant_value', $result, 'Config::get() must return constant value when env var is not set.' );
	}

	/**
	 * Test that get() returns default when neither env nor constant exists.
	 */
	public function test_get_returns_default_when_nothing_set(): void {
		$result = Config::get( 'PA_TEST_NONEXISTENT_KEY_12345', 'my_default' );

		$this->assertSame( 'my_default', $result, 'Config::get() must return default when neither env nor constant exists.' );
	}

	/**
	 * Test that get() prioritizes env var over constant.
	 */
	public function test_get_priority_env_over_constant(): void {
		if ( ! defined( 'PA_TEST_PRIORITY_KEY' ) ) {
			define( 'PA_TEST_PRIORITY_KEY', 'from_constant' );
		}
		$this->set_env( 'PA_TEST_PRIORITY_KEY', 'from_env' );

		$result = Config::get( 'PA_TEST_PRIORITY_KEY', 'default' );

		$this->assertSame( 'from_env', $result, 'Environment variable must take priority over constant.' );
	}

	/**
	 * Test that get_bool() returns true for truthy strings.
	 *
	 * @group smoke
	 */
	public function test_get_bool_returns_true_for_truthy_strings(): void {
		$truthy_values = [ 'true', '1', 'yes', 'on' ];

		foreach ( $truthy_values as $value ) {
			$this->set_env( 'PA_TEST_BOOL_KEY', $value );
			$result = Config::get_bool( 'PA_TEST_BOOL_KEY', false );
			$this->assertTrue( $result, "Config::get_bool() must return true for '{$value}'." );
		}
	}

	/**
	 * Test that get_bool() returns false for falsy strings.
	 */
	public function test_get_bool_returns_false_for_falsy_strings(): void {
		$falsy_values = [ 'false', '0', 'no', 'off' ];

		foreach ( $falsy_values as $value ) {
			$this->set_env( 'PA_TEST_BOOL_KEY', $value );
			$result = Config::get_bool( 'PA_TEST_BOOL_KEY', true );
			$this->assertFalse( $result, "Config::get_bool() must return false for '{$value}'." );
		}
	}

	/**
	 * Test that get_bool() returns default when key is not set.
	 */
	public function test_get_bool_returns_default_when_not_set(): void {
		$result_true  = Config::get_bool( 'PA_TEST_BOOL_NONEXISTENT_99999', true );
		$result_false = Config::get_bool( 'PA_TEST_BOOL_NONEXISTENT_99999', false );

		$this->assertTrue( $result_true, 'Config::get_bool() must return true default when not set.' );
		$this->assertFalse( $result_false, 'Config::get_bool() must return false default when not set.' );
	}

	/**
	 * Test that get_list() splits comma-separated values.
	 */
	public function test_get_list_splits_comma_separated(): void {
		$this->set_env( 'PA_TEST_LIST_KEY', 'a,b,c' );

		$result = Config::get_list( 'PA_TEST_LIST_KEY' );

		$this->assertSame( [ 'a', 'b', 'c' ], $result, 'Config::get_list() must split comma-separated values.' );
	}

	/**
	 * Test that get_list() trims whitespace from values.
	 */
	public function test_get_list_trims_whitespace(): void {
		$this->set_env( 'PA_TEST_LIST_KEY', 'a, b , c' );

		$result = Config::get_list( 'PA_TEST_LIST_KEY' );

		$this->assertSame( [ 'a', 'b', 'c' ], $result, 'Config::get_list() must trim whitespace from each value.' );
	}

	/**
	 * Test that get_list() returns default for empty string.
	 */
	public function test_get_list_returns_default_for_empty(): void {
		$default = [ 'x', 'y' ];

		$result = Config::get_list( 'PA_TEST_LIST_NONEXISTENT_99999', $default );

		$this->assertSame( $default, $result, 'Config::get_list() must return default array when key is not set.' );
	}

	/**
	 * Test that has() returns true when env var is set.
	 *
	 * @group smoke
	 */
	public function test_has_returns_true_for_env_var(): void {
		$this->set_env( 'PA_TEST_HAS_KEY', 'some_value' );

		$this->assertTrue( Config::has( 'PA_TEST_HAS_KEY' ), 'Config::has() must return true when env var is set.' );
	}

	/**
	 * Test that has() returns true when constant is defined.
	 */
	public function test_has_returns_true_for_constant(): void {
		if ( ! defined( 'PA_TEST_HAS_CONST' ) ) {
			define( 'PA_TEST_HAS_CONST', 'value' );
		}

		$this->assertTrue( Config::has( 'PA_TEST_HAS_CONST' ), 'Config::has() must return true when constant is defined.' );
	}

	/**
	 * Test that has() returns false when neither env var nor constant exists.
	 */
	public function test_has_returns_false_when_nothing_set(): void {
		$this->assertFalse( Config::has( 'PA_TEST_HAS_NONEXISTENT_99999' ), 'Config::has() must return false when nothing is set.' );
	}
}
