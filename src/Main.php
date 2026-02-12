<?php
/**
 * Initializes a singleton instance of the plugin and loads all modules.
 *
 * @package ProjectAssistant\HeadlessToolkit
 */

namespace ProjectAssistant\HeadlessToolkit;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Modules\Revalidation\Revalidation;
use ProjectAssistant\HeadlessToolkit\Modules\RestSecurity\RestSecurity;
use ProjectAssistant\HeadlessToolkit\Modules\FrontendRedirect\FrontendRedirect;
use ProjectAssistant\HeadlessToolkit\Modules\MigrateDbCompat\MigrateDbCompat;
use ProjectAssistant\HeadlessToolkit\Modules\HeadCleanup\HeadCleanup;

if ( ! class_exists( 'ProjectAssistant\HeadlessToolkit\Main' ) ) :

	/**
	 * Class - Main
	 */
	final class Main {
		/**
		 * Class instance.
		 *
		 * @var ?self $instance
		 */
		private static $instance;

		/**
		 * Loaded module instances.
		 *
		 * @var ModuleInterface[]
		 */
		private array $modules = [];

		/**
		 * Constructor - singleton pattern.
		 */
		public static function instance(): self {
			if ( ! isset( self::$instance ) || ! ( is_a( self::$instance, self::class ) ) ) {
				if ( ! function_exists( 'is_plugin_active' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php'; // @phpstan-ignore requireOnce.fileNotFound
				}
				self::$instance = new self();
				self::$instance->includes();
				self::$instance->load_modules();
			}

			/**
			 * Fire off init action.
			 *
			 * @param self $instance The instance of the plugin class.
			 */
			do_action( 'pa_headless_init', self::$instance );

			return self::$instance;
		}

		/**
		 * Includes the required files with Composer's autoload.
		 *
		 * @codeCoverageIgnore
		 */
		private function includes(): void {
			if ( defined( 'PA_HEADLESS_AUTOLOAD' ) && false !== PA_HEADLESS_AUTOLOAD && defined( 'PA_HEADLESS_PLUGIN_DIR' ) ) {
				require_once PA_HEADLESS_PLUGIN_DIR . 'vendor/autoload.php';
			}
		}

		/**
		 * Register and initialize all modules.
		 */
		private function load_modules(): void {
			/**
			 * Filter the list of module classes to load.
			 *
			 * @param string[] $module_classes Array of module class names implementing ModuleInterface.
			 */
			$module_classes = apply_filters( 'pa_headless_module_classes', $this->get_default_modules() );

			foreach ( $module_classes as $module_class ) {
				if ( ! is_a( $module_class, ModuleInterface::class, true ) ) {
					continue;
				}

				if ( ! $module_class::is_enabled() ) {
					continue;
				}

				$module = new $module_class();
				$module->init();
				$this->modules[ $module_class::get_slug() ] = $module;
			}

			/**
			 * Fires after all modules have been loaded.
			 *
			 * @param ModuleInterface[] $modules The loaded module instances.
			 */
			do_action( 'pa_headless_modules_loaded', $this->modules );
		}

		/**
		 * Get the default module classes for Phase 1.
		 *
		 * @return string[]
		 */
		private function get_default_modules(): array {
			return [
				Revalidation::class,
				RestSecurity::class,
				FrontendRedirect::class,
				MigrateDbCompat::class,
				HeadCleanup::class,
			];
		}

		/**
		 * Get a loaded module instance by slug.
		 *
		 * @param string $slug The module slug.
		 *
		 * @return ?ModuleInterface
		 */
		public function get_module( string $slug ): ?ModuleInterface {
			return $this->modules[ $slug ] ?? null;
		}

		/**
		 * Get all loaded module instances.
		 *
		 * @return ModuleInterface[]
		 */
		public function get_modules(): array {
			return $this->modules;
		}

		/**
		 * Throw error on object clone.
		 *
		 * @codeCoverageIgnore
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'The plugin Main class should not be cloned.', 'pa-headless-toolkit' ), '1.0.0' );
		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @codeCoverageIgnore
		 */
		public function __wakeup(): void {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'De-serializing instances of the plugin Main class is not allowed.', 'pa-headless-toolkit' ), '1.0.0' );
		}
	}
endif;
