<?php
/**
 * Public origin of this WordPress site.
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
 * Derives the site origin from the WordPress address.
 *
 * Callers must not pass an origin from the browser. Loopback names are
 * refused here so a local site never asks Formlyx to store them.
 *
 * @since 1.0.0
 */
final class SiteOrigin {

	/**
	 * Hosts that must not be added to a form.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const LOOPBACK = array( 'localhost', '127.0.0.1', '::1' );

	/**
	 * Describe the public site origin.
	 *
	 * @since 1.0.0
	 *
	 * @return array{origin: string, notice: string}
	 */
	public static function describe(): array {
		$parts = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return array(
				'origin' => '',
				'notice' => 'invalid_site',
			);
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( trim( (string) $parts['host'], '[]' ) );
		$host   = rtrim( $host, '.' );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return array(
				'origin' => '',
				'notice' => 'invalid_site',
			);
		}

		if ( in_array( $host, self::LOOPBACK, true ) ) {
			return array(
				'origin' => '',
				'notice' => 'local_site',
			);
		}

		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		$origin = $scheme . '://' . $host;
		$omit   = ( 'https' === $scheme && 443 === $port ) || ( 'http' === $scheme && 80 === $port );
		if ( $port > 0 && ! $omit ) {
			$origin .= ':' . $port;
		}

		return array(
			'origin' => $origin,
			'notice' => '',
		);
	}
}
