<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Wp_Term_Timestamps
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {

	/**
	 * Bootstrap WPGraphQL to run the tests for that plugin
	 */
	bootstrap_wp_graphql();

	require dirname( dirname( __FILE__ ) ) . '/wp-term-timestamps.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * This loads the WPGraphQL plugin so the tests for the functions that extend the GraphQL API can be properly tested
 */
function bootstrap_wp_graphql() {
	/**
	 * Bootstrap WPGraphQL files
	 */
	$wp_graphql = dirname( __FILE__, 3 ) . '/wp-graphql/wp-graphql.php';
	$wp_graphql_autoload = dirname( __FILE__, 3 ) . '/wp-graphql/vendor/autoload.php';
	$wp_graphql_access_functions = dirname( __FILE__, 3 ) . '/wp-graphql/access-functions.php';

	/**
	 * If the WPGraphQL files exist, load them, otherwise output a message that WPGraphQL is not installed and Unit Tests
	 * for it will not be run
	 */
	if ( file_exists( $wp_graphql ) && file_exists( $wp_graphql_autoload ) && file_exists( $wp_graphql_access_functions ) ) {
		require_once $wp_graphql;
		require_once $wp_graphql_autoload;
		require_once $wp_graphql_access_functions;
	} else {
		var_dump( 'WPGraphQL is not installed in the proper directory and the related unit tests will not run' );
	}
}


// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
