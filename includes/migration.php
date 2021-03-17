<?php
/**
 * WP Term Migration Functions
 *
 * @package WPTermMigration
 */

namespace WPTermMigration\Migration;

/**
 * Gets default results array.
 *
 * @return array
 */
function default_results() {
	return [
		'success'       => false,
		'error_code'    => false,
		'error_message' => false,
	];
}

/**
 * Parses a migration file.
 *
 * @param  string $filename The JSON filename with migration steps.
 * @return array
 */
function parse_file( $filename ) {

	if ( ! file_exists( $filename ) ) {
		return new \WP_Error( 'file-not-found', sprintf( __( 'File "%s" not found', 'wp-cli-term-migration' ), $filename ) );
	}

	$contents = file_get_contents( $filename );

	if ( empty( $contents ) ) {
		return new \WP_Error( 'file-empty', sprintf( __( 'File "%s" is empty', 'wp-cli-term-migration' ), $filename ) );
	}

	return apply_filters( 'wp_term_migration_parse_file', parse_content( $contents ), $filename );
}

/**
 * Parses migration contents.
 *
 * @param  string $contents The JSON migration contents.
 * @return array
 */
function parse_content( $contents ) {

	$contents = json_decode( $contents, null, 512, JSON_OBJECT_AS_ARRAY );

	if ( is_array( $contents ) ) {

		if ( ! isset( $contents['steps'] ) ) {
			$contents['steps'] = [];
		}

		$step_id = 0;

		// Assign step IDs for steps that don't have one.
		foreach ( array_keys( $contents['steps'] ) as $key ) {
			if ( ! isset( $contents['steps'][ $key ]['id'] ) ) {
				$contents['steps'][ $key ]['id'] = '';
			}

			if ( empty( $contents['steps'][ $key ]['id'] ) ) {
				$contents['steps'][ $key ]['id'] = str_pad( $step_id, '6', '0', STR_PAD_LEFT ) . '-step';
			}

			$step_id++;
		}
	}

	return apply_filters( 'wp_term_migration_parse_content', $contents );
}

/**
 * Processes all steps.
 *
 * @param  array $steps List of steps.
 * @return array
 */
function process_steps( $steps ) {

	foreach ( array_keys( $steps ) as $key ) {
		$steps[ $key ]['results'] = process_step( $steps[ $key ], $steps );
	}

	return apply_filters( 'wp_term_migration_process_steps', $steps );
}

/**
 * Processes a step.
 *
 * @param  array $step  The step data.
 * @param  array $steps The list of steps, including results from steps that
 *                      were already processed.
 * @return array
 */
function process_step( $step, $steps = [] ) {

	$results = default_results();

	if ( isset( $step['type'] ) ) {

		switch ( $step['type'] ) {
			case 'create':
				$results = process_create_step( $step, $steps );
				break;
			case 'update':
				$results = process_update_step( $step, $steps );
				break;
			case 'reassign':
				$results = process_reassign_step( $step, $steps );
				break;
		}
	}

	return apply_filters( 'wp_term_migration_process_step', $results, $step, $steps );
}

/**
 * Processes a create step.
 *
 * @param  array $step  The step data.
 * @param  array $steps The list of steps, including results from steps that
 *                      were already processed.
 * @return array
 */
function process_create_step( $step, $steps = [] ) {

	$results = default_results();

	$create_data = wp_parse_args(
		$step['create'],
		[
			'taxonomy'    => '',
			'name'        => '',
			'description' => '',
			'slug'        => '',
			'parent'      => '',
		]
	);

	$taxonomy = $create_data['taxonomy'];
	unset( $create_data['taxonomy'] );

	if ( ! taxonomy_exists( $taxonomy ) ) {
		$results['error_code']    = 'taxonomy_error';
		$results['error_message'] = sprintf( __( 'Taxonomy %s does not exist', 'wp-term-migration' ), $taxonomy );
	} else if ( isset( $step['create'] ) ) {

		$name = $create_data['name'];
		unset( $create_data['name'] );

		if ( ! empty( $create_data['parent'] ) ) {
			$parent_term = get_term_by( 'slug', $create_data['parent'], $taxonomy );
			if ( is_a( $parent_term, '\WP_Term' ) ) {
				$create_data['parent'] = $parent_term->term_id;
			} else {
				unset( $create_data['parent'] );
			}
		}

		$term_data = wp_insert_term( $name, $taxonomy, $create_data );

		if ( is_array( $term_data ) ) {
			$results['success'] = true;
			$results['term_id'] = $term_data['term_id'];

			if ( true === apply_filters( 'wp_term_migration_flush_cache_after_create', true, $step ) ) {
				wp_cache_flush();
			}
		} else if ( is_wp_error( $term_data ) ) {
			$results['error_code']    = $term_data->get_error_code();
			$results['error_message'] = $term_data->get_error_message();
		}
	}

	return apply_filters( 'wp_term_migration_proccess_create_step', $results, $step, $steps );
}

/**
 * Processes an update step.
 *
 * @param  array $step  The step data.
 * @param  array $steps The list of steps, including results from steps that
 *                      were already processed.
 * @return array
 */
function process_update_step( $step, $steps = [] ) {

	$results = default_results();

	$data = wp_parse_args(
		$step,
		[
			'slug' => '', // The slug we're updating.
		]
	);

	$slug = $data['slug'];

	if ( empty( $slug ) ) {
		$results['error_code']    = 'empty_slug';
		$results['error_message'] = __( 'Empty slug in update command', 'wp-term-migration' );
		return $results;
	}

	$update_data = [];

	$taxonomy = $step['update']['taxonomy'];

	// TODO update tests.
	$keys = [
		'name',
		'description',
		'slug',
		'parent',
	];

	foreach ( $keys as $key ) {
		if ( isset( $step['update'][ $key ] ) ) {
			$update_data[ $key ] = $step['update'][ $key ];
		}
	}

	// Get the term to update.
	$term = get_term_by( 'slug', $slug, $taxonomy );

	if ( ! is_a( $term, '\WP_Term' ) ) {
		$results['error_code']    = 'invalid_slug';
		$results['error_message'] = sprintf( __( 'Slug %1$s does not exist in taxonomy %2$s', 'wp-term-migration' ), $slug, $taxonomy );
		return $results;
	}

	// Set the parent term ID.
	if ( isset( $update_data['parent'] ) ) {
		$parent_term = get_term_by( 'slug', $update_data['parent'], $taxonomy );
		if ( is_a( $parent_term, '\WP_Term' ) ) {
			$update_data['parent'] = $parent_term->term_id;
		} else {
			unset( $update_data['parent'] );
		}
	}

	// TODO update parent slug to term ID.
	$term_data = wp_update_term( $term->term_id, $taxonomy, $update_data );

	if ( is_array( $term_data ) ) {
		$results['success'] = true;
		$results['term_id'] = $term_data['term_id'];

		if ( true === apply_filters( 'wp_term_migration_flush_cache_after_update', true, $step ) ) {
			wp_cache_flush();
		}
	} else if ( is_wp_error( $term_data ) ) {
		$results['error_code']    = $term_data->get_error_code();
		$results['error_message'] = $term_data->get_error_message();
	}

	return apply_filters( 'wp_term_migration_proccess_update_step', $results, $step, $steps );
}

/**
 * Processes an reassign step.
 *
 * @param  array $step  The step data.
 * @param  array $steps The list of steps, including results from steps that
 *                      were already processed.
 * @return array
 */
function process_reassign_step( $step, $steps = [] ) {

	$results = default_results();

	$reassign_data = wp_parse_args(
		$step['reassign'],
		[
			'taxonomy'  => '',
			'post_type' => '',
			'from_slug' => '',
			'to_slug'   => '',
		]
	);

	$taxonomy = $reassign_data['taxonomy'];
	unset( $reassign_data['taxonomy'] );

	// Verify the slugs.
	$from_term = get_term_by( 'slug', $reassign_data['from_slug'], $taxonomy );
	$to_term   = get_term_by( 'slug', $reassign_data['to_slug'], $taxonomy );

	if ( ! is_a( $from_term, '\WP_Term' ) ) {
		$results['error_code']    = 'invalid_from_slug';
		$results['error_message'] = sprintf( __( 'From Slug %1$s does not exist in taxonomy %2$s', 'wp-term-migration' ), $reassign_data['from_slug'], $taxonomy );
		return $results;
	}

	if ( ! is_a( $to_term, '\WP_Term' ) ) {
		$results['error_code']    = 'invalid_to_slug';
		$results['error_message'] = sprintf( __( 'To Slug %1$s does not exist in taxonomy %2$s', 'wp-term-migration' ), $reassign_data['to_slug'], $taxonomy );
		return $results;
	}

	// Get the list of post IDs.
	$query_args = [
		'post_type'              => $reassign_data['post_type'],
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'update_post_meta_cache' => false,
		'update_term_meta_cache' => false,
		'tax_query'              => [
			[
				'taxonomy' => $from_term->taxonomy,
				'terms'    => $from_term->term_id,
			],
		],
	];

	$query = new \WP_Query( $query_args );

	$results['post_ids'] = [];

	foreach ( $query->posts as $post_id ) {
		$results['post_ids'][ $post_id ] = reassign_post_terms( $post_id, $from_term, $to_term );
	}

	$results['success'] = true;

	return apply_filters( 'wp_term_migration_proccess_reassign_step', $results, $step, $steps );
}

/**
 * Removes a post's from term and adds the to term.
 *
 * @param  int     $post_id   The post ID.
 * @param  WP_Term $from_term The from term.
 * @param  WP_Term $to_term   The to term.
 * @return bool
 */
function reassign_post_terms( $post_id, $from_term, $to_term ) {

	$results = wp_remove_object_terms( $post_id, $from_term->term_id, $from_term->taxonomy );

	if ( ! is_wp_error( $results ) ) {
		$term_data = wp_add_object_terms( $post_id, $to_term->term_id, $to_term->taxonomy );
		$results   = is_array( $term_data );
	}

	return $results;
}
