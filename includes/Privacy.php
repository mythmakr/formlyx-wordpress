<?php
/**
 * Suggested site privacy policy text.
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
 * Tells site owners what the embed sends to Formlyx.
 *
 * @since 1.0.0
 */
final class Privacy {

	/**
	 * Add the suggested policy on the WordPress privacy screen.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$text  = __( 'When an administrator connects this site to Formlyx, WordPress stores an access token and a refresh token so the site can list forms. Those tokens are not shown to visitors.', 'formlyx' );
		$text .= "\n\n";
		$text .= __( 'When a visitor opens a page that contains a Formlyx form, their browser loads the script at https://www.formlyx.com/embed.js. Submitting the form sends the information they enter to Formlyx. Formlyx explains how it handles that information at https://www.formlyx.com/privacy.', 'formlyx' );

		wp_add_privacy_policy_content(
			'Formlyx Forms',
			wp_kses_post( wpautop( $text ) )
		);
	}
}
