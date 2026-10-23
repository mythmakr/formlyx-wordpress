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
	 * Reset the late-print flag during unit tests.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		if ( defined( 'FORMLYX_UNIT_TESTS' ) ) {
			self::$printed = false;
		}
	}

	/**
	 * Register and enqueue the embed assets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! wp_script_is( self::SCRIPT, 'registered' ) ) {
			wp_register_script( self::SCRIPT, self::SRC, array(), FORMLYX_VERSION, true );
			wp_script_add_data( self::SCRIPT, 'strategy', 'defer' );
		}
		wp_enqueue_script( self::SCRIPT );

		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, plugins_url( 'embed.css', __FILE__ ), array(), FORMLYX_VERSION );
		}
		wp_enqueue_style( self::STYLE );

		if ( self::$printed ) {
			return;
		}
		if ( did_action( 'wp_print_footer_scripts' ) || did_action( 'wp_footer' ) ) {
			wp_print_styles( self::STYLE );
			wp_print_scripts( self::SCRIPT );
			self::$printed = true;
		}
	}
}
