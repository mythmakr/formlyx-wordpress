<?php
/**
 * Cached list of forms that can be embedded.
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
 * Loads published, publicly embeddable forms for the editor.
 *
 * The Formlyx list includes every form and its private configuration. This
 * class keeps only the fields the block picker needs.
 *
 * @since 1.0.0
 */
final class FormCatalog {

	/**
	 * How long a successful list is reused.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const TTL = 300;

	/**
	 * Largest accepted form-list body. The list includes fields the editor
	 * discards, so it is larger than an OAuth response.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const RESPONSE_LIMIT = 1048576;

	/**
	 * Most forms returned to the editor from one account.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const MAX_FORMS = 100;

	/**
	 * Longest form name stored for the picker.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const NAME_LIMIT = 200;

	/**
	 * Longest form description stored for the picker.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const DESCRIPTION_LIMIT = 300;

	/**
	 * Drop the cached list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function forget(): void {
		delete_transient( Storage::TRANSIENT_FORMS );
	}

	/**
	 * Return embeddable forms for the connected account.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $refresh Bypass the transient.
	 * @return array{ok: bool, forms: array<int, array{id: string, name: string, description: string}>, notice: string}
	 */
	public static function list( bool $refresh = false ): array {
		$access = TokenManager::access_token();
		if ( ! $access['ok'] ) {
			return self::failure( $access['notice'] );
		}

		if ( ! $refresh ) {
			$cached = get_transient( Storage::TRANSIENT_FORMS );
			if ( is_array( $cached ) ) {
				return array(
					'ok'     => true,
					'forms'  => self::embeddable( $cached ),
					'notice' => '',
				);
			}
		}

		$response = Http::get_json( Api::forms_url(), 'Bearer ' . $access['token'], self::RESPONSE_LIMIT );
		if ( ! $response['ok'] ) {
			$notice = TokenManager::failure_notice( $response['error_code'] );
			if ( 'expired' === $notice ) {
				TokenManager::mark_expired();
			} elseif ( 'unreachable' === $notice ) {
				TokenManager::mark_unreachable();
			}
			return self::failure( $notice );
		}

		$data = isset( $response['json']['data'] ) ? $response['json']['data'] : null;
		if ( ! is_array( $data ) ) {
			return self::failure( 'connect_failed' );
		}

		$forms = self::embeddable( $data );
		set_transient( Storage::TRANSIENT_FORMS, $forms, self::TTL );

		return array(
			'ok'     => true,
			'forms'  => $forms,
			'notice' => '',
		);
	}

	/**
	 * Keep published forms that Formlyx will render publicly.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $forms Form records or previously reduced rows.
	 * @return array<int, array{id: string, name: string, description: string}>
	 */
	public static function embeddable( array $forms ): array {
		$selected = array();
		foreach ( $forms as $form ) {
			if ( count( $selected ) >= self::MAX_FORMS ) {
				break;
			}
			if ( ! is_array( $form ) ) {
				continue;
			}
			$row = self::row( $form );
			if ( null === $row ) {
				continue;
			}
			$selected[] = $row;
		}
		return $selected;
	}

	/**
	 * Reduce one API record to editor fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $form API form.
	 * @return array{id: string, name: string, description: string}|null
	 */
	private static function row( array $form ): ?array {
		if ( array_key_exists( 'status', $form ) || array_key_exists( 'publicEmbedEnabled', $form ) ) {
			$status = isset( $form['status'] ) ? $form['status'] : '';
			$public = isset( $form['publicEmbedEnabled'] ) ? $form['publicEmbedEnabled'] : false;
			if ( 'published' !== $status || true !== $public ) {
				return null;
			}
		}

		$id = isset( $form['id'] ) && is_string( $form['id'] ) ? $form['id'] : '';
		if ( ! Renderer::is_form_id( $id ) ) {
			return null;
		}

		$name = '';
		if ( isset( $form['displayTitle'] ) && is_string( $form['displayTitle'] ) ) {
			$name = sanitize_text_field( $form['displayTitle'] );
		}
		if ( '' === $name && isset( $form['name'] ) && is_string( $form['name'] ) ) {
			$name = sanitize_text_field( $form['name'] );
		}
		if ( '' === $name ) {
			$name = $id;
		}
		$name = self::limit_text( $name, self::NAME_LIMIT );

		$description = '';
		if ( isset( $form['description'] ) && is_string( $form['description'] ) ) {
			$description = self::limit_text( sanitize_text_field( $form['description'] ), self::DESCRIPTION_LIMIT );
		}

		return array(
			'id'          => $id,
			'name'        => $name,
			'description' => $description,
		);
	}

	/**
	 * Limit stored picker text by characters.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Text.
	 * @param int    $limit Maximum characters.
	 * @return string
	 */
	private static function limit_text( string $value, int $limit ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}
		return substr( $value, 0, $limit );
	}

	/**
	 * Failed list result.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notice Notice code.
	 * @return array{ok: bool, forms: array<int, array{id: string, name: string, description: string}>, notice: string}
	 */
	private static function failure( string $notice ): array {
		return array(
			'ok'     => false,
			'forms'  => array(),
			'notice' => '' === $notice ? 'connect_failed' : $notice,
		);
	}
}
