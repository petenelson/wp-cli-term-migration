<?php
/**
 * WP Term Migration Helpers
 *
 * @package WPTermMigration
 */

namespace WPTermMigration\Helpers;

/**
 * Parses a migration file.
 *
 * @param  string $filename The JSON filename with migration steps.
 * @return array
 */
function parse_migration( $filename ) {

	if ( ! file_exists( $filename ) ) {
		return new \WP_Error( 'file-not-found', $filename . ' not found' );
	}

	$contents = file_get_contents( $filename );

	if ( empty( $contents ) ) {
		return new \WP_Error( 'file-empty', $filename . ' is empty' );
	}

	$contents = json_decode( $contents );
	if ( is_object( $contents ) ) {
		$contents = (array) $contents;
	}

	return apply_filters( 'wp_term_migration_parse_migration', $contents, $filename );
}
