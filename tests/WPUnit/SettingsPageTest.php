<?php
/**
 * Tests for src/Admin/SettingsPage.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use ProjectAssistant\HeadlessToolkit\Admin\SettingsPage;
use ProjectAssistant\HeadlessToolkit\Main;

/**
 * Tests for the SettingsPage admin page.
 */
class SettingsPageTest extends WPTestCase {

	/**
	 * The SettingsPage instance under test.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Environment variables to clean up in tear_down.
	 *
	 * @var string[]
	 */
	private array $env_vars_to_clean = [];

	/**
	 * Set up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->settings_page = new SettingsPage();
	}

	/**
	 * Clean up after each test.
	 */
	protected function tear_down(): void {
		// Clean up env vars.
		foreach ( $this->env_vars_to_clean as $key ) {
			putenv( $key );
		}
		$this->env_vars_to_clean = [];

		// Remove filters used in tests.
		remove_all_filters( 'wp_headless_module_classes' );
		remove_all_filters( 'wp_headless_module_enabled' );

		parent::tear_down();
	}

	/**
	 * Helper to set an env var and register it for cleanup.
	 *
	 * @param string $key   The env var name.
	 * @param string $value The env var value.
	 */
	private function set_env( string $key, string $value ): void {
		putenv( "{$key}={$value}" );
		$this->env_vars_to_clean[] = $key;
	}

	// -------------------------------------------------------------------------
	// 1. Menu Registration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that init() registers the admin_menu action.
	 */
	public function test_init_registers_admin_menu_action(): void {
		$this->settings_page->init();

		$this->assertNotFalse(
			has_action( 'admin_menu', [ $this->settings_page, 'add_settings_page' ] ),
			'init() should register add_settings_page on admin_menu action'
		);
	}

	/**
	 * Test that add_settings_page registers an options page.
	 */
	public function test_add_settings_page_registers_options_page(): void {
		// Set current user to admin so add_options_page succeeds.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		set_current_screen( 'dashboard' );

		$this->settings_page->add_settings_page();

		// The menu slug should be registered in the global $submenu.
		global $submenu;
		$found = false;
		if ( isset( $submenu['options-general.php'] ) ) {
			foreach ( $submenu['options-general.php'] as $item ) {
				if ( 'wp-headless-toolkit' === $item[2] ) {
					$found = true;
					break;
				}
			}
		}

		$this->assertTrue( $found, 'Settings page should be registered under options-general.php submenu' );
	}

	/**
	 * Test that the settings page requires manage_options capability.
	 */
	public function test_settings_page_requires_manage_options_capability(): void {
		// Create a subscriber (no manage_options).
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );
		set_current_screen( 'dashboard' );

		$this->settings_page->add_settings_page();

		// The page should be registered but the subscriber should not have access.
		// Check that the menu was added with manage_options capability.
		global $_wp_submenu_nopriv;

		// For a subscriber, the page should be in the no-priv list.
		$has_no_access = isset( $_wp_submenu_nopriv['options-general.php']['wp-headless-toolkit'] );

		$this->assertTrue(
			$has_no_access,
			'Subscriber should not have access to the settings page (requires manage_options)'
		);
	}

	// -------------------------------------------------------------------------
	// 2. Module Status Data Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_module_statuses returns all registered modules.
	 */
	public function test_get_module_statuses_returns_all_registered_modules(): void {
		// Ensure Main is initialized so modules are registered.
		Main::instance();

		$statuses = $this->settings_page->get_module_statuses();

		$this->assertCount( 5, $statuses, 'Should return data for all 5 registered modules' );
	}

	/**
	 * Test that get_module_statuses includes disabled modules.
	 */
	public function test_get_module_statuses_includes_disabled_modules(): void {
		// Disable a module via env var.
		$this->set_env( 'WP_HEADLESS_DISABLE_HEAD_CLEANUP', 'true' );
		Main::instance();

		$statuses = $this->settings_page->get_module_statuses();

		// Find the head_cleanup module.
		$head_cleanup = null;
		foreach ( $statuses as $status ) {
			if ( 'head_cleanup' === $status['slug'] ) {
				$head_cleanup = $status;
				break;
			}
		}

		$this->assertNotNull( $head_cleanup, 'Disabled module should appear in statuses' );
		$this->assertFalse( $head_cleanup['enabled'], 'Disabled module should have enabled => false' );
	}

	/**
	 * Test that enabled modules have enabled => true.
	 */
	public function test_get_module_statuses_shows_enabled_status(): void {
		Main::instance();

		$statuses = $this->settings_page->get_module_statuses();

		// Find head_cleanup module (enabled by default, no config requirements).
		$head_cleanup = null;
		foreach ( $statuses as $status ) {
			if ( 'head_cleanup' === $status['slug'] ) {
				$head_cleanup = $status;
				break;
			}
		}

		$this->assertNotNull( $head_cleanup, 'Head Cleanup module should appear in statuses' );
		$this->assertTrue( $head_cleanup['enabled'], 'Head Cleanup module should be enabled by default' );
	}

	/**
	 * Test that disabled modules have enabled => false.
	 */
	public function test_get_module_statuses_shows_disabled_status(): void {
		$this->set_env( 'WP_HEADLESS_DISABLE_REVALIDATION', 'true' );
		Main::instance();

		$statuses = $this->settings_page->get_module_statuses();

		$revalidation = null;
		foreach ( $statuses as $status ) {
			if ( 'revalidation' === $status['slug'] ) {
				$revalidation = $status;
				break;
			}
		}

		$this->assertNotNull( $revalidation, 'Disabled revalidation module should appear in statuses' );
		$this->assertFalse( $revalidation['enabled'], 'Revalidation module should show disabled when env var set' );
	}

	/**
	 * Test that each module status includes its name.
	 */
	public function test_get_module_statuses_includes_module_name(): void {
		Main::instance();

		$statuses = $this->settings_page->get_module_statuses();

		foreach ( $statuses as $status ) {
			$this->assertArrayHasKey( 'name', $status, 'Each module status should have a name field' );
			$this->assertNotEmpty( $status['name'], 'Module name should not be empty' );
		}
	}

	/**
	 * Test that each module status includes its slug.
	 */
	public function test_get_module_statuses_includes_module_slug(): void {
		Main::instance();

		$statuses = $this->settings_page->get_module_statuses();

		foreach ( $statuses as $status ) {
			$this->assertArrayHasKey( 'slug', $status, 'Each module status should have a slug field' );
			$this->assertNotEmpty( $status['slug'], 'Module slug should not be empty' );
		}
	}

	// -------------------------------------------------------------------------
	// 3. Environment Variable Display Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_env_config returns entries for all documented env vars.
	 */
	public function test_get_env_config_returns_documented_vars(): void {
		$config = $this->settings_page->get_env_config();

		// Extract keys.
		$keys = array_column( $config, 'key' );

		$expected_keys = [
			'NEXTJS_REVALIDATION_URL',
			'NEXTJS_REVALIDATION_SECRET',
			'HEADLESS_FRONTEND_URL',
			'HEADLESS_CORS_ORIGINS',
			'HEADLESS_PREVIEW_SECRET',
			'WP_HEADLESS_DISABLE_REVALIDATION',
			'WP_HEADLESS_DISABLE_REST_SECURITY',
			'WP_HEADLESS_DISABLE_FRONTEND_REDIRECT',
			'WP_HEADLESS_DISABLE_MIGRATE_DB_COMPAT',
			'WP_HEADLESS_DISABLE_HEAD_CLEANUP',
			'WP_HEADLESS_DISABLE_CORS',
			'WP_HEADLESS_DISABLE_SECURITY_HEADERS',
		];

		foreach ( $expected_keys as $expected_key ) {
			$this->assertContains( $expected_key, $keys, "Expected env var {$expected_key} should be in the config" );
		}

		$this->assertCount( 12, $config, 'Should return 12 documented environment variables' );
	}

	/**
	 * Test that is_set is true when env var has a value.
	 */
	public function test_get_env_config_shows_set_status_when_configured(): void {
		$this->set_env( 'NEXTJS_REVALIDATION_URL', 'https://example.com/api/revalidate' );

		$config = $this->settings_page->get_env_config();

		$reval_url = null;
		foreach ( $config as $entry ) {
			if ( 'NEXTJS_REVALIDATION_URL' === $entry['key'] ) {
				$reval_url = $entry;
				break;
			}
		}

		$this->assertNotNull( $reval_url, 'NEXTJS_REVALIDATION_URL should be in config' );
		$this->assertTrue( $reval_url['is_set'], 'is_set should be true when env var is configured' );
	}

	/**
	 * Test that is_set is false when env var is not set.
	 */
	public function test_get_env_config_shows_not_set_when_unconfigured(): void {
		// Make sure the var is NOT set.
		putenv( 'NEXTJS_REVALIDATION_URL' );

		$config = $this->settings_page->get_env_config();

		$reval_url = null;
		foreach ( $config as $entry ) {
			if ( 'NEXTJS_REVALIDATION_URL' === $entry['key'] ) {
				$reval_url = $entry;
				break;
			}
		}

		$this->assertNotNull( $reval_url, 'NEXTJS_REVALIDATION_URL should be in config' );
		$this->assertFalse( $reval_url['is_set'], 'is_set should be false when env var is not configured' );
	}

	/**
	 * Test that secret values are masked.
	 */
	public function test_get_env_config_masks_secret_values(): void {
		$this->set_env( 'NEXTJS_REVALIDATION_SECRET', 'my-super-secret-value' );

		$config = $this->settings_page->get_env_config();

		$secret = null;
		foreach ( $config as $entry ) {
			if ( 'NEXTJS_REVALIDATION_SECRET' === $entry['key'] ) {
				$secret = $entry;
				break;
			}
		}

		$this->assertNotNull( $secret, 'NEXTJS_REVALIDATION_SECRET should be in config' );
		$this->assertSame( '********', $secret['value'], 'Secret value should be masked as ********' );
		$this->assertTrue( $secret['is_secret'], 'NEXTJS_REVALIDATION_SECRET should be marked as secret' );
	}

	/**
	 * Test that non-secret values are shown as-is.
	 */
	public function test_get_env_config_shows_non_secret_values(): void {
		$this->set_env( 'HEADLESS_FRONTEND_URL', 'https://example.com' );

		$config = $this->settings_page->get_env_config();

		$frontend_url = null;
		foreach ( $config as $entry ) {
			if ( 'HEADLESS_FRONTEND_URL' === $entry['key'] ) {
				$frontend_url = $entry;
				break;
			}
		}

		$this->assertNotNull( $frontend_url, 'HEADLESS_FRONTEND_URL should be in config' );
		$this->assertSame( 'https://example.com', $frontend_url['value'], 'Non-secret value should be shown as-is' );
		$this->assertFalse( $frontend_url['is_secret'], 'HEADLESS_FRONTEND_URL should not be marked as secret' );
	}

	// -------------------------------------------------------------------------
	// 4. Secret Masking Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that is_secret_key returns true for secret keys.
	 */
	public function test_is_secret_key_returns_true_for_secret_keys(): void {
		// Use reflection to access private method.
		$reflection = new \ReflectionMethod( SettingsPage::class, 'is_secret_key' );
		$reflection->setAccessible( true );

		$this->assertTrue(
			$reflection->invoke( $this->settings_page, 'NEXTJS_REVALIDATION_SECRET' ),
			'Key containing SECRET should be identified as secret'
		);
		$this->assertTrue(
			$reflection->invoke( $this->settings_page, 'HEADLESS_PREVIEW_SECRET' ),
			'Key containing SECRET should be identified as secret'
		);
	}

	/**
	 * Test that is_secret_key returns false for non-secret keys.
	 */
	public function test_is_secret_key_returns_false_for_non_secret_keys(): void {
		$reflection = new \ReflectionMethod( SettingsPage::class, 'is_secret_key' );
		$reflection->setAccessible( true );

		$this->assertFalse(
			$reflection->invoke( $this->settings_page, 'HEADLESS_FRONTEND_URL' ),
			'Key not containing SECRET should not be identified as secret'
		);
		$this->assertFalse(
			$reflection->invoke( $this->settings_page, 'WP_HEADLESS_DISABLE_REVALIDATION' ),
			'Key not containing SECRET should not be identified as secret'
		);
	}

	/**
	 * Test that mask_value masks secret key values.
	 */
	public function test_mask_value_masks_secret(): void {
		$reflection = new \ReflectionMethod( SettingsPage::class, 'mask_value' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $this->settings_page, 'NEXTJS_REVALIDATION_SECRET', 'my-secret' );

		$this->assertSame( '********', $result, 'mask_value should return ******** for secret keys' );
	}

	/**
	 * Test that mask_value returns "Not configured" for empty values.
	 */
	public function test_mask_value_shows_not_configured_for_empty(): void {
		$reflection = new \ReflectionMethod( SettingsPage::class, 'mask_value' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $this->settings_page, 'NEXTJS_REVALIDATION_URL', '' );

		$this->assertSame( 'Not configured', $result, 'mask_value should return Not configured for empty values' );
	}

	/**
	 * Test that mask_value returns actual value for non-secret keys.
	 */
	public function test_mask_value_shows_value_for_non_secret(): void {
		$reflection = new \ReflectionMethod( SettingsPage::class, 'mask_value' );
		$reflection->setAccessible( true );

		$result = $reflection->invoke( $this->settings_page, 'HEADLESS_FRONTEND_URL', 'https://example.com' );

		$this->assertSame( 'https://example.com', $result, 'mask_value should return actual value for non-secret keys' );
	}

	// -------------------------------------------------------------------------
	// 5. Page Rendering Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that render_page outputs the plugin name.
	 */
	public function test_render_page_outputs_plugin_name(): void {
		Main::instance();

		ob_start();
		$this->settings_page->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'WP Headless Toolkit', $output, 'Rendered page should contain plugin name' );
	}

	/**
	 * Test that render_page contains a module status table.
	 */
	public function test_render_page_contains_module_status_table(): void {
		Main::instance();

		ob_start();
		$this->settings_page->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Module Status', $output, 'Rendered page should contain Module Status heading' );
		$this->assertStringContainsString( '<table', $output, 'Rendered page should contain a table element' );
	}

	/**
	 * Test that render_page contains environment config section.
	 */
	public function test_render_page_contains_env_config_section(): void {
		Main::instance();

		ob_start();
		$this->settings_page->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Environment Configuration', $output, 'Rendered page should contain Environment Configuration heading' );
	}

	/**
	 * Test that render_page contains a documentation link.
	 */
	public function test_render_page_contains_documentation_link(): void {
		Main::instance();

		ob_start();
		$this->settings_page->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Documentation', $output, 'Rendered page should contain Documentation heading' );
		$this->assertStringContainsString(
			'https://bitbucket.org/projectassistant/wordpress-headless-toolkit',
			$output,
			'Rendered page should contain documentation link'
		);
	}

	/**
	 * Test that render_page shows enabled modules.
	 */
	public function test_render_page_shows_enabled_modules(): void {
		Main::instance();

		ob_start();
		$this->settings_page->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Enabled', $output, 'Rendered page should show Enabled text for active modules' );
	}

	/**
	 * Test that render_page shows disabled modules.
	 */
	public function test_render_page_shows_disabled_modules(): void {
		$this->set_env( 'WP_HEADLESS_DISABLE_HEAD_CLEANUP', 'true' );
		Main::instance();

		ob_start();
		$this->settings_page->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Disabled', $output, 'Rendered page should show Disabled text for inactive modules' );
	}

	// -------------------------------------------------------------------------
	// 6. Main.php Integration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_registered_module_classes returns all 5 module classes.
	 */
	public function test_get_registered_module_classes_returns_all_classes(): void {
		$main    = Main::instance();
		$classes = $main->get_registered_module_classes();

		$this->assertCount( 5, $classes, 'Should return all 5 registered module class names' );
	}

	/**
	 * Test that get_registered_module_classes respects the filter.
	 */
	public function test_get_registered_module_classes_respects_filter(): void {
		$main = Main::instance();

		// Add a filter to remove a module.
		add_filter(
			'wp_headless_module_classes',
			function ( array $classes ): array {
				return array_filter(
					$classes,
					function ( string $class ): bool {
						return false === strpos( $class, 'HeadCleanup' );
					}
				);
			}
		);

		$classes = $main->get_registered_module_classes();

		$this->assertCount( 4, $classes, 'Filter should be able to remove modules from the list' );

		// Verify HeadCleanup is not in the list.
		foreach ( $classes as $class ) {
			$this->assertStringNotContainsString( 'HeadCleanup', $class, 'HeadCleanup should be filtered out' );
		}
	}
}
