<?php
/**
 * Configuration Helper
 *
 * Environment variable and constant helpers for stateless configuration.
 *
 * @package ProjectAssistant\HeadlessToolkit\Helpers
 */

namespace ProjectAssistant\HeadlessToolkit\Helpers;

class Config {
	/**
	 * Get a configuration value from environment or constant.
	 *
	 * Priority: environment variable > wp-config constant > default.
	 *
	 * @param string $key     The configuration key.
	 * @param mixed  $default The default value.
	 *
	 * @return mixed
	 */
	public static function get( string $key, $default = '' ) {
		return pa_headless_get_config( $key, $default );
	}

	/**
	 * Get a boolean configuration value.
	 *
	 * @param string $key     The configuration key.
	 * @param bool   $default The default value.
	 *
	 * @return bool
	 */
	public static function get_bool( string $key, bool $default = false ): bool {
		$value = self::get( $key, $default );

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), [ 'true', '1', 'yes', 'on' ], true );
		}

		return (bool) $value;
	}

	/**
	 * Get a list configuration value (comma-separated string to array).
	 *
	 * @param string $key     The configuration key.
	 * @param array  $default The default value.
	 *
	 * @return string[]
	 */
	public static function get_list( string $key, array $default = [] ): array {
		$value = self::get( $key, '' );

		if ( empty( $value ) || ! is_string( $value ) ) {
			return $default;
		}

		return array_map( 'trim', explode( ',', $value ) );
	}

	/**
	 * Check if a required configuration key is set.
	 *
	 * @param string $key The configuration key.
	 *
	 * @return bool
	 */
	public static function has( string $key ): bool {
		$env_value = getenv( $key );
		if ( false !== $env_value && '' !== $env_value ) {
			return true;
		}

		return defined( $key );
	}
}
