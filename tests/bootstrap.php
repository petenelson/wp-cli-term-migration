<?php // phpcs:ignore

/**
 * Unit tests bootstrap.
 */
class WPTermMigration_Unit_Tests_Bootstrap {

	/**
	 * The plugin root dir.
	 *
	 * @var string
	 */
	protected $plugin_root = '';

	/**
	 * Setup the unit test environment
	 *
	 * @return void
	 * @throws Exception Errors when WP_DEVELOP_DIR not set.
	 */
	public function bootstrap() {

		// Run this in your shell to point to whever you cloned WP.
		// git clone git@github.com:WordPress/wordpress-develop.git
		// Example: export WP_DEVELOP_DIR="/Users/petenelson/projects/wordpress/wordpress-develop/".
		$wp_develop_dir = getenv( 'WP_DEVELOP_DIR' );

		if ( empty( $wp_develop_dir ) ) {
			throw new Exception(
				'ERROR' . PHP_EOL . PHP_EOL .
				'You must define the WP_DEVELOP_DIR environment variable.' . PHP_EOL
			);
		}

		// Load the Composer autoloader.
		$this->plugin_root = dirname( dirname( __FILE__ ) );
		if ( ! file_exists( $this->plugin_root . '/vendor/autoload.php' ) ) {
			throw new Exception(
				'ERROR' . PHP_EOL . PHP_EOL .
				'You must use Composer to install the test suite\'s dependencies.' . PHP_EOL
			);
		}
		$autoloader = require_once $this->plugin_root . '/vendor/autoload.php';

		// Give access to tests_add_filter() function.
		require_once $wp_develop_dir . '/tests/phpunit/includes/functions.php';

		tests_add_filter( 'muplugins_loaded', [ $this, 'manually_load_plugin' ] );

		// Start up the WP testing environment.
		require $wp_develop_dir . '/tests/phpunit/includes/bootstrap.php';

		define( 'WP_TERM_MIGRATION_PATH_PHPUNIT', dirname( __FILE__ ) . '/phpunit/' );
	}

	/**
	 * Manually load the plugin being tested.
	 */
	public function manually_load_plugin() {
		require $this->plugin_root . '/wp-cli-term-migration.php';
	}
}

$unit_tests_bootstrap = new WPTermMigration_Unit_Tests_Bootstrap();
$unit_tests_bootstrap->bootstrap();
