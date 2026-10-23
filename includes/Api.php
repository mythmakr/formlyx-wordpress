<?php
/**
 * Formlyx production endpoints.
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
 * Fixed Formlyx URLs. Administrators cannot point the plugin at another host.
 *
 * @since 1.0.0
 */
final class Api {

	/**
	 * API origin and OAuth issuer.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const BASE = 'https://api.formlyx.com';

	/**
	 * REST resource audience. This is not the MCP resource.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const RESOURCE = 'https://api.formlyx.com/v1';

	/**
	 * Scope required to read the workspace and forms.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const SCOPE = 'forms:read';

	/**
	 * Scope that may add this site to one form's allowed origins.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const EMBED_SCOPE = 'forms:embed';

	/**
	 * Scopes requested when an administrator connects the site.
	 *
	 * Existing connections that only granted forms:read keep working for the
	 * form list. Approving an origin requires a reconnect that includes
	 * forms:embed.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function requested_scope(): string {
		return self::SCOPE . ' ' . self::EMBED_SCOPE;
	}

	/**
	 * Public account signup that continues an authorization request.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function signup_url(): string {
		return self::BASE . '/oauth/signup';
	}

	/**
	 * Authorization endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function authorize_url(): string {
		return self::BASE . '/oauth/authorize';
	}

	/**
	 * Dynamic client registration endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function register_url(): string {
		return self::BASE . '/oauth/register';
	}

	/**
	 * Token endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function token_url(): string {
		return self::BASE . '/oauth/token';
	}

	/**
	 * Revocation endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function revoke_url(): string {
		return self::BASE . '/oauth/revoke';
	}

	/**
	 * Authenticated workspace endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function workspace_url(): string {
		return self::RESOURCE . '/workspace';
	}

	/**
	 * Authenticated form list.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function forms_url(): string {
		return self::RESOURCE . '/forms';
	}

	/**
	 * Check or approve this site for one form.
	 *
	 * @since 1.0.0
	 *
	 * @param string $form_id Formlyx form ID.
	 * @return string
	 */
	public static function embed_origin_url( string $form_id ): string {
		return self::RESOURCE . '/forms/' . rawurlencode( $form_id ) . '/embed-origin';
	}

	/**
	 * Allow redirects only to the Formlyx API host.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $hosts Allowed hosts.
	 * @return string[]
	 */
	public static function allowed_hosts( array $hosts ): array {
		$hosts[] = 'api.formlyx.com';
		return $hosts;
	}
}
