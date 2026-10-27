<?php
/**
 * Bundled RazorSoft Theme Adapter.
 *
 * @package Formlyx
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Formlyx;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the Theme Adapter copy shipped in vendor/theme-adapter.
 *
 * `require` of load.php returns this directory's runtime. Calling that
 * object's enqueue method publishes this plugin's copy when another plugin
 * has bundled the library as well.
 *
 * @since 1.0.0
 */
final class ThemeAdapter {

	/**
	 * Runtime returned by load.php.
	 *
	 * @since 1.0.0
	 * @var object|null
	 */
	private static $runtime = null;

	/**
	 * Whether load.php has already been required.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $loaded = false;

	/**
	 * Return this plugin's adapter runtime.
	 *
	 * @since 1.0.0
	 *
	 * @return object|null
	 */
	public static function runtime() {
		if ( self::$loaded ) {
			return self::$runtime;
		}

		self::$loaded = true;
		$path         = dirname( __DIR__ ) . '/vendor/theme-adapter/load.php';
		if ( ! is_file( $path ) ) {
			return null;
		}

		$loaded = require $path;
		if ( is_object( $loaded ) ) {
			self::$runtime = $loaded;
		}

		return self::$runtime;
	}

	/**
	 * Publish this plugin's theme tokens.
	 *
	 * @since 1.0.0
	 *
	 * @return string Style handle, or an empty string when the bundle is missing.
	 */
	public static function enqueue(): string {
		$runtime = self::runtime();
		if ( is_object( $runtime ) && method_exists( $runtime, 'enqueue' ) ) {
			$runtime->enqueue();
		}
		if ( ! is_object( $runtime ) || ! method_exists( $runtime, 'styleHandle' ) ) {
			return '';
		}

		$handle = $runtime->styleHandle();
		if ( ! is_string( $handle ) || '' === $handle ) {
			return '';
		}

		return $handle;
	}
}
