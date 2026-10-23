<?php
/**
 * PKCE and state helpers.
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
 * Creates PKCE material and compares secrets in constant time.
 *
 * @since 1.0.0
 */
final class Pkce {

	/**
	 * Create a URL-safe verifier of 64 characters.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function verifier(): string {
		return self::encode( random_bytes( 48 ) );
	}

	/**
	 * Derive the S256 challenge for a verifier.
	 *
	 * @since 1.0.0
	 *
	 * @param string $verifier PKCE code verifier.
	 * @return string
	 */
	public static function challenge( string $verifier ): string {
		return self::encode( hash( 'sha256', $verifier, true ) );
	}

	/**
	 * Create a URL-safe state value.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function state(): string {
		return self::encode( random_bytes( 32 ) );
	}

	/**
	 * Compare two strings without leaking length through hash_equals().
	 *
	 * @since 1.0.0
	 *
	 * @param string $known Expected value.
	 * @param string $given Provided value.
	 * @return bool
	 */
	public static function equals( string $known, string $given ): bool {
		if ( strlen( $known ) !== strlen( $given ) ) {
			return false;
		}

		return hash_equals( $known, $given );
	}

	/**
	 * Encode bytes as unpadded base64url.
	 *
	 * @since 1.0.0
	 *
	 * @param string $bytes Raw bytes.
	 * @return string
	 */
	private static function encode( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PKCE uses unpadded base64url, not obfuscation.
	}
}
