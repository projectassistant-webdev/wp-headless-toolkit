<?php
/**
 * Tests for src/Modules/FrontendRedirect/FrontendRedirect.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use lucatume\WPBrowser\TestCase\WPTestCase;
use ProjectAssistant\HeadlessToolkit\Modules\FrontendRedirect\FrontendRedirect;

/**
 * Tests for the FrontendRedirect module.
 *
 * IMPORTANT: Test method order matters. Tests that define PHP constants
 * (WP_CLI, REST_REQUEST, DOING_AJAX, DOING_CRON, GRAPHQL_HTTP_REQUEST)
 * are placed LAST because constants cannot be undefined once set and would
 * cause all subsequent redirect tests to fail (is_passthrough_request()
 * would always return true).
 */
class FrontendRedirectTest extends WPTestCase {

	/**
	 * The module instance under test.
	 *
	 * @var FrontendRedirect
	 */
	private FrontendRedirect $module;

	/**
	 * Original $_SERVER['REQUEST_URI'] value for restoration.
	 *
	 * @var string|null
	 */
	private ?string $original_request_uri;

	/**
	 * Original $GLOBALS['pagenow'] value for restoration.
	 *
	 * @var string|null
	 */
	private ?string $original_pagenow;

	/**
	 * Original $GLOBALS['current_screen'] value for restoration.
	 *
	 * @var mixed
	 */
	private $original_current_screen;

	/**
	 * Captured redirect URL from wp_redirect filter.
	 *
	 * @var string|null
	 */
	private ?string $captured_redirect_url = null;

	/**
	 * Captured redirect status from wp_redirect filter.
	 *
	 * @var int|null
	 */
	private ?int $captured_redirect_status = null;

	/**
	 * Set up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->module = new FrontendRedirect();

		putenv( 'HEADLESS_FRONTEND_URL=https://frontend.example.com' );

		// Save globals for restoration.
		$this->original_request_uri    = $_SERVER['REQUEST_URI'] ?? null;
		$this->original_pagenow        = $GLOBALS['pagenow'] ?? null;
		$this->original_current_screen = $GLOBALS['current_screen'] ?? null;

		// Default to a non-admin, non-login page.
		$GLOBALS['pagenow']        = 'index.php';
		$GLOBALS['current_screen'] = null;
		$_SERVER['REQUEST_URI']    = '/';

		// Reset captured redirect data.
		$this->captured_redirect_url    = null;
		$this->captured_redirect_status = null;
	}

	/**
	 * Clean up filters, env vars, and globals after each test.
	 */
	protected function tear_down(): void {
		putenv( 'HEADLESS_FRONTEND_URL' );

		remove_all_filters( 'wp_headless_module_enabled' );
		remove_all_filters( 'wp_headless_redirect_url' );
		remove_all_filters( 'wp_headless_preview_link' );
		remove_all_filters( 'wp_headless_is_passthrough_request' );
		remove_all_filters( 'wp_redirect' );
		remove_all_filters( 'template_redirect' );
		remove_all_filters( 'preview_post_link' );

		// Restore globals.
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		if ( null === $this->original_pagenow ) {
			unset( $GLOBALS['pagenow'] );
		} else {
			$GLOBALS['pagenow'] = $this->original_pagenow;
		}

		if ( null === $this->original_current_screen ) {
			unset( $GLOBALS['current_screen'] );
		} else {
			$GLOBALS['current_screen'] = $this->original_current_screen;
		}

		parent::tear_down();
	}

	/**
	 * Install a wp_redirect filter that captures the redirect and throws
	 * a RuntimeException to prevent the subsequent exit() call.
	 */
	private function install_redirect_trap(): void {
		add_filter( 'wp_redirect', function ( $location, $status ) {
			$this->captured_redirect_url    = $location;
			$this->captured_redirect_status = (int) $status;
			// Throw to prevent exit() after wp_redirect().
			throw new \RuntimeException( 'redirect_intercepted' );
		}, 10, 2 );
	}

	/**
	 * Call maybe_redirect() and expect a redirect to be captured.
	 *
	 * @return array{url: string, status: int} The captured redirect data.
	 */
	private function call_maybe_redirect_expecting_redirect(): array {
		$this->install_redirect_trap();

		try {
			$this->module->maybe_redirect();
			$this->fail( 'Expected redirect did not occur.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'redirect_intercepted', $e->getMessage() );
		}

		$this->assertNotNull( $this->captured_redirect_url, 'Redirect URL should have been captured.' );
		$this->assertNotNull( $this->captured_redirect_status, 'Redirect status should have been captured.' );

		return [
			'url'    => $this->captured_redirect_url,
			'status' => $this->captured_redirect_status,
		];
	}

	// -------------------------------------------------------------------
	// 1. ModuleInterface Contract Tests
	// -------------------------------------------------------------------

	/**
	 * @test
	 */
	public function test_get_slug_returns_frontend_redirect(): void {
		$this->assertSame( 'frontend_redirect', FrontendRedirect::get_slug() );
	}

	/**
	 * @test
	 */
	public function test_get_name_returns_frontend_redirect(): void {
		$this->assertSame( 'Frontend Redirect', FrontendRedirect::get_name() );
	}

	/**
	 * @test
	 */
	public function test_is_enabled_returns_false_without_frontend_url(): void {
		putenv( 'HEADLESS_FRONTEND_URL' );
		$this->assertFalse( FrontendRedirect::is_enabled() );
	}

	/**
	 * @test
	 */
	public function test_is_enabled_returns_true_with_frontend_url(): void {
		$this->assertTrue( FrontendRedirect::is_enabled() );
	}

	/**
	 * @test
	 */
	public function test_is_enabled_returns_false_when_disabled_via_filter(): void {
		add_filter( 'wp_headless_module_enabled', function ( $enabled, $slug ) {
			if ( 'frontend_redirect' === $slug ) {
				return false;
			}
			return $enabled;
		}, 10, 2 );

		$this->assertFalse( FrontendRedirect::is_enabled() );
	}

	// -------------------------------------------------------------------
	// 2. Hook Registration Tests
	// -------------------------------------------------------------------

	/**
	 * @test
	 */
	public function test_init_registers_template_redirect_action(): void {
		$this->module->init();

		$this->assertNotFalse( has_action( 'template_redirect', [ $this->module, 'maybe_redirect' ] ) );
		$this->assertSame( 10, has_action( 'template_redirect', [ $this->module, 'maybe_redirect' ] ) );
	}

	/**
	 * @test
	 */
	public function test_init_registers_preview_post_link_filter(): void {
		$this->module->init();

		$this->assertNotFalse( has_filter( 'preview_post_link', [ $this->module, 'rewrite_preview_link' ] ) );
		$this->assertSame( 10, has_filter( 'preview_post_link', [ $this->module, 'rewrite_preview_link' ] ) );
	}

	// -------------------------------------------------------------------
	// 3. Passthrough Tests (non-constant -- safe to run before redirects)
	// -------------------------------------------------------------------

	/**
	 * @test
	 */
	public function test_maybe_redirect_skips_when_admin(): void {
		set_current_screen( 'dashboard' );

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		// Since is_admin() will return true, maybe_redirect returns early.
		// It won't call wp_redirect, so no exit occurs.
		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect for admin requests.' );
	}

	/**
	 * @test
	 */
	public function test_maybe_redirect_skips_for_login_page(): void {
		$GLOBALS['pagenow'] = 'wp-login.php';

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect for login page.' );
	}

	/**
	 * @test
	 */
	public function test_maybe_redirect_skips_for_register_page(): void {
		$GLOBALS['pagenow'] = 'wp-register.php';

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect for register page.' );
	}

	/**
	 * @test
	 */
	public function test_passthrough_filter_can_force_passthrough(): void {
		add_filter( 'wp_headless_is_passthrough_request', '__return_true' );

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect when passthrough filter returns true.' );
	}

	// -------------------------------------------------------------------
	// 4. maybe_redirect() Redirect Behavior Tests
	// -------------------------------------------------------------------

	/**
	 * @test
	 */
	public function test_maybe_redirect_sends_301_to_frontend_url(): void {
		$_SERVER['REQUEST_URI'] = '/';

		$redirect = $this->call_maybe_redirect_expecting_redirect();

		$this->assertSame( 301, $redirect['status'] );
		$this->assertStringStartsWith( 'https://frontend.example.com/', $redirect['url'] );
	}

	/**
	 * @test
	 */
	public function test_maybe_redirect_preserves_request_path(): void {
		$_SERVER['REQUEST_URI'] = '/about/team/';

		$redirect = $this->call_maybe_redirect_expecting_redirect();

		$this->assertStringContainsString( 'about/team/', $redirect['url'] );
		$this->assertSame( 'https://frontend.example.com/about/team/', $redirect['url'] );
	}

	/**
	 * @test
	 */
	public function test_maybe_redirect_does_not_redirect_without_frontend_url(): void {
		putenv( 'HEADLESS_FRONTEND_URL' );

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect without HEADLESS_FRONTEND_URL.' );
	}

	/**
	 * @test
	 */
	public function test_redirect_url_filter_modifies_redirect_target(): void {
		$_SERVER['REQUEST_URI'] = '/original-path/';

		add_filter( 'wp_headless_redirect_url', function ( $redirect_url, $request_uri, $frontend_url ) {
			$this->assertSame( '/original-path/', $request_uri );
			$this->assertSame( 'https://frontend.example.com', $frontend_url );
			return 'https://custom.example.com/modified/';
		}, 10, 3 );

		$redirect = $this->call_maybe_redirect_expecting_redirect();

		$this->assertSame( 'https://custom.example.com/modified/', $redirect['url'] );
	}

	// -------------------------------------------------------------------
	// 5. rewrite_preview_link() Tests
	// -------------------------------------------------------------------

	/**
	 * @test
	 */
	public function test_rewrite_preview_link_returns_frontend_preview_url(): void {
		$post = self::factory()->post->create_and_get( [
			'post_status' => 'draft',
			'post_type'   => 'post',
		] );

		$result = $this->module->rewrite_preview_link( 'https://wp.example.com/?p=' . $post->ID . '&preview=true', $post );

		$this->assertStringStartsWith( 'https://frontend.example.com/api/preview/', $result );
		$this->assertStringContainsString( 'id=' . $post->ID, $result );
		$this->assertStringContainsString( 'status=draft', $result );
		$this->assertStringContainsString( 'type=post', $result );
	}

	/**
	 * @test
	 */
	public function test_rewrite_preview_link_includes_post_id(): void {
		$post = self::factory()->post->create_and_get();

		$result = $this->module->rewrite_preview_link( 'https://wp.example.com/preview/', $post );

		// Parse query string to verify exact value.
		$parsed = wp_parse_url( $result );
		parse_str( $parsed['query'] ?? '', $query_args );
		$this->assertSame( (string) $post->ID, $query_args['id'] );
	}

	/**
	 * @test
	 */
	public function test_rewrite_preview_link_includes_post_status(): void {
		$post = self::factory()->post->create_and_get( [
			'post_status' => 'pending',
		] );

		$result = $this->module->rewrite_preview_link( 'https://wp.example.com/preview/', $post );

		$parsed = wp_parse_url( $result );
		parse_str( $parsed['query'] ?? '', $query_args );
		$this->assertSame( 'pending', $query_args['status'] );
	}

	/**
	 * @test
	 */
	public function test_rewrite_preview_link_includes_post_type(): void {
		$post = self::factory()->post->create_and_get( [
			'post_type' => 'page',
		] );

		$result = $this->module->rewrite_preview_link( 'https://wp.example.com/preview/', $post );

		$parsed = wp_parse_url( $result );
		parse_str( $parsed['query'] ?? '', $query_args );
		$this->assertSame( 'page', $query_args['type'] );
	}

	/**
	 * @test
	 */
	public function test_rewrite_preview_link_returns_original_without_frontend_url(): void {
		putenv( 'HEADLESS_FRONTEND_URL' );

		$post          = self::factory()->post->create_and_get();
		$original_link = 'https://wp.example.com/?p=' . $post->ID . '&preview=true';

		$result = $this->module->rewrite_preview_link( $original_link, $post );

		$this->assertSame( $original_link, $result );
	}

	/**
	 * @test
	 */
	public function test_preview_link_filter_modifies_preview_url(): void {
		$post          = self::factory()->post->create_and_get();
		$original_link = 'https://wp.example.com/?p=' . $post->ID . '&preview=true';

		add_filter( 'wp_headless_preview_link', function ( $preview_url, $preview_link, $filtered_post ) use ( $post, $original_link ) {
			$this->assertSame( $original_link, $preview_link );
			$this->assertSame( $post->ID, $filtered_post->ID );
			return 'https://custom-preview.example.com/my-preview/';
		}, 10, 3 );

		$result = $this->module->rewrite_preview_link( $original_link, $post );

		$this->assertSame( 'https://custom-preview.example.com/my-preview/', $result );
	}

	// -------------------------------------------------------------------
	// 6. Edge Cases and Integration Tests
	// -------------------------------------------------------------------

	/**
	 * @test
	 */
	public function test_maybe_redirect_handles_root_request_uri(): void {
		$_SERVER['REQUEST_URI'] = '/';

		$redirect = $this->call_maybe_redirect_expecting_redirect();

		// Ensure no double-slash in URL.
		$this->assertSame( 'https://frontend.example.com/', $redirect['url'] );
		$this->assertStringNotContainsString( 'example.com//', $redirect['url'] );
	}

	/**
	 * @test
	 */
	public function test_maybe_redirect_handles_request_uri_with_query_string(): void {
		$_SERVER['REQUEST_URI'] = '/search/?q=hello&page=2';

		$redirect = $this->call_maybe_redirect_expecting_redirect();

		$this->assertStringContainsString( 'search/', $redirect['url'] );
		$this->assertStringContainsString( 'q=hello', $redirect['url'] );
		$this->assertStringContainsString( 'page=2', $redirect['url'] );
	}

	/**
	 * @test
	 */
	public function test_rewrite_preview_link_with_custom_post_type(): void {
		register_post_type( 'product', [ 'public' => true ] );

		$post = self::factory()->post->create_and_get( [
			'post_type'   => 'product',
			'post_status' => 'draft',
		] );

		$result = $this->module->rewrite_preview_link( 'https://wp.example.com/preview/', $post );

		$parsed = wp_parse_url( $result );
		parse_str( $parsed['query'] ?? '', $query_args );
		$this->assertSame( 'product', $query_args['type'] );
		$this->assertSame( (string) $post->ID, $query_args['id'] );
		$this->assertSame( 'draft', $query_args['status'] );
	}

	// -------------------------------------------------------------------
	// 7. Passthrough Tests (constant-defining -- MUST run LAST)
	//
	// These tests define PHP constants that persist across the entire
	// test process. They are placed at the end of the file so they
	// don't interfere with redirect behavior tests that require
	// is_passthrough_request() to return false.
	// -------------------------------------------------------------------

	/**
	 * @test
	 */
	public function test_maybe_redirect_skips_for_wp_cli_request(): void {
		if ( defined( 'WP_CLI' ) ) {
			if ( WP_CLI ) {
				$redirected = false;
				add_filter( 'wp_redirect', function () use ( &$redirected ) {
					$redirected = true;
					return false;
				} );
				$this->module->maybe_redirect();
				$this->assertFalse( $redirected, 'Should not redirect for WP-CLI requests.' );
			} else {
				$this->markTestSkipped( 'WP_CLI constant is defined as false; cannot redefine.' );
			}
			return;
		}

		define( 'WP_CLI', true );

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect for WP-CLI requests.' );
	}

	/**
	 * @test
	 */
	public function test_maybe_redirect_skips_for_rest_request(): void {
		if ( defined( 'REST_REQUEST' ) ) {
			if ( REST_REQUEST ) {
				$redirected = false;
				add_filter( 'wp_redirect', function () use ( &$redirected ) {
					$redirected = true;
					return false;
				} );
				$this->module->maybe_redirect();
				$this->assertFalse( $redirected, 'Should not redirect for REST requests.' );
			} else {
				$this->markTestSkipped( 'REST_REQUEST constant is defined as false; cannot redefine.' );
			}
			return;
		}

		define( 'REST_REQUEST', true );

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect for REST requests.' );
	}

	/**
	 * @test
	 */
	public function test_maybe_redirect_skips_for_ajax_request(): void {
		if ( defined( 'DOING_AJAX' ) ) {
			if ( DOING_AJAX ) {
				$redirected = false;
				add_filter( 'wp_redirect', function () use ( &$redirected ) {
					$redirected = true;
					return false;
				} );
				$this->module->maybe_redirect();
				$this->assertFalse( $redirected, 'Should not redirect for AJAX requests.' );
			} else {
				$this->markTestSkipped( 'DOING_AJAX constant is defined as false; cannot redefine.' );
			}
			return;
		}

		define( 'DOING_AJAX', true );

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect for AJAX requests.' );
	}

	/**
	 * @test
	 */
	public function test_maybe_redirect_skips_for_cron_request(): void {
		if ( defined( 'DOING_CRON' ) ) {
			if ( DOING_CRON ) {
				$redirected = false;
				add_filter( 'wp_redirect', function () use ( &$redirected ) {
					$redirected = true;
					return false;
				} );
				$this->module->maybe_redirect();
				$this->assertFalse( $redirected, 'Should not redirect for cron requests.' );
			} else {
				$this->markTestSkipped( 'DOING_CRON constant is defined as false; cannot redefine.' );
			}
			return;
		}

		define( 'DOING_CRON', true );

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect for cron requests.' );
	}

	/**
	 * @test
	 */
	public function test_maybe_redirect_skips_for_graphql_request(): void {
		if ( defined( 'GRAPHQL_HTTP_REQUEST' ) ) {
			if ( GRAPHQL_HTTP_REQUEST ) {
				$redirected = false;
				add_filter( 'wp_redirect', function () use ( &$redirected ) {
					$redirected = true;
					return false;
				} );
				$this->module->maybe_redirect();
				$this->assertFalse( $redirected, 'Should not redirect for GraphQL requests.' );
			} else {
				$this->markTestSkipped( 'GRAPHQL_HTTP_REQUEST constant is defined as false; cannot redefine.' );
			}
			return;
		}

		define( 'GRAPHQL_HTTP_REQUEST', true );

		$redirected = false;
		add_filter( 'wp_redirect', function () use ( &$redirected ) {
			$redirected = true;
			return false;
		} );

		$this->module->maybe_redirect();
		$this->assertFalse( $redirected, 'Should not redirect for GraphQL requests.' );
	}
}
