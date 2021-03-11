<?php
/**
 * WP Term Migration CLI commands
 *
 * @package WPTermMigration
 */

namespace WPTermMigration\Commands;

use WPTermMigration\Migration;

/**
 * Gets a namespaced function name.
 *
 * @param  string $function The function name
 * @return string
 */
function n( $function ) {
	return __NAMESPACE__ . "\\$function";
};

/**
 * Setup CLI commands.
 *
 * @return void
 */
function add_commands() {
	\WP_CLI::add_command( 'term-migration run', n( 'run_migration' ) );
	\WP_CLI::add_command( 'term-migration create-test-data', n( 'create_test_data' ) );
}

/**
 *
 * Runs term migration.
 *
 * ## OPTIONS
 *
 * <filename>
 * : The JSON file with the term migration steps.
 *
 * [--dry-run]
 * : Only do a dry run, no updates will be made.
 *
 * @param array $args       Positional args.
 * @param array $assoc_args Associative args.
 * @return void
 */
function run_migration( $args, $assoc_args = [] ) {

	$filename = $args[0];
	$dry_run  = isset( $assoc_args['dry-run'] );

	$contents = Migration\parse_file( $filename );

	if ( is_a( $contents, '\WP_Error' ) ) {
		\WP_CLI::error( $contents->get_error_message() );
	}

	if ( empty( $contents ) ) {
		\WP_CLI::error( sprintf( __( 'File "%s" doesn\'t contain any steps.', 'wp-cli-term-migration' ), $filename ) );
	}

	if ( empty( $contents['steps'] ) ) {
		\WP_CLI::error( sprintf( __( 'File "%s" doesn\'t contain any steps.', 'wp-cli-term-migration' ), $filename ) );
	}

	\WP_CLI::line( sprintf( __( 'Running %d steps...', 'wp-cli-term-migration' ), count( $contents['steps'] ) ) );

	foreach ( $contents['steps'] as $step ) {

		$msg = sprintf( __( 'Running ID: %1$s, Type: %2$s, ', '' ), $step['id'], $step['type'] );

		switch ( $step['type'] ) {

			case 'update':
				$msg .= sprintf( __( 'Slug: %s', '' ), $step['slug'] );
				break;

			case 'reassign':
				$msg .= sprintf( __( 'From Slug: %s, ', '' ), $step['reassign']['from_slug'] );
				$msg .= sprintf( __( 'To Slug: %s', '' ), $step['reassign']['to_slug'] );
				break;
		}

		\WP_CLI::line( $msg );

		if ( ! $dry_run ) {

			$results = Migration\process_step( $step, $contents['steps'] );

			if ( $results['success'] ) {
				\WP_CLI::success( __( 'Success', 'wp-cli-term-migration' ) );
			} else {
				\WP_CLI::error( $results['error_code'] . ': ' . $results['error_message'] );
			}
		}
	}
}

/**
 * Creates terms and posts for easy testing.
 *
 * [--cleanup]
 * : Cleanup test data
 *
 * @param array $args       Positional args.
 * @param array $assoc_args Associative args.
 * @return void
 */
function create_test_data( $args, $assoc_args = [] ) {

	$taxonomy = 'category';
	$meta_key = 'wp-cli-test-data';

	if ( isset( $assoc_args['cleanup'] ) ) {

		\WP_CLI::line( 'Running cleanup...' );

		$query = new \WP_Query(
			[
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_key'       => $meta_key,
				'meta_value'     => '1',
			]
		);

		foreach ( $query->posts as $post_id ) {
			wp_delete_post( $post_id, true );
			\WP_CLI::success( 'Deleted Post: ' . $post_id );
		}

		$query = new \WP_Term_Query(
			[
				'taxonomy'   => $taxonomy,
				'meta_key'   => $meta_key,
				'meta_value' => '1',
				'hide_empty' => false,
			]
		);

		foreach ( $query->get_terms() as $term ) {
			wp_delete_term( $term->term_id, $term->taxonomy );
			\WP_CLI::success( 'Deleted Term: ' . $term->name );
		}

		\WP_CLI::success( 'Cleaned up' );

		exit;
	}

	$news = wp_insert_term( 'News', $taxonomy, [ 'slug' => 'local-news' ] );
	update_term_meta( $news['term_id'], $meta_key, '1' );
	\WP_CLI::success( 'Created Term: News' );

	$austin_news = wp_insert_term( 'Austin News', $taxonomy, [ 'parent' => $news['term_id'] ] );
	update_term_meta( $austin_news['term_id'], $meta_key, '1' );
	\WP_CLI::success( 'Created Term: Austin News' );

	$eats = wp_insert_term( 'Eats', $taxonomy );
	update_term_meta( $eats['term_id'], $meta_key, '1' );
	\WP_CLI::success( 'Created Term: Eats' );

	$foods = wp_insert_term( 'Foods', $taxonomy );
	update_term_meta( $foods['term_id'], $meta_key, '1' );
	\WP_CLI::success( 'Created Term: Foods' );

	for ( $i = 0; $i < 8; $i++ ) {

		$post_id = wp_insert_post(
			[
				'post_title'  => 'News Test Post #' . $i,
				'post_status' => 'publish',
			]
		);

		wp_set_object_terms( $post_id, [ $news['term_id'] ], $taxonomy );

		update_post_meta( $post_id, $meta_key, '1' );

		$post = get_post( $post_id );

		\WP_CLI::success( 'Created Post: ' . $post->post_title );
	}

	for ( $i = 0; $i < 6; $i++ ) {

		$post_id = wp_insert_post(
			[
				'post_title'  => 'Austin News Test Post #' . $i,
				'post_status' => 'publish',
			]
		);

		wp_set_object_terms( $post_id, [ $austin_news['term_id'] ], $taxonomy );

		update_post_meta( $post_id, $meta_key, '1' );

		$post = get_post( $post_id );

		\WP_CLI::success( 'Created Post: ' . $post->post_title );
	}

	for ( $i = 0; $i < 12; $i++ ) {

		$post_id = wp_insert_post(
			[
				'post_title'  => 'Eats Test Post #' . $i,
				'post_status' => 'publish',
			]
		);

		wp_set_object_terms( $post_id, [ $eats['term_id'] ], $taxonomy );

		update_post_meta( $post_id, $meta_key, '1' );

		$post = get_post( $post_id );

		\WP_CLI::success( 'Created Post: ' . $post->post_title );
	}

	\WP_CLI::success( 'All Done' );
}
