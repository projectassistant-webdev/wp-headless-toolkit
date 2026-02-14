<?php
declare(strict_types=1);
/**
 * Admin Settings Page
 *
 * Display-only settings page showing module status and environment configuration.
 *
 * @package ProjectAssistant\HeadlessToolkit\Admin
 */

namespace ProjectAssistant\HeadlessToolkit\Admin;

use ProjectAssistant\HeadlessToolkit\Helpers\Config;
use ProjectAssistant\HeadlessToolkit\Main;

/**
 * Class - SettingsPage
 *
 * Registers a WordPress admin settings page under Settings > WP Headless Toolkit.
 * This is a display-only page with no form inputs or database writes.
 */
class SettingsPage {

	/**
	 * Register the admin_menu hook.
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
	}

	/**
	 * Register the settings page under the Settings menu.
	 */
	public function add_settings_page(): void {
		add_options_page(
			'WP Headless Toolkit',
			'WP Headless Toolkit',
			'manage_options',
			'wp-headless-toolkit',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the settings page HTML.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-headless-toolkit' ),
				403
			);
		}

		$module_statuses = $this->get_module_statuses();
		$env_config      = $this->get_env_config();
		$version         = $this->get_plugin_version();
		?>
		<div class="wrap">
			<h1>WP Headless Toolkit <small>v<?php echo esc_html( $version ); ?></small></h1>

			<h2>Module Status</h2>
			<table class="widefat">
				<thead>
					<tr>
						<th>Module Name</th>
						<th>Slug</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $module_statuses as $module ) : ?>
					<tr>
						<td><?php echo esc_html( $module['name'] ); ?></td>
						<td><code><?php echo esc_html( $module['slug'] ); ?></code></td>
						<td>
							<?php if ( $module['enabled'] ) : ?>
								<span style="color: green; font-weight: bold;">Enabled</span>
							<?php else : ?>
								<span style="color: red; font-weight: bold;">Disabled</span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Environment Configuration</h2>
			<table class="widefat">
				<thead>
					<tr>
						<th>Variable</th>
						<th>Status</th>
						<th>Value</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $env_config as $entry ) : ?>
					<tr>
						<td><code><?php echo esc_html( $entry['key'] ); ?></code></td>
						<td>
							<?php if ( $entry['is_set'] ) : ?>
								<span style="color: green;">Set</span>
							<?php else : ?>
								<span style="color: #999;">Not Set</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $entry['value'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2>Documentation</h2>
			<p>
				For full documentation and setup instructions, visit the
				<a href="https://github.com/projectassistant-webdev/wp-headless-toolkit" target="_blank" rel="noopener noreferrer">
					plugin documentation
				</a>.
			</p>
		</div>
		<?php
	}

	/**
	 * Get status data for all registered modules.
	 *
	 * Returns data for ALL modules including disabled ones.
	 *
	 * @return array<int, array{slug: string, name: string, enabled: bool, class: string}>
	 */
	public function get_module_statuses(): array {
		$main           = Main::instance();
		$module_classes = $main->get_registered_module_classes();
		$statuses       = [];

		foreach ( $module_classes as $module_class ) {
			$statuses[] = [
				'slug'    => $module_class::get_slug(),
				'name'    => $module_class::get_name(),
				'enabled' => $module_class::is_enabled(),
				'class'   => $module_class,
			];
		}

		return $statuses;
	}

	/**
	 * Get environment variable configuration data.
	 *
	 * Returns documentation for all environment variables with their
	 * current status, masked values for secrets, and set/unset status.
	 *
	 * @return array<int, array{key: string, description: string, is_set: bool, value: string, is_secret: bool}>
	 */
	public function get_env_config(): array {
		$env_vars = [
			[
				'key'         => 'NEXTJS_REVALIDATION_URL',
				'description' => 'Next.js revalidation endpoint URL',
			],
			[
				'key'         => 'NEXTJS_REVALIDATION_SECRET',
				'description' => 'Shared secret for revalidation requests',
			],
			[
				'key'         => 'HEADLESS_FRONTEND_URL',
				'description' => 'Frontend application URL',
			],
			[
				'key'         => 'HEADLESS_CORS_ORIGINS',
				'description' => 'Allowed CORS origins (comma-separated)',
			],
			[
				'key'         => 'HEADLESS_PREVIEW_SECRET',
				'description' => 'Preview mode shared secret',
			],
			[
				'key'         => 'WP_HEADLESS_DISABLE_REVALIDATION',
				'description' => 'Disable revalidation module',
			],
			[
				'key'         => 'WP_HEADLESS_DISABLE_REST_SECURITY',
				'description' => 'Disable REST security module',
			],
			[
				'key'         => 'WP_HEADLESS_DISABLE_FRONTEND_REDIRECT',
				'description' => 'Disable frontend redirect module',
			],
			[
				'key'         => 'WP_HEADLESS_DISABLE_MIGRATE_DB_COMPAT',
				'description' => 'Disable WP Migrate DB compat module',
			],
			[
				'key'         => 'WP_HEADLESS_DISABLE_HEAD_CLEANUP',
				'description' => 'Disable head cleanup module',
			],
			[
				'key'         => 'WP_HEADLESS_PREVIEW_TOKEN_EXPIRY',
				'description' => 'Preview token expiry in seconds (default: 300)',
			],
			[
				'key'         => 'WP_HEADLESS_DISABLE_PREVIEW_MODE',
				'description' => 'Disable preview mode module',
			],
			[
				'key'         => 'WP_HEADLESS_DISABLE_CORS',
				'description' => 'Disable CORS module (Phase 2)',
			],
			[
				'key'         => 'WP_HEADLESS_ENABLE_SECURITY_HEADERS',
				'description' => 'Enable security headers module (disabled by default)',
			],
			[
				'key'         => 'WP_HEADLESS_DISABLE_SECURITY_HEADERS',
				'description' => 'Disable security headers module (Phase 2)',
			],
		];

		$config = [];

		foreach ( $env_vars as $var ) {
			$key       = $var['key'];
			$is_set    = Config::has( $key );
			$raw_value = $is_set ? (string) Config::get( $key, '' ) : '';
			$is_secret = $this->is_secret_key( $key );
			$value     = $this->mask_value( $key, $raw_value );

			$config[] = [
				'key'         => $key,
				'description' => $var['description'],
				'is_set'      => $is_set,
				'value'       => $value,
				'is_secret'   => $is_secret,
			];
		}

		return $config;
	}

	/**
	 * Mask a value if it belongs to a secret key.
	 *
	 * @param string $key   The environment variable key.
	 * @param string $value The raw value.
	 *
	 * @return string Masked or actual value.
	 */
	private function mask_value( string $key, string $value ): string {
		if ( '' === $value ) {
			return 'Not configured';
		}

		if ( $this->is_secret_key( $key ) ) {
			return '********';
		}

		return $value;
	}

	/**
	 * Check if a key is a secret key.
	 *
	 * Keys containing "SECRET" (case-insensitive) are treated as secret.
	 *
	 * @param string $key The environment variable key name.
	 *
	 * @return bool True if the key contains SECRET.
	 */
	private function is_secret_key( string $key ): bool {
		return false !== stripos( $key, 'SECRET' );
	}

	/**
	 * Get the plugin version.
	 *
	 * @return string The plugin version from WP_HEADLESS_VERSION constant.
	 */
	private function get_plugin_version(): string {
		if ( defined( 'WP_HEADLESS_VERSION' ) ) {
			return (string) WP_HEADLESS_VERSION;
		}

		return '0.0.0';
	}
}
