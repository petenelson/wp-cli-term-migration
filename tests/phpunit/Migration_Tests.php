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

		$this->assertCount( 5, $parsed_files['steps'] );

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

	/**
	 * Tests reassign_post_terms().
	 *
	 * @return void
	 */
	public function test_reassign_post_terms() {

		$taxonomy = 'category';

		$post_id_1 = self::factory()->post->create();
		$this->assertGreaterThan( 0, $post_id_1 );

		$post_id_2 = self::factory()->post->create();
		$this->assertGreaterThan( 0, $post_id_1 );

		$t1 = wp_insert_term( 'My Test Term 1', $taxonomy );
		$t2 = wp_insert_term( 'My Test Term 2', $taxonomy );
		$t3 = wp_insert_term( 'My Test Term 3', $taxonomy );
		$t4 = wp_insert_term( 'My Test Term 4', $taxonomy );

		// Set the first post to the test terms 1 & 2.
		wp_set_object_terms( $post_id_1, [ $t1['term_id'], $t2['term_id'] ], $taxonomy );

		// Set the first post to term 4.
		wp_set_object_terms( $post_id_2, [ $t4['term_id'] ], $taxonomy );

		// Verify the test data.
		$terms = get_the_terms( $post_id_1, $taxonomy );
		$terms = wp_list_pluck( $terms, 'term_id' );

		$this->assertCount( 2, $terms );
		$this->assertContains( $t1['term_id'], $terms );
		$this->assertContains( $t2['term_id'], $terms );

		$from_term = get_term( $t1['term_id'], $taxonomy );
		$to_term   = get_term( $t3['term_id'], $taxonomy );

		$results = Migration\reassign_post_terms( $post_id_1, $from_term, $to_term );

		$this->assertTrue( $results );

		// Verify post 1 has test term 2 and 3, no longer has test term 1.
		$terms = get_the_terms( $post_id_1, $taxonomy );
		$terms = wp_list_pluck( $terms, 'slug' );

		$this->assertCount( 2, $terms );
		$this->assertContains( 'my-test-term-2', $terms );
		$this->assertContains( 'my-test-term-3', $terms );
		$this->assertNotContains( 'my-test-term-1', $terms );

		// Verify post 2 still has only test term 4 applied to it.
		$terms = get_the_terms( $post_id_2, $taxonomy );
		$terms = wp_list_pluck( $terms, 'slug' );

		$this->assertCount( 1, $terms );
		$this->assertContains( 'my-test-term-4', $terms );
	}

	/**
	 * Tests test_process_reassign_step().
	 *
	 * @return void
	 */
	public function test_process_reassign_step() {

		// Setup all the test data.
		$post_id_1 = self::factory()->post->create();
		$this->assertGreaterThan( 0, $post_id_1 );

		$post_id_2 = self::factory()->post->create();
		$this->assertGreaterThan( 0, $post_id_2 );

		$post_id_3 = self::factory()->post->create();
		$this->assertGreaterThan( 0, $post_id_3 );

		$post_id_4 = self::factory()->post->create();
		$this->assertGreaterThan( 0, $post_id_4 );

		$page_id = self::factory()->post->create( [ 'post_type' => 'page' ] );

		$taxonomy = 'category';

		$t1 = wp_insert_term( 'Austin News', $taxonomy );
		$t2 = wp_insert_term( 'Round Rock News', $taxonomy );
		$t3 = wp_insert_term( 'Local News', $taxonomy );

		// No updates will be made to this term.
		$t4 = wp_insert_term( 'Concerts', $taxonomy );

		// Set the first post to Austin and Round Rock, and Concerts.
		wp_set_object_terms( $post_id_1, [ $t1['term_id'], $t2['term_id'], $t4['term_id'] ], $taxonomy );

		// Set the second post to Round Rock, and the page.
		wp_set_object_terms( $post_id_2, [ $t2['term_id'] ], $taxonomy );
		wp_set_object_terms( $page_id, [ $t2['term_id'] ], $taxonomy );

		// Set the third post to Local News.
		wp_set_object_terms( $post_id_3, [ $t3['term_id'] ], $taxonomy );

		// Verify the test data.
		$terms = get_the_terms( $post_id_1, $taxonomy );
		$terms = wp_list_pluck( $terms, 'term_id' );

		// This Austin post should have three terms.
		$this->assertCount( 3, $terms );
		$this->assertContains( $t1['term_id'], $terms );
		$this->assertContains( $t2['term_id'], $terms );
		$this->assertContains( $t4['term_id'], $terms );

		// Round Rock post and page.
		$terms = get_the_terms( $post_id_2, $taxonomy );
		$terms = wp_list_pluck( $terms, 'term_id' );

		$this->assertCount( 1, $terms );
		$this->assertContains( $t2['term_id'], $terms );

		// Round Rock post and page.
		$terms = get_the_terms( $post_id_2, $taxonomy );
		$terms = wp_list_pluck( $terms, 'term_id' );

		$this->assertCount( 1, $terms );
		$this->assertContains( $t2['term_id'], $terms );

		$terms = get_the_terms( $page_id, $taxonomy );
		$terms = wp_list_pluck( $terms, 'term_id' );

		$this->assertCount( 1, $terms );
		$this->assertContains( $t2['term_id'], $terms );

		// Third post.
		$terms = get_the_terms( $post_id_3, $taxonomy );
		$terms = wp_list_pluck( $terms, 'term_id' );

		$this->assertCount( 1, $terms );
		$this->assertContains( $t3['term_id'], $terms );

		// Now run the steps to migrate Austin News to Local News, then
		// Round Rock News to Local News.
		$parsed_files = Migration\parse_file( WP_TERM_MIGRATION_PATH_PHPUNIT . 'migrations/005-reassign.json' );

		$this->assertCount( 2, $parsed_files['steps'] );

		$step_results = Migration\process_steps( $parsed_files['steps'] );

		// Get the Austin post's terms. Should have only Local News and Concerts.
		$austin_terms = get_the_terms( $post_id_1, $taxonomy );
		$austin_terms = wp_list_pluck( $austin_terms, 'slug' );

		$this->assertCount( 2, $austin_terms );
		$this->assertContains( 'local-news', $austin_terms );

		// Get the Round Rock post's terms. Should have Local News.
		$round_rock_terms = get_the_terms( $post_id_2, $taxonomy );
		$round_rock_terms = wp_list_pluck( $round_rock_terms, 'slug' );

		$this->assertCount( 1, $round_rock_terms );
		$this->assertContains( 'local-news', $round_rock_terms );

		// Get the Round Rock post's terms. Should have Local News.
		$round_rock_terms = get_the_terms( $page_id, $taxonomy );
		$round_rock_terms = wp_list_pluck( $round_rock_terms, 'slug' );

		$this->assertCount( 1, $round_rock_terms );
		$this->assertContains( 'local-news', $round_rock_terms );

		// At this point, the Austin News and Round Rock news terms should
		// have no posts.
		$query_args = [
			'post_type'              => [ 'post', 'page' ],
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'update_post_meta_cache' => false,
			'update_term_meta_cache' => false,
			'tax_query'              => [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => [ 'austin-news', 'round-rock-news' ],
				]
			]
		];

		$query = new \WP_Query( $query_args );

		$this->assertEmpty( $query->posts );

		// Local News should have 4.
		$query_args['tax_query'] = [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => [ 'local-news' ],
			]
		];

		$query    = new \WP_Query( $query_args );
		$post_ids = $query->posts;

		$this->assertCount( 4, $post_ids );
		$this->assertContains( $post_id_1, $post_ids );
		$this->assertContains( $post_id_2, $post_ids );
		$this->assertContains( $post_id_3, $post_ids );
		$this->assertContains( $page_id, $post_ids );

		// Concerts should have 1.
		$query_args['tax_query'] = [
			[
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => [ 'concerts' ],
			]
		];

		$query    = new \WP_Query( $query_args );
		$post_ids = $query->posts;

		$this->assertCount( 1, $post_ids );
		$this->assertContains( $post_id_1, $post_ids );
	}

	/**
	 * Tests parent term updates.
	 *
	 * @return void
	 */
	public function test_process_parent_term_updates() {

		$taxonomy = 'category';

		// Setup all the test data.
		$united_states = wp_insert_term( 'United States', $taxonomy );
		$texas         = wp_insert_term( 'Texas', $taxonomy );
		$austin        = wp_insert_term( 'Austin', $taxonomy );

		$this->assertGreaterThan( 0, $united_states['term_id'] );
		$this->assertGreaterThan( 0, $texas['term_id'] );
		$this->assertGreaterThan( 0, $austin['term_id'] );

		// Now run the steps to update parent term IDs.
		$parsed_files = Migration\parse_file( WP_TERM_MIGRATION_PATH_PHPUNIT . 'migrations/007-update-parent-terms.json' );

		$this->assertCount( 3, $parsed_files['steps'] );

		Migration\process_steps( $parsed_files['steps'] );

		// Verify Texas has a parent of United States.
		$texas_term = get_term( $texas['term_id'] );
		$this->assertSame( $united_states['term_id'], $texas_term->parent );

		// Verify Austin has a parent of Texas.
		$austin_term = get_term( $austin['term_id'] );
		$this->assertSame( $texas['term_id'], $austin_term->parent );

		// No updates to United States.
		$us_term = get_term( $united_states['term_id'] );
		$this->assertSame( 0, $us_term->parent );
	}
}
