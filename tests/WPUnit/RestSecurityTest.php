<?php
/**
 * Tests for src/Modules/RestSecurity/RestSecurity.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use ProjectAssistant\HeadlessToolkit\Modules\RestSecurity\RestSecurity;

/**
 * Tests for the RestSecurity module.
 */
class RestSecurityTest extends WPTestCase {

	/**
	 * The module instance under test.
	 *
	 * @var RestSecurity
	 */
	private RestSecurity $module;

	/**
	 * Original $GLOBALS['wp'] value for restoration.
	 *
	 * @var mixed
	 */
	private $original_wp_global;

	/**
	 * Set up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->module             = new RestSecurity();
		$this->original_wp_global = $GLOBALS['wp'] ?? null;

		// Ensure $GLOBALS['wp'] exists with query_vars for route testing.
		if ( ! isset( $GLOBALS['wp'] ) ) {
			$GLOBALS['wp'] = new \stdClass();
		}
		if ( ! isset( $GLOBALS['wp']->query_vars ) ) {
			$GLOBALS['wp']->query_vars = [];
		}
	}

	/**
	 * Clean up filters and globals after each test.
	 */
	protected function tear_down(): void {
		// Restore the original $GLOBALS['wp'].
		if ( null === $this->original_wp_global ) {
			unset( $GLOBALS['wp'] );
		} else {
			$GLOBALS['wp'] = $this->original_wp_global;
		}

		// Reset current user.
		wp_set_current_user( 0 );

		// Remove all filters used in tests.
		remove_all_filters( 'wp_headless_module_enabled' );
		remove_all_filters( 'wp_headless_rest_blocked_prefixes' );
		remove_all_filters( 'wp_headless_rest_allowed_prefixes' );
		remove_all_filters( 'rest_endpoints' );
		remove_all_filters( 'rest_authentication_errors' );

		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// 1. ModuleInterface Contract Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_slug() returns the expected slug.
	 */
	public function test_get_slug_returns_rest_security(): void {
		$this->assertSame(
			'rest_security',
			RestSecurity::get_slug(),
			'get_slug() must return "rest_security".'
		);
	}

	/**
	 * Test that get_name() returns the expected human-readable name.
	 */
	public function test_get_name_returns_rest_api_security(): void {
		$this->assertSame(
			'REST API Security',
			RestSecurity::get_name(),
			'get_name() must return "REST API Security".'
		);
	}

	/**
	 * Test that is_enabled() returns true by default.
	 */
	public function test_is_enabled_returns_true_by_default(): void {
		$this->assertTrue(
			RestSecurity::is_enabled(),
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
				if ( 'rest_security' === $slug ) {
					return false;
				}
				return $enabled;
			},
			10,
			2
		);

		$this->assertFalse(
			RestSecurity::is_enabled(),
			'is_enabled() must return false when disabled via wp_headless_module_enabled filter.'
		);
	}

	// -------------------------------------------------------------------------
	// 2. Hook Registration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that init() registers the rest_endpoints filter at priority 999.
	 */
	public function test_init_registers_rest_endpoints_filter(): void {
		$this->module->init();

		$this->assertSame(
			999,
			has_filter( 'rest_endpoints', [ $this->module, 'filter_endpoints' ] ),
			'init() must register filter_endpoints on rest_endpoints at priority 999.'
		);
	}

	/**
	 * Test that init() registers the rest_authentication_errors filter at priority 99.
	 */
	public function test_init_registers_rest_authentication_errors_filter(): void {
		$this->module->init();

		$this->assertSame(
			99,
			has_filter( 'rest_authentication_errors', [ $this->module, 'restrict_unauthenticated_access' ] ),
			'init() must register restrict_unauthenticated_access on rest_authentication_errors at priority 99.'
		);
	}

	// -------------------------------------------------------------------------
	// 3. filter_endpoints() Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that filter_endpoints() removes the comments endpoint.
	 */
	public function test_filter_endpoints_removes_comments_endpoint(): void {
		$endpoints = [
			'/wp/v2/comments'      => [ 'handler' ],
			'/wp/v2/comments/(?P<id>[\\d]+)' => [ 'handler' ],
			'/wp/v2/posts'         => [ 'handler' ],
		];

		$result = $this->module->filter_endpoints( $endpoints );

		$this->assertArrayNotHasKey(
			'/wp/v2/comments',
			$result,
			'filter_endpoints() must remove endpoints starting with /wp/v2/comments.'
		);
		$this->assertArrayNotHasKey(
			'/wp/v2/comments/(?P<id>[\\d]+)',
			$result,
			'filter_endpoints() must remove sub-endpoints of /wp/v2/comments.'
		);
	}

	/**
	 * Test that filter_endpoints() removes the users endpoint.
	 */
	public function test_filter_endpoints_removes_users_endpoint(): void {
		$endpoints = [
			'/wp/v2/users'         => [ 'handler' ],
			'/wp/v2/users/(?P<id>[\\d]+)' => [ 'handler' ],
			'/wp/v2/posts'         => [ 'handler' ],
		];

		$result = $this->module->filter_endpoints( $endpoints );

		$this->assertArrayNotHasKey(
			'/wp/v2/users',
			$result,
			'filter_endpoints() must remove endpoints starting with /wp/v2/users.'
		);
		$this->assertArrayNotHasKey(
			'/wp/v2/users/(?P<id>[\\d]+)',
			$result,
			'filter_endpoints() must remove sub-endpoints of /wp/v2/users.'
		);
	}

	/**
	 * Test that filter_endpoints() removes the search endpoint.
	 */
	public function test_filter_endpoints_removes_search_endpoint(): void {
		$endpoints = [
			'/wp/v2/search'        => [ 'handler' ],
			'/wp/v2/posts'         => [ 'handler' ],
		];

		$result = $this->module->filter_endpoints( $endpoints );

		$this->assertArrayNotHasKey(
			'/wp/v2/search',
			$result,
			'filter_endpoints() must remove endpoints starting with /wp/v2/search.'
		);
	}

	/**
	 * Test that filter_endpoints() preserves allowed endpoints.
	 */
	public function test_filter_endpoints_preserves_allowed_endpoints(): void {
		$endpoints = [
			'/wp/v2/posts'         => [ 'handler' ],
			'/wp/v2/pages'         => [ 'handler' ],
			'/wp/v2/categories'    => [ 'handler' ],
			'/wp/v2/comments'      => [ 'handler' ],
		];

		$result = $this->module->filter_endpoints( $endpoints );

		$this->assertArrayHasKey(
			'/wp/v2/posts',
			$result,
			'filter_endpoints() must preserve /wp/v2/posts.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/pages',
			$result,
			'filter_endpoints() must preserve /wp/v2/pages.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/categories',
			$result,
			'filter_endpoints() must preserve /wp/v2/categories.'
		);
	}

	/**
	 * Test that filter_endpoints() bypasses filtering for admin users.
	 */
	public function test_filter_endpoints_bypasses_for_admin_user(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$endpoints = [
			'/wp/v2/comments'      => [ 'handler' ],
			'/wp/v2/users'         => [ 'handler' ],
			'/wp/v2/search'        => [ 'handler' ],
			'/wp/v2/posts'         => [ 'handler' ],
		];

		$result = $this->module->filter_endpoints( $endpoints );

		$this->assertSame(
			$endpoints,
			$result,
			'filter_endpoints() must return all endpoints unchanged for admin users.'
		);
	}

	/**
	 * Test that filter_endpoints() still filters for subscriber (non-admin) users.
	 */
	public function test_filter_endpoints_filters_for_subscriber_user(): void {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$endpoints = [
			'/wp/v2/comments'      => [ 'handler' ],
			'/wp/v2/users'         => [ 'handler' ],
			'/wp/v2/search'        => [ 'handler' ],
			'/wp/v2/posts'         => [ 'handler' ],
		];

		$result = $this->module->filter_endpoints( $endpoints );

		$this->assertArrayNotHasKey(
			'/wp/v2/comments',
			$result,
			'filter_endpoints() must still remove blocked endpoints for subscriber users.'
		);
		$this->assertArrayNotHasKey(
			'/wp/v2/users',
			$result,
			'filter_endpoints() must still remove blocked endpoints for subscriber users.'
		);
		$this->assertArrayNotHasKey(
			'/wp/v2/search',
			$result,
			'filter_endpoints() must still remove blocked endpoints for subscriber users.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/posts',
			$result,
			'filter_endpoints() must preserve allowed endpoints for subscriber users.'
		);
	}

	/**
	 * Test that filter_endpoints() respects the blocked prefixes filter to add custom prefixes.
	 */
	public function test_filter_endpoints_respects_blocked_prefixes_filter(): void {
		add_filter(
			'wp_headless_rest_blocked_prefixes',
			static function ( array $prefixes ): array {
				$prefixes[] = '/wp/v2/tags';
				return $prefixes;
			}
		);

		$endpoints = [
			'/wp/v2/tags'          => [ 'handler' ],
			'/wp/v2/posts'         => [ 'handler' ],
		];

		$result = $this->module->filter_endpoints( $endpoints );

		$this->assertArrayNotHasKey(
			'/wp/v2/tags',
			$result,
			'filter_endpoints() must remove custom prefixes added via wp_headless_rest_blocked_prefixes filter.'
		);
		$this->assertArrayHasKey(
			'/wp/v2/posts',
			$result,
			'filter_endpoints() must preserve non-blocked endpoints.'
		);
	}

	/**
	 * Test that filter_endpoints() respects the blocked prefixes filter to remove default prefixes.
	 */
	public function test_filter_endpoints_respects_blocked_prefixes_filter_removal(): void {
		add_filter(
			'wp_headless_rest_blocked_prefixes',
			static function (): array {
				return [];
			}
		);

		$endpoints = [
			'/wp/v2/comments'      => [ 'handler' ],
			'/wp/v2/users'         => [ 'handler' ],
			'/wp/v2/search'        => [ 'handler' ],
			'/wp/v2/posts'         => [ 'handler' ],
		];

		$result = $this->module->filter_endpoints( $endpoints );

		$this->assertSame(
			$endpoints,
			$result,
			'filter_endpoints() must not block any endpoints when blocked_prefixes filter returns empty array.'
		);
	}

	// -------------------------------------------------------------------------
	// 4. restrict_unauthenticated_access() Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that restrict passes through when result is not null.
	 */
	public function test_restrict_passes_through_when_result_not_null(): void {
		$error = new \WP_Error( 'rest_forbidden', 'Already errored.' );

		$result = $this->module->restrict_unauthenticated_access( $error );

		$this->assertSame(
			$error,
			$result,
			'restrict_unauthenticated_access() must return $result unchanged when $result is not null.'
		);
	}

	/**
	 * Test that restrict passes through for logged-in users.
	 */
	public function test_restrict_passes_through_for_logged_in_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$result = $this->module->restrict_unauthenticated_access( null );

		$this->assertNull(
			$result,
			'restrict_unauthenticated_access() must return $result unchanged for logged-in users.'
		);
	}

	/**
	 * Test that restrict allows site-health route.
	 */
	public function test_restrict_allows_site_health_route(): void {
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp-site-health/v1/tests/background-updates';

		$result = $this->module->restrict_unauthenticated_access( null );

		$this->assertNull(
			$result,
			'restrict_unauthenticated_access() must return null (allow) for /wp-site-health/ routes.'
		);
	}

	/**
	 * Test that restrict allows settings route.
	 */
	public function test_restrict_allows_settings_route(): void {
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/settings';

		$result = $this->module->restrict_unauthenticated_access( null );

		$this->assertNull(
			$result,
			'restrict_unauthenticated_access() must return null (allow) for /wp/v2/settings route.'
		);
	}

	/**
	 * Test that restrict allows wpgraphql route.
	 */
	public function test_restrict_allows_wpgraphql_route(): void {
		$GLOBALS['wp']->query_vars['rest_route'] = '/wpgraphql/v1/introspection';

		$result = $this->module->restrict_unauthenticated_access( null );

		$this->assertNull(
			$result,
			'restrict_unauthenticated_access() must return null (allow) for /wpgraphql/ routes.'
		);
	}

	/**
	 * Test that restrict allows batch route.
	 */
	public function test_restrict_allows_batch_route(): void {
		$GLOBALS['wp']->query_vars['rest_route'] = '/batch/v1';

		$result = $this->module->restrict_unauthenticated_access( null );

		$this->assertNull(
			$result,
			'restrict_unauthenticated_access() must return null (allow) for /batch/v1 route.'
		);
	}

	/**
	 * Test that restrict returns null for non-allowed routes (current pass-through behavior).
	 */
	public function test_restrict_returns_null_for_non_allowed_route(): void {
		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';

		$result = $this->module->restrict_unauthenticated_access( null );

		$this->assertNull(
			$result,
			'restrict_unauthenticated_access() must return null for non-allowed routes (current pass-through implementation).'
		);
	}

	/**
	 * Test that restrict respects the allowed prefixes filter.
	 */
	public function test_restrict_respects_allowed_prefixes_filter(): void {
		add_filter(
			'wp_headless_rest_allowed_prefixes',
			static function ( array $prefixes ): array {
				$prefixes[] = '/custom/v1/';
				return $prefixes;
			}
		);

		$GLOBALS['wp']->query_vars['rest_route'] = '/custom/v1/data';

		$result = $this->module->restrict_unauthenticated_access( null );

		$this->assertNull(
			$result,
			'restrict_unauthenticated_access() must return null (allow) for custom prefixes added via filter.'
		);
	}

	/**
	 * Test that restrict handles missing rest_route query var gracefully.
	 *
	 * When $GLOBALS['wp']->query_vars exists but has no 'rest_route' key,
	 * the implementation uses the null coalescing operator (??) to default
	 * to an empty string, so no route matches any allowed prefix.
	 */
	public function test_restrict_handles_missing_rest_route_query_var(): void {
		// Ensure $GLOBALS['wp'] exists but has no rest_route key.
		$GLOBALS['wp']             = new \stdClass();
		$GLOBALS['wp']->query_vars = [];

		$result = $this->module->restrict_unauthenticated_access( null );

		$this->assertNull(
			$result,
			'restrict_unauthenticated_access() must handle missing rest_route query var gracefully.'
		);
	}

	/**
	 * Test that restrict handles empty allowed prefixes array.
	 */
	public function test_restrict_handles_empty_allowed_prefixes(): void {
		add_filter(
			'wp_headless_rest_allowed_prefixes',
			static function (): array {
				return [];
			}
		);

		$GLOBALS['wp']->query_vars['rest_route'] = '/wp/v2/posts';

		$result = $this->module->restrict_unauthenticated_access( null );

		$this->assertNull(
			$result,
			'restrict_unauthenticated_access() must return $result when allowed_prefixes is empty (pass-through behavior).'
		);
	}
}
