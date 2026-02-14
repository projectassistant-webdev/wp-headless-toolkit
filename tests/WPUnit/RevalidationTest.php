<?php
/**
 * Tests for src/Modules/Revalidation/Revalidation.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use Tests\ProjectAssistant\HeadlessToolkit\HeadlessToolkitTestCase;
use ProjectAssistant\HeadlessToolkit\Modules\Revalidation\Revalidation;

/**
 * Tests for the Revalidation module.
 */
class RevalidationTest extends HeadlessToolkitTestCase {

	/**
	 * Captured HTTP request data from pre_http_request filter.
	 *
	 * @var array|null
	 */
	private ?array $captured_request = null;

	/**
	 * Captured action callback data from wp_headless_revalidation_sent.
	 *
	 * @var array|null
	 */
	private ?array $captured_sent_action = null;

	/**
	 * Set up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->set_env( 'NEXTJS_REVALIDATION_URL', 'https://example.com/api/revalidate/' );
		$this->set_env( 'NEXTJS_REVALIDATION_SECRET', 'test-secret-123' );

		$this->captured_request     = null;
		$this->captured_sent_action = null;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_filters_to_clean(): array {
		return [
			'pre_http_request',
			'wp_headless_module_enabled',
			'wp_headless_revalidation_post_types',
			'wp_headless_revalidation_tags',
			'wp_headless_revalidation_request_args',
			'wp_headless_revalidation_sent',
		];
	}

	/**
	 * Clean up additional env vars after each test.
	 */
	protected function tear_down(): void {
		putenv( 'WP_HEADLESS_DISABLE_REVALIDATION' );

		parent::tear_down();
	}

	/**
	 * Register the pre_http_request filter to intercept outbound HTTP requests.
	 */
	private function intercept_http_requests(): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) {
				$this->captured_request = [
					'url'  => $url,
					'args' => $args,
				];
				return [
					'response' => [ 'code' => 200, 'message' => 'OK' ],
					'body'     => '',
				];
			},
			10,
			3
		);
	}

	/**
	 * Register the wp_headless_revalidation_sent action listener.
	 */
	private function listen_for_sent_action(): void {
		add_action(
			'wp_headless_revalidation_sent',
			function ( array $tags, string $url ) {
				$this->captured_sent_action = [
					'tags' => $tags,
					'url'  => $url,
				];
			},
			10,
			2
		);
	}

	// -------------------------------------------------------------------------
	// 1. ModuleInterface Contract Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_slug() returns the expected slug.
	 */
	public function test_get_slug_returns_revalidation(): void {
		$this->assertSame(
			'revalidation',
			Revalidation::get_slug(),
			'get_slug() must return "revalidation".'
		);
	}

	/**
	 * Test that get_name() returns the expected human-readable name.
	 */
	public function test_get_name_returns_isr_revalidation(): void {
		$this->assertSame(
			'ISR Revalidation',
			Revalidation::get_name(),
			'get_name() must return "ISR Revalidation".'
		);
	}

	// -------------------------------------------------------------------------
	// 2. is_enabled() Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that is_enabled() returns true when both env vars are set.
	 */
	public function test_is_enabled_true_when_url_and_secret_configured(): void {
		$this->assertTrue(
			Revalidation::is_enabled(),
			'is_enabled() must return true when both NEXTJS_REVALIDATION_URL and NEXTJS_REVALIDATION_SECRET are set.'
		);
	}

	/**
	 * Test that is_enabled() returns false when URL is not configured.
	 */
	public function test_is_enabled_false_when_url_not_configured(): void {
		putenv( 'NEXTJS_REVALIDATION_URL' );

		$this->assertFalse(
			Revalidation::is_enabled(),
			'is_enabled() must return false when NEXTJS_REVALIDATION_URL is not set.'
		);
	}

	/**
	 * Test that is_enabled() returns false when secret is not configured.
	 */
	public function test_is_enabled_false_when_secret_not_configured(): void {
		putenv( 'NEXTJS_REVALIDATION_SECRET' );

		$this->assertFalse(
			Revalidation::is_enabled(),
			'is_enabled() must return false when NEXTJS_REVALIDATION_SECRET is not set.'
		);
	}

	/**
	 * Test that is_enabled() returns false when both env vars are missing.
	 */
	public function test_is_enabled_false_when_both_missing(): void {
		putenv( 'NEXTJS_REVALIDATION_URL' );
		putenv( 'NEXTJS_REVALIDATION_SECRET' );

		$this->assertFalse(
			Revalidation::is_enabled(),
			'is_enabled() must return false when both env vars are missing.'
		);
	}

	/**
	 * Test that is_enabled() returns false when disabled via filter.
	 */
	public function test_is_enabled_false_when_disabled_via_filter(): void {
		add_filter(
			'wp_headless_module_enabled',
			static function ( $enabled, $slug ) {
				if ( 'revalidation' === $slug ) {
					return false;
				}
				return $enabled;
			},
			10,
			2
		);

		$this->assertFalse(
			Revalidation::is_enabled(),
			'is_enabled() must return false when disabled via wp_headless_module_enabled filter.'
		);
	}

	/**
	 * Test that is_enabled() returns false when disabled via env var.
	 */
	public function test_is_enabled_false_when_disabled_via_env(): void {
		putenv( 'WP_HEADLESS_DISABLE_REVALIDATION=true' );

		$this->assertFalse(
			Revalidation::is_enabled(),
			'is_enabled() must return false when WP_HEADLESS_DISABLE_REVALIDATION is "true".'
		);
	}

	// -------------------------------------------------------------------------
	// 3. Hook Registration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that init() registers the save_post action hook.
	 */
	public function test_init_registers_save_post_hook(): void {
		$module = new Revalidation();
		$module->init();

		$this->assertGreaterThan(
			0,
			has_action( 'save_post', [ $module, 'handle_post_change' ] ),
			'init() must register handle_post_change on save_post action.'
		);
	}

	/**
	 * Test that init() registers the delete_post action hook.
	 */
	public function test_init_registers_delete_post_hook(): void {
		$module = new Revalidation();
		$module->init();

		$this->assertGreaterThan(
			0,
			has_action( 'delete_post', [ $module, 'handle_post_delete' ] ),
			'init() must register handle_post_delete on delete_post action.'
		);
	}

	/**
	 * Test that init() registers the wp_trash_post action hook.
	 */
	public function test_init_registers_wp_trash_post_hook(): void {
		$module = new Revalidation();
		$module->init();

		$this->assertGreaterThan(
			0,
			has_action( 'wp_trash_post', [ $module, 'handle_post_delete' ] ),
			'init() must register handle_post_delete on wp_trash_post action.'
		);
	}

	/**
	 * Test that init() registers the edited_term action hook.
	 */
	public function test_init_registers_edited_term_hook(): void {
		$module = new Revalidation();
		$module->init();

		$this->assertGreaterThan(
			0,
			has_action( 'edited_term', [ $module, 'handle_term_change' ] ),
			'init() must register handle_term_change on edited_term action.'
		);
	}

	/**
	 * Test that init() registers the delete_term action hook.
	 */
	public function test_init_registers_delete_term_hook(): void {
		$module = new Revalidation();
		$module->init();

		$this->assertGreaterThan(
			0,
			has_action( 'delete_term', [ $module, 'handle_term_change' ] ),
			'init() must register handle_term_change on delete_term action.'
		);
	}

	/**
	 * Test that init() registers the wp_update_nav_menu action hook.
	 */
	public function test_init_registers_wp_update_nav_menu_hook(): void {
		$module = new Revalidation();
		$module->init();

		$this->assertGreaterThan(
			0,
			has_action( 'wp_update_nav_menu', [ $module, 'handle_menu_change' ] ),
			'init() must register handle_menu_change on wp_update_nav_menu action.'
		);
	}

	// -------------------------------------------------------------------------
	// 4. handle_post_change() Guard Condition Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that handle_post_change() skips revisions.
	 */
	public function test_handle_post_change_skips_revision(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		// Create a revision of this post.
		$revision_id = _wp_put_post_revision( $post );
		$revision    = get_post( $revision_id );

		$module = new Revalidation();
		$module->handle_post_change( $revision_id, $revision );

		$this->assertNull(
			$this->captured_request,
			'handle_post_change() must not send revalidation for post revisions.'
		);
	}

	/**
	 * Test that handle_post_change() skips non-published posts.
	 */
	public function test_handle_post_change_skips_non_published(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertNull(
			$this->captured_request,
			'handle_post_change() must not send revalidation for draft posts.'
		);
	}

	/**
	 * Test that handle_post_change() skips disallowed post types.
	 */
	public function test_handle_post_change_skips_disallowed_post_type(): void {
		$this->intercept_http_requests();

		register_post_type( 'secret_doc' );
		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_type'   => 'secret_doc',
		] );
		$post = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertNull(
			$this->captured_request,
			'handle_post_change() must not send revalidation for disallowed post types.'
		);
	}

	/**
	 * Test that handle_post_change() sends revalidation for a published post.
	 */
	public function test_handle_post_change_sends_for_published_post(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertNotNull(
			$this->captured_request,
			'handle_post_change() must send revalidation for a published post.'
		);
	}

	/**
	 * Test that handle_post_change() sends revalidation for a published page.
	 */
	public function test_handle_post_change_sends_for_published_page(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_type'   => 'page',
		] );
		$post = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertNotNull(
			$this->captured_request,
			'handle_post_change() must send revalidation for a published page.'
		);
	}

	/**
	 * Test that handle_post_change() respects the post types filter.
	 */
	public function test_handle_post_change_respects_post_types_filter(): void {
		$this->intercept_http_requests();

		register_post_type( 'product' );

		add_filter(
			'wp_headless_revalidation_post_types',
			static function ( array $types ): array {
				$types[] = 'product';
				return $types;
			}
		);

		$post_id = self::factory()->post->create( [
			'post_status' => 'publish',
			'post_type'   => 'product',
		] );
		$post = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertNotNull(
			$this->captured_request,
			'handle_post_change() must send revalidation for custom post types added via filter.'
		);
	}

	// -------------------------------------------------------------------------
	// 5. Tag Generation Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that post change generates the post type tag.
	 */
	public function test_post_change_generates_post_type_tag(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		$this->assertContains(
			'post',
			$tags,
			'Tags must include the post type.'
		);
	}

	/**
	 * Test that post change generates the post type-ID tag.
	 */
	public function test_post_change_generates_post_type_id_tag(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		$this->assertContains(
			"post-{$post_id}",
			$tags,
			'Tags must include "{post_type}-{ID}".'
		);
	}

	/**
	 * Test that post change generates taxonomy term tags.
	 */
	public function test_post_change_generates_taxonomy_term_tags(): void {
		$this->intercept_http_requests();

		$term    = self::factory()->term->create_and_get( [
			'taxonomy' => 'category',
			'slug'     => 'test-cat',
		] );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_post_terms( $post_id, [ $term->term_id ], 'category' );
		$post = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		$this->assertContains(
			'category-test-cat',
			$tags,
			'Tags must include "{taxonomy}-{term_slug}" for assigned terms.'
		);
	}

	/**
	 * Test that post change generates the correct total tag count.
	 */
	public function test_post_change_generates_correct_tag_count(): void {
		$this->intercept_http_requests();

		// Create a post with no extra terms besides the default 'Uncategorized'.
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		// Clear all terms so we have a predictable baseline.
		wp_set_post_terms( $post_id, [], 'category' );
		wp_set_post_terms( $post_id, [], 'post_tag' );

		// Assign exactly one category and one tag.
		$cat = self::factory()->term->create( [
			'taxonomy' => 'category',
			'slug'     => 'count-cat',
		] );
		$tag = self::factory()->term->create( [
			'taxonomy' => 'post_tag',
			'slug'     => 'count-tag',
		] );
		wp_set_post_terms( $post_id, [ $cat ], 'category' );
		wp_set_post_terms( $post_id, [ $tag ], 'post_tag' );

		$post = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		// Expected tags: 'post', 'post-{ID}', 'category-count-cat', 'post_tag-count-tag' = 4
		$this->assertCount(
			4,
			$tags,
			'Tag count must match expected: post type + post type-ID + 1 category + 1 post_tag.'
		);
	}

	/**
	 * Test that the tags filter can modify revalidation tags.
	 */
	public function test_post_change_respects_tags_filter(): void {
		$this->intercept_http_requests();

		add_filter(
			'wp_headless_revalidation_tags',
			static function ( array $tags ): array {
				$tags[] = 'custom-tag';
				return $tags;
			}
		);

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		$this->assertContains(
			'custom-tag',
			$tags,
			'Tags must include custom tags added via wp_headless_revalidation_tags filter.'
		);
	}

	// -------------------------------------------------------------------------
	// 6. send_revalidation() Behavior Tests (via public handlers)
	// -------------------------------------------------------------------------

	/**
	 * Test that revalidation sends to the configured URL.
	 */
	public function test_send_revalidation_posts_to_configured_url(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertSame(
			'https://example.com/api/revalidate/',
			$this->captured_request['url'],
			'HTTP POST must be sent to the configured NEXTJS_REVALIDATION_URL.'
		);
	}

	/**
	 * Test that revalidation includes tags in the request body.
	 */
	public function test_send_revalidation_includes_tags_in_body(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$body = json_decode( $this->captured_request['args']['body'], true );

		$this->assertArrayHasKey(
			'tags',
			$body,
			'Request body must contain a "tags" key.'
		);
		$this->assertIsArray(
			$body['tags'],
			'The "tags" value must be an array.'
		);
	}

	/**
	 * Test that revalidation includes the secret in the request body.
	 */
	public function test_send_revalidation_includes_secret_in_body(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$body = json_decode( $this->captured_request['args']['body'], true );

		$this->assertSame(
			'test-secret-123',
			$body['secret'],
			'Request body must contain the secret matching NEXTJS_REVALIDATION_SECRET.'
		);
	}

	/**
	 * Test that the revalidation_sent action fires after sending.
	 */
	public function test_send_revalidation_fires_sent_action(): void {
		$this->intercept_http_requests();
		$this->listen_for_sent_action();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertNotNull(
			$this->captured_sent_action,
			'wp_headless_revalidation_sent action must fire after sending revalidation.'
		);
		$this->assertSame(
			'https://example.com/api/revalidate/',
			$this->captured_sent_action['url'],
			'The sent action must receive the revalidation URL.'
		);
		$this->assertIsArray(
			$this->captured_sent_action['tags'],
			'The sent action must receive the revalidation tags.'
		);
	}

	/**
	 * Test that the request args filter can modify request arguments.
	 */
	public function test_send_revalidation_respects_request_args_filter(): void {
		$this->intercept_http_requests();

		add_filter(
			'wp_headless_revalidation_request_args',
			static function ( array $args ): array {
				$args['timeout'] = 30;
				return $args;
			}
		);

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertSame(
			30,
			$this->captured_request['args']['timeout'],
			'wp_headless_revalidation_request_args filter must be able to modify request args.'
		);
	}

	/**
	 * Test that no HTTP request is sent when URL is empty.
	 */
	public function test_send_revalidation_skips_when_url_empty(): void {
		putenv( 'NEXTJS_REVALIDATION_URL=' );
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertNull(
			$this->captured_request,
			'No HTTP request must be sent when NEXTJS_REVALIDATION_URL is empty.'
		);
	}

	/**
	 * Test that no HTTP request is sent when secret is empty.
	 */
	public function test_send_revalidation_skips_when_secret_empty(): void {
		putenv( 'NEXTJS_REVALIDATION_SECRET=' );
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post    = get_post( $post_id );

		$module = new Revalidation();
		$module->handle_post_change( $post_id, $post );

		$this->assertNull(
			$this->captured_request,
			'No HTTP request must be sent when NEXTJS_REVALIDATION_SECRET is empty.'
		);
	}

	// -------------------------------------------------------------------------
	// 7. handle_post_delete() Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that handle_post_delete() sends revalidation with correct tags.
	 */
	public function test_handle_post_delete_sends_revalidation(): void {
		$this->intercept_http_requests();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		// Clear terms for predictable tags.
		wp_set_post_terms( $post_id, [], 'category' );
		wp_set_post_terms( $post_id, [], 'post_tag' );

		$module = new Revalidation();
		$module->handle_post_delete( $post_id );

		$this->assertNotNull(
			$this->captured_request,
			'handle_post_delete() must send a revalidation request.'
		);

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		$this->assertContains(
			'post',
			$tags,
			'Delete tags must include the post type.'
		);
		$this->assertContains(
			"post-{$post_id}",
			$tags,
			'Delete tags must include the post type-ID.'
		);
		$this->assertCount(
			2,
			$tags,
			'Delete tags for a post with no terms must contain exactly 2 tags.'
		);
	}

	/**
	 * Test that handle_post_delete() handles nonexistent post gracefully.
	 */
	public function test_handle_post_delete_handles_nonexistent_post(): void {
		$this->intercept_http_requests();

		$module = new Revalidation();
		$module->handle_post_delete( 999999 );

		$this->assertNull(
			$this->captured_request,
			'handle_post_delete() must not send revalidation for a nonexistent post.'
		);
	}

	// -------------------------------------------------------------------------
	// 8. handle_term_change() Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that handle_term_change() generates the taxonomy tag.
	 */
	public function test_handle_term_change_generates_taxonomy_tag(): void {
		$this->intercept_http_requests();

		$term = self::factory()->term->create_and_get( [ 'taxonomy' => 'category' ] );

		$module = new Revalidation();
		$module->handle_term_change( $term->term_id, $term->term_taxonomy_id, 'category' );

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		$this->assertContains(
			'category',
			$tags,
			'Term change tags must include the taxonomy slug.'
		);
	}

	/**
	 * Test that handle_term_change() generates the term-ID tag.
	 */
	public function test_handle_term_change_generates_term_id_tag(): void {
		$this->intercept_http_requests();

		$term = self::factory()->term->create_and_get( [ 'taxonomy' => 'category' ] );

		$module = new Revalidation();
		$module->handle_term_change( $term->term_id, $term->term_taxonomy_id, 'category' );

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		$this->assertContains(
			"term-{$term->term_id}",
			$tags,
			'Term change tags must include "term-{term_id}".'
		);
	}

	// -------------------------------------------------------------------------
	// 9. handle_menu_change() Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that handle_menu_change() generates the menu tag.
	 */
	public function test_handle_menu_change_generates_menu_tag(): void {
		$this->intercept_http_requests();

		$menu_id = 42;

		$module = new Revalidation();
		$module->handle_menu_change( $menu_id );

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		$this->assertContains(
			'menu',
			$tags,
			'Menu change tags must include "menu".'
		);
	}

	/**
	 * Test that handle_menu_change() generates the menu-ID tag.
	 */
	public function test_handle_menu_change_generates_menu_id_tag(): void {
		$this->intercept_http_requests();

		$menu_id = 42;

		$module = new Revalidation();
		$module->handle_menu_change( $menu_id );

		$body = json_decode( $this->captured_request['args']['body'], true );
		$tags = $body['tags'];

		$this->assertContains(
			'menu-42',
			$tags,
			'Menu change tags must include "menu-{menu_id}".'
		);
	}
}
