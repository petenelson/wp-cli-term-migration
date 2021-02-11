<?php // phpcs:ignore

namespace WPTermMigration\Tests;

/**
 * Base test class.
 */
class Base_Tests extends \WP_UnitTestCase {

	/**
	 * Cleans up after each test.
	 *
	 * @return void
	 */
	public function tearDown() {
		$this->delete_all_terms();
	}

	/**
	 * Deletes all of the terms.
	 *
	 * @return void
	 */
	public function delete_all_terms() {
		$taxonomies = get_taxonomies();

		foreach ( array_keys( $taxonomies ) as $taxonomy ) {

			$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' ] );

			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, $term->taxonomy );
			}
		}		
	}

	/**
	 * Empty test to bypass warnings.
	 *
	 * @return void
	 */
	public function test_empty() {
		$this->assertTrue( true );
	}
}
