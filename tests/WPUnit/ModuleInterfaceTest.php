<?php
/**
 * Tests for src/Modules/ModuleInterface.php
 *
 * @package Tests\ProjectAssistant\HeadlessToolkit\WPUnit
 */

namespace Tests\ProjectAssistant\HeadlessToolkit\WPUnit;

use Tests\ProjectAssistant\HeadlessToolkit\HeadlessToolkitTestCase;
use ProjectAssistant\HeadlessToolkit\Modules\ModuleInterface;
use ProjectAssistant\HeadlessToolkit\Modules\MigrateDbCompat\MigrateDbCompat;

/**
 * Tests for the ModuleInterface contract.
 */
class ModuleInterfaceTest extends HeadlessToolkitTestCase {

	/**
	 * Test that MigrateDbCompat implements ModuleInterface.
	 */
	public function test_migrate_db_compat_implements_interface(): void {
		$this->assertTrue(
			is_subclass_of( MigrateDbCompat::class, ModuleInterface::class )
				|| in_array( ModuleInterface::class, class_implements( MigrateDbCompat::class ), true ),
			'MigrateDbCompat must implement ModuleInterface.'
		);
	}

	/**
	 * Test that ModuleInterface declares the required methods.
	 */
	public function test_interface_declares_required_methods(): void {
		$reflection = new \ReflectionClass( ModuleInterface::class );

		$this->assertTrue( $reflection->isInterface(), 'ModuleInterface must be an interface.' );
		$this->assertTrue( $reflection->hasMethod( 'get_slug' ), 'ModuleInterface must declare get_slug().' );
		$this->assertTrue( $reflection->hasMethod( 'get_name' ), 'ModuleInterface must declare get_name().' );
		$this->assertTrue( $reflection->hasMethod( 'is_enabled' ), 'ModuleInterface must declare is_enabled().' );
		$this->assertTrue( $reflection->hasMethod( 'init' ), 'ModuleInterface must declare init().' );
	}
}
