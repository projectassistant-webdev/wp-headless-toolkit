<?php
declare(strict_types=1);
/**
 * Module Interface
 *
 * Contract that all toolkit modules must implement.
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules
 */

namespace ProjectAssistant\HeadlessToolkit\Modules;

interface ModuleInterface {
	/**
	 * Get the module slug used for configuration.
	 *
	 * @return string e.g. 'revalidation', 'rest_security'
	 */
	public static function get_slug(): string;

	/**
	 * Get the human-readable module name.
	 *
	 * @return string e.g. 'ISR Revalidation', 'REST Security'
	 */
	public static function get_name(): string;

	/**
	 * Check if this module is enabled.
	 *
	 * Modules are enabled by default and can be disabled via
	 * constants (WP_HEADLESS_DISABLE_{SLUG}) or filters.
	 *
	 * Two valid implementation patterns:
	 *
	 * Pattern A (Direct return) -- For modules with no additional prerequisites:
	 *   return wp_headless_is_module_enabled( static::get_slug() );
	 *
	 * Pattern B (Guard + additional checks) -- For modules requiring extra conditions
	 * (e.g., WPGraphQL dependency):
	 *   if ( ! wp_headless_is_module_enabled( static::get_slug() ) ) {
	 *       return false;
	 *   }
	 *   return some_condition();
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool;

	/**
	 * Register WordPress hooks for this module.
	 *
	 * Only called if is_enabled() returns true.
	 */
	public function init(): void;
}
