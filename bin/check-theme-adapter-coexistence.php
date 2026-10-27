<?php
/**
 * Load two Theme Adapter v0.1.0 bundles and confirm they keep separate owners.
 *
 * Run: php bin/check-theme-adapter-coexistence.php
 *
 * @package Formlyx
 */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "theme-adapter-coexistence.php is a command-line script.\n" );
	exit( 1 );
}

define( 'RAZOR_THEME_ADAPTER_TEST', true );

$bundle = dirname( __DIR__ ) . '/vendor/theme-adapter';
if ( ! is_file( $bundle . '/load.php' ) ) {
	fwrite( STDERR, "Bundled Theme Adapter is missing.\n" );
	exit( 1 );
}

$root = sys_get_temp_dir() . '/formlyx-theme-coexist-' . getmypid() . '/plugins';
$owners = array(
	'formlyx'  => $root . '/formlyx',
	'peerkit'  => $root . '/peerkit-chat',
);

/**
 * Copy a directory.
 *
 * @param string $from Source.
 * @param string $to   Destination.
 * @return void
 */
function formlyx_coexist_copy( string $from, string $to ): void {
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ( $items as $item ) {
		if ( ! $item instanceof SplFileInfo ) {
			continue;
		}
		$target = $to . substr( $item->getPathname(), strlen( $from ) );
		if ( $item->isDir() ) {
			if ( ! is_dir( $target ) && ! mkdir( $target, 0777, true ) && ! is_dir( $target ) ) {
				fwrite( STDERR, "Could not create {$target}\n" );
				exit( 1 );
			}
			continue;
		}
		$parent = dirname( $target );
		if ( ! is_dir( $parent ) && ! mkdir( $parent, 0777, true ) && ! is_dir( $parent ) ) {
			fwrite( STDERR, "Could not create {$parent}\n" );
			exit( 1 );
		}
		if ( ! copy( $item->getPathname(), $target ) ) {
			fwrite( STDERR, "Could not copy {$target}\n" );
			exit( 1 );
		}
	}
}

/**
 * Remove a directory tree.
 *
 * @param string $dir Directory.
 * @return void
 */
function formlyx_coexist_remove( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		if ( ! $item instanceof SplFileInfo ) {
			continue;
		}
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}
	rmdir( $dir );
}

foreach ( $owners as $dir ) {
	formlyx_coexist_copy( $bundle, $dir . '/vendor/theme-adapter' );
	$loader = $dir . '/load-adapter.php';
	file_put_contents(
		$loader,
		"<?php\nreturn require __DIR__ . '/vendor/theme-adapter/load.php';\n"
	);
}

try {
	$formlyx = require $owners['formlyx'] . '/load-adapter.php';
	$other   = require $owners['peerkit'] . '/load-adapter.php';
	if ( ! is_object( $formlyx ) || ! is_object( $other ) ) {
		fwrite( STDERR, "A bundle did not return a runtime.\n" );
		exit( 1 );
	}
	if ( ! method_exists( $formlyx, 'styleHandle' ) || ! method_exists( $other, 'styleHandle' ) ) {
		fwrite( STDERR, "A runtime is missing styleHandle().\n" );
		exit( 1 );
	}
	if ( RazorSoft\ThemeAdapter\Registry::ownerCount() !== 2 ) {
		fwrite( STDERR, "Expected two adapter owners.\n" );
		exit( 1 );
	}
	$formlyx_handle = $formlyx->styleHandle();
	$other_handle   = $other->styleHandle();
	if ( $other_handle === $formlyx_handle || 'razor-theme-adapter' === $formlyx_handle || 'razor-theme-adapter' === $other_handle ) {
		fwrite( STDERR, "Owners shared a style handle: {$formlyx_handle} {$other_handle}\n" );
		exit( 1 );
	}
	if ( '0.1.0' !== $formlyx->version() || '0.1.0' !== $other->version() ) {
		fwrite( STDERR, "Expected both bundles to be version 0.1.0.\n" );
		exit( 1 );
	}
	echo "ok two Theme Adapter 0.1.0 bundles keep separate handles\n";
} finally {
	formlyx_coexist_remove( dirname( $root ) );
}
