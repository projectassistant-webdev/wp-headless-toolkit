<?php
/**
 * Tests for src/Modules/HeadCleanup/HeadCleanup.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use Tests\ProjectAssistant\HeadlessToolkit\HeadlessToolkitTestCase;
use ProjectAssistant\HeadlessToolkit\Modules\HeadCleanup\HeadCleanup;

/**
 * Tests for the HeadCleanup module.
 *
 * @group module
 * @group head-cleanup
 */
class HeadCleanupTest extends HeadlessToolkitTestCase {

	/**
	 * The module instance under test.
	 *
	 * @var HeadCleanup
	 */
	private HeadCleanup $module;

	/**
	 * Set up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->module = new HeadCleanup();

		// Ensure default WordPress head actions are registered for baseline testing.
		// WordPress core registers these during bootstrap; WPTestCase set_up()
		// reinitializes state, but we explicitly add them to guarantee baseline.
		$this->ensure_default_head_actions();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_filters_to_clean(): array {
		return [
			'wp_headless_module_enabled',
			'wp_headless_head_cleanup_removals',
		];
	}

	/**
	 * Clean up and restore defaults after each test.
	 */
	protected function tear_down(): void {
		// Re-add default head actions so subsequent tests have a clean baseline.
		$this->ensure_default_head_actions();

		parent::tear_down();
	}

	/**
	 * Ensure default WordPress head actions are registered.
	 *
	 * This method re-adds the core wp_head actions that HeadCleanup removes,
	 * guaranteeing a consistent baseline for each test.
	 */
	private function ensure_default_head_actions(): void {
		// These are the default WordPress head actions at their core priorities.
		if ( false === has_action( 'wp_head', 'wp_generator' ) ) {
			add_action( 'wp_head', 'wp_generator' );
		}
		if ( false === has_action( 'wp_head', 'wlwmanifest_link' ) ) {
			add_action( 'wp_head', 'wlwmanifest_link' );
		}
		if ( false === has_action( 'wp_head', 'rsd_link' ) ) {
			add_action( 'wp_head', 'rsd_link' );
		}
		if ( false === has_action( 'wp_head', 'wp_shortlink_wp_head' ) ) {
			add_action( 'wp_head', 'wp_shortlink_wp_head' );
		}
		if ( false === has_action( 'wp_head', 'rest_output_link_wp_head' ) ) {
			add_action( 'wp_head', 'rest_output_link_wp_head' );
		}
		if ( false === has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) ) {
			add_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}
		if ( false === has_action( 'wp_head', 'print_emoji_detection_script' ) ) {
			add_action( 'wp_head', 'print_emoji_detection_script', 7 );
		}
		if ( false === has_action( 'wp_print_styles', 'print_emoji_styles' ) ) {
			add_action( 'wp_print_styles', 'print_emoji_styles' );
		}
		if ( false === has_action( 'admin_print_scripts', 'print_emoji_detection_script' ) ) {
			add_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		}
		if ( false === has_action( 'admin_print_styles', 'print_emoji_styles' ) ) {
			add_action( 'admin_print_styles', 'print_emoji_styles' );
		}
		if ( false === has_action( 'wp_head', 'feed_links' ) ) {
			add_action( 'wp_head', 'feed_links', 2 );
		}
		if ( false === has_action( 'wp_head', 'feed_links_extra' ) ) {
			add_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}

	// -------------------------------------------------------------------------
	// 1. ModuleInterface Contract Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_slug() returns the expected slug.
	 */
	public function test_get_slug_returns_head_cleanup(): void {
		$this->assertSame(
			'head_cleanup',
			HeadCleanup::get_slug(),
			'get_slug() must return "head_cleanup".'
		);
	}

	/**
	 * Test that get_name() returns the expected human-readable name.
	 */
	public function test_get_name_returns_head_cleanup(): void {
		$this->assertSame(
			'Head Cleanup',
			HeadCleanup::get_name(),
			'get_name() must return "Head Cleanup".'
		);
	}

	/**
	 * Test that is_enabled() returns true by default.
	 */
	public function test_is_enabled_returns_true_by_default(): void {
		$this->assertTrue(
			HeadCleanup::is_enabled(),
			'is_enabled() must return true when module toggle is on by default.'
		);
	}

	/**
	 * Test that is_enabled() returns false when disabled via filter.
	 */
	public function test_is_enabled_false_when_disabled_via_filter(): void {
		add_filter(
			'wp_headless_module_enabled',
			static function ( $enabled, $slug ) {
				if ( 'head_cleanup' === $slug ) {
					return false;
				}
				return $enabled;
			},
			10,
			2
		);

		$this->assertFalse(
			HeadCleanup::is_enabled(),
			'is_enabled() must return false when disabled via wp_headless_module_enabled filter.'
		);
	}

	// -------------------------------------------------------------------------
	// 2. Head Action Removal Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that init() removes wp_generator from wp_head.
	 */
	public function test_init_removes_wp_generator(): void {
		$this->assertNotFalse(
			has_action( 'wp_head', 'wp_generator' ),
			'wp_generator should be registered by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', 'wp_generator' ),
			'wp_generator should be removed after init().'
		);
	}

	/**
	 * Test that init() removes wlwmanifest_link from wp_head.
	 */
	public function test_init_removes_wlwmanifest_link(): void {
		$this->assertNotFalse(
			has_action( 'wp_head', 'wlwmanifest_link' ),
			'wlwmanifest_link should be registered by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', 'wlwmanifest_link' ),
			'wlwmanifest_link should be removed after init().'
		);
	}

	/**
	 * Test that init() removes rsd_link from wp_head.
	 */
	public function test_init_removes_rsd_link(): void {
		$this->assertNotFalse(
			has_action( 'wp_head', 'rsd_link' ),
			'rsd_link should be registered by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', 'rsd_link' ),
			'rsd_link should be removed after init().'
		);
	}

	/**
	 * Test that init() removes wp_shortlink_wp_head from wp_head.
	 */
	public function test_init_removes_wp_shortlink(): void {
		$this->assertNotFalse(
			has_action( 'wp_head', 'wp_shortlink_wp_head' ),
			'wp_shortlink_wp_head should be registered by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', 'wp_shortlink_wp_head' ),
			'wp_shortlink_wp_head should be removed after init().'
		);
	}

	/**
	 * Test that init() removes rest_output_link_wp_head from wp_head.
	 */
	public function test_init_removes_rest_output_link(): void {
		$this->assertNotFalse(
			has_action( 'wp_head', 'rest_output_link_wp_head' ),
			'rest_output_link_wp_head should be registered by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', 'rest_output_link_wp_head' ),
			'rest_output_link_wp_head should be removed after init().'
		);
	}

	/**
	 * Test that init() removes wp_oembed_add_discovery_links from wp_head.
	 */
	public function test_init_removes_oembed_discovery(): void {
		$this->assertNotFalse(
			has_action( 'wp_head', 'wp_oembed_add_discovery_links' ),
			'wp_oembed_add_discovery_links should be registered by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', 'wp_oembed_add_discovery_links' ),
			'wp_oembed_add_discovery_links should be removed after init().'
		);
	}

	/**
	 * Test that init() removes print_emoji_detection_script from wp_head.
	 */
	public function test_init_removes_emoji_detection_script(): void {
		$this->assertNotFalse(
			has_action( 'wp_head', 'print_emoji_detection_script' ),
			'print_emoji_detection_script should be registered on wp_head by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', 'print_emoji_detection_script' ),
			'print_emoji_detection_script should be removed from wp_head after init().'
		);
	}

	/**
	 * Test that init() removes print_emoji_styles from wp_print_styles.
	 */
	public function test_init_removes_emoji_styles(): void {
		$this->assertNotFalse(
			has_action( 'wp_print_styles', 'print_emoji_styles' ),
			'print_emoji_styles should be registered on wp_print_styles by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_print_styles', 'print_emoji_styles' ),
			'print_emoji_styles should be removed from wp_print_styles after init().'
		);
	}

	/**
	 * Test that init() removes print_emoji_detection_script from admin_print_scripts.
	 */
	public function test_init_removes_admin_emoji_scripts(): void {
		$this->assertNotFalse(
			has_action( 'admin_print_scripts', 'print_emoji_detection_script' ),
			'print_emoji_detection_script should be registered on admin_print_scripts by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'admin_print_scripts', 'print_emoji_detection_script' ),
			'print_emoji_detection_script should be removed from admin_print_scripts after init().'
		);
	}

	/**
	 * Test that init() removes print_emoji_styles from admin_print_styles.
	 */
	public function test_init_removes_admin_emoji_styles(): void {
		$this->assertNotFalse(
			has_action( 'admin_print_styles', 'print_emoji_styles' ),
			'print_emoji_styles should be registered on admin_print_styles by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'admin_print_styles', 'print_emoji_styles' ),
			'print_emoji_styles should be removed from admin_print_styles after init().'
		);
	}

	/**
	 * Test that init() removes feed_links from wp_head.
	 */
	public function test_init_removes_feed_links(): void {
		$this->assertNotFalse(
			has_action( 'wp_head', 'feed_links' ),
			'feed_links should be registered on wp_head by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', 'feed_links' ),
			'feed_links should be removed from wp_head after init().'
		);
	}

	/**
	 * Test that init() removes feed_links_extra from wp_head.
	 */
	public function test_init_removes_feed_links_extra(): void {
		$this->assertNotFalse(
			has_action( 'wp_head', 'feed_links_extra' ),
			'feed_links_extra should be registered on wp_head by default.'
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', 'feed_links_extra' ),
			'feed_links_extra should be removed from wp_head after init().'
		);
	}

	// -------------------------------------------------------------------------
	// 3. Extensibility Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that init() processes additional removals from the filter.
	 */
	public function test_init_processes_additional_removals_filter(): void {
		// Add a custom action to wp_head that should be removed via the filter.
		$custom_callback = static function () {
			echo '<!-- custom head output -->';
		};
		add_action( 'wp_head', $custom_callback, 15 );

		$this->assertNotFalse(
			has_action( 'wp_head', $custom_callback ),
			'Custom wp_head action should be registered before init().'
		);

		// Use the filter to tell HeadCleanup to remove our custom action.
		add_filter(
			'wp_headless_head_cleanup_removals',
			static function () use ( $custom_callback ): array {
				return [
					[ 'wp_head', $custom_callback, 15 ],
				];
			}
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', $custom_callback ),
			'Custom wp_head action should be removed after init() via wp_headless_head_cleanup_removals filter.'
		);
	}

	/**
	 * Test that init() handles empty additional removals without errors.
	 */
	public function test_init_handles_empty_additional_removals(): void {
		add_filter(
			'wp_headless_head_cleanup_removals',
			static function (): array {
				return [];
			}
		);

		// Should not throw any errors or warnings.
		$this->module->init();

		// Verify standard removals still happened.
		$this->assertFalse(
			has_action( 'wp_head', 'wp_generator' ),
			'wp_generator should still be removed when additional removals filter returns empty array.'
		);
	}

	/**
	 * Test that additional removal without priority defaults to priority 10.
	 */
	public function test_init_handles_additional_removal_without_priority(): void {
		// Add a custom action at default priority (10).
		$custom_callback = static function () {
			echo '<!-- no-priority custom output -->';
		};
		add_action( 'wp_head', $custom_callback ); // Default priority 10.

		$this->assertNotFalse(
			has_action( 'wp_head', $custom_callback ),
			'Custom wp_head action at default priority should be registered before init().'
		);

		// Use the filter without specifying priority (only [action, callback]).
		add_filter(
			'wp_headless_head_cleanup_removals',
			static function () use ( $custom_callback ): array {
				return [
					[ 'wp_head', $custom_callback ], // No priority = defaults to 10.
				];
			}
		);

		$this->module->init();

		$this->assertFalse(
			has_action( 'wp_head', $custom_callback ),
			'Custom wp_head action should be removed using default priority 10 when no priority specified in filter.'
		);
	}
}
