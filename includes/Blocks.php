<?php
/**
 * Block and shortcode registration.
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
 * Registers the dynamic Formlyx Form block and the formlyx shortcode.
 *
 * @since 1.0.0
 */
final class Blocks {

	/**
	 * Hook registration into WordPress init.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'init', array( self::class, 'register_content' ) );
		add_action( 'rest_api_init', array( FormsEndpoint::class, 'register' ) );
		add_action( 'rest_api_init', array( EmbedOriginEndpoint::class, 'register' ) );
	}

	/**
	 * Register the block metadata and the shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_content(): void {
		$block = register_block_type(
			dirname( __DIR__ ) . '/build',
			array(
				'render_callback' => array( Renderer::class, 'render_block' ),
			)
		);
		self::translate_editor( $block );
		add_shortcode( 'formlyx', array( Renderer::class, 'render_shortcode' ) );
	}

	/**
	 * Load editor translations and the settings URL.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $block Block type returned by WordPress.
	 * @return void
	 */
	private static function translate_editor( $block ): void {
		if ( ! is_object( $block ) ) {
			return;
		}

		$handles = array();
		if ( isset( $block->editor_script_handles ) && is_array( $block->editor_script_handles ) ) {
			$handles = $block->editor_script_handles;
		} elseif ( isset( $block->editor_script ) && is_string( $block->editor_script ) && '' !== $block->editor_script ) {
			$handles = array( $block->editor_script );
		}

		$settings = wp_json_encode(
			array(
				'settingsUrl' => admin_url( 'options-general.php?page=formlyx' ),
			)
		);
		if ( ! is_string( $settings ) ) {
			$settings = '{}';
		}

		foreach ( $handles as $handle ) {
			if ( ! is_string( $handle ) || '' === $handle ) {
				continue;
			}
			wp_set_script_translations( $handle, 'formlyx', dirname( __DIR__ ) . '/languages' );
			wp_add_inline_script( $handle, 'var formlyxEditor = ' . $settings . ';', 'before' );
		}
	}
}
