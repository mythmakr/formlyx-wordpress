<?php
/**
 * Editor REST route for the form picker.
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
 * Serves a reduced form list to users who can edit content.
 *
 * Authentication is the WordPress cookie and REST nonce. OAuth tokens are
 * not part of the response.
 *
 * @since 1.0.0
 */
final class FormsEndpoint {

	/**
	 * Register GET /formlyx/v1/forms.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		register_rest_route(
			'formlyx/v1',
			'/forms',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => array( self::class, 'permission' ),
				'args'                => array(
					'refresh' => array(
						'description' => __( 'Load a fresh form list.', 'formlyx' ),
						'type'        => 'boolean',
						'default'     => false,
					),
				),
			)
		);
	}

	/**
	 * Whether the current user can edit posts or pages.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function permission(): bool {
		return current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * REST callback.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request REST request.
	 * @return array{forms: array<int, array{id: string, name: string, description: string}>}|\WP_Error
	 */
	public static function handle( $request ) {
		$refresh = false;
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$refresh = (bool) $request->get_param( 'refresh' );
		}
		return self::response( $refresh );
	}

	/**
	 * Build the JSON body or a safe error.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $refresh Bypass the form cache.
	 * @return array{forms: array<int, array{id: string, name: string, description: string}>}|\WP_Error
	 */
	public static function response( bool $refresh ) {
		$result = FormCatalog::list( $refresh );
		if ( ! $result['ok'] ) {
			return self::error( $result['notice'] );
		}

		return array(
			'forms' => $result['forms'],
		);
	}

	/**
	 * Map a connection notice to a REST error without API details.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notice Notice code.
	 * @return \WP_Error
	 */
	private static function error( string $notice ): \WP_Error {
		if ( 'disconnected' === $notice ) {
			return new \WP_Error(
				'formlyx_disconnected',
				__( 'Connect Formlyx to select a form.', 'formlyx' ),
				array( 'status' => 409 )
			);
		}
		if ( 'expired' === $notice || 'missing_scope' === $notice ) {
			return new \WP_Error(
				'formlyx_expired',
				__( 'The Formlyx connection has expired. Reconnect from Settings → Formlyx.', 'formlyx' ),
				array( 'status' => 401 )
			);
		}
		if ( 'unreachable' === $notice || 'refresh_busy' === $notice ) {
			return new \WP_Error(
				'formlyx_unreachable',
				__( 'Formlyx could not be reached. Try again in a moment.', 'formlyx' ),
				array( 'status' => 502 )
			);
		}
		return new \WP_Error(
			'formlyx_unavailable',
			__( 'The form list could not be loaded.', 'formlyx' ),
			array( 'status' => 502 )
		);
	}
}
