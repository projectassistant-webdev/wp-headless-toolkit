<?php
/**
 * Tests for activation.php and deactivation.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * Tests for plugin activation and deactivation callbacks.
 */
class ActivationTest extends WPTestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function set_up(): void {
		parent::set_up();

		// Ensure the activation/deactivation files are loaded.
		require_once dirname( __DIR__, 2 ) . '/activation.php';
		require_once dirname( __DIR__, 2 ) . '/deactivation.php';
	}

	/**
	 * Test that the activation callback return type is void (not callable).
	 *
	 * This validates the bug fix: the original implementation returned a callable
	 * (closure) instead of executing the activation logic directly.
	 */
	public function test_activation_callback_return_type_is_void(): void {
		$reflection = new \ReflectionFunction( 'pa_headless_activation_callback' );
		$return_type = $reflection->getReturnType();

		$this->assertNotNull( $return_type, 'Activation callback must have a return type declaration.' );
		$this->assertInstanceOf( \ReflectionNamedType::class, $return_type );
		$this->assertSame( 'void', $return_type->getName(), 'Activation callback must return void, not callable.' );
	}

	/**
	 * Test that the deactivation callback return type is void (not callable).
	 *
	 * This validates the bug fix: the original implementation returned a callable
	 * (closure) instead of executing the deactivation logic directly.
	 */
	public function test_deactivation_callback_return_type_is_void(): void {
		$reflection = new \ReflectionFunction( 'pa_headless_deactivation_callback' );
		$return_type = $reflection->getReturnType();

		$this->assertNotNull( $return_type, 'Deactivation callback must have a return type declaration.' );
		$this->assertInstanceOf( \ReflectionNamedType::class, $return_type );
		$this->assertSame( 'void', $return_type->getName(), 'Deactivation callback must return void, not callable.' );
	}

	/**
	 * Test that activation callback stores the plugin version in the options table.
	 */
	public function test_activation_callback_stores_version(): void {
		// Ensure the option does not exist before activation.
		delete_option( 'pa_headless_toolkit_version' );

		// Define the version constant if not already defined.
		if ( ! defined( 'PA_HEADLESS_VERSION' ) ) {
			define( 'PA_HEADLESS_VERSION', '1.0.0' );
		}

		// Run the activation callback.
		pa_headless_activation_callback();

		// Verify the version option was stored.
		$stored_version = get_option( 'pa_headless_toolkit_version' );
		$this->assertSame( PA_HEADLESS_VERSION, $stored_version, 'Activation must store the plugin version in the pa_headless_toolkit_version option.' );
	}

	/**
	 * Test that activation callback fires the pa_headless_activate action.
	 */
	public function test_activation_callback_fires_action(): void {
		$action_fired = false;
		add_action(
			'pa_headless_activate',
			static function () use ( &$action_fired ): void {
				$action_fired = true;
			}
		);

		// Define the version constant if not already defined.
		if ( ! defined( 'PA_HEADLESS_VERSION' ) ) {
			define( 'PA_HEADLESS_VERSION', '1.0.0' );
		}

		pa_headless_activation_callback();

		$this->assertTrue( $action_fired, 'Activation must fire the pa_headless_activate action.' );
	}

	/**
	 * Test that deactivation callback fires the pa_headless_deactivate action.
	 */
	public function test_deactivation_callback_fires_action(): void {
		$action_fired = false;
		add_action(
			'pa_headless_deactivate',
			static function () use ( &$action_fired ): void {
				$action_fired = true;
			}
		);

		pa_headless_deactivation_callback();

		$this->assertTrue( $action_fired, 'Deactivation must fire the pa_headless_deactivate action.' );
	}
}
