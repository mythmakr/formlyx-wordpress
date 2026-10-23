<?php
/**
 * WordPress integration test bootstrap.
 *
 * @package Formlyx
 */

declare(strict_types=1);

$tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! is_string( $tests_dir ) || '' === $tests_dir ) {
	fwrite( STDERR, "WP_TESTS_DIR is not set. Run bin/install-wp-tests.sh first.\n" );
	exit( 1 );
}

$polyfills = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( ! is_string( $polyfills ) || '' === $polyfills ) {
	$polyfills = dirname( __DIR__, 3 ) . '/vendor/yoast/phpunit-polyfills';
	putenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH=' . $polyfills );
}

require $tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__, 3 ) . '/formlyx.php';
	}
);

require $tests_dir . '/includes/bootstrap.php';
