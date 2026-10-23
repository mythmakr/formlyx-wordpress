<?php
/**
 * OAuth and settings tests.
 *
 * @package Formlyx
 */

declare(strict_types=1);

namespace Formlyx\Tests\Unit;

use Formlyx\AdminPage;
use Formlyx\Api;
use Formlyx\Authorization;
use Formlyx\ClientRegistration;
use Formlyx\ConnectionStatus;
use Formlyx\Http;
use Formlyx\Pkce;
use Formlyx\Redirect;
use Formlyx\Storage;
use Formlyx\TokenManager;
use PHPUnit\Framework\TestCase;

/**
 * Covers PKCE, registration, callbacks, refresh locking, and the settings screen.
 *
 * @since 1.0.0
 */
final class OAuthTest extends TestCase {

	/**
	 * Reset stubs before each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['formlyx_test_options']    = array();
		$GLOBALS['formlyx_test_user_id']    = 7;
		$GLOBALS['formlyx_test_can_manage'] = true;
		$GLOBALS['formlyx_test_nonce_ok']   = true;
		$GLOBALS['formlyx_test_redirects']  = array();
		$GLOBALS['formlyx_test_admin_base'] = 'https://example.test/wp-admin/';
		$GLOBALS['formlyx_http_log']        = array();
		Http::set_transport(
			static function ( $method, $url, $args ) {
				$GLOBALS['formlyx_http_log'][] = array( $method, $url, $args );
				$next                          = array_shift( $GLOBALS['formlyx_http_queue'] );
				return is_array( $next ) ? $next : new \WP_Error( 'http', 'missing response' );
			}
		);
		$GLOBALS['formlyx_http_queue'] = array();
	}

	/**
	 * PKCE matches the RFC 7636 appendix B vector.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_pkce_s256_matches_rfc_vector(): void {
		$verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
		$this->assertSame( 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', Pkce::challenge( $verifier ) );
	}

	/**
	 * Generated verifiers and challenges use the S256 length Formlyx requires.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_generated_pkce_material_has_required_length(): void {
		$verifier  = Pkce::verifier();
		$challenge = Pkce::challenge( $verifier );
		$this->assertSame( 64, strlen( $verifier ) );
		$this->assertSame( 43, strlen( $challenge ) );
		$this->assertSame( 1, preg_match( '/^[A-Za-z0-9_-]+$/', $verifier ) );
	}

	/**
	 * State comparison rejects a different value and a different length.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_state_comparison_rejects_mismatch(): void {
		$state = Pkce::state();
		$this->assertTrue( Pkce::equals( $state, $state ) );
		$this->assertFalse( Pkce::equals( $state, $state . 'x' ) );
		$this->assertFalse( Pkce::equals( $state, str_repeat( 'a', strlen( $state ) ) ) );
	}

	/**
	 * Callback URL rules follow the Formlyx redirect policy.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_callback_url_policy(): void {
		$this->assertTrue( ClientRegistration::is_allowed_callback( 'https://example.test/wp-admin/admin-post.php?action=formlyx_oauth_callback' ) );
		$this->assertTrue( ClientRegistration::is_allowed_callback( 'http://localhost/wp-admin/admin-post.php?action=formlyx_oauth_callback' ) );
		$this->assertTrue( ClientRegistration::is_allowed_callback( 'http://127.0.0.1:8888/wp-admin/admin-post.php?action=formlyx_oauth_callback' ) );
		$this->assertFalse( ClientRegistration::is_allowed_callback( 'http://example.test/wp-admin/admin-post.php?action=formlyx_oauth_callback' ) );
	}

	/**
	 * The first connection registers a public client and a later one reuses it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_registration_is_reused_until_the_callback_changes(): void {
		$callback = ClientRegistration::callback_url();
		$this->queue_json(
			201,
			array(
				'client_id'     => 'flxclient_one',
				'redirect_uris' => array( $callback ),
			)
		);

		$first = ClientRegistration::ensure();
		$this->assertTrue( $first['ok'] );
		$this->assertSame( 'flxclient_one', $first['client_id'] );
		$this->assertArrayNotHasKey( 'client_secret', Storage::read( Storage::OPTION_CLIENT ) );

		$second = ClientRegistration::ensure();
		$this->assertSame( 'flxclient_one', $second['client_id'] );
		$this->assertCount( 1, $GLOBALS['formlyx_http_log'] );

		$body = json_decode( $GLOBALS['formlyx_http_log'][0][2]['body'], true );
		$this->assertSame( 'none', $body['token_endpoint_auth_method'] );
		$this->assertSame( array( $callback ), $body['redirect_uris'] );

		$GLOBALS['formlyx_test_admin_base'] = 'https://moved.example/wp-admin/';
		$moved                              = ClientRegistration::callback_url();
		$this->queue_json(
			201,
			array(
				'client_id'     => 'flxclient_two',
				'redirect_uris' => array( $moved ),
			)
		);
		$third = ClientRegistration::ensure();
		$this->assertSame( 'flxclient_two', $third['client_id'] );
		$this->assertSame( $moved, ClientRegistration::stored()['redirect_uri'] );
	}

	/**
	 * A plain HTTP site does not call Formlyx.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_http_site_does_not_register(): void {
		$GLOBALS['formlyx_test_admin_base'] = 'http://example.test/wp-admin/';
		$result                             = ClientRegistration::ensure();
		$this->assertSame( 'https_required', $result['notice'] );
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * Connect stores the verifier on the server and redirects to Formlyx.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_connect_redirects_with_pkce_and_rest_resource(): void {
		$this->queue_registration();
		try {
			AdminPage::handle_connect();
			$this->fail( 'Redirect expected.' );
		} catch ( Redirect $redirect ) {
			$url = $GLOBALS['formlyx_test_redirects'][ count( $GLOBALS['formlyx_test_redirects'] ) - 1 ];
		}

		$this->assertStringStartsWith( Api::authorize_url() . '?', $url );
		$parts = wp_parse_url( $url );
		$query = array();
		parse_str( (string) $parts['query'], $query );
		$this->assertSame( 'code', $query['response_type'] );
		$this->assertSame( Api::requested_scope(), $query['scope'] );
		$this->assertSame( Api::RESOURCE, $query['resource'] );
		$this->assertSame( 'S256', $query['code_challenge_method'] );
		$this->assertArrayNotHasKey( 'code_verifier', $query );

		$pending = Storage::read( Storage::OPTION_PENDING );
		$this->assertSame( $query['state'], $pending['state'] );
		$this->assertSame( $query['code_challenge'], Pkce::challenge( $pending['verifier'] ) );
		$this->assertSame( 7, (int) $pending['user_id'] );
		$this->assertFalse( get_option( Storage::OPTION_CONNECTION, false ) );
	}

	/**
	 * Account creation uses the Formlyx OAuth signup endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_create_account_uses_oauth_signup(): void {
		$_POST['formlyx_entry'] = 'signup';
		$this->queue_registration();
		try {
			AdminPage::handle_connect();
			$this->fail( 'Redirect expected.' );
		} catch ( Redirect $redirect ) {
			$this->assertStringStartsWith( Api::signup_url() . '?', $GLOBALS['formlyx_test_redirects'][0] );
		}
		unset( $_POST['formlyx_entry'] );
	}

	/**
	 * A successful callback stores tokens without autoload and clears the verifier.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_callback_stores_tokens_and_workspace(): void {
		$started = $this->start_authorization();
		$this->queue_token( 'flx_access', 'flxr_refresh' );
		$this->queue_json(
			200,
			array(
				'success' => true,
				'data'    => array(
					'id'    => 'user_1',
					'name'  => 'Northwind',
					'email' => 'ada@example.com',
				),
			)
		);

		$notice = Authorization::complete(
			7,
			array(
				'code'  => 'auth-code',
				'state' => $started['state'],
				'error' => '',
				'iss'   => Api::BASE,
			)
		);

		$this->assertSame( 'connected', $notice );
		$stored = Storage::read( Storage::OPTION_CONNECTION );
		$this->assertSame( 'flx_access', $stored['access_token'] );
		$this->assertSame( 'flxr_refresh', $stored['refresh_token'] );
		$this->assertSame( 'Northwind', $stored['workspace_name'] );
		$this->assertFalse( get_option( Storage::OPTION_PENDING, false ) );
		$this->assertNotFalse( get_option( Storage::OPTION_PENDING_LOCK, false ) );

		$token_body = array();
		parse_str( $GLOBALS['formlyx_http_log'][0][2]['body'], $token_body );
		$this->assertSame( 'authorization_code', $token_body['grant_type'] );
		$this->assertSame( $started['verifier'], $token_body['code_verifier'] );
		$this->assertSame( Api::RESOURCE, $token_body['resource'] );
		$this->assertArrayNotHasKey( 'client_secret', $token_body );
	}

	/**
	 * Expired, replayed, and foreign attempts are rejected.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_state_expiration_replay_and_user_binding(): void {
		$started               = $this->start_authorization();
		$pending               = Storage::read( Storage::OPTION_PENDING );
		$pending['expires_at'] = time() - 5;
		Storage::write( Storage::OPTION_PENDING, $pending );
		$this->assertSame(
			'expired_link',
			Authorization::complete(
				7,
				array(
					'code'  => 'c',
					'state' => $started['state'],
					'error' => '',
					'iss'   => '',
				)
			)
		);

		$started = $this->start_authorization();
		$this->assertSame(
			'invalid_link',
			Authorization::complete(
				9,
				array(
					'code'  => 'c',
					'state' => $started['state'],
					'error' => '',
					'iss'   => '',
				)
			)
		);

		Storage::try_lock( Storage::OPTION_PENDING_LOCK, array( 'state' => hash( 'sha256', $started['state'] ) ) );
		$this->assertSame(
			'invalid_link',
			Authorization::complete(
				7,
				array(
					'code'  => 'c',
					'state' => $started['state'],
					'error' => '',
					'iss'   => Api::BASE,
				)
			)
		);
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * OAuth errors and a foreign issuer do not replace stored credentials.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_denied_and_invalid_issuer_keep_existing_tokens(): void {
		$this->seed_connection( 'flx_old', 'flxr_old', time() + 3600 );
		$started = $this->start_authorization();
		$this->assertSame(
			'denied',
			Authorization::complete(
				7,
				array(
					'code'  => '',
					'state' => $started['state'],
					'error' => 'access_denied',
					'iss'   => Api::BASE,
				)
			)
		);
		$this->assertSame( 'flx_old', TokenManager::connection()['access_token'] );

		$started = $this->start_authorization();
		$this->assertSame(
			'invalid_issuer',
			Authorization::complete(
				7,
				array(
					'code'  => 'c',
					'state' => $started['state'],
					'error' => '',
					'iss'   => 'https://evil.example',
				)
			)
		);
		$this->assertSame( 'flx_old', TokenManager::connection()['access_token'] );
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );

		$started = $this->start_authorization();
		$this->assertSame(
			'invalid_issuer',
			Authorization::complete(
				7,
				array(
					'code'  => 'c',
					'state' => $started['state'],
					'error' => '',
					'iss'   => '',
				)
			)
		);
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * A token payload without forms:read is refused.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_token_response_requires_forms_read(): void {
		$started = $this->start_authorization();
		$this->queue_token( 'flx_access', 'flxr_refresh', 'forms:write' );
		$this->queue_json( 200, array() );
		$this->assertSame(
			'missing_scope',
			Authorization::complete(
				7,
				array(
					'code'  => 'c',
					'state' => $started['state'],
					'error' => '',
					'iss'   => Api::BASE,
				)
			)
		);
		$this->assertSame( '', TokenManager::connection()['access_token'] );
		$this->assertStringContainsString( Api::revoke_url(), $GLOBALS['formlyx_http_log'][1][1] );
	}

	/**
	 * An invalid token payload is rejected.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_invalid_token_response_is_rejected(): void {
		$parsed = TokenManager::parse_token(
			array(
				'access_token' => 'flx_access',
				'token_type'   => 'Bearer',
			)
		);
		$this->assertFalse( $parsed['ok'] );
	}

	/**
	 * Credentials are saved with autoload disabled.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_connection_option_is_not_autoloaded(): void {
		TokenManager::store_token_response( $this->token_payload( 'flx_access', 'flxr_refresh' ) );
		$this->assertSame( 'flx_access', TokenManager::connection()['access_token'] );
		$this->assertFalse( $GLOBALS['formlyx_test_autoload'][ Storage::OPTION_CONNECTION ] );
	}

	/**
	 * An expired access token is refreshed once and the new pair replaces it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_refresh_rotates_tokens(): void {
		$this->seed_connection( 'flx_old', 'flxr_old', time() - 10 );
		$this->queue_token( 'flx_new', 'flxr_new' );
		$result = TokenManager::access_token();
		$this->assertTrue( $result['ok'] );
		$this->assertSame( 'flx_new', $result['token'] );
		$this->assertSame( 'flxr_new', TokenManager::connection()['refresh_token'] );
		$this->assertFalse( get_option( Storage::OPTION_REFRESH_LOCK, false ) );

		$body = array();
		parse_str( $GLOBALS['formlyx_http_log'][0][2]['body'], $body );
		$this->assertSame( 'refresh_token', $body['grant_type'] );
		$this->assertSame( 'flxr_old', $body['refresh_token'] );
		$this->assertSame( Api::RESOURCE, $body['resource'] );
	}

	/**
	 * A held lock prevents a second refresh, and a stale lock can be replaced.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_concurrent_refresh_waits_and_stale_lock_recovers(): void {
		$this->seed_connection( 'flx_old', 'flxr_old', time() - 10 );
		Storage::try_lock(
			Storage::OPTION_REFRESH_LOCK,
			array(
				'owner'      => 'other',
				'expires_at' => time() + 30,
			)
		);
		$busy = TokenManager::access_token();
		$this->assertSame( 'refresh_busy', $busy['notice'] );
		$this->assertSame( 'flxr_old', TokenManager::connection()['refresh_token'] );
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );

		Storage::write(
			Storage::OPTION_REFRESH_LOCK,
			array(
				'owner'      => 'stale',
				'expires_at' => time() - 5,
			)
		);
		$this->queue_token( 'flx_new', 'flxr_new' );
		$recovered = TokenManager::access_token();
		$this->assertSame( 'flx_new', $recovered['token'] );
	}

	/**
	 * A newer token pair is kept when a late refresh fails.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_failed_refresh_does_not_replace_a_newer_pair(): void {
		$this->seed_connection( 'flx_old', 'flxr_old', time() - 10 );
		Http::set_transport(
			static function () {
				TokenManager::store_token_response(
					array(
						'access_token'  => 'flx_winner',
						'refresh_token' => 'flxr_winner',
						'token_type'    => 'Bearer',
						'expires_in'    => 3600,
						'scope'         => 'forms:read',
					)
				);
				return array(
					'code' => 400,
					'body' => wp_json_encode(
						array(
							'success' => false,
							'error'   => array(
								'code'    => 'INVALID_GRANT',
								'message' => 'Refresh token is invalid or expired.',
							),
						)
					),
				);
			}
		);

		$result = TokenManager::refresh( 'flxr_old' );
		$this->assertSame( 'flx_winner', $result['token'] );
		$this->assertSame( 'flxr_winner', TokenManager::connection()['refresh_token'] );
	}

	/**
	 * An invalid_grant response clears the token that was presented.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_invalid_grant_expires_the_presented_token(): void {
		$this->seed_connection( 'flx_old', 'flxr_old', time() - 10 );
		$this->queue_json(
			400,
			array(
				'success' => false,
				'error'   => array(
					'code'    => 'INVALID_GRANT',
					'message' => 'Refresh token is invalid or expired.',
				),
			)
		);
		$result = TokenManager::access_token();
		$this->assertSame( 'expired', $result['notice'] );
		$this->assertSame( '', TokenManager::connection()['refresh_token'] );
		$this->assertSame( 'expired', TokenManager::connection()['status'] );
	}

	/**
	 * A refresh that finishes after disconnect does not restore tokens.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_refresh_does_not_restore_tokens_after_disconnect(): void {
		$this->seed_connection( 'flx_old', 'flxr_old', time() - 10 );
		Http::set_transport(
			static function () {
				TokenManager::clear_tokens();
				return array(
					'code' => 200,
					'body' => (string) wp_json_encode(
						array(
							'access_token'  => 'flx_late',
							'refresh_token' => 'flxr_late',
							'token_type'    => 'Bearer',
							'expires_in'    => 3600,
							'scope'         => 'forms:read',
						)
					),
				);
			}
		);

		$result = TokenManager::refresh( 'flxr_old' );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'disconnected', $result['notice'] );
		$this->assertSame( '', TokenManager::connection()['access_token'] );
		$this->assertSame( 'disconnected', TokenManager::connection()['status'] );
		$this->assertFalse( get_option( Storage::OPTION_REFRESH_LOCK, false ) );
	}

	/**
	 * A late success does not replace a newer pair that is already inside the refresh window.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_late_refresh_keeps_a_newer_pair_inside_the_skew_window(): void {
		$this->seed_connection( 'flx_old', 'flxr_old', time() - 10 );
		Http::set_transport(
			static function () {
				TokenManager::store_token_response(
					array(
						'access_token'  => 'flx_winner',
						'refresh_token' => 'flxr_winner',
						'token_type'    => 'Bearer',
						'expires_in'    => 30,
						'scope'         => 'forms:read',
					)
				);
				return array(
					'code' => 200,
					'body' => (string) wp_json_encode(
						array(
							'access_token'  => 'flx_late',
							'refresh_token' => 'flxr_late',
							'token_type'    => 'Bearer',
							'expires_in'    => 3600,
							'scope'         => 'forms:read',
						)
					),
				);
			}
		);

		$result = TokenManager::refresh( 'flxr_old' );
		$this->assertSame( 'refresh_busy', $result['notice'] );
		$this->assertSame( 'flxr_winner', TokenManager::connection()['refresh_token'] );
		$this->assertSame( 'flx_winner', TokenManager::connection()['access_token'] );
	}

	/**
	 * After the refresh token is rejected, later requests stay expired and do not call Formlyx.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_expired_status_persists_without_another_request(): void {
		$this->seed_connection( 'flx_old', 'flxr_old', time() - 10 );
		$this->queue_json(
			400,
			array(
				'success' => false,
				'error'   => array(
					'code'    => 'INVALID_GRANT',
					'message' => 'Refresh token is invalid or expired.',
				),
			)
		);
		$this->assertSame( 'expired', TokenManager::access_token()['notice'] );
		$GLOBALS['formlyx_http_log'] = array();
		$again                       = TokenManager::access_token();
		$this->assertSame( 'expired', $again['notice'] );
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );

		ob_start();
		AdminPage::render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Needs reconnecting', $html );
		$this->assertStringContainsString( 'Reconnect', $html );
		$this->assertStringNotContainsString( 'flx_old', $html );
	}

	/**
	 * A body over the request limit is rejected and the saved tokens stay.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_oversized_response_does_not_change_the_connection(): void {
		$this->seed_connection( 'flx_access', 'flxr_refresh', time() + 3600 );
		$GLOBALS['formlyx_http_queue'][] = array(
			'code' => 200,
			'body' => str_repeat( 'a', 65537 ),
		);
		$this->assertSame( 'connect_failed', ConnectionStatus::check() );
		$this->assertSame( 'flx_access', TokenManager::connection()['access_token'] );
		$this->assertSame( 'connected', TokenManager::connection()['status'] );
	}

	/**
	 * Network failures keep the saved tokens.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_network_failure_keeps_tokens(): void {
		$this->seed_connection( 'flx_old', 'flxr_old', time() - 10 );
		$GLOBALS['formlyx_http_queue'][] = new \WP_Error( 'http', 'down' );
		$result                          = TokenManager::access_token();
		$this->assertSame( 'unreachable', $result['notice'] );
		$this->assertSame( 'flxr_old', TokenManager::connection()['refresh_token'] );
		$this->assertSame( 'unreachable', TokenManager::connection()['status'] );
	}

	/**
	 * Workspace checks use the bearer token and the REST resource.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_connection_check_reads_workspace(): void {
		$this->seed_connection( 'flx_access', 'flxr_refresh', time() + 3600 );
		$this->queue_json(
			200,
			array(
				'success' => true,
				'data'    => array(
					'id'    => 'user_1',
					'name'  => 'Northwind',
					'email' => 'ada@example.com',
				),
			)
		);
		$this->assertSame( 'connected', ConnectionStatus::check() );
		$this->assertSame( Api::workspace_url(), $GLOBALS['formlyx_http_log'][0][1] );
		$this->assertSame( 'Bearer flx_access', $GLOBALS['formlyx_http_log'][0][2]['headers']['Authorization'] );
		$this->assertSame( 'Northwind', TokenManager::connection()['workspace_name'] );
	}

	/**
	 * Disconnect removes tokens, keeps the client, and reports revocation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_disconnect_clears_tokens_and_keeps_client(): void {
		$this->seed_connection( 'flx_access', 'flxr_refresh', time() + 3600 );
		Storage::write(
			Storage::OPTION_CLIENT,
			array(
				'client_id'    => 'flxclient_one',
				'redirect_uri' => ClientRegistration::callback_url(),
			)
		);
		$this->queue_json( 200, array() );
		try {
			AdminPage::handle_disconnect();
			$this->fail( 'Redirect expected.' );
		} catch ( Redirect $redirect ) {
			$this->assertStringContainsString( 'formlyx_notice=disconnected', $GLOBALS['formlyx_test_redirects'][0] );
		}
		$this->assertSame( '', TokenManager::connection()['access_token'] );
		$this->assertSame( 'flxclient_one', ClientRegistration::stored()['client_id'] );
	}

	/**
	 * A revocation network failure still disconnects locally and says so.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_disconnect_reports_unconfirmed_revocation(): void {
		$this->seed_connection( 'flx_access', 'flxr_refresh', time() + 3600 );
		Storage::write(
			Storage::OPTION_CLIENT,
			array(
				'client_id'    => 'flxclient_one',
				'redirect_uri' => ClientRegistration::callback_url(),
			)
		);
		$GLOBALS['formlyx_http_queue'][] = new \WP_Error( 'http', 'down' );
		try {
			AdminPage::handle_disconnect();
			$this->fail( 'Redirect expected.' );
		} catch ( Redirect $redirect ) {
			$this->assertStringContainsString( 'disconnect_unconfirmed', $GLOBALS['formlyx_test_redirects'][0] );
		}
		$this->assertSame( 'disconnected', TokenManager::connection()['status'] );
	}

	/**
	 * Capability and nonce failures stop before registration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_admin_actions_require_capability_and_nonce(): void {
		$GLOBALS['formlyx_test_can_manage'] = false;
		try {
			AdminPage::handle_connect();
			$this->fail( 'Capability failure expected.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'not allowed', $exception->getMessage() );
		}

		$GLOBALS['formlyx_test_can_manage'] = true;
		$GLOBALS['formlyx_test_nonce_ok']   = false;
		try {
			AdminPage::handle_disconnect();
			$this->fail( 'Nonce failure expected.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'nonce', $exception->getMessage() );
		}
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * The settings screen changes with connection state and never prints tokens.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_settings_screen_hides_tokens(): void {
		ob_start();
		AdminPage::render();
		$disconnected = ob_get_clean();
		$this->assertStringContainsString( 'Connect to Formlyx', $disconnected );
		$this->assertStringContainsString( 'Create a Formlyx account', $disconnected );
		$this->assertStringNotContainsString( 'Connected to Formlyx', $disconnected );

		$this->seed_connection( 'flx_secret_access', 'flxr_secret_refresh', time() + 3600 );
		$connection                    = TokenManager::connection();
		$connection['workspace_name']  = 'Northwind';
		$connection['workspace_email'] = 'ada@example.com';
		$connection['checked_at']      = 1760745600;
		$connection['status']          = 'connected';
		Storage::write( Storage::OPTION_CONNECTION, $connection );

		ob_start();
		AdminPage::render();
		$connected = ob_get_clean();
		$this->assertStringContainsString( 'Connected to Formlyx', $connected );
		$this->assertStringContainsString( 'Northwind', $connected );
		$this->assertStringContainsString( 'ada@example.com', $connected );
		$this->assertStringContainsString( 'Reconnect', $connected );
		$this->assertStringContainsString( 'Disconnect', $connected );
		$this->assertStringNotContainsString( 'flx_secret_access', $connected );
		$this->assertStringNotContainsString( 'flxr_secret_refresh', $connected );
	}

	/**
	 * Only the Formlyx API host is added for safe redirects.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_redirect_allowlist_is_the_formlyx_api(): void {
		$this->assertContains( 'api.formlyx.com', Api::allowed_hosts( array( 'example.test' ) ) );
	}

	/**
	 * Queue a registration response for the current callback.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function queue_registration(): void {
		$this->queue_json(
			201,
			array(
				'client_id'     => 'flxclient_one',
				'redirect_uris' => array( ClientRegistration::callback_url() ),
			)
		);
	}

	/**
	 * Start authorization and return the pending record.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function start_authorization(): array {
		$this->queue_registration();
		$started = Authorization::begin( 7, 'authorize' );
		$this->assertTrue( $started['ok'] );
		$GLOBALS['formlyx_http_log'] = array();
		return Storage::read( Storage::OPTION_PENDING );
	}

	/**
	 * Queue a successful token response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $access  Access token.
	 * @param string $refresh Refresh token.
	 * @param string $scope   Scope string.
	 * @return void
	 */
	private function queue_token( string $access, string $refresh, string $scope = 'forms:read' ): void {
		$this->queue_json( 200, $this->token_payload( $access, $refresh, $scope ) );
	}

	/**
	 * Build a token payload.
	 *
	 * @since 1.0.0
	 *
	 * @param string $access  Access token.
	 * @param string $refresh Refresh token.
	 * @param string $scope   Scope string.
	 * @return array<string, mixed>
	 */
	private function token_payload( string $access, string $refresh, string $scope = 'forms:read' ): array {
		return array(
			'access_token'  => $access,
			'refresh_token' => $refresh,
			'token_type'    => 'Bearer',
			'expires_in'    => 3600,
			'scope'         => $scope,
		);
	}

	/**
	 * Queue a JSON HTTP response.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $code HTTP status.
	 * @param array<string, mixed> $body JSON body.
	 * @return void
	 */
	private function queue_json( int $code, array $body ): void {
		$GLOBALS['formlyx_http_queue'][] = array(
			'code' => $code,
			'body' => (string) wp_json_encode( $body ),
		);
	}

	/**
	 * Seed a connection and matching client.
	 *
	 * @since 1.0.0
	 *
	 * @param string $access     Access token.
	 * @param string $refresh    Refresh token.
	 * @param int    $expires_at Access expiry.
	 * @return void
	 */
	private function seed_connection( string $access, string $refresh, int $expires_at ): void {
		Storage::write(
			Storage::OPTION_CLIENT,
			array(
				'client_id'    => 'flxclient_one',
				'redirect_uri' => ClientRegistration::callback_url(),
			)
		);
		Storage::write(
			Storage::OPTION_CONNECTION,
			array(
				'access_token'       => $access,
				'refresh_token'      => $refresh,
				'access_expires_at'  => $expires_at,
				'refresh_expires_at' => time() + 2592000,
				'scope'              => 'forms:read',
				'workspace_id'       => '',
				'workspace_name'     => '',
				'workspace_email'    => '',
				'checked_at'         => 0,
				'status'             => 'connected',
			)
		);
	}
}
