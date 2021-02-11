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
		return new \WP_Error( 'file-not-found', $filename . ' not found' );
	}

	$contents = file_get_contents( $filename );

	if ( empty( $contents ) ) {
		return new \WP_Error( 'file-empty', $filename . ' is empty' );
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
		} else if ( is_wp_error( $term_data ) ) {
			$results['error_code']    = $term_data->get_error_code();
			$results['error_message'] = $term_data->get_error_message();
		}
	}

	return apply_filters( 'wp_term_migration_proccess_create_step', $results, $step, $steps );
}
