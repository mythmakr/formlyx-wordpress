<?php
/**
 * Authorization-code flow.
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
 * Starts and finishes the Formlyx authorization redirect.
 *
 * @since 1.0.0
 */
final class Authorization {

	/**
	 * Pending attempts expire after 10 minutes.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const PENDING_TTL = 600;

	/**
	 * Begin authorization or the Formlyx-hosted signup screen.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id WordPress user id.
	 * @param string $entry   `authorize` or `signup`.
	 * @return array{ok: bool, url: string, notice: string}
	 */
	public static function begin( int $user_id, string $entry ): array {
		$registration = ClientRegistration::ensure();
		if ( ! $registration['ok'] ) {
			return array(
				'ok'     => false,
				'url'    => '',
				'notice' => $registration['notice'],
			);
		}

		$state    = Pkce::state();
		$verifier = Pkce::verifier();
		delete_option( Storage::OPTION_PENDING_LOCK );
		Storage::write(
			Storage::OPTION_PENDING,
			array(
				'state'        => $state,
				'verifier'     => $verifier,
				'user_id'      => $user_id,
				'redirect_uri' => $registration['redirect_uri'],
				'expires_at'   => time() + self::PENDING_TTL,
			)
		);

		$params = array(
			'response_type'         => 'code',
			'client_id'             => $registration['client_id'],
			'redirect_uri'          => $registration['redirect_uri'],
			'scope'                 => Api::requested_scope(),
			'resource'              => Api::RESOURCE,
			'state'                 => $state,
			'code_challenge'        => Pkce::challenge( $verifier ),
			'code_challenge_method' => 'S256',
		);

		$base = 'signup' === $entry ? Api::signup_url() : Api::authorize_url();
		return array(
			'ok'     => true,
			'url'    => $base . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ),
			'notice' => '',
		);
	}

	/**
	 * Handle the browser return from Formlyx.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_callback(): void {
		AdminPage::require_admin();
		$notice = self::complete(
			get_current_user_id(),
			array(
				'code'  => self::query_text( 'code' ),
				'state' => self::query_text( 'state' ),
				'error' => self::query_text( 'error' ),
				'iss'   => self::query_text( 'iss' ),
			)
		);
		Redirect::away( AdminPage::settings_url( $notice ) );
	}

	/**
	 * Validate state and exchange the authorization code.
	 *
	 * @since 1.0.0
	 *
	 * @param int                   $user_id Current user id.
	 * @param array<string, string> $query  Callback query values.
	 * @return string Notice code.
	 */
	public static function complete( int $user_id, array $query ): string {
		$pending = Storage::read( Storage::OPTION_PENDING );
		$state   = isset( $query['state'] ) ? $query['state'] : '';
		$known   = isset( $pending['state'] ) && is_string( $pending['state'] ) ? $pending['state'] : '';

		if ( '' === $known || '' === $state || ! Pkce::equals( $known, $state ) ) {
			return 'invalid_link';
		}

		if ( (int) $pending['user_id'] !== $user_id ) {
			return 'invalid_link';
		}

		if ( (int) $pending['expires_at'] <= time() ) {
			delete_option( Storage::OPTION_PENDING );
			return 'expired_link';
		}

		$issuer = isset( $query['iss'] ) ? rtrim( $query['iss'], '/' ) : '';
		if ( ! Pkce::equals( Api::BASE, $issuer ) ) {
			delete_option( Storage::OPTION_PENDING );
			return 'invalid_issuer';
		}

		if ( ! Storage::try_lock( Storage::OPTION_PENDING_LOCK, array( 'state' => hash( 'sha256', $state ) ) ) ) {
			return 'invalid_link';
		}

		delete_option( Storage::OPTION_PENDING );

		if ( isset( $query['error'] ) && '' !== $query['error'] ) {
			return 'access_denied' === $query['error'] ? 'denied' : 'connect_failed';
		}

		$code = isset( $query['code'] ) ? $query['code'] : '';
		if ( '' === $code ) {
			return 'connect_failed';
		}

		$client   = ClientRegistration::stored();
		$verifier = isset( $pending['verifier'] ) && is_string( $pending['verifier'] ) ? $pending['verifier'] : '';
		$redirect = isset( $pending['redirect_uri'] ) && is_string( $pending['redirect_uri'] ) ? $pending['redirect_uri'] : '';
		$response = Http::post_form(
			Api::token_url(),
			array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => $redirect,
				'client_id'     => $client['client_id'],
				'code_verifier' => $verifier,
				'resource'      => Api::RESOURCE,
			)
		);

		if ( ! $response['ok'] ) {
			return TokenManager::failure_notice( $response['error_code'] );
		}

		$parsed = TokenManager::parse_token( $response['json'] );
		if ( ! $parsed['ok'] ) {
			if ( 'missing_scope' === $parsed['notice'] && '' !== $parsed['refresh_token'] ) {
				Http::post_form(
					Api::revoke_url(),
					array(
						'token'     => $parsed['refresh_token'],
						'client_id' => $client['client_id'],
					)
				);
			}
			return $parsed['notice'];
		}

		$saved = TokenManager::store_token_response( $response['json'] );
		if ( ! $saved['ok'] ) {
			return $saved['notice'];
		}

		$check = ConnectionStatus::check();
		if ( 'connected' === $check ) {
			return 'connected';
		}
		return $check;
	}

	/**
	 * Read one sanitized callback field.
	 *
	 * OAuth state, not a WordPress nonce, authenticates this request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Query key.
	 * @return string
	 */
	private static function query_text( string $key ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ $key ] ) || ! is_string( $_GET[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}
}
