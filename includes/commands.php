<?php
/**
 * WP Term Migration CLI commands
 *
 * @package WPTermMigration
 */

namespace WPTermMigration\Commands;

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


}
