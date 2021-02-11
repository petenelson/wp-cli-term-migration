<?php // phpcs:ignore

namespace WPTermMigration\Tests;

use WPTermMigration\Migration;

/**
 * Migration tests.
 */
class Migration_Tests extends Base_Tests {

	/**
	 * Tests default_results().
	 *
	 * @return void
	 * @group  migration
	 */
	public function test_default_results() {

		$results = Migration\default_results();

		$this->assertArrayHasKey( 'success', $results );
		$this->assertArrayHasKey( 'error', $results );

		$this->assertFalse( $results['success'] );
		$this->assertFalse( $results['error'] );
	}
}

