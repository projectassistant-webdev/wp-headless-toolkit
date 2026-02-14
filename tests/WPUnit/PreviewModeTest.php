<?php
/**
 * Tests for src/Modules/PreviewMode/PreviewMode.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use Tests\ProjectAssistant\HeadlessToolkit\HeadlessToolkitTestCase;
use ProjectAssistant\HeadlessToolkit\Modules\PreviewMode\PreviewMode;
use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;

/**
 * Tests for the Preview Mode module.
 */
class PreviewModeTest extends HeadlessToolkitTestCase {

	/**
	 * The module instance under test.
	 *
	 * @var PreviewMode
	 */
	private PreviewMode $module;

	/**
	 * Set up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->module = new PreviewMode();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_filters_to_clean(): array {
		return [
			'wp_headless_module_enabled',
			'wp_headless_preview_url',
			'wp_headless_config_value',
			'preview_post_link',
			'rest_api_init',
		];
	}

	/**
	 * Helper to configure both required env vars.
	 *
	 * @param string $frontend_url The frontend URL.
	 * @param string $secret       The preview secret.
	 */
	private function configure_preview( string $frontend_url = 'https://frontend.example.com', string $secret = 'test-secret-key-for-preview' ): void {
		$this->set_env( 'HEADLESS_FRONTEND_URL', $frontend_url );
		$this->set_env( 'HEADLESS_PREVIEW_SECRET', $secret );
	}

	// -------------------------------------------------------------------------
	// 1. ModuleInterface Contract Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that PreviewMode implements ModuleInterface.
	 */
	public function test_implements_module_interface(): void {
		$this->assertInstanceOf(
			ModuleInterface::class,
			$this->module,
			'PreviewMode must implement ModuleInterface.'
		);
	}

	/**
	 * Test that get_slug() returns the expected slug.
	 */
	public function test_get_slug_returns_preview_mode(): void {
		$this->assertSame(
			'preview_mode',
			PreviewMode::get_slug(),
			'get_slug() must return "preview_mode".'
		);
	}

	/**
	 * Test that get_name() returns the expected human-readable name.
	 */
	public function test_get_name_returns_preview_mode(): void {
		$this->assertSame(
			'Preview Mode',
			PreviewMode::get_name(),
			'get_name() must return "Preview Mode".'
		);
	}

	// -------------------------------------------------------------------------
	// 2. Enabled-By-Default Behavior
	// -------------------------------------------------------------------------

	/**
	 * Test that is_enabled() returns true by default.
	 */
	public function test_is_enabled_returns_true_by_default(): void {
		// Ensure disable env var is not set.
		putenv( 'WP_HEADLESS_DISABLE_PREVIEW_MODE' );

		$this->assertTrue(
			PreviewMode::is_enabled(),
			'is_enabled() must return true by default (enabled by default module).'
		);
	}

	/**
	 * Test that is_enabled() returns false when explicitly disabled via env var.
	 */
	public function test_is_enabled_returns_false_when_disabled_via_env(): void {
		$this->set_env( 'WP_HEADLESS_DISABLE_PREVIEW_MODE', 'true' );

		$this->assertFalse(
			PreviewMode::is_enabled(),
			'is_enabled() must return false when WP_HEADLESS_DISABLE_PREVIEW_MODE is set.'
		);
	}

	/**
	 * Test that is_enabled() returns false when disabled via filter.
	 */
	public function test_is_enabled_false_when_disabled_via_filter(): void {
		add_filter(
			'wp_headless_module_enabled',
			static function ( $enabled, $slug ) {
				if ( 'preview_mode' === $slug ) {
					return false;
				}
				return $enabled;
			},
			10,
			2
		);

		$this->assertFalse(
			PreviewMode::is_enabled(),
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
			PreviewMode::class,
			$classes,
			'PreviewMode must be listed in Main::get_default_modules().'
		);
	}

	// -------------------------------------------------------------------------
	// 4. JWT Token Generation Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that generate_jwt produces a valid three-part token.
	 */
	public function test_generate_jwt_produces_three_part_token(): void {
		$this->configure_preview();

		$payload = [
			'post_id' => 42,
			'user_id' => 1,
			'iat'     => time(),
			'exp'     => time() + 300,
		];

		$token = $this->module->generate_jwt( $payload );
		$parts = explode( '.', $token );

		$this->assertCount( 3, $parts, 'JWT token must have exactly three parts (header.payload.signature).' );
	}

	/**
	 * Test that JWT header contains correct algorithm.
	 */
	public function test_jwt_header_contains_correct_algorithm(): void {
		$this->configure_preview();

		$token  = $this->module->generate_jwt( [ 'test' => true, 'exp' => time() + 300 ] );
		$parts  = explode( '.', $token );
		$header = json_decode( $this->module->base64url_decode( $parts[0] ), true );

		$this->assertSame( 'HS256', $header['alg'], 'JWT header must specify HS256 algorithm.' );
		$this->assertSame( 'JWT', $header['typ'], 'JWT header must specify JWT type.' );
	}

	/**
	 * Test that JWT payload contains the correct claims.
	 */
	public function test_jwt_payload_contains_correct_claims(): void {
		$this->configure_preview();

		$now     = time();
		$payload = [
			'post_id' => 42,
			'user_id' => 1,
			'iat'     => $now,
			'exp'     => $now + 300,
		];

		$token        = $this->module->generate_jwt( $payload );
		$parts        = explode( '.', $token );
		$decoded_data = json_decode( $this->module->base64url_decode( $parts[1] ), true );

		$this->assertSame( 42, $decoded_data['post_id'], 'JWT payload must contain correct post_id.' );
		$this->assertSame( 1, $decoded_data['user_id'], 'JWT payload must contain correct user_id.' );
		$this->assertSame( $now, $decoded_data['iat'], 'JWT payload must contain correct iat.' );
		$this->assertSame( $now + 300, $decoded_data['exp'], 'JWT payload must contain correct exp.' );
	}

	// -------------------------------------------------------------------------
	// 5. JWT Token Verification Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that verify_jwt returns payload for a valid token.
	 */
	public function test_verify_jwt_returns_payload_for_valid_token(): void {
		$this->configure_preview();

		$payload = [
			'post_id' => 42,
			'user_id' => 1,
			'iat'     => time(),
			'exp'     => time() + 300,
		];

		$token  = $this->module->generate_jwt( $payload );
		$result = $this->module->verify_jwt( $token );

		$this->assertIsArray( $result, 'verify_jwt must return an array for a valid token.' );
		$this->assertSame( 42, $result['post_id'], 'Verified payload must contain correct post_id.' );
		$this->assertSame( 1, $result['user_id'], 'Verified payload must contain correct user_id.' );
	}

	/**
	 * Test that verify_jwt returns null for an expired token.
	 */
	public function test_verify_jwt_returns_null_for_expired_token(): void {
		$this->configure_preview();

		$payload = [
			'post_id' => 42,
			'user_id' => 1,
			'iat'     => time() - 600,
			'exp'     => time() - 300,
		];

		$token  = $this->module->generate_jwt( $payload );
		$result = $this->module->verify_jwt( $token );

		$this->assertNull( $result, 'verify_jwt must return null for an expired token.' );
	}

	/**
	 * Test that verify_jwt returns null for a tampered token.
	 */
	public function test_verify_jwt_returns_null_for_tampered_token(): void {
		$this->configure_preview();

		$payload = [
			'post_id' => 42,
			'user_id' => 1,
			'iat'     => time(),
			'exp'     => time() + 300,
		];

		$token = $this->module->generate_jwt( $payload );

		// Tamper with the payload by modifying post_id.
		$parts          = explode( '.', $token );
		$decoded        = json_decode( $this->module->base64url_decode( $parts[1] ), true );
		$decoded['post_id'] = 999;
		$parts[1]       = $this->module->base64url_encode( wp_json_encode( $decoded ) );
		$tampered_token = implode( '.', $parts );

		$result = $this->module->verify_jwt( $tampered_token );

		$this->assertNull( $result, 'verify_jwt must return null for a tampered token.' );
	}

	/**
	 * Test that verify_jwt returns null for a malformed token (not 3 parts).
	 */
	public function test_verify_jwt_returns_null_for_malformed_token(): void {
		$this->configure_preview();

		$this->assertNull(
			$this->module->verify_jwt( 'not.a.valid.jwt.token' ),
			'verify_jwt must return null for a token with wrong number of parts.'
		);

		$this->assertNull(
			$this->module->verify_jwt( 'just-a-string' ),
			'verify_jwt must return null for a plain string.'
		);

		$this->assertNull(
			$this->module->verify_jwt( '' ),
			'verify_jwt must return null for an empty string.'
		);
	}

	/**
	 * Test that verify_jwt returns null for a token with invalid JSON payload.
	 */
	public function test_verify_jwt_returns_null_for_invalid_json_payload(): void {
		$this->configure_preview();

		$header    = $this->module->base64url_encode( wp_json_encode( [ 'alg' => 'HS256', 'typ' => 'JWT' ] ) );
		$payload   = $this->module->base64url_encode( 'not-json' );
		$secret    = 'test-secret-key-for-preview';
		$signature = $this->module->base64url_encode(
			hash_hmac( 'sha256', "{$header}.{$payload}", $secret, true )
		);

		$token  = "{$header}.{$payload}.{$signature}";
		$result = $this->module->verify_jwt( $token );

		$this->assertNull( $result, 'verify_jwt must return null for a token with non-JSON payload.' );
	}

	// -------------------------------------------------------------------------
	// 6. Preview URL Rewriting Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that rewrite_preview_link generates correct URL format.
	 */
	public function test_rewrite_preview_link_generates_correct_format(): void {
		$this->configure_preview();

		$post = self::factory()->post->create_and_get( [
			'post_title'  => 'Test Post',
			'post_status' => 'draft',
		] );

		$preview_link = $this->module->rewrite_preview_link( 'https://wordpress.test/?p=1&preview=true', $post );

		$this->assertStringStartsWith(
			'https://frontend.example.com/api/preview',
			$preview_link,
			'Preview link must start with the frontend URL and preview path.'
		);

		// Parse the URL and check query parameters.
		$parsed = wp_parse_url( $preview_link );
		parse_str( $parsed['query'], $query_params );

		$this->assertArrayHasKey( 'secret', $query_params, 'Preview URL must include a secret query parameter.' );
		$this->assertArrayHasKey( 'id', $query_params, 'Preview URL must include an id query parameter.' );
		$this->assertEquals( $post->ID, $query_params['id'], 'Preview URL id must match the post ID.' );
	}

	/**
	 * Test that the secret query parameter is a valid JWT token.
	 */
	public function test_preview_link_secret_is_valid_jwt(): void {
		$this->configure_preview();

		$post = self::factory()->post->create_and_get( [
			'post_title'  => 'Test Post',
			'post_status' => 'draft',
		] );

		$preview_link = $this->module->rewrite_preview_link( 'https://wordpress.test/?p=1&preview=true', $post );

		$parsed = wp_parse_url( $preview_link );
		parse_str( $parsed['query'], $query_params );

		$token   = $query_params['secret'];
		$payload = $this->module->verify_jwt( $token );

		$this->assertIsArray( $payload, 'Secret parameter must be a valid JWT token.' );
		$this->assertEquals( $post->ID, $payload['post_id'], 'JWT payload post_id must match the post ID.' );
	}

	/**
	 * Test that rewrite_preview_link returns original link when frontend URL is not configured.
	 */
	public function test_rewrite_preview_link_returns_original_when_no_frontend_url(): void {
		// Only set secret, not frontend URL.
		putenv( 'HEADLESS_FRONTEND_URL' );
		$this->set_env( 'HEADLESS_PREVIEW_SECRET', 'test-secret' );

		$post          = self::factory()->post->create_and_get();
		$original_link = 'https://wordpress.test/?p=1&preview=true';
		$result        = $this->module->rewrite_preview_link( $original_link, $post );

		$this->assertSame(
			$original_link,
			$result,
			'Must return original link when HEADLESS_FRONTEND_URL is not configured.'
		);
	}

	/**
	 * Test that rewrite_preview_link returns original link when secret is not configured.
	 */
	public function test_rewrite_preview_link_returns_original_when_no_secret(): void {
		// Only set frontend URL, not secret.
		$this->set_env( 'HEADLESS_FRONTEND_URL', 'https://frontend.example.com' );
		putenv( 'HEADLESS_PREVIEW_SECRET' );

		$post          = self::factory()->post->create_and_get();
		$original_link = 'https://wordpress.test/?p=1&preview=true';
		$result        = $this->module->rewrite_preview_link( $original_link, $post );

		$this->assertSame(
			$original_link,
			$result,
			'Must return original link when HEADLESS_PREVIEW_SECRET is not configured.'
		);
	}

	// -------------------------------------------------------------------------
	// 7. Per-Post-Type Preview URL Filter Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_preview_path returns default path.
	 */
	public function test_get_preview_path_returns_default(): void {
		$post = self::factory()->post->create_and_get();

		$path = $this->module->get_preview_path( $post );

		$this->assertSame(
			'api/preview',
			$path,
			'get_preview_path must return "api/preview" by default.'
		);
	}

	/**
	 * Test that wp_headless_preview_url filter allows customizing preview path.
	 */
	public function test_preview_url_filter_allows_custom_path(): void {
		add_filter(
			'wp_headless_preview_url',
			static function ( $path, $post, $post_type ) {
				if ( 'page' === $post_type ) {
					return 'api/preview/pages';
				}
				return $path;
			},
			10,
			3
		);

		$page = self::factory()->post->create_and_get( [ 'post_type' => 'page' ] );
		$post = self::factory()->post->create_and_get( [ 'post_type' => 'post' ] );

		$this->assertSame(
			'api/preview/pages',
			$this->module->get_preview_path( $page ),
			'Filter must be able to customize preview path for pages.'
		);

		$this->assertSame(
			'api/preview',
			$this->module->get_preview_path( $post ),
			'Filter must preserve default path for posts.'
		);
	}

	/**
	 * Test that custom preview path is used in the rewritten URL.
	 */
	public function test_custom_preview_path_used_in_rewritten_url(): void {
		$this->configure_preview();

		add_filter(
			'wp_headless_preview_url',
			static function () {
				return 'api/preview/pages';
			}
		);

		$page = self::factory()->post->create_and_get( [ 'post_type' => 'page' ] );

		$preview_link = $this->module->rewrite_preview_link( 'https://wordpress.test/?page_id=1&preview=true', $page );

		$this->assertStringContainsString(
			'api/preview/pages',
			$preview_link,
			'Rewritten URL must use the custom preview path from the filter.'
		);
	}

	// -------------------------------------------------------------------------
	// 8. Token Expiry Configuration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_token_expiry returns default value.
	 */
	public function test_get_token_expiry_returns_default(): void {
		putenv( 'WP_HEADLESS_PREVIEW_TOKEN_EXPIRY' );

		$this->assertSame(
			300,
			$this->module->get_token_expiry(),
			'get_token_expiry must return 300 (5 minutes) by default.'
		);
	}

	/**
	 * Test that get_token_expiry respects environment variable.
	 */
	public function test_get_token_expiry_respects_env_var(): void {
		$this->set_env( 'WP_HEADLESS_PREVIEW_TOKEN_EXPIRY', '600' );

		$this->assertSame(
			600,
			$this->module->get_token_expiry(),
			'get_token_expiry must return the configured value from env var.'
		);
	}

	/**
	 * Test that get_token_expiry enforces minimum of 1 second.
	 */
	public function test_get_token_expiry_enforces_minimum(): void {
		$this->set_env( 'WP_HEADLESS_PREVIEW_TOKEN_EXPIRY', '0' );

		$this->assertSame(
			1,
			$this->module->get_token_expiry(),
			'get_token_expiry must enforce a minimum of 1 second.'
		);
	}

	// -------------------------------------------------------------------------
	// 9. Hook Registration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that init() registers the preview_post_link filter.
	 */
	public function test_init_registers_preview_post_link_filter(): void {
		$this->module->init();

		$this->assertSame(
			10,
			has_filter( 'preview_post_link', [ $this->module, 'rewrite_preview_link' ] ),
			'init() must register rewrite_preview_link on preview_post_link filter.'
		);
	}

	/**
	 * Test that init() registers the rest_api_init action.
	 */
	public function test_init_registers_rest_api_init_action(): void {
		$this->module->init();

		$this->assertNotFalse(
			has_action( 'rest_api_init', [ $this->module, 'register_rest_routes' ] ),
			'init() must register register_rest_routes on rest_api_init action.'
		);
	}

	// -------------------------------------------------------------------------
	// 10. REST API Verify Endpoint Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that REST verify endpoint returns valid for a good token.
	 */
	public function test_rest_verify_returns_valid_for_good_token(): void {
		$this->configure_preview();

		$token = $this->module->generate_jwt( [
			'post_id' => 42,
			'user_id' => 1,
			'iat'     => time(),
			'exp'     => time() + 300,
		] );

		$request = new \WP_REST_Request( 'GET', '/wp-headless-toolkit/v1/preview/verify' );
		$request->set_param( 'token', $token );

		$response = $this->module->rest_verify_token( $request );

		$this->assertSame( 200, $response->get_status(), 'Valid token should return 200 status.' );
		$data = $response->get_data();
		$this->assertTrue( $data['valid'], 'Valid token response must have valid=true.' );
		$this->assertSame( 42, $data['post_id'], 'Valid token response must contain post_id.' );
	}

	/**
	 * Test that REST verify endpoint returns invalid for expired token.
	 */
	public function test_rest_verify_returns_invalid_for_expired_token(): void {
		$this->configure_preview();

		$token = $this->module->generate_jwt( [
			'post_id' => 42,
			'user_id' => 1,
			'iat'     => time() - 600,
			'exp'     => time() - 300,
		] );

		$request = new \WP_REST_Request( 'GET', '/wp-headless-toolkit/v1/preview/verify' );
		$request->set_param( 'token', $token );

		$response = $this->module->rest_verify_token( $request );

		$this->assertSame( 401, $response->get_status(), 'Expired token should return 401 status.' );
		$data = $response->get_data();
		$this->assertFalse( $data['valid'], 'Expired token response must have valid=false.' );
		$this->assertSame( 'invalid_token', $data['error'], 'Expired token response must have error=invalid_token.' );
	}

	/**
	 * Test that REST verify endpoint returns error for missing token.
	 */
	public function test_rest_verify_returns_error_for_missing_token(): void {
		$this->configure_preview();

		$request = new \WP_REST_Request( 'GET', '/wp-headless-toolkit/v1/preview/verify' );
		$request->set_param( 'token', '' );

		$response = $this->module->rest_verify_token( $request );

		$this->assertSame( 400, $response->get_status(), 'Missing token should return 400 status.' );
		$data = $response->get_data();
		$this->assertFalse( $data['valid'], 'Missing token response must have valid=false.' );
		$this->assertSame( 'missing_token', $data['error'], 'Missing token response must have error=missing_token.' );
	}

	/**
	 * Test that REST verify endpoint returns invalid for tampered token.
	 */
	public function test_rest_verify_returns_invalid_for_tampered_token(): void {
		$this->configure_preview();

		$request = new \WP_REST_Request( 'GET', '/wp-headless-toolkit/v1/preview/verify' );
		$request->set_param( 'token', 'invalid.token.value' );

		$response = $this->module->rest_verify_token( $request );

		$this->assertSame( 401, $response->get_status(), 'Invalid token should return 401 status.' );
		$data = $response->get_data();
		$this->assertFalse( $data['valid'], 'Invalid token response must have valid=false.' );
	}

	// -------------------------------------------------------------------------
	// 11. Base64url Encoding/Decoding Edge Cases
	// -------------------------------------------------------------------------

	/**
	 * Test base64url_encode produces URL-safe output.
	 */
	public function test_base64url_encode_produces_url_safe_output(): void {
		// Input that would produce +, /, = in standard base64.
		$input  = "\xff\xfe\xfd\xfc\xfb";
		$result = $this->module->base64url_encode( $input );

		$this->assertStringNotContainsString( '+', $result, 'base64url_encode must not contain + character.' );
		$this->assertStringNotContainsString( '/', $result, 'base64url_encode must not contain / character.' );
		$this->assertStringNotContainsString( '=', $result, 'base64url_encode must not contain = padding.' );
	}

	/**
	 * Test base64url roundtrip encoding/decoding.
	 */
	public function test_base64url_roundtrip(): void {
		$inputs = [
			'Hello, World!',
			'{"post_id":42,"user_id":1}',
			'binary data: ' . chr( 0 ) . chr( 255 ),
			'', // empty string.
			str_repeat( 'A', 1000 ), // long string.
		];

		foreach ( $inputs as $input ) {
			$encoded = $this->module->base64url_encode( $input );
			$decoded = $this->module->base64url_decode( $encoded );

			$this->assertSame(
				$input,
				$decoded,
				'base64url_decode(base64url_encode(x)) must return x for all inputs.'
			);
		}
	}

	/**
	 * Test base64url_encode with empty string.
	 */
	public function test_base64url_encode_empty_string(): void {
		$result = $this->module->base64url_encode( '' );

		$this->assertSame(
			'',
			$result,
			'base64url_encode of empty string must return empty string.'
		);
	}

	// -------------------------------------------------------------------------
	// 12. Configuration Helper Tests
	// -------------------------------------------------------------------------

	/**
	 * Test get_frontend_url returns configured value.
	 */
	public function test_get_frontend_url_returns_configured_value(): void {
		$this->set_env( 'HEADLESS_FRONTEND_URL', 'https://frontend.example.com' );

		$this->assertSame(
			'https://frontend.example.com',
			$this->module->get_frontend_url(),
			'get_frontend_url must return the configured HEADLESS_FRONTEND_URL.'
		);
	}

	/**
	 * Test get_frontend_url returns empty string when not configured.
	 */
	public function test_get_frontend_url_returns_empty_when_not_configured(): void {
		putenv( 'HEADLESS_FRONTEND_URL' );

		$this->assertSame(
			'',
			$this->module->get_frontend_url(),
			'get_frontend_url must return empty string when not configured.'
		);
	}

	/**
	 * Test get_secret returns configured value.
	 */
	public function test_get_secret_returns_configured_value(): void {
		$this->set_env( 'HEADLESS_PREVIEW_SECRET', 'my-secret' );

		$this->assertSame(
			'my-secret',
			$this->module->get_secret(),
			'get_secret must return the configured HEADLESS_PREVIEW_SECRET.'
		);
	}

	/**
	 * Test get_secret returns empty string when not configured.
	 */
	public function test_get_secret_returns_empty_when_not_configured(): void {
		putenv( 'HEADLESS_PREVIEW_SECRET' );

		$this->assertSame(
			'',
			$this->module->get_secret(),
			'get_secret must return empty string when not configured.'
		);
	}

	// -------------------------------------------------------------------------
	// 13. Frontend URL Trailing Slash Handling
	// -------------------------------------------------------------------------

	/**
	 * Test that trailing slash on frontend URL is handled correctly.
	 */
	public function test_rewrite_preview_link_handles_trailing_slash(): void {
		$this->set_env( 'HEADLESS_FRONTEND_URL', 'https://frontend.example.com/' );
		$this->set_env( 'HEADLESS_PREVIEW_SECRET', 'test-secret-key-for-preview' );

		$post = self::factory()->post->create_and_get( [ 'post_status' => 'draft' ] );

		$preview_link = $this->module->rewrite_preview_link( 'https://wordpress.test/?p=1&preview=true', $post );

		// Should NOT have double slash between domain and path.
		$this->assertStringNotContainsString(
			'example.com//api',
			$preview_link,
			'Trailing slash on frontend URL must not cause double slash in preview URL.'
		);

		$this->assertStringContainsString(
			'example.com/api/preview',
			$preview_link,
			'Preview link must contain correctly joined URL path.'
		);
	}
}
