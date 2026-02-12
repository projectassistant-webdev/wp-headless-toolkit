<?php
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
	 * constants (PA_HEADLESS_DISABLE_{SLUG}) or filters.
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
