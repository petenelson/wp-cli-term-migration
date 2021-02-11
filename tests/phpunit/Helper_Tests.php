<?php // phpcs:ignore

namespace WPTermMigration\Tests;

use WPTermMigration\Migration;
use WPTermMigration\Helpers;

/**
 * Migration tests.
 */
class Helper_Tests extends Base_Tests {

	/**
	 * Tests default_results().
	 *
	 * @return void
	 * @group  migration
	 */
	public function test_parse_migration() {

		$steps = Helpers\parse_migration( WP_TERM_MIGRATION_PATH_PHPUNIT . 'migrations/invalid' );
		$this->assertInstanceOf( '\WP_Error', $steps );

		$steps = Helpers\parse_migration( WP_TERM_MIGRATION_PATH_PHPUNIT . 'migrations/002-empty.json' );
		$this->assertInstanceOf( '\WP_Error', $steps );

		$steps = Helpers\parse_migration( WP_TERM_MIGRATION_PATH_PHPUNIT . 'migrations/003-invalid.json' );
		$this->assertNull( $steps );

		$steps = Helpers\parse_migration( WP_TERM_MIGRATION_PATH_PHPUNIT . 'migrations/001-create.json' );
		$this->assertIsArray( $steps );
		$this->assertArrayHasKey( 'steps', $steps );

		$this->assertIsArray( $steps['steps'] );
		$this->assertCount( 2, $steps['steps'] );
	}
}
