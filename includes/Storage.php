<?php
/**
 * Names of plugin-owned options and transients.
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
 * Storage keys owned by Formlyx.
 *
 * Later phases persist OAuth credentials and cached form metadata under these
 * keys. No custom database tables are used.
 *
 * @since 1.0.0
 */
final class Storage {

	/**
	 * Plugin settings option.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_SETTINGS = 'formlyx_settings';

	/**
	 * OAuth connection option, including access and refresh tokens.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_CONNECTION = 'formlyx_connection';

	/**
	 * Transient holding cached hosted-form metadata.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const TRANSIENT_FORMS = 'formlyx_forms';

	/**
	 * Transient used as a lock so only one request refreshes tokens.
	 *
	 * Refresh locking uses an option. This transient name is still removed so
	 * older installs do not keep a leftover value.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const TRANSIENT_REFRESH_LOCK = 'formlyx_refresh_lock';

	/**
	 * Registered public OAuth client. Contains no client secret.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_CLIENT = 'formlyx_oauth_client';

	/**
	 * In-progress authorization attempt. Holds the PKCE verifier server-side.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_PENDING = 'formlyx_oauth_pending';

	/**
	 * One-time lock that marks an authorization attempt as used.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_PENDING_LOCK = 'formlyx_oauth_pending_lock';

	/**
	 * Atomic refresh lock. add_option() fails when another request holds it.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_REFRESH_LOCK = 'formlyx_refresh_lock';

	/**
	 * Option names removed on uninstall.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function options(): array {
		return array(
			self::OPTION_SETTINGS,
			self::OPTION_CONNECTION,
			self::OPTION_CLIENT,
			self::OPTION_PENDING,
			self::OPTION_PENDING_LOCK,
			self::OPTION_REFRESH_LOCK,
		);
	}

	/**
	 * Transient names removed on uninstall.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function transients(): array {
		return array(
			self::TRANSIENT_FORMS,
			self::TRANSIENT_REFRESH_LOCK,
		);
	}

	/**
	 * Read an array option. Missing values are empty arrays.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Option name.
	 * @return array<string, mixed>
	 */
	public static function read( string $key ): array {
		$stored = get_option( $key, array() );
		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Write an option with autoload disabled.
	 *
	 * WordPress options are not encrypted. Callers must keep secrets out of
	 * pages, scripts, and logs.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $key   Option name.
	 * @param array<string, mixed> $value Option value.
	 * @return void
	 */
	public static function write( string $key, array $value ): void {
		if ( false === get_option( $key, false ) ) {
			add_option( $key, $value, '', false );
			return;
		}

		update_option( $key, $value, false );
	}

	/**
	 * Try to create a lock option. Fails when the option already exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $key   Option name.
	 * @param array<string, mixed> $value Lock payload.
	 * @return bool
	 */
	public static function try_lock( string $key, array $value ): bool {
		return add_option( $key, $value, '', false );
	}
}
