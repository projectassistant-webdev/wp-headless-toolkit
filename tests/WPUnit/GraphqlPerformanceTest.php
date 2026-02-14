<?php
/**
 * Tests for src/Modules/GraphqlPerformance/GraphqlPerformance.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use Tests\ProjectAssistant\HeadlessToolkit\HeadlessToolkitTestCase;
use ProjectAssistant\HeadlessToolkit\Modules\GraphqlPerformance\GraphqlPerformance;
use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;

/**
 * Tests for the GraphqlPerformance module.
 *
 * @group module
 * @group graphql-performance
 */
class GraphqlPerformanceTest extends HeadlessToolkitTestCase {

	/**
	 * The module instance under test.
	 *
	 * @var GraphqlPerformance
	 */
	private GraphqlPerformance $module;

	/**
	 * Set up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->module = new GraphqlPerformance();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_filters_to_clean(): array {
		return [
			'wp_headless_module_enabled',
			'wp_headless_graphql_cache_headers',
			'wp_headless_graphql_complexity_limit',
			'graphql_response_headers_to_send',
			'graphql_query_complexity_limit',
			'graphql_request_results',
		];
	}

	// -------------------------------------------------------------------------
	// 1. ModuleInterface Contract Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that GraphqlPerformance implements ModuleInterface.
	 */
	public function test_implements_module_interface(): void {
		$this->assertInstanceOf(
			ModuleInterface::class,
			$this->module,
			'GraphqlPerformance must implement ModuleInterface.'
		);
	}

	/**
	 * Test that get_slug() returns the expected slug.
	 */
	public function test_get_slug_returns_graphql_performance(): void {
		$this->assertSame(
			'graphql_performance',
			GraphqlPerformance::get_slug(),
			'get_slug() must return "graphql_performance".'
		);
	}

	/**
	 * Test that get_name() returns the expected human-readable name.
	 */
	public function test_get_name_returns_graphql_performance(): void {
		$this->assertSame(
			'GraphQL Performance',
			GraphqlPerformance::get_name(),
			'get_name() must return "GraphQL Performance".'
		);
	}

	/**
	 * Test that is_enabled() returns true by default.
	 */
	public function test_is_enabled_returns_true_by_default(): void {
		$this->assertTrue(
			GraphqlPerformance::is_enabled(),
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
				if ( 'graphql_performance' === $slug ) {
					return false;
				}
				return $enabled;
			},
			10,
			2
		);

		$this->assertFalse(
			GraphqlPerformance::is_enabled(),
			'is_enabled() must return false when disabled via wp_headless_module_enabled filter.'
		);
	}

	// -------------------------------------------------------------------------
	// 2. Module Auto-Discovery Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that the module class is registered in Main's default modules.
	 */
	public function test_module_is_registered_in_main(): void {
		$main    = \ProjectAssistant\HeadlessToolkit\Main::instance();
		$classes = $main->get_registered_module_classes();

		$this->assertContains(
			GraphqlPerformance::class,
			$classes,
			'GraphqlPerformance must be listed in Main::get_default_modules().'
		);
	}

	// -------------------------------------------------------------------------
	// 3. WPGraphQL Detection Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that WPGraphQL detection returns true when WPGraphQL is active.
	 *
	 * Note: WPGraphQL IS installed in the test environment.
	 */
	public function test_wpgraphql_detection_returns_true_when_installed(): void {
		$this->assertTrue(
			$this->module->is_wpgraphql_active(),
			'is_wpgraphql_active() must return true when WPGraphQL class exists.'
		);
	}

	/**
	 * Test that init() registers hooks when WPGraphQL is active.
	 */
	public function test_init_registers_hooks_with_wpgraphql(): void {
		$this->module->init();

		$this->assertSame(
			10,
			has_filter( 'graphql_response_headers_to_send', [ $this->module, 'add_cache_headers' ] ),
			'init() must register graphql_response_headers_to_send filter when WPGraphQL is active.'
		);

		$this->assertSame(
			10,
			has_filter( 'graphql_query_complexity_limit', [ $this->module, 'set_complexity_limit' ] ),
			'init() must register graphql_query_complexity_limit filter when WPGraphQL is active.'
		);

		$this->assertSame(
			10,
			has_filter( 'graphql_request_results', [ $this->module, 'maybe_cache_response' ] ),
			'init() must register graphql_request_results filter when WPGraphQL is active.'
		);
	}

	/**
	 * Test that init() completes without errors when WPGraphQL is active.
	 */
	public function test_init_no_errors_with_wpgraphql(): void {
		// This should not throw any errors or produce warnings.
		$this->module->init();

		// If we got here without errors, the test passes.
		$this->assertTrue( true, 'init() must not produce errors when WPGraphQL is active.' );
	}

	// -------------------------------------------------------------------------
	// 4. Cache-Control Header Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that add_cache_headers() sets public Cache-Control for queries.
	 */
	public function test_add_cache_headers_sets_public_cache_for_queries(): void {
		$headers = [
			'Content-Type' => 'application/json',
		];

		$result = $this->module->add_cache_headers( $headers );

		$this->assertArrayHasKey(
			'Cache-Control',
			$result,
			'add_cache_headers() must add a Cache-Control header.'
		);
		$this->assertSame(
			'public, max-age=600',
			$result['Cache-Control'],
			'Cache-Control must default to public, max-age=600 for queries.'
		);
	}

	/**
	 * Test that add_cache_headers() preserves existing headers.
	 */
	public function test_add_cache_headers_preserves_existing_headers(): void {
		$headers = [
			'Content-Type'  => 'application/json',
			'X-Custom'      => 'value',
		];

		$result = $this->module->add_cache_headers( $headers );

		$this->assertSame(
			'application/json',
			$result['Content-Type'],
			'add_cache_headers() must preserve existing Content-Type header.'
		);
		$this->assertSame(
			'value',
			$result['X-Custom'],
			'add_cache_headers() must preserve existing custom headers.'
		);
	}

	// -------------------------------------------------------------------------
	// 5. Object Cache Integration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that maybe_cache_response() caches non-mutation responses.
	 */
	public function test_maybe_cache_response_caches_query_response(): void {
		$query     = '{ posts { nodes { title } } }';
		$variables = null;
		$response  = [ 'data' => [ 'posts' => [ 'nodes' => [] ] ] ];

		// First call should store in cache.
		$result = $this->module->maybe_cache_response( $response, null, 'query', $query, $variables );
		$this->assertSame( $response, $result, 'First call must return the original response.' );

		// Verify it was cached.
		$cache_key = $this->module->generate_cache_key( $query, $variables );
		$cached    = wp_cache_get( $cache_key, 'wp_headless_toolkit_graphql' );
		$this->assertSame( $response, $cached, 'Response must be stored in object cache.' );
	}

	/**
	 * Test that maybe_cache_response() returns cached response on subsequent calls.
	 */
	public function test_maybe_cache_response_returns_cached_on_second_call(): void {
		$query     = '{ posts { nodes { id } } }';
		$variables = null;
		$response  = [ 'data' => [ 'posts' => [ 'nodes' => [ [ 'id' => 1 ] ] ] ] ];

		// First call caches.
		$this->module->maybe_cache_response( $response, null, 'query', $query, $variables );

		// Second call with different response should return the cached version.
		$new_response = [ 'data' => [ 'posts' => [ 'nodes' => [ [ 'id' => 2 ] ] ] ] ];
		$result       = $this->module->maybe_cache_response( $new_response, null, 'query', $query, $variables );

		$this->assertSame(
			$response,
			$result,
			'Second call with same query must return cached response, not the new one.'
		);
	}

	/**
	 * Test that maybe_cache_response() does not cache mutations.
	 */
	public function test_maybe_cache_response_skips_mutations(): void {
		$query    = 'mutation { createPost(input: { title: "Test" }) { post { id } } }';
		$response = [ 'data' => [ 'createPost' => [ 'post' => [ 'id' => 1 ] ] ] ];

		$result = $this->module->maybe_cache_response( $response, null, 'mutation', $query, null );
		$this->assertSame( $response, $result, 'Mutations must return the response without caching.' );

		// Verify nothing was cached.
		$cache_key = $this->module->generate_cache_key( $query, null );
		$cached    = wp_cache_get( $cache_key, 'wp_headless_toolkit_graphql' );
		$this->assertFalse( $cached, 'Mutation responses must not be stored in object cache.' );
	}

	// -------------------------------------------------------------------------
	// 6. Cache Key Uniqueness Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that different queries produce different cache keys.
	 */
	public function test_different_queries_produce_different_cache_keys(): void {
		$key1 = $this->module->generate_cache_key( '{ posts { nodes { title } } }', null );
		$key2 = $this->module->generate_cache_key( '{ pages { nodes { title } } }', null );

		$this->assertNotSame(
			$key1,
			$key2,
			'Different queries must produce different cache keys.'
		);
	}

	/**
	 * Test that same query with different variables produces different cache keys.
	 */
	public function test_different_variables_produce_different_cache_keys(): void {
		$query = '{ post(id: $id) { title } }';
		$key1  = $this->module->generate_cache_key( $query, [ 'id' => 1 ] );
		$key2  = $this->module->generate_cache_key( $query, [ 'id' => 2 ] );

		$this->assertNotSame(
			$key1,
			$key2,
			'Same query with different variables must produce different cache keys.'
		);
	}

	/**
	 * Test that same query with same variables produces the same cache key.
	 */
	public function test_same_query_and_variables_produce_same_cache_key(): void {
		$query     = '{ posts { nodes { title } } }';
		$variables = [ 'first' => 10 ];
		$key1      = $this->module->generate_cache_key( $query, $variables );
		$key2      = $this->module->generate_cache_key( $query, $variables );

		$this->assertSame(
			$key1,
			$key2,
			'Same query and variables must produce identical cache keys.'
		);
	}

	// -------------------------------------------------------------------------
	// 7. Query Complexity Limit Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that set_complexity_limit() returns default limit of 500.
	 */
	public function test_set_complexity_limit_returns_default(): void {
		$result = $this->module->set_complexity_limit( 1000 );

		$this->assertSame(
			500,
			$result,
			'set_complexity_limit() must return 500 as default complexity limit.'
		);
	}

	/**
	 * Test that set_complexity_limit() respects constant override.
	 */
	public function test_set_complexity_limit_respects_constant_override(): void {
		// Use the wp_headless_config_value filter to simulate a constant.
		add_filter(
			'wp_headless_config_value',
			static function ( $value, $key ) {
				if ( 'HEADLESS_GRAPHQL_COMPLEXITY_LIMIT' === $key ) {
					return 750;
				}
				return $value;
			},
			10,
			2
		);

		$result = $this->module->set_complexity_limit( 1000 );

		$this->assertSame(
			750,
			$result,
			'set_complexity_limit() must respect HEADLESS_GRAPHQL_COMPLEXITY_LIMIT configuration.'
		);
	}

	// -------------------------------------------------------------------------
	// 8. Configuration Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that get_cache_ttl() returns default of 600 seconds.
	 */
	public function test_get_cache_ttl_returns_default(): void {
		$this->assertSame(
			600,
			$this->module->get_cache_ttl(),
			'get_cache_ttl() must return 600 as default TTL.'
		);
	}

	/**
	 * Test that get_cache_ttl() respects constant override.
	 */
	public function test_get_cache_ttl_respects_constant_override(): void {
		add_filter(
			'wp_headless_config_value',
			static function ( $value, $key ) {
				if ( 'HEADLESS_GRAPHQL_CACHE_TTL' === $key ) {
					return 300;
				}
				return $value;
			},
			10,
			2
		);

		$this->assertSame(
			300,
			$this->module->get_cache_ttl(),
			'get_cache_ttl() must respect HEADLESS_GRAPHQL_CACHE_TTL configuration.'
		);
	}

	// -------------------------------------------------------------------------
	// 9. Mutation Detection Tests
	// -------------------------------------------------------------------------

	/**
	 * Test that is_mutation_query() returns true for mutation queries.
	 */
	public function test_is_mutation_query_detects_mutations(): void {
		$this->assertTrue(
			$this->module->is_mutation_query( 'mutation { createPost(input: {}) { post { id } } }' ),
			'is_mutation_query() must return true for mutation queries.'
		);
	}

	/**
	 * Test that is_mutation_query() returns false for regular queries.
	 */
	public function test_is_mutation_query_returns_false_for_queries(): void {
		$this->assertFalse(
			$this->module->is_mutation_query( '{ posts { nodes { title } } }' ),
			'is_mutation_query() must return false for regular queries.'
		);
	}

	/**
	 * Test that is_mutation_query() returns false for empty strings.
	 */
	public function test_is_mutation_query_returns_false_for_empty_string(): void {
		$this->assertFalse(
			$this->module->is_mutation_query( '' ),
			'is_mutation_query() must return false for empty strings.'
		);
	}

	/**
	 * Test that is_mutation_query() handles whitespace-prefixed mutations.
	 */
	public function test_is_mutation_query_handles_whitespace_prefix(): void {
		$this->assertTrue(
			$this->module->is_mutation_query( '  mutation { deletePost(input: {}) { deletedId } }' ),
			'is_mutation_query() must handle whitespace-prefixed mutation queries.'
		);
	}

	/**
	 * Test that is_mutation_query() does not false-positive on query mentioning "mutation".
	 */
	public function test_is_mutation_query_no_false_positive_for_query_with_mutation_word(): void {
		$this->assertFalse(
			$this->module->is_mutation_query( '{ posts(where: { search: "mutation" }) { nodes { title } } }' ),
			'is_mutation_query() must not false-positive when "mutation" appears inside a query string value.'
		);
	}
}
