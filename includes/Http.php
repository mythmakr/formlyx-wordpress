<?php
/**
 * Server-side requests to Formlyx.
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
 * Sends JSON and form requests without following redirects.
 *
 * @since 1.0.0
 */
final class Http {

	/**
	 * Test transport. Production leaves this unset.
	 *
	 * @since 1.0.0
	 * @var callable|null
	 */
	private static $transport;

	/**
	 * Replace the HTTP transport during unit tests.
	 *
	 * @since 1.0.0
	 *
	 * @param callable|null $transport Callback receiving method, URL, and args.
	 * @return void
	 */
	public static function set_transport( $transport ): void {
		if ( ! defined( 'FORMLYX_UNIT_TESTS' ) ) {
			return;
		}

		self::$transport = $transport;
	}

	/**
	 * POST a JSON body.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $url           Absolute Formlyx URL.
	 * @param array<string, mixed> $body          JSON body.
	 * @param string               $authorization Optional Authorization header.
	 * @return array{ok: bool, code: int, json: array<string, mixed>, error_code: string}
	 */
	public static function post_json( string $url, array $body, string $authorization = '' ): array {
		$encoded = wp_json_encode( $body );
		if ( ! is_string( $encoded ) ) {
			return self::failure( 'invalid_response' );
		}

		return self::request(
			'POST',
			$url,
			array(
				'headers' => self::headers( 'application/json', $authorization ),
				'body'    => $encoded,
			)
		);
	}

	/**
	 * POST an OAuth form body.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $url  Absolute Formlyx URL.
	 * @param array<string, mixed> $body Form fields.
	 * @return array{ok: bool, code: int, json: array<string, mixed>, error_code: string}
	 */
	public static function post_form( string $url, array $body ): array {
		return self::request(
			'POST',
			$url,
			array(
				'headers' => self::headers( 'application/x-www-form-urlencoded' ),
				'body'    => http_build_query( $body, '', '&', PHP_QUERY_RFC3986 ),
			)
		);
	}

	/**
	 * GET a JSON resource.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url           Absolute Formlyx URL.
	 * @param string $authorization Authorization header value.
	 * @param int    $limit         Maximum response size in bytes.
	 * @return array{ok: bool, code: int, json: array<string, mixed>, error_code: string}
	 */
	public static function get_json( string $url, string $authorization, int $limit = 65536 ): array {
		return self::request(
			'GET',
			$url,
			array(
				'headers' => self::headers( 'application/json', $authorization ),
			),
			$limit
		);
	}

	/**
	 * Perform one request.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $method HTTP method.
	 * @param string               $url    Absolute URL.
	 * @param array<string, mixed> $args   Request args.
	 * @param int                  $limit  Maximum response size in bytes.
	 * @return array{ok: bool, code: int, json: array<string, mixed>, error_code: string}
	 */
	private static function request( string $method, string $url, array $args, int $limit = 65536 ): array {
		if ( ! self::is_formlyx_url( $url ) ) {
			return self::failure( 'invalid_target' );
		}

		$args['timeout']             = 15;
		$args['redirection']         = 0;
		$args['limit_response_size'] = $limit;
		$args['user-agent']          = 'Formlyx Forms/' . FORMLYX_VERSION;

		if ( null !== self::$transport ) {
			$raw = call_user_func( self::$transport, $method, $url, $args );
		} else {
			$raw = 'GET' === $method ? wp_remote_get( $url, $args ) : wp_remote_post( $url, $args );
		}

		if ( is_wp_error( $raw ) ) {
			return self::failure( 'network' );
		}

		if ( ! is_array( $raw ) ) {
			return self::failure( 'invalid_response' );
		}

		$code = isset( $raw['code'] ) ? (int) $raw['code'] : (int) wp_remote_retrieve_response_code( $raw );
		$body = isset( $raw['body'] ) && is_string( $raw['body'] ) ? $raw['body'] : wp_remote_retrieve_body( $raw );
		if ( ! is_string( $body ) ) {
			return self::failure( 'invalid_response', $code );
		}

		if ( strlen( $body ) > $limit ) {
			return self::failure( 'response_too_large', $code );
		}

		if ( $code < 200 || $code >= 300 ) {
			return self::from_body( $code, $body, false );
		}

		return self::from_body( $code, $body, true );
	}

	/**
	 * Parse a response body.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $code    HTTP status.
	 * @param string $body    Response body.
	 * @param bool   $success Whether the status is 2xx.
	 * @return array{ok: bool, code: int, json: array<string, mixed>, error_code: string}
	 */
	private static function from_body( int $code, string $body, bool $success ): array {
		$decoded = array();
		if ( '' !== $body ) {
			$parsed = json_decode( $body, true );
			if ( ! is_array( $parsed ) ) {
				return self::failure( 'invalid_response', $code );
			}
			$decoded = $parsed;
		}

		if ( isset( $decoded['success'] ) && false === $decoded['success'] ) {
			$error = '';
			if ( isset( $decoded['error'] ) && is_array( $decoded['error'] ) && isset( $decoded['error']['code'] ) && is_string( $decoded['error']['code'] ) ) {
				$error = $decoded['error']['code'];
			}
			return array(
				'ok'         => false,
				'code'       => $code,
				'json'       => $decoded,
				'error_code' => '' !== $error ? $error : 'request_failed',
			);
		}

		if ( ! $success ) {
			return array(
				'ok'         => false,
				'code'       => $code,
				'json'       => $decoded,
				'error_code' => 'request_failed',
			);
		}

		return array(
			'ok'         => true,
			'code'       => $code,
			'json'       => $decoded,
			'error_code' => '',
		);
	}

	/**
	 * Build a failed result.
	 *
	 * @since 1.0.0
	 *
	 * @param string $error Error code.
	 * @param int    $code  HTTP status.
	 * @return array{ok: bool, code: int, json: array<string, mixed>, error_code: string}
	 */
	private static function failure( string $error, int $code = 0 ): array {
		return array(
			'ok'         => false,
			'code'       => $code,
			'json'       => array(),
			'error_code' => $error,
		);
	}

	/**
	 * Request headers. The authorization value is not logged.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content_type Content type.
	 * @param string $authorization Optional Authorization header.
	 * @return array<string, string>
	 */
	private static function headers( string $content_type, string $authorization = '' ): array {
		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => $content_type,
		);
		if ( '' !== $authorization ) {
			$headers['Authorization'] = $authorization;
		}
		return $headers;
	}

	/**
	 * Accept only the production Formlyx API origin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	private static function is_formlyx_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		return 'https' === strtolower( (string) $parts['scheme'] ) && 'api.formlyx.com' === strtolower( (string) $parts['host'] );
	}
}
