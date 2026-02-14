<?php
/**
 * Tests for Composer distribution readiness and CI verification.
 *
 * Verifies that the plugin is properly set up for Composer-based
 * distribution and that all classes are autoloadable.
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use ProjectAssistant\HeadlessToolkit\Main;
use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Modules\Revalidation\Revalidation;
use ProjectAssistant\HeadlessToolkit\Modules\RestSecurity\RestSecurity;
use ProjectAssistant\HeadlessToolkit\Modules\FrontendRedirect\FrontendRedirect;
use ProjectAssistant\HeadlessToolkit\Modules\MigrateDbCompat\MigrateDbCompat;
use ProjectAssistant\HeadlessToolkit\Modules\HeadCleanup\HeadCleanup;
use ProjectAssistant\HeadlessToolkit\Helpers\Config;

/**
 * Tests for Composer distribution and CI readiness.
 */
class ComposerCiTest extends WPTestCase {

	/**
	 * Reset the Main singleton between tests to prevent state leakage.
	 */
	protected function tear_down(): void {
		$reflection    = new \ReflectionClass( Main::class );
		$instance_prop = $reflection->getProperty( 'instance' );
		$instance_prop->setAccessible( true );
		$instance_prop->setValue( null, null );

		remove_all_filters( 'wp_headless_module_classes' );

		parent::tear_down();
	}

	/**
	 * Test that the main plugin file exists at the expected location.
	 */
	public function test_plugin_file_exists(): void {
		$plugin_file = WP_HEADLESS_PLUGIN_DIR . 'wp-headless-toolkit.php';
		$this->assertFileExists( $plugin_file );
	}

	/**
	 * Test that the Composer autoload file exists.
	 */
	public function test_composer_autoload_exists(): void {
		$autoload_file = WP_HEADLESS_PLUGIN_DIR . 'vendor/autoload.php';
		$this->assertFileExists( $autoload_file );
	}

	/**
	 * Test that the Main class is autoloadable via Composer.
	 */
	public function test_main_class_is_autoloadable(): void {
		$this->assertTrue( class_exists( Main::class ) );
	}

	/**
	 * Test that the ModuleInterface is autoloadable via Composer.
	 */
	public function test_module_interface_is_autoloadable(): void {
		$this->assertTrue( interface_exists( ModuleInterface::class ) );
	}

	/**
	 * Test that the Config helper is autoloadable via Composer.
	 */
	public function test_config_class_is_autoloadable(): void {
		$this->assertTrue( class_exists( Config::class ) );
	}

	/**
	 * Test that all module classes are autoloadable via Composer.
	 *
	 * @dataProvider module_class_provider
	 *
	 * @param string $class_name The fully qualified module class name.
	 */
	public function test_module_class_is_autoloadable( string $class_name ): void {
		$this->assertTrue(
			class_exists( $class_name ),
			"Module class $class_name should be autoloadable."
		);
	}

	/**
	 * Data provider for module classes.
	 *
	 * @return array<string, array{string}>
	 */
	public static function module_class_provider(): array {
		return [
			'Revalidation'     => [ Revalidation::class ],
			'RestSecurity'     => [ RestSecurity::class ],
			'FrontendRedirect' => [ FrontendRedirect::class ],
			'MigrateDbCompat'  => [ MigrateDbCompat::class ],
			'HeadCleanup'      => [ HeadCleanup::class ],
		];
	}

	/**
	 * Test that all 5 default modules are registered after plugin initialization.
	 */
	public function test_all_default_modules_registered(): void {
		$main    = Main::instance();
		$classes = $main->get_registered_module_classes();

		$expected_modules = [
			Revalidation::class,
			RestSecurity::class,
			FrontendRedirect::class,
			MigrateDbCompat::class,
			HeadCleanup::class,
		];

		foreach ( $expected_modules as $module_class ) {
			$this->assertContains(
				$module_class,
				$classes,
				"Default module $module_class should be in the registered module classes."
			);
		}

		$this->assertCount( 5, $expected_modules );
	}

	/**
	 * Test that the plugin version constant is defined.
	 */
	public function test_plugin_version_constant_defined(): void {
		$this->assertTrue( defined( 'WP_HEADLESS_VERSION' ) );
		$this->assertSame( '1.3.0', WP_HEADLESS_VERSION );
	}

	/**
	 * Test that composer.json exists and is valid JSON.
	 */
	public function test_composer_json_valid(): void {
		$composer_file = WP_HEADLESS_PLUGIN_DIR . 'composer.json';
		$this->assertFileExists( $composer_file );

		$content = file_get_contents( $composer_file );
		$this->assertIsString( $content );

		$json = json_decode( $content, true );
		$this->assertIsArray( $json );
		$this->assertSame( 'projectassistant/wordpress-headless-toolkit', $json['name'] );
		$this->assertSame( 'wordpress-plugin', $json['type'] );
	}

	/**
	 * Test that composer.json has required PSR-4 autoload config.
	 */
	public function test_composer_json_has_psr4_autoload(): void {
		$composer_file = WP_HEADLESS_PLUGIN_DIR . 'composer.json';
		$json          = json_decode( file_get_contents( $composer_file ), true );

		$this->assertArrayHasKey( 'autoload', $json );
		$this->assertArrayHasKey( 'psr-4', $json['autoload'] );
		$this->assertArrayHasKey( 'ProjectAssistant\\HeadlessToolkit\\', $json['autoload']['psr-4'] );
		$this->assertSame( 'src/', $json['autoload']['psr-4']['ProjectAssistant\\HeadlessToolkit\\'] );
	}
}
