<?php
/**
 * Site-origin approval tests.
 *
 * @package Formlyx
 */

declare(strict_types=1);

namespace Formlyx\Tests\Unit;

use Formlyx\Api;
use Formlyx\EmbedAuthorization;
use Formlyx\EmbedOriginEndpoint;
use Formlyx\Http;
use Formlyx\SiteOrigin;
use Formlyx\Storage;
use Formlyx\TokenManager;
use PHPUnit\Framework\TestCase;

/**
 * Covers origin derivation, the editor proxy, and preserved restrictions.
 *
 * @since 1.0.0
 */
final class EmbedOriginTest extends TestCase {

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
		$GLOBALS['formlyx_test_transients'] = array();
		$GLOBALS['formlyx_http_log']        = array();
		$GLOBALS['formlyx_http_queue']      = array();
		$GLOBALS['formlyx_test_routes']     = array();
		$GLOBALS['formlyx_test_home_url']   = 'https://example.test/';
		Http::set_transport(
			static function ( $method, $url, $args ) {
				$GLOBALS['formlyx_http_log'][] = array( $method, $url, $args );
				$next                          = array_shift( $GLOBALS['formlyx_http_queue'] );
				return is_array( $next ) ? $next : new \WP_Error( 'http', 'missing response' );
			}
		);
	}

	/**
	 * A new connection requests the embed scope without replacing forms:read.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_requested_scope_includes_read_and_embed(): void {
		$this->assertSame( 'forms:read forms:embed', Api::requested_scope() );
		$parsed = TokenManager::parse_token(
			array(
				'access_token'  => 'flx_access',
				'refresh_token' => 'flxr_refresh',
				'token_type'    => 'Bearer',
				'expires_in'    => 3600,
				'scope'         => Api::requested_scope(),
			)
		);
		$this->assertTrue( $parsed['ok'] );
	}

	/**
	 * The public site address is the origin, including a non-default port.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_origin_comes_from_the_site_url(): void {
		$GLOBALS['formlyx_test_home_url'] = 'https://example.test:8443/blog/';
		$this->assertSame(
			array(
				'origin' => 'https://example.test:8443',
				'notice' => '',
			),
			SiteOrigin::describe()
		);
	}

	/**
	 * A local site is explained and never sent to Formlyx.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_local_site_is_not_sent(): void {
		$this->seed_connection();
		$GLOBALS['formlyx_test_home_url'] = 'http://127.0.0.1:8099/';

		$result = EmbedAuthorization::approve( 'form_owned' );

		$this->assertSame( 'local_site', $result['state'] );
		$this->assertFalse( $result['authorized'] );
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * The approval request uses the site origin and ignores a browser origin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_approval_ignores_the_browser_origin_and_keeps_other_domains_private(): void {
		$this->seed_connection();
		$this->queue_decision(
			array(
				'authorized'          => true,
				'restricted'          => true,
				'allowAnyOrigin'      => false,
				'publicEmbedEnabled'  => true,
				'status'              => 'published',
				'added'               => true,
				'canApprove'          => false,
				'allowedEmbedOrigins' => array( 'https://findmyfamily.pro', 'https://example.test' ),
				'access_token'        => 'flx_secret_access',
			)
		);

		$request = new class() {
			/**
			 * Return a request field, including an origin the proxy must ignore.
			 *
			 * @param string $key Field name.
			 * @return string|null
			 */
			public function get_param( string $key ) {
				if ( 'id' === $key ) {
					return 'form_owned';
				}
				if ( 'origin' === $key ) {
					return 'https://evil.example';
				}
				return null;
			}

			/**
			 * Return a JSON body the proxy must ignore.
			 *
			 * @return array{origin: string}
			 */
			public function get_json_params(): array {
				return array(
					'origin' => 'https://evil.example',
				);
			}
		};
		$result  = EmbedOriginEndpoint::approve( $request );

		$this->assertSame( 'authorized', $result['state'] );
		$this->assertTrue( $result['authorized'] );
		$this->assertStringContainsString( 'other allowed domains were kept', $result['message'] );
		$encoded = (string) wp_json_encode( $result );
		$this->assertStringNotContainsString( 'findmyfamily', $encoded );
		$this->assertStringNotContainsString( 'flx_secret_access', $encoded );
		$this->assertStringNotContainsString( 'evil.example', $encoded );

		$call = $GLOBALS['formlyx_http_log'][0];
		$this->assertSame( 'POST', $call[0] );
		$this->assertSame( Api::embed_origin_url( 'form_owned' ), $call[1] );
		$this->assertSame( 'Bearer flx_secret_access', $call[2]['headers']['Authorization'] );
		$sent = json_decode( (string) $call[2]['body'], true );
		$this->assertSame( array( 'origin' => 'https://example.test' ), $sent );
	}

	/**
	 * A duplicate approval confirms the site is already allowed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_duplicate_origin_confirms_without_claiming_a_new_grant(): void {
		$this->seed_connection();
		$this->queue_decision(
			array(
				'authorized'         => true,
				'allowAnyOrigin'     => false,
				'publicEmbedEnabled' => true,
				'status'             => 'published',
				'added'              => false,
				'canApprove'         => false,
			)
		);

		$result = EmbedAuthorization::approve( 'form_owned' );

		$this->assertSame( 'authorized', $result['state'] );
		$this->assertStringContainsString( 'already embed', $result['message'] );
	}

	/**
	 * An unrestricted form is reported as already allowed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_unrestricted_form_needs_no_approval(): void {
		$this->seed_connection();
		$this->queue_decision(
			array(
				'authorized'         => true,
				'allowAnyOrigin'     => true,
				'publicEmbedEnabled' => true,
				'status'             => 'published',
				'added'              => false,
				'canApprove'         => false,
			)
		);

		$result = EmbedAuthorization::status( 'form_owned' );

		$this->assertSame( 'authorized', $result['state'] );
		$this->assertStringContainsString( 'any site', $result['message'] );
		$this->assertStringContainsString( 'origin=https%3A%2F%2Fexample.test', $GLOBALS['formlyx_http_log'][0][1] );
	}

	/**
	 * A restricted form that this site cannot yet embed offers approval.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_restricted_form_requires_approval(): void {
		$this->seed_connection();
		$this->queue_decision(
			array(
				'authorized'         => false,
				'allowAnyOrigin'     => false,
				'publicEmbedEnabled' => true,
				'status'             => 'published',
				'added'              => false,
				'canApprove'         => true,
			)
		);

		$result = EmbedAuthorization::status( 'form_owned' );

		$this->assertSame( 'approval_required', $result['state'] );
		$this->assertFalse( $result['authorized'] );
	}

	/**
	 * A reader connection can see the gap but cannot approve it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_missing_embed_scope_does_not_call_the_approval_api(): void {
		$this->seed_connection( 'forms:read' );
		$this->queue_decision(
			array(
				'authorized'         => false,
				'allowAnyOrigin'     => false,
				'publicEmbedEnabled' => true,
				'status'             => 'published',
				'added'              => false,
				'canApprove'         => false,
			)
		);

		$status = EmbedAuthorization::status( 'form_owned' );
		$this->assertSame( 'reconnect', $status['state'] );
		$this->assertCount( 1, $GLOBALS['formlyx_http_log'] );

		$approved = EmbedAuthorization::approve( 'form_owned' );
		$this->assertSame( 'reconnect', $approved['state'] );
		$this->assertStringContainsString( 'Reconnect', $approved['message'] );
		$this->assertCount( 1, $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * A revoked token becomes a reconnect prompt and is removed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_revoked_connection_is_cleared(): void {
		$this->seed_connection();
		$GLOBALS['formlyx_http_queue'][] = array(
			'code' => 401,
			'body' => (string) wp_json_encode(
				array(
					'success' => false,
					'error'   => array(
						'code'    => 'CREDENTIAL_REVOKED',
						'message' => 'token flx_secret_access revoked',
					),
				)
			),
		);

		$result = EmbedAuthorization::status( 'form_owned' );

		$this->assertSame( 'reconnect', $result['state'] );
		$this->assertStringNotContainsString( 'flx_secret_access', (string) wp_json_encode( $result ) );
		$this->assertSame( '', TokenManager::connection()['access_token'] );
		$this->assertSame( 'expired', TokenManager::connection()['status'] );
	}

	/**
	 * An invalid form ID never leaves WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_invalid_form_id_is_rejected_locally(): void {
		$this->seed_connection();
		$result = EmbedAuthorization::approve( 'not a form' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * Editors reach the route, and the route does not accept an origin argument.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_route_uses_the_editor_permission(): void {
		EmbedOriginEndpoint::register();
		$this->assertSame( 'formlyx/v1', $GLOBALS['formlyx_test_routes'][0][0] );
		$this->assertSame( '/forms/(?P<id>[A-Za-z0-9_-]+)/embed-site', $GLOBALS['formlyx_test_routes'][0][1] );
		$methods = $GLOBALS['formlyx_test_routes'][0][2];
		$this->assertSame( 'GET', $methods[0]['methods'] );
		$this->assertSame( 'POST', $methods[1]['methods'] );
		$this->assertArrayNotHasKey( 'origin', $methods[0] );
		$this->assertArrayNotHasKey( 'origin', $methods[1] );
	}

	/**
	 * Seed a current access token.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scope Granted scopes.
	 * @return void
	 */
	private function seed_connection( string $scope = 'forms:read forms:embed' ): void {
		Storage::write(
			Storage::OPTION_CONNECTION,
			array(
				'access_token'       => 'flx_secret_access',
				'refresh_token'      => 'flx_secret_refresh',
				'access_expires_at'  => time() + 3600,
				'refresh_expires_at' => time() + 2592000,
				'scope'              => $scope,
				'status'             => 'connected',
			)
		);
	}

	/**
	 * Queue one successful decision.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Decision.
	 * @return void
	 */
	private function queue_decision( array $data ): void {
		$GLOBALS['formlyx_http_queue'][] = array(
			'code' => 200,
			'body' => (string) wp_json_encode(
				array(
					'success' => true,
					'data'    => $data,
				)
			),
		);
	}
}
