<?php
/**
 * Server-side check and approval of this site's embed origin.
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
 * Talks to Formlyx with the stored bearer token.
 *
 * The origin always comes from the WordPress site address. Responses contain
 * a state and a message, never the token or the form's other allowed domains.
 *
 * @since 1.0.0
 */
final class EmbedAuthorization {

	/**
	 * Check whether this site may embed the form.
	 *
	 * @since 1.0.0
	 *
	 * @param string $form_id Formlyx form ID.
	 * @return array{state: string, message: string, authorized: bool}|\WP_Error
	 */
	public static function status( string $form_id ) {
		if ( ! Renderer::is_form_id( $form_id ) ) {
			return self::invalid_form();
		}
		return self::call( 'GET', $form_id );
	}

	/**
	 * Add this site to the form when the connection is allowed to.
	 *
	 * @since 1.0.0
	 *
	 * @param string $form_id Formlyx form ID.
	 * @return array{state: string, message: string, authorized: bool}|\WP_Error
	 */
	public static function approve( string $form_id ) {
		if ( ! Renderer::is_form_id( $form_id ) ) {
			return self::invalid_form();
		}

		$site = SiteOrigin::describe();
		if ( '' !== $site['notice'] ) {
			return self::site_notice( $site['notice'] );
		}

		$access = TokenManager::access_token();
		if ( ! $access['ok'] ) {
			return self::connection_notice( $access['notice'] );
		}

		if ( ! TokenManager::has_scope( Api::EMBED_SCOPE ) ) {
			return self::result(
				'reconnect',
				false,
				__( 'Reconnect Formlyx to allow this site to embed forms.', 'formlyx' )
			);
		}

		return self::call( 'POST', $form_id );
	}

	/**
	 * Perform the Formlyx request for a derived origin.
	 *
	 * @since 1.0.0
	 *
	 * @param string $method  GET or POST.
	 * @param string $form_id Form ID.
	 * @return array{state: string, message: string, authorized: bool}
	 */
	private static function call( string $method, string $form_id ): array {
		$site = SiteOrigin::describe();
		if ( '' !== $site['notice'] ) {
			return self::site_notice( $site['notice'] );
		}

		$access = TokenManager::access_token();
		if ( ! $access['ok'] ) {
			return self::connection_notice( $access['notice'] );
		}

		$url           = Api::embed_origin_url( $form_id );
		$authorization = 'Bearer ' . $access['token'];
		if ( 'POST' === $method ) {
			$response = Http::post_json(
				$url,
				array(
					'origin' => $site['origin'],
				),
				$authorization
			);
		} else {
			$response = Http::get_json( $url . '?origin=' . rawurlencode( $site['origin'] ), $authorization );
		}

		if ( ! $response['ok'] ) {
			return self::from_error( $response['error_code'] );
		}

		$data = array();
		if ( isset( $response['json']['data'] ) && is_array( $response['json']['data'] ) ) {
			$data = $response['json']['data'];
		}
		return self::from_decision( $data );
	}

	/**
	 * Map a successful decision onto editor copy.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Decision fields.
	 * @return array{state: string, message: string, authorized: bool}
	 */
	private static function from_decision( array $data ): array {
		if ( empty( $data['publicEmbedEnabled'] ) ) {
			return self::result(
				'embed_disabled',
				false,
				__( 'Public embed is turned off for this form in Formlyx.', 'formlyx' )
			);
		}

		$authorized = ! empty( $data['authorized'] );
		$published  = isset( $data['status'] ) && 'published' === $data['status'];
		if ( $authorized && ! empty( $data['allowAnyOrigin'] ) ) {
			return self::result(
				'authorized',
				true,
				__( 'This form can be embedded on any site.', 'formlyx' )
			);
		}

		if ( $authorized && ! empty( $data['added'] ) ) {
			$message = __( 'This site can now embed this form. Its other allowed domains were kept, and embedding is still limited to those domains.', 'formlyx' );
			if ( ! $published ) {
				$message .= ' ' . __( 'Visitors can load it after the form is published.', 'formlyx' );
			}
			return self::result( 'authorized', true, $message );
		}

		if ( $authorized ) {
			$message = __( 'This site can already embed this form.', 'formlyx' );
			if ( ! $published ) {
				$message .= ' ' . __( 'Visitors can load it after the form is published.', 'formlyx' );
			}
			return self::result( 'authorized', true, $message );
		}

		if ( ! empty( $data['canApprove'] ) ) {
			return self::result(
				'approval_required',
				false,
				__( 'This form only allows specific sites. This WordPress site is not one of them yet.', 'formlyx' )
			);
		}

		return self::result(
			'reconnect',
			false,
			__( 'Reconnect Formlyx to allow this site to embed forms.', 'formlyx' )
		);
	}

	/**
	 * Map a Formlyx error code onto editor copy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $error_code Error code.
	 * @return array{state: string, message: string, authorized: bool}
	 */
	private static function from_error( string $error_code ): array {
		if ( in_array( $error_code, array( 'CREDENTIAL_REVOKED', 'CREDENTIAL_EXPIRED', 'INVALID_GRANT', 'INVALID_CREDENTIAL', 'INVALID_CLIENT' ), true ) ) {
			TokenManager::mark_expired();
			return self::result(
				'reconnect',
				false,
				__( 'The Formlyx connection has expired. Reconnect from Settings → Formlyx.', 'formlyx' )
			);
		}
		if ( 'INSUFFICIENT_SCOPE' === $error_code ) {
			return self::result(
				'reconnect',
				false,
				__( 'Reconnect Formlyx to allow this site to embed forms.', 'formlyx' )
			);
		}
		if ( 'LOCAL_ORIGIN' === $error_code ) {
			return self::site_notice( 'local_site' );
		}
		if ( 'ORIGIN_MISMATCH' === $error_code ) {
			return self::result(
				'mismatch',
				false,
				__( 'This site address does not match the WordPress address used to connect Formlyx.', 'formlyx' )
			);
		}
		if ( 'EMBED_DISABLED' === $error_code ) {
			return self::result(
				'embed_disabled',
				false,
				__( 'Public embed is turned off for this form in Formlyx.', 'formlyx' )
			);
		}
		if ( 'FORM_NOT_FOUND' === $error_code ) {
			return self::result(
				'not_found',
				false,
				__( 'Formlyx could not find that form on the connected account.', 'formlyx' )
			);
		}

		return self::result(
			'unavailable',
			false,
			__( 'Formlyx could not check embed permission for this site.', 'formlyx' )
		);
	}

	/**
	 * Copy for a site address that cannot be approved.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notice Site notice.
	 * @return array{state: string, message: string, authorized: bool}
	 */
	private static function site_notice( string $notice ): array {
		if ( 'local_site' === $notice ) {
			return self::result(
				'local_site',
				false,
				__( 'This WordPress site uses a local address, so Formlyx will not add it to the form.', 'formlyx' )
			);
		}

		return self::result(
			'unavailable',
			false,
			__( 'Formlyx could not determine this site\'s public address.', 'formlyx' )
		);
	}

	/**
	 * Copy for a connection that cannot call Formlyx.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notice Connection notice.
	 * @return array{state: string, message: string, authorized: bool}
	 */
	private static function connection_notice( string $notice ): array {
		if ( 'disconnected' === $notice ) {
			return self::result(
				'reconnect',
				false,
				__( 'Connect Formlyx to allow this site to embed forms.', 'formlyx' )
			);
		}

		return self::result(
			'reconnect',
			false,
			__( 'The Formlyx connection has expired. Reconnect from Settings → Formlyx.', 'formlyx' )
		);
	}

	/**
	 * Editor payload. Only these three fields leave the server.
	 *
	 * @since 1.0.0
	 *
	 * @param string $state      Editor state.
	 * @param bool   $authorized Whether the site may embed the form.
	 * @param string $message    Message for the editor.
	 * @return array{state: string, message: string, authorized: bool}
	 */
	private static function result( string $state, bool $authorized, string $message ): array {
		return array(
			'state'      => $state,
			'message'    => $message,
			'authorized' => $authorized,
		);
	}

	/**
	 * Reject a form ID before any Formlyx request.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error
	 */
	private static function invalid_form(): \WP_Error {
		return new \WP_Error(
			'formlyx_invalid_form',
			__( 'Use the form ID from Formlyx. It can contain letters, numbers, hyphens, and underscores.', 'formlyx' ),
			array( 'status' => 400 )
		);
	}
}
