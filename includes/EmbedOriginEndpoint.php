<?php
/**
 * Editor routes for this site's embed permission.
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
 * Proxies embed-origin checks for users who can edit content.
 *
 * The browser supplies the form ID only. WordPress derives the origin.
 *
 * @since 1.0.0
 */
final class EmbedOriginEndpoint {

	/**
	 * Register the status and approval routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		register_rest_route(
			'formlyx/v1',
			'/forms/(?P<id>[A-Za-z0-9_-]+)/embed-site',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'status' ),
					'permission_callback' => array( FormsEndpoint::class, 'permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'approve' ),
					'permission_callback' => array( FormsEndpoint::class, 'permission' ),
				),
			)
		);
	}

	/**
	 * Report whether this site is allowed to embed the form.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request REST request.
	 * @return array{state: string, message: string, authorized: bool}|\WP_Error
	 */
	public static function status( $request ) {
		return EmbedAuthorization::status( self::form_id( $request ) );
	}

	/**
	 * Approve this site for the form.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request REST request.
	 * @return array{state: string, message: string, authorized: bool}|\WP_Error
	 */
	public static function approve( $request ) {
		return EmbedAuthorization::approve( self::form_id( $request ) );
	}

	/**
	 * Read the route form ID and ignore every other request field.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request REST request.
	 * @return string
	 */
	private static function form_id( $request ): string {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
			return '';
		}
		$id = $request->get_param( 'id' );
		return is_string( $id ) ? $id : '';
	}
}
