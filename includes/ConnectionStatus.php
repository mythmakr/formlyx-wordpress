<?php
/**
 * Connection check against the Formlyx workspace.
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
 * Reads GET /v1/workspace and caches the non-sensitive identity.
 *
 * @since 1.0.0
 */
final class ConnectionStatus {

	/**
	 * Validate the saved connection.
	 *
	 * Network failures keep the stored tokens.
	 *
	 * @since 1.0.0
	 *
	 * @return string Notice code: connected, unreachable, expired, disconnected, refresh_busy, or connect_failed.
	 */
	public static function check(): string {
		$access = TokenManager::access_token();
		if ( ! $access['ok'] ) {
			return $access['notice'];
		}

		$response = Http::get_json( Api::workspace_url(), 'Bearer ' . $access['token'] );
		if ( 'network' === $response['error_code'] ) {
			TokenManager::mark_unreachable();
			return 'unreachable';
		}

		if ( ! $response['ok'] ) {
			$notice = TokenManager::failure_notice( $response['error_code'] );
			if ( 'expired' === $notice ) {
				TokenManager::mark_expired();
			}
			return $notice;
		}

		if ( empty( $response['json']['success'] ) || ! isset( $response['json']['data'] ) || ! is_array( $response['json']['data'] ) ) {
			return 'connect_failed';
		}

		$data                          = $response['json']['data'];
		$connection                    = TokenManager::connection();
		$connection['workspace_id']    = isset( $data['id'] ) && is_string( $data['id'] ) ? sanitize_text_field( $data['id'] ) : '';
		$connection['workspace_name']  = isset( $data['name'] ) && is_string( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$connection['workspace_email'] = isset( $data['email'] ) && is_string( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$connection['checked_at']      = time();
		$connection['status']          = 'connected';
		Storage::write( Storage::OPTION_CONNECTION, $connection );

		return 'connected';
	}
}
