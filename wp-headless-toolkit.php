<?php
declare(strict_types=1);
/**
 * Plugin Name: WP Headless Toolkit
 * Plugin URI: https://bitbucket.org/projectassistant/wp-headless-toolkit
 * Description: Unified headless WordPress plugin for Next.js projects - ISR revalidation, REST security, CORS, preview mode, and more.
 * Author: Project Assistant
 * Author URI: https://projectassistant.org
 * Version: 1.4.0
 * Text Domain: wp-headless-toolkit
 * Domain Path: /languages
 * Requires at least: 6.4
 * Tested up to: 6.8
 * Requires PHP: 8.1
 * Requires Plugins: wp-graphql
 * WPGraphQL requires at least: 2.0.0
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package ProjectAssistant\HeadlessToolkit
 * @author Project Assistant
 * @license GPL-3
 * @version 1.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If the codeception remote coverage file exists, require it.
if ( file_exists( __DIR__ . '/c3.php' ) ) {
	require_once __DIR__ . '/c3.php';
}

// Run this function when the plugin is activated.
if ( file_exists( __DIR__ . '/activation.php' ) ) {
	require_once __DIR__ . '/activation.php';
	register_activation_hook( __FILE__, 'wp_headless_activation_callback' );
}

// Run this function when the plugin is deactivated.
if ( file_exists( __DIR__ . '/deactivation.php' ) ) {
	require_once __DIR__ . '/deactivation.php';
	register_deactivation_hook( __FILE__, 'wp_headless_deactivation_callback' );
}

/**
 * Define plugin constants.
 */
function wp_headless_constants(): void {
	// Plugin version.
	if ( ! defined( 'WP_HEADLESS_VERSION' ) ) {
		define( 'WP_HEADLESS_VERSION', '1.4.0' );
	}

	// Plugin Folder Path.
	if ( ! defined( 'WP_HEADLESS_PLUGIN_DIR' ) ) {
		define( 'WP_HEADLESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	}

	// Plugin Folder URL.
	if ( ! defined( 'WP_HEADLESS_PLUGIN_URL' ) ) {
		define( 'WP_HEADLESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	// Plugin Root File.
	if ( ! defined( 'WP_HEADLESS_PLUGIN_FILE' ) ) {
		define( 'WP_HEADLESS_PLUGIN_FILE', __FILE__ );
	}

	// Whether to autoload the files or not.
	if ( ! defined( 'WP_HEADLESS_AUTOLOAD' ) ) {
		define( 'WP_HEADLESS_AUTOLOAD', true );
	}
}

/**
 * Checks if all the required plugins are installed and activated.
 *
 * @return string[]
 */
function wp_headless_dependencies_not_ready(): array {
	$deps = [];

	if ( ! class_exists( '\WPGraphQL' ) ) {
		$deps[] = 'WPGraphQL';
	}

	return $deps;
}

/**
 * Initializes plugin.
 */
function wp_headless_init(): void {
	wp_headless_constants();

	$not_ready = wp_headless_dependencies_not_ready();

	if ( empty( $not_ready ) && defined( 'WP_HEADLESS_PLUGIN_DIR' ) ) {
		require_once WP_HEADLESS_PLUGIN_DIR . 'src/Main.php';
		return;
	}

	foreach ( $not_ready as $dep ) {
		add_action(
			'admin_notices',
			static function () use ( $dep ) {
				?>
				<div class="error notice">
					<p>
						<?php
							printf(
								/* translators: dependency not ready error message */
								esc_html__( '%1$s must be active for WP Headless Toolkit to work.', 'wp-headless-toolkit' ),
								esc_html( $dep )
							);
						?>
					</p>
				</div>
				<?php
			}
		);
	}
}

add_action( 'graphql_init', 'wp_headless_init' );
