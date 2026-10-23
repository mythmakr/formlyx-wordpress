<?php
/**
 * Plugin initialization.
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
 * Boots the plugin.
 *
 * Account connection, the form block, the shortcode, and embedding are
 * registered here.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Register plugin hooks.
	 *
	 * WordPress.org loads translations for the `formlyx` text domain just in
	 * time. The block editor script calls `wp_set_script_translations()` with
	 * that text domain and `FORMLYX_PLUGIN_DIR . 'languages'`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( AdminPage::class, 'register_menu' ) );
		add_action( 'admin_post_formlyx_connect', array( AdminPage::class, 'handle_connect' ) );
		add_action( 'admin_post_formlyx_disconnect', array( AdminPage::class, 'handle_disconnect' ) );
		add_action( 'admin_post_formlyx_check', array( AdminPage::class, 'handle_check' ) );
		add_action( 'admin_post_formlyx_oauth_callback', array( Authorization::class, 'handle_callback' ) );
		add_action( 'admin_init', array( Privacy::class, 'register' ) );
		add_filter( 'allowed_redirect_hosts', array( Api::class, 'allowed_hosts' ) );
		Blocks::register_hooks();

		/**
		 * Fires after Formlyx has initialized.
		 *
		 * @since 1.0.0
		 */
		do_action( 'formlyx_loaded' );
	}
}
