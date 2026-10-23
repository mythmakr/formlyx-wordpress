<?php
/**
 * Redirect helper.
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
 * Stops request handling after a redirect.
 *
 * Unit tests throw instead of exiting the PHPUnit process.
 *
 * @since 1.0.0
 */
final class Redirect extends \RuntimeException {

	/**
	 * Redirect and stop.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Destination URL.
	 * @return void
	 *
	 * @throws Redirect When unit tests must stop without exiting.
	 */
	public static function away( string $url ): void {
		wp_safe_redirect( $url );
		if ( defined( 'FORMLYX_UNIT_TESTS' ) ) {
			throw new self( 'redirect' );
		}
		exit;
	}
}
