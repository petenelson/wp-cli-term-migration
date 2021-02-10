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
		'success' => false,
		'error'   => false,
	];
}

/**
 * Updates a term.
 *
 * @param  array $args List of args.
 * @return array
 */
function update_term( $args ) {

	$results = default_results();


	return $results;
}
