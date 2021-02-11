<?php // phpcs:ignore

namespace WPTermMigration\Tests;

use WPTermMigration\Migration;
use WPTermMigration\Helpers;

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
		$this->assertArrayHasKey( 'error_code', $results );
		$this->assertArrayHasKey( 'error_message', $results );

		$this->assertFalse( $results['success'] );
		$this->assertFalse( $results['error_code'] );
		$this->assertFalse( $results['error_message'] );
	}

	/**
	 * Tests the process_create_step() function.
	 *
	 * @return void
	 */
	public function test_process_create_step() {

		// Valid term.
		$step = [
			'type'     => 'create',
			'taxonomy' => 'category',
			'create'   => [
				'name'        => 'Hello World',
				'description' => 'Bacon Ipsum Dolor Amet',
				'slug'        => 'hello-world-slug',
			]
		];

		$results = Migration\process_step( $step );

		$this->assertTrue( $results['success'] );
		$this->assertGreaterThan( 0, $results['term_id'] );
	}
}

