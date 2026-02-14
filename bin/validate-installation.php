<?php
/**
 * Installation Validation Script
 *
 * Validates WP Headless Toolkit installation and configuration.
 * Run with WP-CLI: wp eval-file wp-content/plugins/wp-headless-toolkit/bin/validate-installation.php
 *
 * Exit codes:
 *   0 - All checks passed
 *   1 - One or more checks failed
 *
 * @package ProjectAssistant\HeadlessToolkit
 */

// Ensure this is run within WordPress context.
if ( ! defined( 'ABSPATH' ) ) {
	// If run directly (not via WP-CLI), try to bootstrap WordPress.
	$wp_load_paths = [
		__DIR__ . '/../../../../wp-load.php',       // Standard plugins dir.
		__DIR__ . '/../../../wp-load.php',           // Root-level plugin.
	];

	$loaded = false;
	foreach ( $wp_load_paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			$loaded = true;
			break;
		}
	}

	if ( ! $loaded ) {
		echo "Error: Could not locate wp-load.php. Run this script via WP-CLI:\n";
		echo "  wp eval-file wp-content/plugins/wp-headless-toolkit/bin/validate-installation.php\n";
		exit( 1 );
	}
}

/**
 * Run all validation checks and output results.
 */
function wp_headless_validate_installation(): int {
	$results  = [];
	$warnings = [];
	$errors   = [];

	echo "\n";
	echo "=============================================================\n";
	echo "  WP Headless Toolkit - Installation Validation\n";
	echo "=============================================================\n\n";

	// -------------------------------------------------------------------------
	// 1. PHP Version Check
	// -------------------------------------------------------------------------
	$php_version = PHP_VERSION;
	$php_ok      = version_compare( $php_version, '8.1.0', '>=' );

	$results[] = [
		'check'  => 'PHP Version',
		'status' => $php_ok ? 'PASS' : 'FAIL',
		'detail' => $php_version . ( $php_ok ? '' : ' (requires >= 8.1)' ),
	];

	if ( ! $php_ok ) {
		$errors[] = 'PHP 8.1+ is required. Current version: ' . $php_version;
	}

	// -------------------------------------------------------------------------
	// 2. WordPress Version Check
	// -------------------------------------------------------------------------
	global $wp_version;
	$wp_ok = version_compare( $wp_version, '6.4', '>=' );

	$results[] = [
		'check'  => 'WordPress Version',
		'status' => $wp_ok ? 'PASS' : 'FAIL',
		'detail' => $wp_version . ( $wp_ok ? '' : ' (requires >= 6.4)' ),
	];

	if ( ! $wp_ok ) {
		$errors[] = 'WordPress 6.4+ is required. Current version: ' . $wp_version;
	}

	// -------------------------------------------------------------------------
	// 3. WPGraphQL Status
	// -------------------------------------------------------------------------
	$wpgraphql_active = class_exists( 'WPGraphQL' );

	$results[] = [
		'check'  => 'WPGraphQL Active',
		'status' => $wpgraphql_active ? 'PASS' : 'FAIL',
		'detail' => $wpgraphql_active ? 'Active' : 'Not active (required dependency)',
	];

	if ( ! $wpgraphql_active ) {
		$errors[] = 'WPGraphQL plugin must be installed and activated.';
	}

	// -------------------------------------------------------------------------
	// 4. Plugin Active Check
	// -------------------------------------------------------------------------
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_file   = 'wp-headless-toolkit/wp-headless-toolkit.php';
	$plugin_active = is_plugin_active( $plugin_file );

	$results[] = [
		'check'  => 'Plugin Active',
		'status' => $plugin_active ? 'PASS' : 'WARN',
		'detail' => $plugin_active ? 'Active' : 'Not active',
	];

	if ( ! $plugin_active ) {
		$warnings[] = 'WP Headless Toolkit plugin is not active. Activate it in wp-admin.';
	}

	// -------------------------------------------------------------------------
	// 5. Plugin Version
	// -------------------------------------------------------------------------
	$version = defined( 'WP_HEADLESS_VERSION' ) ? WP_HEADLESS_VERSION : 'Unknown';

	$results[] = [
		'check'  => 'Plugin Version',
		'status' => 'INFO',
		'detail' => $version,
	];

	// -------------------------------------------------------------------------
	// 6. Module Status
	// -------------------------------------------------------------------------
	echo "--- System Checks ---\n\n";

	foreach ( $results as $result ) {
		printf(
			"  [%s] %-25s %s\n",
			wp_headless_format_status( $result['status'] ),
			$result['check'],
			$result['detail']
		);
	}

	echo "\n--- Module Status ---\n\n";

	$modules = wp_headless_get_module_info();

	foreach ( $modules as $module ) {
		$status = $module['enabled'] ? 'ON ' : 'OFF';
		$icon   = $module['enabled'] ? 'PASS' : 'INFO';

		printf(
			"  [%s] %-30s %s\n",
			wp_headless_format_status( $icon ),
			$module['name'],
			$status
		);
	}

	// -------------------------------------------------------------------------
	// 7. Environment Variables
	// -------------------------------------------------------------------------
	echo "\n--- Environment Variables ---\n\n";

	$env_vars = wp_headless_get_env_var_list();

	foreach ( $env_vars as $var ) {
		$is_set = wp_headless_check_env_var( $var['key'] );
		$status = $is_set ? 'SET' : 'NOT SET';
		$icon   = $is_set ? 'PASS' : ( $var['required'] ? 'FAIL' : 'INFO' );

		printf(
			"  [%s] %-45s %s\n",
			wp_headless_format_status( $icon ),
			$var['key'],
			$status
		);

		if ( ! $is_set && $var['required'] ) {
			$errors[] = $var['key'] . ' is required but not configured.';
		}
	}

	// -------------------------------------------------------------------------
	// Summary
	// -------------------------------------------------------------------------
	echo "\n--- Summary ---\n\n";

	$error_count   = count( $errors );
	$warning_count = count( $warnings );

	if ( $error_count > 0 ) {
		echo "  ERRORS ({$error_count}):\n";
		foreach ( $errors as $error ) {
			echo "    - {$error}\n";
		}
		echo "\n";
	}

	if ( $warning_count > 0 ) {
		echo "  WARNINGS ({$warning_count}):\n";
		foreach ( $warnings as $warning ) {
			echo "    - {$warning}\n";
		}
		echo "\n";
	}

	if ( 0 === $error_count && 0 === $warning_count ) {
		echo "  All checks passed. Installation is correctly configured.\n";
	} elseif ( 0 === $error_count ) {
		echo "  No errors found. {$warning_count} warning(s) to review.\n";
	} else {
		echo "  {$error_count} error(s) found. Please resolve before using in production.\n";
	}

	echo "\n=============================================================\n\n";

	return $error_count > 0 ? 1 : 0;
}

/**
 * Format a status label for terminal output.
 *
 * @param string $status The status (PASS, FAIL, WARN, INFO).
 *
 * @return string Formatted status string.
 */
function wp_headless_format_status( string $status ): string {
	$labels = [
		'PASS' => 'PASS',
		'FAIL' => 'FAIL',
		'WARN' => 'WARN',
		'INFO' => 'INFO',
	];

	return $labels[ $status ] ?? $status;
}

/**
 * Get module information.
 *
 * Returns information about all registered modules and their enabled status.
 *
 * @return array<int, array{slug: string, name: string, enabled: bool}>
 */
function wp_headless_get_module_info(): array {
	$modules = [
		[ 'slug' => 'revalidation', 'name' => 'ISR Revalidation', 'class' => 'ProjectAssistant\HeadlessToolkit\Modules\Revalidation\Revalidation' ],
		[ 'slug' => 'rest_security', 'name' => 'REST API Security', 'class' => 'ProjectAssistant\HeadlessToolkit\Modules\RestSecurity\RestSecurity' ],
		[ 'slug' => 'head_cleanup', 'name' => 'Head Cleanup', 'class' => 'ProjectAssistant\HeadlessToolkit\Modules\HeadCleanup\HeadCleanup' ],
		[ 'slug' => 'frontend_redirect', 'name' => 'Frontend Redirect', 'class' => 'ProjectAssistant\HeadlessToolkit\Modules\FrontendRedirect\FrontendRedirect' ],
		[ 'slug' => 'graphql_performance', 'name' => 'GraphQL Performance', 'class' => 'ProjectAssistant\HeadlessToolkit\Modules\GraphqlPerformance\GraphqlPerformance' ],
		[ 'slug' => 'cors', 'name' => 'CORS', 'class' => 'ProjectAssistant\HeadlessToolkit\Modules\Cors\Cors' ],
		[ 'slug' => 'security_headers', 'name' => 'Security Headers', 'class' => 'ProjectAssistant\HeadlessToolkit\Modules\SecurityHeaders\SecurityHeaders' ],
		[ 'slug' => 'preview_mode', 'name' => 'Preview Mode', 'class' => 'ProjectAssistant\HeadlessToolkit\Modules\PreviewMode\PreviewMode' ],
		[ 'slug' => 'migrate_db_compat', 'name' => 'WP Migrate DB Compat', 'class' => 'ProjectAssistant\HeadlessToolkit\Modules\MigrateDbCompat\MigrateDbCompat' ],
	];

	$result = [];

	foreach ( $modules as $module ) {
		$enabled = false;

		if ( class_exists( $module['class'] ) && method_exists( $module['class'], 'is_enabled' ) ) {
			$enabled = $module['class']::is_enabled();
		}

		$result[] = [
			'slug'    => $module['slug'],
			'name'    => $module['name'],
			'enabled' => $enabled,
		];
	}

	return $result;
}

/**
 * Check if an environment variable or constant is set.
 *
 * @param string $key The variable name.
 *
 * @return bool
 */
function wp_headless_check_env_var( string $key ): bool {
	$env_value = getenv( $key );
	if ( false !== $env_value && '' !== $env_value ) {
		return true;
	}

	return defined( $key );
}

/**
 * Get the list of environment variables to check.
 *
 * @return array<int, array{key: string, required: bool, description: string}>
 */
function wp_headless_get_env_var_list(): array {
	return [
		[ 'key' => 'HEADLESS_FRONTEND_URL', 'required' => true, 'description' => 'Frontend application URL' ],
		[ 'key' => 'NEXTJS_REVALIDATION_URL', 'required' => false, 'description' => 'Revalidation endpoint URL' ],
		[ 'key' => 'NEXTJS_REVALIDATION_SECRET', 'required' => false, 'description' => 'Revalidation shared secret' ],
		[ 'key' => 'HEADLESS_CORS_ORIGINS', 'required' => false, 'description' => 'Allowed CORS origins' ],
		[ 'key' => 'HEADLESS_PREVIEW_SECRET', 'required' => false, 'description' => 'Preview mode JWT secret' ],
		[ 'key' => 'WP_HEADLESS_PREVIEW_TOKEN_EXPIRY', 'required' => false, 'description' => 'Preview token expiry' ],
		[ 'key' => 'HEADLESS_GRAPHQL_CACHE_TTL', 'required' => false, 'description' => 'GraphQL cache TTL' ],
		[ 'key' => 'HEADLESS_GRAPHQL_COMPLEXITY_LIMIT', 'required' => false, 'description' => 'GraphQL complexity limit' ],
		[ 'key' => 'WP_HEADLESS_ENABLE_SECURITY_HEADERS', 'required' => false, 'description' => 'Enable security headers' ],
	];
}

// Run the validation.
$exit_code = wp_headless_validate_installation();
exit( $exit_code );
