<?php
/**
 * Removes plugin-owned data.
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
 * Deletes Formlyx settings, credentials, and cached metadata.
 *
 * WordPress posts, pages, Formlyx-hosted forms, Formlyx submissions, and the
 * Formlyx account are left untouched.
 *
 * @since 1.0.0
 */
final class Uninstaller {

	/**
	 * Remove plugin data for the current site, or for every site on a network.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		if ( is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::delete_current_site_data();
				restore_current_blog();
			}

			return;
		}

		self::delete_current_site_data();
	}

	/**
	 * Remove plugin-owned options and transients from the current site.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function delete_current_site_data(): void {
		foreach ( Storage::options() as $option ) {
			delete_option( $option );
		}

		foreach ( Storage::transients() as $transient ) {
			delete_transient( $transient );
			delete_site_transient( $transient );
		}
	}
}
