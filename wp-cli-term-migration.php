<?php
/**
 * Plugin Name: WP CLI Term Migration
 * Description: WP CLI commands to migrate taxonomy terms
 * Version:     0.1.0
 * Author:      Pete Nelson
 * Author URI:  https://petenelson.io
 * Plugin URI:  https://github.com/petenelson/wp-cli-term-migration
 * License:     GPLv2 or later
 *
 * @package  WPTermMigration
 */

namespace WPTermMigration;

define( 'WP_TERM_MIGRATION_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WP_TERM_MIGRATION_VERSION', '0.1.0' );

$files = [
	'helpers.php',
	'migration.php',
	'commands.php',
];

foreach ( $files as $file ) {
	require_once WP_TERM_MIGRATION_PATH . 'includes/' . $file;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// Adds the migration CLI Commands.
	\WPTermMigration\Commands\add_commands();
}
