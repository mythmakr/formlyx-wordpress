<?php
/**
 * Shared Formlyx embed markup.
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
 * Renders the hosted embed used by the block and the shortcode.
 *
 * @since 1.0.0
 */
final class Renderer {

	/**
	 * Appearance values stored for a block or shortcode.
	 *
	 * `auto`, `light`, `dark`, `inherit`, and `unstyled` are values embed.js
	 * understands. `match` is Match WordPress Theme. embed.js treats that
	 * unrecognized value as `auto`, so saved Formlyx tokens remain the
	 * fallback while this plugin's CSS variables override them. `formlyx` is
	 * an alias the script treats as `auto`. Anything else is stored as `auto`.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	public const THEMES = array( 'auto', 'light', 'dark', 'inherit', 'unstyled', 'match' );

	/**
	 * Whether a string can be placed in data-form-id.
	 *
	 * Formlyx form ids are CUIDs. The check matches the character set the
	 * hosted snippet allows, with a length cap, and does not require one
	 * CUID version.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Form id.
	 * @return bool
	 */
	public static function is_form_id( string $id ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9_-]{1,128}$/', $id );
	}

	/**
	 * Map a theme attribute to a stored appearance value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $theme Requested theme.
	 * @return string
	 */
	public static function theme( string $theme ): string {
		$theme = strtolower( trim( $theme ) );
		if ( 'formlyx' === $theme ) {
			return 'auto';
		}
		if ( in_array( $theme, self::THEMES, true ) ) {
			return $theme;
		}
		return 'auto';
	}

	/**
	 * Render a dynamic block.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ): string {
		if ( ! is_array( $attributes ) ) {
			return '';
		}
		$id    = isset( $attributes['formId'] ) && is_string( $attributes['formId'] ) ? $attributes['formId'] : '';
		$theme = isset( $attributes['theme'] ) && is_string( $attributes['theme'] ) ? $attributes['theme'] : 'auto';
		return self::markup( $id, $theme );
	}

	/**
	 * Render the formlyx shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_shortcode( $atts ): string {
		$atts  = shortcode_atts(
			array(
				'id'    => '',
				'theme' => 'auto',
			),
			is_array( $atts ) ? $atts : array(),
			'formlyx'
		);
		$id    = isset( $atts['id'] ) ? (string) $atts['id'] : '';
		$theme = isset( $atts['theme'] ) ? (string) $atts['theme'] : 'auto';
		return self::markup( $id, $theme );
	}

	/**
	 * Build one embed container and enqueue the hosted script.
	 *
	 * A missing or rejected id produces no markup and no script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id    Form id.
	 * @param string $theme Theme attribute.
	 * @return string
	 */
	public static function markup( string $id, string $theme ): string {
		if ( ! self::is_form_id( $id ) ) {
			return '';
		}

		$theme = self::theme( $theme );
		Embed::enqueue( $theme );

		return sprintf(
			'<div class="formlyx-embed" data-form-id="%1$s" data-theme="%2$s"></div>',
			esc_attr( $id ),
			esc_attr( $theme )
		);
	}
}
