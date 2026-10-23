<?php
/**
 * OAuth public client registration.
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
 * Registers one public client for the current callback URL.
 *
 * Formlyx has no API to change redirect URIs. A new site URL registers a new
 * client and replaces the local record. The previous server-side registration
 * cannot be deleted from here.
 *
 * @since 1.0.0
 */
final class ClientRegistration {

	/**
	 * Stable WordPress callback.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function callback_url(): string {
		return admin_url( 'admin-post.php?action=formlyx_oauth_callback' );
	}

	/**
	 * Whether Formlyx will accept this callback.
	 *
	 * HTTPS is required. HTTP is limited to localhost, 127.0.0.1, and ::1.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Callback URL.
	 * @return bool
	 */
	public static function is_allowed_callback( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( trim( (string) $parts['host'], '[]' ) );
		if ( 'https' === $scheme ) {
			return true;
		}

		return 'http' === $scheme && in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
	}

	/**
	 * Return a client id registered for the current callback.
	 *
	 * @since 1.0.0
	 *
	 * @return array{ok: bool, client_id: string, redirect_uri: string, notice: string}
	 */
	public static function ensure(): array {
		$callback = self::callback_url();
		if ( ! self::is_allowed_callback( $callback ) ) {
			return self::failure( 'https_required' );
		}

		$stored = self::stored();
		if ( '' !== $stored['client_id'] && Pkce::equals( $stored['redirect_uri'], $callback ) ) {
			return array(
				'ok'           => true,
				'client_id'    => $stored['client_id'],
				'redirect_uri' => $stored['redirect_uri'],
				'notice'       => '',
			);
		}

		$response = Http::post_json(
			Api::register_url(),
			array(
				'client_name'                => 'Formlyx Forms',
				'redirect_uris'              => array( $callback ),
				'token_endpoint_auth_method' => 'none',
				'grant_types'                => array( 'authorization_code', 'refresh_token' ),
				'response_types'             => array( 'code' ),
			)
		);

		if ( ! $response['ok'] ) {
			return self::failure( 'registration_failed' );
		}

		$client_id = isset( $response['json']['client_id'] ) && is_string( $response['json']['client_id'] ) ? $response['json']['client_id'] : '';
		$returned  = '';
		if ( isset( $response['json']['redirect_uris'] ) && is_array( $response['json']['redirect_uris'] ) && isset( $response['json']['redirect_uris'][0] ) && is_string( $response['json']['redirect_uris'][0] ) ) {
			$returned = $response['json']['redirect_uris'][0];
		}

		if ( '' === $client_id || ! Pkce::equals( $returned, $callback ) ) {
			return self::failure( 'registration_failed' );
		}

		Storage::write(
			Storage::OPTION_CLIENT,
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => $returned,
				'registered_at' => time(),
			)
		);

		return array(
			'ok'           => true,
			'client_id'    => $client_id,
			'redirect_uri' => $returned,
			'notice'       => '',
		);
	}

	/**
	 * Read the stored registration.
	 *
	 * @since 1.0.0
	 *
	 * @return array{client_id: string, redirect_uri: string}
	 */
	public static function stored(): array {
		$stored = Storage::read( Storage::OPTION_CLIENT );
		return array(
			'client_id'    => isset( $stored['client_id'] ) && is_string( $stored['client_id'] ) ? $stored['client_id'] : '',
			'redirect_uri' => isset( $stored['redirect_uri'] ) && is_string( $stored['redirect_uri'] ) ? $stored['redirect_uri'] : '',
		);
	}

	/**
	 * Failed registration result.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notice Notice code.
	 * @return array{ok: bool, client_id: string, redirect_uri: string, notice: string}
	 */
	private static function failure( string $notice ): array {
		return array(
			'ok'           => false,
			'client_id'    => '',
			'redirect_uri' => '',
			'notice'       => $notice,
		);
	}
}
