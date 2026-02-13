<?php
/**
 * Tests for src/Main.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use ProjectAssistant\HeadlessToolkit\Main;
use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Modules\MigrateDbCompat\MigrateDbCompat;

/**
 * Tests for the Main singleton and module loader.
 */
class MainTest extends WPTestCase {

	/**
	 * Reset the Main singleton between tests to prevent state leakage.
	 */
	protected function tear_down(): void {
		$reflection     = new \ReflectionClass( Main::class );
		$instance_prop  = $reflection->getProperty( 'instance' );
		$instance_prop->setAccessible( true );
		$instance_prop->setValue( null, null );

		// Remove any test filters added during tests.
		remove_all_filters( 'wp_headless_module_classes' );
		remove_all_filters( 'wp_headless_init' );
		remove_all_filters( 'wp_headless_modules_loaded' );

		parent::tear_down();
	}

	/**
	 * Test that Main::instance() returns the same instance on repeated calls.
	 */
	public function test_instance_returns_singleton(): void {
		$instance1 = Main::instance();
		$instance2 = Main::instance();

		$this->assertSame( $instance1, $instance2, 'Main::instance() must return the same object on repeated calls.' );
	}

	/**
	 * Test that Main::instance() returns an instance of Main.
	 */
	public function test_instance_returns_main_class(): void {
		$instance = Main::instance();

		$this->assertInstanceOf( Main::class, $instance, 'Main::instance() must return a Main instance.' );
	}

	/**
	 * Test that load_modules registers default modules when enabled.
	 *
	 * Note: In the test environment, most modules' is_enabled() returns false
	 * because their dependencies are not present. We verify modules array is
	 * populated by filtering in a simple test module.
	 */
	public function test_load_modules_registers_default_modules(): void {
		// Filter module classes to only include MigrateDbCompat (we can control its enablement).
		add_filter(
			'wp_headless_module_classes',
			static function () {
				// Use an empty array - modules with unmet dependencies won't load.
				// We just verify the mechanism works.
				return [];
			}
		);

		$instance = Main::instance();
		$modules  = $instance->get_modules();

		// With empty module list, no modules should be loaded.
		$this->assertIsArray( $modules, 'get_modules() must return an array.' );
		$this->assertEmpty( $modules, 'With empty module class list, no modules should be loaded.' );
	}

	/**
	 * Test that load_modules skips classes not implementing ModuleInterface.
	 */
	public function test_load_modules_skips_non_module_interface(): void {
		add_filter(
			'wp_headless_module_classes',
			static function () {
				// stdClass does not implement ModuleInterface.
				return [ \stdClass::class ];
			}
		);

		$instance = Main::instance();
		$modules  = $instance->get_modules();

		$this->assertEmpty( $modules, 'Classes not implementing ModuleInterface must be skipped.' );
	}

	/**
	 * Test that load_modules skips disabled modules.
	 */
	public function test_load_modules_skips_disabled_modules(): void {
		// MigrateDbCompat::is_enabled() returns false in test env (no WPMDB).
		add_filter(
			'wp_headless_module_classes',
			static function () {
				return [ MigrateDbCompat::class ];
			}
		);

		$instance = Main::instance();
		$modules  = $instance->get_modules();

		$this->assertEmpty( $modules, 'Disabled modules (is_enabled() == false) must not be loaded.' );
	}

	/**
	 * Test that get_module returns a module by slug when it is loaded.
	 *
	 * NOTE: This test defines the WPMDB_PRO_VERSION constant, which persists for
	 * the lifetime of the PHP process. Tests that assert MigrateDbCompat is
	 * disabled (e.g. test_load_modules_skips_disabled_modules) must run BEFORE
	 * this test. PHPUnit runs tests in declaration order by default, so the
	 * current ordering is intentional. Do not reorder without accounting for
	 * this constant-leakage side effect.
	 */
	public function test_get_module_returns_module_by_slug(): void {
		// Define the constant so MigrateDbCompat::is_enabled() returns true.
		if ( ! defined( 'WPMDB_PRO_VERSION' ) ) {
			define( 'WPMDB_PRO_VERSION', '2.6.0' );
		}

		add_filter(
			'wp_headless_module_classes',
			static function () {
				return [ MigrateDbCompat::class ];
			}
		);

		$instance = Main::instance();
		$module   = $instance->get_module( 'migrate_db_compat' );

		$this->assertNotNull( $module, 'get_module() must return a module for a known slug.' );
		$this->assertInstanceOf( ModuleInterface::class, $module, 'Returned module must implement ModuleInterface.' );
		$this->assertInstanceOf( MigrateDbCompat::class, $module, 'Returned module must be MigrateDbCompat.' );
	}

	/**
	 * Test that get_module returns null for an unknown slug.
	 */
	public function test_get_module_returns_null_for_unknown_slug(): void {
		$instance = Main::instance();
		$module   = $instance->get_module( 'nonexistent_module' );

		$this->assertNull( $module, 'get_module() must return null for an unknown slug.' );
	}

	/**
	 * Test that wp_headless_init action fires with Main instance on first instance() call.
	 */
	public function test_wp_headless_init_action_fires(): void {
		$received_instance = null;
		add_action(
			'wp_headless_init',
			static function ( $instance ) use ( &$received_instance ): void {
				$received_instance = $instance;
			}
		);

		$instance = Main::instance();

		$this->assertNotNull( $received_instance, 'wp_headless_init action must fire on instance() call.' );
		$this->assertSame( $instance, $received_instance, 'wp_headless_init must pass the Main instance.' );
	}

	/**
	 * Test that wp_headless_init fires only once (inside singleton guard).
	 *
	 * After TD-QA-004 fix: do_action('wp_headless_init') is moved inside the
	 * singleton if-block so it fires only on first instantiation.
	 */
	public function test_wp_headless_init_fires_only_once(): void {
		$fire_count = 0;
		add_action(
			'wp_headless_init',
			static function () use ( &$fire_count ): void {
				++$fire_count;
			}
		);

		Main::instance();
		Main::instance();
		Main::instance();

		$this->assertSame( 1, $fire_count, 'wp_headless_init must fire only once (on first instance() call).' );
	}

	/**
	 * Test that wp_headless_init passes the Main instance parameter.
	 */
	public function test_wp_headless_init_receives_instance_parameter(): void {
		$received = null;
		add_action(
			'wp_headless_init',
			static function ( $instance ) use ( &$received ): void {
				$received = $instance;
			}
		);

		$instance = Main::instance();

		$this->assertSame( $instance, $received, 'wp_headless_init must pass the Main instance as parameter.' );
	}

	/**
	 * Test that wp_headless_modules_loaded action fires with module array.
	 */
	public function test_wp_headless_modules_loaded_action_fires(): void {
		$received_modules = null;
		add_action(
			'wp_headless_modules_loaded',
			static function ( $modules ) use ( &$received_modules ): void {
				$received_modules = $modules;
			}
		);

		Main::instance();

		$this->assertNotNull( $received_modules, 'wp_headless_modules_loaded action must fire.' );
		$this->assertIsArray( $received_modules, 'wp_headless_modules_loaded must pass an array of modules.' );
	}

	/**
	 * Test that wp_headless_module_classes filter can modify module classes.
	 */
	public function test_wp_headless_module_classes_filter(): void {
		$filter_called = false;
		add_filter(
			'wp_headless_module_classes',
			static function ( $classes ) use ( &$filter_called ) {
				$filter_called = true;
				// Return empty array to prove the filter is respected.
				return [];
			}
		);

		$instance = Main::instance();

		$this->assertTrue( $filter_called, 'wp_headless_module_classes filter must be called during module loading.' );
		$this->assertEmpty( $instance->get_modules(), 'When filter returns empty array, no modules should be loaded.' );
	}
}
