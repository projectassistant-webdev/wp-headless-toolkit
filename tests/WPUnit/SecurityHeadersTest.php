<?php
/**
 * Tests for src/Modules/SecurityHeaders/SecurityHeaders.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use Tests\ProjectAssistant\HeadlessToolkit\HeadlessToolkitTestCase;
use ProjectAssistant\HeadlessToolkit\Modules\SecurityHeaders\SecurityHeaders;
use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;

/**
 * Tests for the Security Headers module.
 *
 * @group module
 * @group security-headers
 */
class SecurityHeadersTest extends HeadlessToolkitTestCase {

	/**
	 * The module instance under test.
	 *
	 * @var SecurityHeaders
	 */
	private SecurityHeaders $module;

	/**
	 * Set up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->module = new SecurityHeaders();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_filters_to_clean(): array {
		return [
			'wp_headless_module_enabled',
			'wp_headless_security_headers',
			'wp_headless_config_value',
			'wp_headers',
			'rest_post_dispatch',
		];
	}

	// -------------------------------------------------------------------------
	// 1. ModuleInterface Contract Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that SecurityHeaders implements ModuleInterface.
	 *
	 * @group smoke
	 */
	public function test_implements_module_interface(): void {
		$this->assertInstanceOf(
			ModuleInterface::class,
			$this->module,
			'SecurityHeaders must implement ModuleInterface.'
		);
	}

	/**
	 * Test that get_slug() returns the expected slug.
	 */
	public function test_get_slug_returns_security_headers(): void {
		$this->assertSame(
			'security_headers',
			SecurityHeaders::get_slug(),
			'get_slug() must return "security_headers".'
		);
	}

	/**
	 * Test that get_name() returns the expected human-readable name.
	 */
	public function test_get_name_returns_security_headers(): void {
		$this->assertSame(
			'Security Headers',
			SecurityHeaders::get_name(),
			'get_name() must return "Security Headers".'
		);
	}

	// -------------------------------------------------------------------------
	// 2. Disabled-By-Default Behavior
	// -------------------------------------------------------------------------

	/**
	 * Test that is_enabled() returns false by default.
	 */
	public function test_is_enabled_returns_false_by_default(): void {
		// Ensure enable env var is not set.
		putenv( 'WP_HEADLESS_ENABLE_SECURITY_HEADERS' );

		$this->assertFalse(
			SecurityHeaders::is_enabled(),
			'is_enabled() must return false when WP_HEADLESS_ENABLE_SECURITY_HEADERS is not configured.'
		);
	}

	/**
	 * Test that is_enabled() returns true when explicitly enabled.
	 */
	public function test_is_enabled_returns_true_when_enabled(): void {
		$this->set_env( 'WP_HEADLESS_ENABLE_SECURITY_HEADERS', 'true' );

		$this->assertTrue(
			SecurityHeaders::is_enabled(),
			'is_enabled() must return true when WP_HEADLESS_ENABLE_SECURITY_HEADERS is set to true.'
		);
	}

	/**
	 * Test that is_enabled() returns false when explicitly disabled via disable env var.
	 */
	public function test_is_enabled_returns_false_when_disabled_via_env(): void {
		$this->set_env( 'WP_HEADLESS_ENABLE_SECURITY_HEADERS', 'true' );
		$this->set_env( 'WP_HEADLESS_DISABLE_SECURITY_HEADERS', 'true' );

		$this->assertFalse(
			SecurityHeaders::is_enabled(),
			'is_enabled() must return false when WP_HEADLESS_DISABLE_SECURITY_HEADERS is set.'
		);
	}

	/**
	 * Test that is_enabled() returns false when disabled via filter.
	 */
	public function test_is_enabled_false_when_disabled_via_filter(): void {
		$this->set_env( 'WP_HEADLESS_ENABLE_SECURITY_HEADERS', 'true' );

		add_filter(
			'wp_headless_module_enabled',
			static function ( $enabled, $slug ) {
				if ( 'security_headers' === $slug ) {
					return false;
				}
				return $enabled;
			},
			10,
			2
		);

		$this->assertFalse(
			SecurityHeaders::is_enabled(),
			'is_enabled() must return false when disabled via wp_headless_module_enabled filter.'
		);
	}

	// -------------------------------------------------------------------------
	// 3. Module Registration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that the module class is registered in Main's default modules.
	 */
	public function test_module_is_registered_in_main(): void {
		$main    = \ProjectAssistant\HeadlessToolkit\Main::instance();
		$classes = $main->get_registered_module_classes();

		$this->assertContains(
			SecurityHeaders::class,
			$classes,
			'SecurityHeaders must be listed in Main::get_default_modules().'
		);
	}

	// -------------------------------------------------------------------------
	// 4. Default Security Headers Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_headers() returns all 5 default security headers.
	 */
	public function test_get_headers_returns_all_defaults(): void {
		$headers = $this->module->get_headers();

		$this->assertCount( 5, $headers, 'get_headers() must return 5 default security headers.' );

		$this->assertArrayHasKey( 'X-Content-Type-Options', $headers );
		$this->assertArrayHasKey( 'X-Frame-Options', $headers );
		$this->assertArrayHasKey( 'Strict-Transport-Security', $headers );
		$this->assertArrayHasKey( 'Referrer-Policy', $headers );
		$this->assertArrayHasKey( 'Permissions-Policy', $headers );
	}

	/**
	 * Test default X-Content-Type-Options header value.
	 */
	public function test_default_x_content_type_options(): void {
		$headers = $this->module->get_headers();

		$this->assertSame(
			'nosniff',
			$headers['X-Content-Type-Options'],
			'Default X-Content-Type-Options must be "nosniff".'
		);
	}

	/**
	 * Test default X-Frame-Options header value.
	 */
	public function test_default_x_frame_options(): void {
		$headers = $this->module->get_headers();

		$this->assertSame(
			'DENY',
			$headers['X-Frame-Options'],
			'Default X-Frame-Options must be "DENY".'
		);
	}

	/**
	 * Test default Strict-Transport-Security header value.
	 */
	public function test_default_hsts(): void {
		$headers = $this->module->get_headers();

		$this->assertSame(
			'max-age=31536000; includeSubDomains',
			$headers['Strict-Transport-Security'],
			'Default Strict-Transport-Security must be "max-age=31536000; includeSubDomains".'
		);
	}

	/**
	 * Test default Referrer-Policy header value.
	 */
	public function test_default_referrer_policy(): void {
		$headers = $this->module->get_headers();

		$this->assertSame(
			'strict-origin-when-cross-origin',
			$headers['Referrer-Policy'],
			'Default Referrer-Policy must be "strict-origin-when-cross-origin".'
		);
	}

	/**
	 * Test default Permissions-Policy header value.
	 */
	public function test_default_permissions_policy(): void {
		$headers = $this->module->get_headers();

		$this->assertSame(
			'camera=(), microphone=(), geolocation=()',
			$headers['Permissions-Policy'],
			'Default Permissions-Policy must be "camera=(), microphone=(), geolocation=()".'
		);
	}

	// -------------------------------------------------------------------------
	// 5. Header Configuration Override Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that X-Content-Type-Options can be overridden via env var.
	 */
	public function test_override_x_content_type_options(): void {
		$this->set_env( 'WP_HEADLESS_X_CONTENT_TYPE_OPTIONS', 'nosniff' );

		$headers = $this->module->get_headers();

		$this->assertSame(
			'nosniff',
			$headers['X-Content-Type-Options'],
			'X-Content-Type-Options should respect env var override.'
		);
	}

	/**
	 * Test that X-Frame-Options can be overridden via env var.
	 */
	public function test_override_x_frame_options(): void {
		$this->set_env( 'WP_HEADLESS_X_FRAME_OPTIONS', 'SAMEORIGIN' );

		$headers = $this->module->get_headers();

		$this->assertSame(
			'SAMEORIGIN',
			$headers['X-Frame-Options'],
			'X-Frame-Options should respect env var override.'
		);
	}

	/**
	 * Test that HSTS can be overridden via env var.
	 */
	public function test_override_hsts(): void {
		$this->set_env( 'WP_HEADLESS_HSTS', 'max-age=63072000; includeSubDomains; preload' );

		$headers = $this->module->get_headers();

		$this->assertSame(
			'max-age=63072000; includeSubDomains; preload',
			$headers['Strict-Transport-Security'],
			'Strict-Transport-Security should respect env var override.'
		);
	}

	/**
	 * Test that Referrer-Policy can be overridden via env var.
	 */
	public function test_override_referrer_policy(): void {
		$this->set_env( 'WP_HEADLESS_REFERRER_POLICY', 'no-referrer' );

		$headers = $this->module->get_headers();

		$this->assertSame(
			'no-referrer',
			$headers['Referrer-Policy'],
			'Referrer-Policy should respect env var override.'
		);
	}

	/**
	 * Test that Permissions-Policy can be overridden via env var.
	 */
	public function test_override_permissions_policy(): void {
		$this->set_env( 'WP_HEADLESS_PERMISSIONS_POLICY', 'camera=(self), microphone=()' );

		$headers = $this->module->get_headers();

		$this->assertSame(
			'camera=(self), microphone=()',
			$headers['Permissions-Policy'],
			'Permissions-Policy should respect env var override.'
		);
	}

	/**
	 * Test that setting a header to empty string removes it.
	 */
	public function test_empty_string_removes_header(): void {
		$this->set_env( 'WP_HEADLESS_X_FRAME_OPTIONS', '' );

		$headers = $this->module->get_headers();

		$this->assertArrayNotHasKey(
			'X-Frame-Options',
			$headers,
			'Setting a header config to empty string should remove that header.'
		);
		$this->assertCount( 4, $headers, 'Should have 4 headers when one is removed.' );
	}

	// -------------------------------------------------------------------------
	// 6. Filter Hook Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_headers() respects the wp_headless_security_headers filter.
	 */
	public function test_get_headers_respects_filter(): void {
		add_filter(
			'wp_headless_security_headers',
			static function ( $headers ) {
				$headers['X-Custom-Security'] = 'custom-value';
				return $headers;
			}
		);

		$headers = $this->module->get_headers();

		$this->assertSame(
			'custom-value',
			$headers['X-Custom-Security'],
			'get_headers() must apply wp_headless_security_headers filter.'
		);
	}

	/**
	 * Test that the filter can remove a header.
	 */
	public function test_filter_can_remove_header(): void {
		add_filter(
			'wp_headless_security_headers',
			static function ( $headers ) {
				unset( $headers['X-Frame-Options'] );
				return $headers;
			}
		);

		$headers = $this->module->get_headers();

		$this->assertArrayNotHasKey(
			'X-Frame-Options',
			$headers,
			'wp_headless_security_headers filter should be able to remove headers.'
		);
	}

	// -------------------------------------------------------------------------
	// 7. WordPress Headers Filter Integration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that add_security_headers() adds all security headers to wp_headers.
	 */
	public function test_add_security_headers_adds_all_headers(): void {
		$input = [
			'X-Powered-By' => 'WordPress',
		];

		$result = $this->module->add_security_headers( $input );

		$this->assertSame( 'WordPress', $result['X-Powered-By'], 'Should preserve existing headers.' );
		$this->assertSame( 'nosniff', $result['X-Content-Type-Options'] );
		$this->assertSame( 'DENY', $result['X-Frame-Options'] );
		$this->assertSame( 'max-age=31536000; includeSubDomains', $result['Strict-Transport-Security'] );
		$this->assertSame( 'strict-origin-when-cross-origin', $result['Referrer-Policy'] );
		$this->assertSame( 'camera=(), microphone=(), geolocation=()', $result['Permissions-Policy'] );
	}

	/**
	 * Test that add_security_headers() preserves existing headers.
	 */
	public function test_add_security_headers_preserves_existing(): void {
		$input = [
			'Content-Type'  => 'text/html; charset=UTF-8',
			'Cache-Control' => 'public, max-age=3600',
		];

		$result = $this->module->add_security_headers( $input );

		$this->assertSame(
			'text/html; charset=UTF-8',
			$result['Content-Type'],
			'add_security_headers() must preserve existing Content-Type header.'
		);
		$this->assertSame(
			'public, max-age=3600',
			$result['Cache-Control'],
			'add_security_headers() must preserve existing Cache-Control header.'
		);
	}

	// -------------------------------------------------------------------------
	// 8. REST API Integration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that add_rest_security_headers() adds headers to WP_REST_Response.
	 */
	public function test_add_rest_security_headers_adds_headers(): void {
		$response = new \WP_REST_Response( [ 'data' => 'test' ] );

		$result = $this->module->add_rest_security_headers( $response );

		$result_headers = $result->get_headers();

		$this->assertSame( 'nosniff', $result_headers['X-Content-Type-Options'] );
		$this->assertSame( 'DENY', $result_headers['X-Frame-Options'] );
		$this->assertSame( 'max-age=31536000; includeSubDomains', $result_headers['Strict-Transport-Security'] );
		$this->assertSame( 'strict-origin-when-cross-origin', $result_headers['Referrer-Policy'] );
		$this->assertSame( 'camera=(), microphone=(), geolocation=()', $result_headers['Permissions-Policy'] );
	}

	/**
	 * Test that add_rest_security_headers() returns the response object.
	 */
	public function test_add_rest_security_headers_returns_response(): void {
		$response = new \WP_REST_Response( [ 'data' => 'test' ] );

		$result = $this->module->add_rest_security_headers( $response );

		$this->assertInstanceOf(
			\WP_REST_Response::class,
			$result,
			'add_rest_security_headers() must return a WP_REST_Response instance.'
		);
	}

	/**
	 * Test that REST response data is preserved.
	 */
	public function test_add_rest_security_headers_preserves_response_data(): void {
		$response = new \WP_REST_Response( [ 'data' => 'test' ] );
		$response->set_status( 200 );

		$result = $this->module->add_rest_security_headers( $response );

		$this->assertSame(
			[ 'data' => 'test' ],
			$result->get_data(),
			'add_rest_security_headers() must preserve response data.'
		);
		$this->assertSame(
			200,
			$result->get_status(),
			'add_rest_security_headers() must preserve response status.'
		);
	}

	// -------------------------------------------------------------------------
	// 9. Hook Registration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that init() registers the wp_headers filter.
	 */
	public function test_init_registers_wp_headers_filter(): void {
		$this->module->init();

		$this->assertSame(
			10,
			has_filter( 'wp_headers', [ $this->module, 'add_security_headers' ] ),
			'init() must register add_security_headers on wp_headers filter.'
		);
	}

	/**
	 * Test that init() registers the rest_post_dispatch filter.
	 */
	public function test_init_registers_rest_post_dispatch_filter(): void {
		$this->module->init();

		$this->assertSame(
			999,
			has_filter( 'rest_post_dispatch', [ $this->module, 'add_rest_security_headers' ] ),
			'init() must register add_rest_security_headers on rest_post_dispatch filter with priority 999.'
		);
	}
}
