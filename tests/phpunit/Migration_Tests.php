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
			'create'   => [
				'taxonomy'    => 'category',
				'name'        => 'Hello World',
				'description' => 'Bacon Ipsum Dolor Amet',
				'slug'        => 'hello-world-slug',
			]
		];

		$results = Migration\process_step( $step );

		$this->assertTrue( $results['success'] );
		$this->assertGreaterThan( 0, $results['term_id'] );

		$term = get_term_by( 'slug', 'hello-world-slug', $step['create']['taxonomy'] );

		$this->assertInstanceOf( '\WP_Term', $term );
		$this->assertSame( $step['create']['slug'], $term->slug );
		$this->assertSame( $step['create']['description'], $term->description );

		$parent_term_id = $results['term_id'];

		// Verify child term.
		$step['create']['name']        = 'Foo Bar';
		$step['create']['description'] = '';
		$step['create']['parent']      = $step['create']['slug'];
		$step['create']['slug']        = '';

		$results = Migration\process_step( $step );

		$this->assertTrue( $results['success'] );
		$this->assertGreaterThan( 0, $results['term_id'] );

		$term = get_term_by( 'slug', 'foo-bar', $step['create']['taxonomy'] );

		$this->assertInstanceOf( '\WP_Term', $term );
		$this->assertSame( 'foo-bar', $term->slug );
		$this->assertSame( $parent_term_id, $term->parent );
	}

	/**
	 * Tests the process_rename_step() function.
	 *
	 * @return void
	 */
	public function test_process_update_step() {

		$parsed_files = Migration\parse_file( WP_TERM_MIGRATION_PATH_PHPUNIT . 'migrations/004-update.json' );

		$this->assertCount( 4, $parsed_files['steps'] );

		$steps = $parsed_files['steps'];

		// Create the term.
		$results = Migration\process_step( $steps[0] );
		$this->assertTrue( $results['success'] );

		$original_term = get_term( $results['term_id'] );
		$this->assertInstanceOf( '\WP_Term', $original_term );

		// Verify invalid terms don't process.
		$results = Migration\process_step( $steps[1] );
		$this->assertFalse( $results['success'] );
		$this->assertSame( 'empty_slug', $results['error_code'] );

		$results = Migration\process_step( $steps[2] );
		$this->assertFalse( $results['success'] );
		$this->assertSame( 'invalid_slug', $results['error_code'] );

		// Verify the original term gets updated.
		$results = Migration\process_step( $steps[3] );
		$this->assertTrue( $results['success'] );
		$this->assertSame( $original_term->term_id, $results['term_id'] );

		$new_term = get_term( $original_term->term_id );

		$this->assertSame( 'Austin News', $new_term->name );
		$this->assertSame( 'austin-news', $new_term->slug );
		$this->assertSame( 'All of the Austin news', $new_term->description );
	}
}
