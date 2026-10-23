<?php
/**
 * Access-token lifecycle.
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
 * Returns a usable access token and rotates refresh tokens under a lock.
 *
 * The token response does not include a refresh expiry. Formlyx documents a
 * 30-day refresh lifetime, so that duration is stored locally.
 *
 * @since 1.0.0
 */
final class TokenManager {

	/**
	 * Refresh shortly before the access token expires.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const ACCESS_SKEW = 60;

	/**
	 * Documented refresh-token lifetime in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const REFRESH_LIFETIME = 2592000;

	/**
	 * How long a refresh lock may be held.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const LOCK_TTL = 30;

	/**
	 * Read the stored connection without exposing it outside this class's callers.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function connection(): array {
		$stored = Storage::read( Storage::OPTION_CONNECTION );
		$status = isset( $stored['status'] ) && is_string( $stored['status'] ) ? $stored['status'] : 'disconnected';
		if ( ! in_array( $status, array( 'connected', 'unreachable', 'expired', 'disconnected' ), true ) ) {
			$status = 'disconnected';
		}

		return array(
			'access_token'       => self::string_value( $stored, 'access_token' ),
			'refresh_token'      => self::string_value( $stored, 'refresh_token' ),
			'access_expires_at'  => self::int_value( $stored, 'access_expires_at' ),
			'refresh_expires_at' => self::int_value( $stored, 'refresh_expires_at' ),
			'scope'              => self::string_value( $stored, 'scope' ),
			'workspace_id'       => self::string_value( $stored, 'workspace_id' ),
			'workspace_name'     => self::string_value( $stored, 'workspace_name' ),
			'workspace_email'    => self::string_value( $stored, 'workspace_email' ),
			'checked_at'         => self::int_value( $stored, 'checked_at' ),
			'status'             => $status,
		);
	}

	/**
	 * Whether the stored token grant includes a scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scope Scope name.
	 * @return bool
	 */
	public static function has_scope( string $scope ): bool {
		$parts = preg_split( '/\s+/', trim( (string) self::connection()['scope'] ) );
		return is_array( $parts ) && in_array( $scope, $parts, true );
	}

	/**
	 * Whether an access token is present and not inside the refresh window.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $connection Connection record.
	 * @return bool
	 */
	public static function access_is_current( array $connection ): bool {
		return '' !== $connection['access_token'] && (int) $connection['access_expires_at'] > time() + self::ACCESS_SKEW;
	}

	/**
	 * Return a current access token, refreshing when needed.
	 *
	 * @since 1.0.0
	 *
	 * @return array{ok: bool, token: string, notice: string}
	 */
	public static function access_token(): array {
		$connection = self::connection();
		if ( self::access_is_current( $connection ) ) {
			return array(
				'ok'     => true,
				'token'  => (string) $connection['access_token'],
				'notice' => '',
			);
		}

		if ( '' === $connection['refresh_token'] ) {
			return array(
				'ok'     => false,
				'token'  => '',
				'notice' => 'expired' === $connection['status'] ? 'expired' : 'disconnected',
			);
		}

		if ( (int) $connection['refresh_expires_at'] > 0 && (int) $connection['refresh_expires_at'] <= time() ) {
			self::mark_expired();
			return array(
				'ok'     => false,
				'token'  => '',
				'notice' => 'expired',
			);
		}

		return self::refresh( (string) $connection['refresh_token'] );
	}

	/**
	 * Store a token response.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $token     Token JSON.
	 * @param array<string, mixed> $preserve  Fields to keep when omitted.
	 * @return array{ok: bool, notice: string}
	 */
	public static function store_token_response( array $token, array $preserve = array() ): array {
		$parsed = self::parse_token( $token );
		if ( ! $parsed['ok'] ) {
			return array(
				'ok'     => false,
				'notice' => 'missing_scope' === $parsed['notice'] ? 'missing_scope' : 'connect_failed',
			);
		}

		$current = self::connection();
		Storage::write(
			Storage::OPTION_CONNECTION,
			array(
				'access_token'       => $parsed['access_token'],
				'refresh_token'      => $parsed['refresh_token'],
				'access_expires_at'  => time() + $parsed['expires_in'],
				'refresh_expires_at' => time() + self::REFRESH_LIFETIME,
				'scope'              => $parsed['scope'],
				'workspace_id'       => isset( $preserve['workspace_id'] ) ? (string) $preserve['workspace_id'] : (string) $current['workspace_id'],
				'workspace_name'     => isset( $preserve['workspace_name'] ) ? (string) $preserve['workspace_name'] : (string) $current['workspace_name'],
				'workspace_email'    => isset( $preserve['workspace_email'] ) ? (string) $preserve['workspace_email'] : (string) $current['workspace_email'],
				'checked_at'         => isset( $preserve['checked_at'] ) ? (int) $preserve['checked_at'] : (int) $current['checked_at'],
				'status'             => 'connected',
			)
		);
		FormCatalog::forget();

		return array(
			'ok'     => true,
			'notice' => '',
		);
	}

	/**
	 * Exchange a refresh token once.
	 *
	 * @since 1.0.0
	 *
	 * @param string $refresh_token Refresh token this request intends to rotate.
	 * @return array{ok: bool, token: string, notice: string}
	 */
	public static function refresh( string $refresh_token ): array {
		$owner    = Pkce::state();
		$acquired = self::acquire_lock( $owner );
		if ( ! $acquired ) {
			return self::wait_for_refresh( $refresh_token );
		}

		$latest = self::connection();
		if ( ! Pkce::equals( (string) $latest['refresh_token'], $refresh_token ) ) {
			self::release_lock( $owner );
			return self::adopt_replaced_connection( $latest );
		}

		$client   = ClientRegistration::stored();
		$response = Http::post_form(
			Api::token_url(),
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh_token,
				'client_id'     => $client['client_id'],
				'resource'      => Api::RESOURCE,
			)
		);

		$after = self::connection();
		if ( ! Pkce::equals( (string) $after['refresh_token'], $refresh_token ) ) {
			self::release_lock( $owner );
			return self::adopt_replaced_connection( $after );
		}

		if ( ! $response['ok'] ) {
			$notice = self::failure_notice( $response['error_code'] );
			if ( 'expired' === $notice && Pkce::equals( (string) self::connection()['refresh_token'], $refresh_token ) ) {
				self::mark_expired();
			}
			if ( 'unreachable' === $notice ) {
				self::mark_unreachable();
			}
			self::release_lock( $owner );
			return array(
				'ok'     => false,
				'token'  => '',
				'notice' => $notice,
			);
		}

		$stored = self::store_token_response( $response['json'] );
		self::release_lock( $owner );
		if ( ! $stored['ok'] ) {
			return array(
				'ok'     => false,
				'token'  => '',
				'notice' => $stored['notice'],
			);
		}

		$fresh = self::connection();
		return array(
			'ok'     => true,
			'token'  => (string) $fresh['access_token'],
			'notice' => '',
		);
	}

	/**
	 * Revoke the current refresh token when one is stored.
	 *
	 * @since 1.0.0
	 *
	 * @return array{confirmed: bool, reachable: bool}
	 */
	public static function revoke(): array {
		$connection = self::connection();
		$client     = ClientRegistration::stored();
		if ( '' === $connection['refresh_token'] || '' === $client['client_id'] ) {
			return array(
				'confirmed' => false,
				'reachable' => true,
			);
		}

		$response = Http::post_form(
			Api::revoke_url(),
			array(
				'token'     => $connection['refresh_token'],
				'client_id' => $client['client_id'],
			)
		);

		if ( 'network' === $response['error_code'] ) {
			return array(
				'confirmed' => false,
				'reachable' => false,
			);
		}

		return array(
			'confirmed' => $response['ok'],
			'reachable' => true,
		);
	}

	/**
	 * Remove tokens and mark the connection expired.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function mark_expired(): void {
		$connection                       = self::connection();
		$connection['access_token']       = '';
		$connection['refresh_token']      = '';
		$connection['access_expires_at']  = 0;
		$connection['refresh_expires_at'] = 0;
		$connection['status']             = 'expired';
		Storage::write( Storage::OPTION_CONNECTION, $connection );
	}

	/**
	 * Keep tokens and record that Formlyx could not be reached.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function mark_unreachable(): void {
		$connection           = self::connection();
		$connection['status'] = 'unreachable';
		Storage::write( Storage::OPTION_CONNECTION, $connection );
	}

	/**
	 * Remove tokens after an administrator disconnects.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear_tokens(): void {
		Storage::write(
			Storage::OPTION_CONNECTION,
			array(
				'access_token'       => '',
				'refresh_token'      => '',
				'access_expires_at'  => 0,
				'refresh_expires_at' => 0,
				'scope'              => '',
				'workspace_id'       => '',
				'workspace_name'     => '',
				'workspace_email'    => '',
				'checked_at'         => 0,
				'status'             => 'disconnected',
			)
		);
		FormCatalog::forget();
	}

	/**
	 * Validate a token endpoint payload.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $token Token JSON.
	 * @return array{ok: bool, notice: string, access_token: string, refresh_token: string, expires_in: int, scope: string}
	 */
	public static function parse_token( array $token ): array {
		$empty = array(
			'ok'            => false,
			'notice'        => 'connect_failed',
			'access_token'  => '',
			'refresh_token' => '',
			'expires_in'    => 0,
			'scope'         => '',
		);

		$access  = isset( $token['access_token'] ) && is_string( $token['access_token'] ) ? $token['access_token'] : '';
		$refresh = isset( $token['refresh_token'] ) && is_string( $token['refresh_token'] ) ? $token['refresh_token'] : '';
		$type    = isset( $token['token_type'] ) && is_string( $token['token_type'] ) ? $token['token_type'] : '';
		$scope   = isset( $token['scope'] ) && is_string( $token['scope'] ) ? $token['scope'] : '';
		$expires = isset( $token['expires_in'] ) ? (int) $token['expires_in'] : 0;

		if ( '' === $access || '' === $refresh || $expires <= 0 || 0 !== strcasecmp( $type, 'Bearer' ) ) {
			return $empty;
		}

		$parts = preg_split( '/\s+/', trim( $scope ) );
		if ( ! is_array( $parts ) || ! in_array( Api::SCOPE, $parts, true ) ) {
			$empty['notice']        = 'missing_scope';
			$empty['access_token']  = $access;
			$empty['refresh_token'] = $refresh;
			$empty['expires_in']    = $expires;
			$empty['scope']         = $scope;
			return $empty;
		}

		return array(
			'ok'            => true,
			'notice'        => '',
			'access_token'  => $access,
			'refresh_token' => $refresh,
			'expires_in'    => $expires,
			'scope'         => $scope,
		);
	}

	/**
	 * Map a Formlyx error code to a notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $error_code API or transport code.
	 * @return string
	 */
	public static function failure_notice( string $error_code ): string {
		if ( 'network' === $error_code ) {
			return 'unreachable';
		}
		if ( in_array( $error_code, array( 'INVALID_GRANT', 'CREDENTIAL_REVOKED', 'CREDENTIAL_EXPIRED', 'INVALID_CLIENT' ), true ) ) {
			return 'expired';
		}
		return 'connect_failed';
	}

	/**
	 * Acquire the refresh lock, replacing a stale lock when it is still stale.
	 *
	 * @since 1.0.0
	 *
	 * @param string $owner Lock owner.
	 * @return bool
	 */
	private static function acquire_lock( string $owner ): bool {
		$now      = time();
		$existing = get_option( Storage::OPTION_REFRESH_LOCK, false );
		if ( is_array( $existing ) && isset( $existing['expires_at'], $existing['owner'] ) && (int) $existing['expires_at'] <= $now ) {
			$again = get_option( Storage::OPTION_REFRESH_LOCK, false );
			$same  = is_array( $again ) && isset( $again['owner'], $again['expires_at'] ) && $again['owner'] === $existing['owner'] && (int) $again['expires_at'] <= $now;
			if ( $same ) {
				delete_option( Storage::OPTION_REFRESH_LOCK );
			}
		}

		return Storage::try_lock(
			Storage::OPTION_REFRESH_LOCK,
			array(
				'owner'      => $owner,
				'expires_at' => $now + self::LOCK_TTL,
			)
		);
	}

	/**
	 * Release the lock only for the request that acquired it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $owner Lock owner.
	 * @return void
	 */
	private static function release_lock( string $owner ): void {
		$existing = get_option( Storage::OPTION_REFRESH_LOCK, false );
		if ( ! is_array( $existing ) || ! isset( $existing['owner'] ) || ! is_string( $existing['owner'] ) ) {
			return;
		}
		if ( ! Pkce::equals( $existing['owner'], $owner ) ) {
			return;
		}
		delete_option( Storage::OPTION_REFRESH_LOCK );
	}

	/**
	 * Wait briefly for the request that holds the lock.
	 *
	 * @since 1.0.0
	 *
	 * @param string $refresh_token Refresh token observed before waiting.
	 * @return array{ok: bool, token: string, notice: string}
	 */
	private static function wait_for_refresh( string $refresh_token ): array {
		$attempts = defined( 'FORMLYX_UNIT_TESTS' ) ? 2 : 5;
		$delay    = defined( 'FORMLYX_UNIT_TESTS' ) ? 0 : 200000;

		for ( $attempt = 0; $attempt < $attempts; $attempt++ ) {
			if ( $delay > 0 ) {
				usleep( $delay );
			}
			$connection = self::connection();
			if ( self::access_is_current( $connection ) ) {
				return array(
					'ok'     => true,
					'token'  => (string) $connection['access_token'],
					'notice' => '',
				);
			}
			if ( ! Pkce::equals( (string) $connection['refresh_token'], $refresh_token ) && '' === $connection['refresh_token'] ) {
				return array(
					'ok'     => false,
					'token'  => '',
					'notice' => 'expired' === $connection['status'] ? 'expired' : 'disconnected',
				);
			}
		}

		return array(
			'ok'     => false,
			'token'  => '',
			'notice' => 'refresh_busy',
		);
	}

	/**
	 * Use a connection that replaced the refresh token this request held.
	 *
	 * The in-flight response is discarded. A disconnect or a newer refresh
	 * must not be overwritten by the request that lost the race.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $connection Connection observed after the replacement.
	 * @return array{ok: bool, token: string, notice: string}
	 */
	private static function adopt_replaced_connection( array $connection ): array {
		if ( self::access_is_current( $connection ) ) {
			return array(
				'ok'     => true,
				'token'  => (string) $connection['access_token'],
				'notice' => '',
			);
		}

		if ( 'expired' === $connection['status'] ) {
			$notice = 'expired';
		} elseif ( '' === $connection['refresh_token'] ) {
			$notice = 'disconnected';
		} else {
			$notice = 'refresh_busy';
		}

		return array(
			'ok'     => false,
			'token'  => '',
			'notice' => $notice,
		);
	}

	/**
	 * Read a string field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $source Source array.
	 * @param string               $key    Field name.
	 * @return string
	 */
	private static function string_value( array $source, string $key ): string {
		return isset( $source[ $key ] ) && is_string( $source[ $key ] ) ? $source[ $key ] : '';
	}

	/**
	 * Read an integer field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $source Source array.
	 * @param string               $key    Field name.
	 * @return int
	 */
	private static function int_value( array $source, string $key ): int {
		return isset( $source[ $key ] ) ? (int) $source[ $key ] : 0;
	}
}
