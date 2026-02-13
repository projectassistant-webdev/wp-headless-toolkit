<?php
/**
 * WP Migrate DB Pro Compatibility Module
 *
 * Resolves compatibility issues between WP Migrate DB Pro and Bedrock's
 * non-standard directory structure.
 *
 * Folded in from production mu-plugin: wp-migrate-db-pro-compatibility.php
 *
 * @package ProjectAssistant\HeadlessToolkit\Modules\MigrateDbCompat
 */

namespace ProjectAssistant\HeadlessToolkit\Modules\MigrateDbCompat;

use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;

class MigrateDbCompat implements ModuleInterface {
	/**
	 * {@inheritDoc}
	 */
	public static function get_slug(): string {
		return 'migrate_db_compat';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_name(): string {
		return 'WP Migrate DB Compatibility';
	}

	/**
	 * {@inheritDoc}
	 */
	public static function is_enabled(): bool {
		if ( ! wp_headless_is_module_enabled( self::get_slug() ) ) {
			return false;
		}

		// Only enable if WP Migrate DB Pro is active.
		return class_exists( '\DeliciousBrains\WPMDB\Pro\Plugin\ProPlugin' )
			|| defined( 'WPMDB_PRO_VERSION' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init(): void {
		// Fix Bedrock directory structure detection.
		add_filter( 'wpmdb_upload_dir', [ $this, 'fix_upload_dir' ] );
		add_filter( 'wpmdb_upload_info', [ $this, 'fix_upload_info' ] );

		// Fix content path for Bedrock.
		add_filter( 'wpmdb_get_path', [ $this, 'fix_content_path' ], 10, 2 );
	}

	/**
	 * Fix upload directory for Bedrock compatibility.
	 *
	 * @param array $upload_dir The upload directory info.
	 *
	 * @return array
	 */
	public function fix_upload_dir( array $upload_dir ): array {
		// If using Bedrock, content directory may differ.
		if ( defined( 'WP_CONTENT_DIR' ) && defined( 'ABSPATH' ) ) {
			$content_dir = WP_CONTENT_DIR;
			$upload_path = $content_dir . '/uploads';

			if ( is_dir( $upload_path ) ) {
				$upload_dir['basedir'] = $upload_path;
				$upload_dir['path']    = $upload_path . $upload_dir['subdir'];
			}
		}

		return $upload_dir;
	}

	/**
	 * Fix upload info for Bedrock.
	 *
	 * @param array $upload_info The upload info.
	 *
	 * @return array
	 */
	public function fix_upload_info( array $upload_info ): array {
		return $this->fix_upload_dir( $upload_info );
	}

	/**
	 * Fix content path for Bedrock directory structure.
	 *
	 * @param string $path The content path.
	 * @param string $type The path type.
	 *
	 * @return string
	 */
	public function fix_content_path( string $path, string $type ): string {
		if ( 'content' === $type && defined( 'WP_CONTENT_DIR' ) ) {
			return WP_CONTENT_DIR;
		}

		return $path;
	}
}
