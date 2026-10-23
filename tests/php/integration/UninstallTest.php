<?php
/**
 * WordPress integration coverage for uninstall.
 *
 * @package Formlyx
 */

declare(strict_types=1);

namespace Formlyx\Tests\Integration;

use Formlyx\Plugin;
use Formlyx\Storage;
use Formlyx\Uninstaller;
use WP_Post;
use WP_UnitTestCase;

/**
 * Runs against the WordPress test suite.
 *
 * @since 1.0.0
 */
final class UninstallTest extends WP_UnitTestCase {

	/**
	 * The plugin bootstrap is loaded inside WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_plugin_bootstrap_is_loaded(): void {
		$this->assertSame( '1.0.0', FORMLYX_VERSION );
		$this->assertNotFalse( has_action( 'plugins_loaded', array( Plugin::class, 'init' ) ) );
	}

	/**
	 * Uninstall removes owned data and leaves WordPress content in place.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_uninstall_removes_plugin_data_and_keeps_posts(): void {
		update_option( Storage::OPTION_SETTINGS, array( 'connected' => false ) );
		update_option(
			Storage::OPTION_CONNECTION,
			array(
				'access_token'  => 'access-secret',
				'refresh_token' => 'refresh-secret',
			)
		);
		set_transient( Storage::TRANSIENT_FORMS, array( 'forms' => array() ), HOUR_IN_SECONDS );
		set_transient( Storage::TRANSIENT_REFRESH_LOCK, '1', MINUTE_IN_SECONDS );

		$post_id = self::factory()->post->create(
			array(
				'post_title' => 'Page that must survive uninstall',
			)
		);

		Uninstaller::uninstall();

		$this->assertFalse( get_option( Storage::OPTION_SETTINGS ) );
		$this->assertFalse( get_option( Storage::OPTION_CONNECTION ) );
		$this->assertFalse( get_transient( Storage::TRANSIENT_FORMS ) );
		$this->assertFalse( get_transient( Storage::TRANSIENT_REFRESH_LOCK ) );

		$post = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'Page that must survive uninstall', $post->post_title );
	}
}
