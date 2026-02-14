<?php
declare(strict_types=1);
/**
 * This file contains access functions for various class methods.
 *
 * @package ProjectAssistant\HeadlessToolkit
 */

/**
 * Get a configuration value from environment or constant.
 *
 * Checks environment variables first (Bedrock .env compatible),
 * then falls back to wp-config.php constants.
 *
 * @param string $key           The configuration key (e.g. 'NEXTJS_REVALIDATION_URL').
 * @param mixed  $default_value The default value if not set.
 *
 * @return mixed
 */
function wp_headless_get_config( string $key, $default_value = '' ) {
	// Check environment variable first (Bedrock .env compatible).
	$env_value = getenv( $key );
	if ( false !== $env_value ) {
		return $env_value;
	}

	// Check for wp-config constant.
	if ( defined( $key ) ) {
		return constant( $key );
	}

	/**
	 * Filter the config value before returning.
	 *
	 * @param mixed  $value The value of the config key.
	 * @param string $key   The config key.
	 */
	return apply_filters( 'wp_headless_config_value', $default_value, $key );
}

/**
 * Check if a specific module is enabled.
 *
 * @param string $slug The module slug (e.g. 'revalidation', 'rest_security').
 *
 * @return bool
 */
function wp_headless_is_module_enabled( string $slug ): bool {
	// Check for disable constant (e.g. WP_HEADLESS_DISABLE_CORS).
	$constant_name = 'WP_HEADLESS_DISABLE_' . strtoupper( $slug );
	if ( defined( $constant_name ) && constant( $constant_name ) ) {
		return false;
	}

	// Check environment variable (e.g. WP_HEADLESS_DISABLE_CORS=true).
	$env_value = getenv( $constant_name );
	if ( false !== $env_value && in_array( strtolower( $env_value ), [ 'true', '1', 'yes' ], true ) ) {
		return false;
	}

	/**
	 * Filter whether a module is enabled.
	 *
	 * @param bool   $enabled Whether the module is enabled.
	 * @param string $slug    The module slug.
	 */
	return (bool) apply_filters( 'wp_headless_module_enabled', true, $slug );
}
