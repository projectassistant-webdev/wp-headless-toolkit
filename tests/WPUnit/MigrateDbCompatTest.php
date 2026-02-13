<?php
/**
 * Tests for src/Modules/MigrateDbCompat/MigrateDbCompat.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use ProjectAssistant\HeadlessToolkit\Modules\MigrateDbCompat\MigrateDbCompat;

/**
 * Tests for the MigrateDbCompat module.
 */
class MigrateDbCompatTest extends WPTestCase {

	/**
	 * Clean up filters after each test.
	 */
	protected function tear_down(): void {
		remove_all_filters( 'wpmdb_upload_dir' );
		remove_all_filters( 'wpmdb_upload_info' );
		remove_all_filters( 'wpmdb_get_path' );
		remove_all_filters( 'wp_headless_module_enabled' );

		parent::tear_down();
	}

	/**
	 * Test that get_slug() returns the expected slug string.
	 */
	public function test_get_slug(): void {
		$this->assertSame(
			'migrate_db_compat',
			MigrateDbCompat::get_slug(),
			'get_slug() must return "migrate_db_compat".'
		);
	}

	/**
	 * Test that get_name() returns the expected human-readable name.
	 */
	public function test_get_name(): void {
		$this->assertSame(
			'WP Migrate DB Compatibility',
			MigrateDbCompat::get_name(),
			'get_name() must return "WP Migrate DB Compatibility".'
		);
	}

	/**
	 * Test that is_enabled() returns true when WPMDB_PRO_VERSION is defined.
	 */
	public function test_is_enabled_with_wpmdb_constant(): void {
		// WPMDB_PRO_VERSION may already be defined from MainTest.
		if ( ! defined( 'WPMDB_PRO_VERSION' ) ) {
			define( 'WPMDB_PRO_VERSION', '2.6.0' );
		}

		$this->assertTrue(
			MigrateDbCompat::is_enabled(),
			'is_enabled() must return true when WPMDB_PRO_VERSION is defined.'
		);
	}

	/**
	 * Test that is_enabled() returns false when module is disabled via filter.
	 */
	public function test_is_enabled_false_when_disabled_via_filter(): void {
		add_filter(
			'wp_headless_module_enabled',
			static function ( $enabled, $slug ) {
				if ( 'migrate_db_compat' === $slug ) {
					return false;
				}
				return $enabled;
			},
			10,
			2
		);

		$this->assertFalse(
			MigrateDbCompat::is_enabled(),
			'is_enabled() must return false when disabled via wp_headless_module_enabled filter.'
		);
	}

	/**
	 * Test that init() registers the wpmdb_upload_dir filter.
	 */
	public function test_init_registers_upload_dir_filter(): void {
		$module = new MigrateDbCompat();
		$module->init();

		$this->assertGreaterThan(
			0,
			has_filter( 'wpmdb_upload_dir', [ $module, 'fix_upload_dir' ] ),
			'init() must register fix_upload_dir on wpmdb_upload_dir filter.'
		);
	}

	/**
	 * Test that init() registers the wpmdb_upload_info filter.
	 */
	public function test_init_registers_upload_info_filter(): void {
		$module = new MigrateDbCompat();
		$module->init();

		$this->assertGreaterThan(
			0,
			has_filter( 'wpmdb_upload_info', [ $module, 'fix_upload_info' ] ),
			'init() must register fix_upload_info on wpmdb_upload_info filter.'
		);
	}

	/**
	 * Test that init() registers the wpmdb_get_path filter.
	 */
	public function test_init_registers_get_path_filter(): void {
		$module = new MigrateDbCompat();
		$module->init();

		$this->assertGreaterThan(
			0,
			has_filter( 'wpmdb_get_path', [ $module, 'fix_content_path' ] ),
			'init() must register fix_content_path on wpmdb_get_path filter.'
		);
	}

	/**
	 * Test that fix_upload_dir() modifies basedir and path when WP_CONTENT_DIR is set.
	 */
	public function test_fix_upload_dir_modifies_paths(): void {
		$module = new MigrateDbCompat();

		// WP_CONTENT_DIR and ABSPATH are defined in WordPress test environment.
		$content_dir = WP_CONTENT_DIR;
		$upload_path = $content_dir . '/uploads';

		// Only test if the uploads directory exists.
		if ( ! is_dir( $upload_path ) ) {
			$this->markTestSkipped( 'Uploads directory does not exist at: ' . $upload_path );
		}

		$input = [
			'basedir' => '/original/basedir',
			'path'    => '/original/path',
			'subdir'  => '/2026/02',
		];

		$result = $module->fix_upload_dir( $input );

		$this->assertSame(
			$upload_path,
			$result['basedir'],
			'fix_upload_dir() must set basedir to WP_CONTENT_DIR/uploads.'
		);
		$this->assertSame(
			$upload_path . '/2026/02',
			$result['path'],
			'fix_upload_dir() must set path to basedir + subdir.'
		);
	}

	/**
	 * Test that fix_content_path() returns WP_CONTENT_DIR for content type.
	 */
	public function test_fix_content_path_returns_content_dir(): void {
		$module = new MigrateDbCompat();

		$result = $module->fix_content_path( '/original/path', 'content' );

		$this->assertSame(
			WP_CONTENT_DIR,
			$result,
			'fix_content_path() must return WP_CONTENT_DIR when type is "content".'
		);
	}

	/**
	 * Test that fix_content_path() returns original path for non-content type.
	 */
	public function test_fix_content_path_returns_original_for_non_content(): void {
		$module = new MigrateDbCompat();

		$result = $module->fix_content_path( '/original/path', 'plugins' );

		$this->assertSame(
			'/original/path',
			$result,
			'fix_content_path() must return original path when type is not "content".'
		);
	}
}
