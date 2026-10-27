<?php
/**
 * Official Formlyx embed script.
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
 * Loads embed.js once, including when a form renders after the footer queue.
 *
 * @since 1.0.0
 */
final class Embed {

	/**
	 * Script handle.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const SCRIPT = 'formlyx-embed';

	/**
	 * Style handle.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const STYLE = 'formlyx-embed';

	/**
	 * Hosted embed script. The plugin does not replace this file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const SRC = 'https://www.formlyx.com/embed.js';

	/**
	 * Whether this request already printed the script after the footer ran.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $printed = false;

	/**
	 * Whether Match WordPress Theme assets were printed after the footer.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $adapter_printed = false;

	/**
	 * Reset the late-print flag during unit tests.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		if ( defined( 'FORMLYX_UNIT_TESTS' ) ) {
			self::$printed         = false;
			self::$adapter_printed = false;
		}
	}

	/**
	 * Register and enqueue the embed assets.
	 *
	 * Match WordPress Theme also publishes this plugin's Theme Adapter copy.
	 * The hosted script still renders the fields. Saved Formlyx tokens stay
	 * the fallback inside that script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $theme Stored appearance value.
	 * @return void
	 */
	public static function enqueue( string $theme = 'auto' ): void {
		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script( self::SCRIPT, self::SRC, array(), FORMLYX_VERSION, true );
			wp_script_add_data( self::SCRIPT, 'strategy', 'defer' );
		}
		wp_enqueue_script( self::SCRIPT );

		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, plugins_url( 'embed.css', __FILE__ ), array(), FORMLYX_VERSION );
		}
		wp_enqueue_style( self::STYLE );

		$adapter_handle = '';
		if ( 'match' === $theme ) {
			$adapter_handle = ThemeAdapter::enqueue();
		}

		$late = did_action( 'wp_print_footer_scripts' ) || did_action( 'wp_footer' );
		if ( ! $late ) {
			return;
		}
		if ( '' !== $adapter_handle && ! self::$adapter_printed ) {
			wp_print_styles( $adapter_handle );
			if ( wp_script_is( $adapter_handle, 'registered' ) ) {
				wp_print_scripts( $adapter_handle );
			}
			self::$adapter_printed = true;
		}
		if ( self::$printed ) {
			return;
		}
		wp_print_styles( self::STYLE );
		wp_print_scripts( self::SCRIPT );
		self::$printed = true;
	}
}
