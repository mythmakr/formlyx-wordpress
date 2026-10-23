<?php
/**
 * Plugin bootstrap tests.
 *
 * @package Formlyx
 */

declare(strict_types=1);

namespace Formlyx\Tests\Unit;

use Formlyx\Plugin;
use Formlyx\Storage;
use Formlyx\Uninstaller;
use PHPUnit\Framework\TestCase;

/**
 * Confirms the plugin file can boot and that uninstall targets only owned keys.
 *
 * @since 1.0.0
 */
final class BootstrapTest extends TestCase {

	/**
	 * Reset recorded WordPress calls.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['formlyx_test_did_actions']             = array();
		$GLOBALS['formlyx_test_deleted_options']         = array();
		$GLOBALS['formlyx_test_deleted_transients']      = array();
		$GLOBALS['formlyx_test_deleted_site_transients'] = array();
		$GLOBALS['formlyx_test_multisite']               = false;
		$GLOBALS['formlyx_test_sites']                   = array();
		$GLOBALS['formlyx_test_blog_id']                 = 1;
	}

	/**
	 * The bootstrap defines the public version and registers initialization.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_bootstrap_loads_plugin(): void {
		$this->assertSame( '1.0.0', FORMLYX_VERSION );
		$this->assertFileExists( FORMLYX_PLUGIN_FILE );
		$this->assertTrue( class_exists( Plugin::class ) );

		$registered = false;
		foreach ( $GLOBALS['formlyx_test_actions'] as $action ) {
			if ( 'plugins_loaded' === $action[0] && array( Plugin::class, 'init' ) === $action[1] ) {
				$registered = true;
			}
		}

		$this->assertTrue( $registered );
	}

	/**
	 * Initialization fires the plugin hook and nothing else.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_init_fires_loaded_hook(): void {
		Plugin::init();

		$this->assertSame( array( 'formlyx_loaded' ), $GLOBALS['formlyx_test_did_actions'] );
	}

	/**
	 * A single-site uninstall deletes only the declared options and transients.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_uninstall_deletes_owned_keys_on_single_site(): void {
		Uninstaller::uninstall();

		$options = array_map(
			static function ( array $call ): string {
				return $call[1];
			},
			$GLOBALS['formlyx_test_deleted_options']
		);

		$this->assertSame( Storage::options(), $options );
		$this->assertSame(
			array(
				array( 1, Storage::TRANSIENT_FORMS ),
				array( 1, Storage::TRANSIENT_REFRESH_LOCK ),
			),
			$GLOBALS['formlyx_test_deleted_transients']
		);
		$this->assertSame(
			$GLOBALS['formlyx_test_deleted_transients'],
			$GLOBALS['formlyx_test_deleted_site_transients']
		);
	}

	/**
	 * Network uninstall visits each site and does not assume a single blog id.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_uninstall_deletes_owned_keys_on_each_site(): void {
		$GLOBALS['formlyx_test_multisite'] = true;
		$GLOBALS['formlyx_test_sites']     = array( 4, 9 );

		Uninstaller::uninstall();

		$blogs = array_map(
			static function ( array $call ): int {
				return $call[0];
			},
			$GLOBALS['formlyx_test_deleted_options']
		);

		$expected = array();
		foreach ( array( 4, 9 ) as $blog_id ) {
			foreach ( Storage::options() as $unused ) {
				unset( $unused );
				$expected[] = $blog_id;
			}
		}

		$this->assertSame( $expected, $blogs );
		$this->assertSame( 1, $GLOBALS['formlyx_test_blog_id'] );
	}
}
