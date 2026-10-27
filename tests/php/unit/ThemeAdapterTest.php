<?php
/**
 * Theme Adapter bundle and Match WordPress Theme rendering.
 *
 * @package Formlyx
 */

declare(strict_types=1);

namespace Formlyx\Tests\Unit;

use Formlyx\Embed;
use Formlyx\Renderer;
use Formlyx\ThemeAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Confirms the bundled adapter and the hosted-form appearance bridge.
 *
 * @since 1.0.0
 */
final class ThemeAdapterTest extends TestCase {

	/**
	 * Reset recorded WordPress calls.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['formlyx_test_scripts']         = array();
		$GLOBALS['formlyx_test_styles']          = array();
		$GLOBALS['formlyx_test_script_enqueues'] = array();
		$GLOBALS['formlyx_test_script_data']     = array();
		$GLOBALS['formlyx_test_printed_scripts'] = array();
		$GLOBALS['formlyx_test_printed_styles']  = array();
		$GLOBALS['formlyx_test_did_action']      = array();
		$GLOBALS['formlyx_test_inline_scripts']  = array();
		Embed::reset();
	}

	/**
	 * The bundle records the published Theme Adapter release.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_bundle_records_the_published_release(): void {
		$root = dirname( __DIR__, 3 );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundle metadata, not a remote request.
		$record = json_decode( (string) file_get_contents( $root . '/vendor/theme-adapter-source.json' ), true );
		$this->assertIsArray( $record );
		$this->assertSame( 'razorsoft/wordpress-theme-adapter', $record['package'] );
		$this->assertSame( '0.1.0', $record['version'] );
		$this->assertSame( 'v0.1.0', $record['tag'] );
		$this->assertSame( '02a1354e42a53c23ae0f3e2eb0c1e088f8619577', $record['commit'] );
		$this->assertSame( 'https://github.com/razorsoftinc/theme-adapter', $record['repository'] );
		$this->assertSame( 'packages/wordpress/bin/bundle.php', $record['bundler'] );
		$this->assertFileExists( $root . '/vendor/theme-adapter/load.php' );
		$this->assertFileExists( $root . '/vendor/theme-adapter/assets/detect.js' );
		$this->assertFileDoesNotExist( $root . '/vendor/theme-adapter/tests/run.php' );
	}

	/**
	 * Block and classic snapshots become the CSS variables the embed reads.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_tokens_cover_block_and_classic_themes(): void {
		$runtime = ThemeAdapter::runtime();
		$this->assertIsObject( $runtime );
		$this->assertTrue( method_exists( $runtime, 'version' ) );
		$this->assertSame( '0.1.0', $runtime->version() );
		$this->assertTrue( function_exists( 'razor_theme_tokens' ) );
		$this->assertTrue( function_exists( 'razor_theme_css' ) );
		$this->assertTrue( function_exists( 'razor_theme_enqueue' ) );

		$block = $runtime->tokens( $this->block_snapshot() );
		$this->assertIsArray( $block );
		$this->assertSame( 'block', $block['kind'] );
		$this->assertSame( 'var(--wp--preset--color--accent-1)', $block['tokens']['color-primary']['value'] );
		$this->assertSame( '#111111', $block['tokens']['color-text']['value'] );
		$block_css = $runtime->css( $block );
		$this->assertIsString( $block_css );
		$this->assertStringContainsString( '--razor-theme-color-primary: var(--wp--preset--color--accent-1);', $block_css );
		$this->assertStringContainsString( '--razor-theme-font-family: var(--wp--preset--font-family--body);', $block_css );
		$this->assertStringContainsString( '--razor-theme-button-background: var(--wp--preset--color--accent-1);', $block_css );
		$this->assertStringNotContainsString( '</style', $block_css );

		$classic = $runtime->tokens( $this->classic_snapshot() );
		$this->assertIsArray( $classic );
		$this->assertSame( 'classic', $classic['kind'] );
		$this->assertSame( '#c8102e', $classic['tokens']['color-primary']['value'] );
		$this->assertSame( '#f7f7f7', $classic['tokens']['color-background']['value'] );
		$this->assertSame( '#222222', $classic['tokens']['color-text']['value'] );
		$classic_css = $runtime->css( $classic );
		$this->assertIsString( $classic_css );
		$this->assertStringContainsString( '--razor-theme-color-primary: #c8102e;', $classic_css );
		$this->assertStringContainsString( '--razor-theme-color-background: #f7f7f7;', $classic_css );
	}

	/**
	 * Match mode scopes host variables to the form and leaves other modes alone.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_match_mode_styles_the_hosted_form(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local stylesheet, not a remote request.
		$css = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/embed.css' );
		$this->assertStringContainsString( '.formlyx-embed[data-theme="match"]', $css );
		$this->assertStringContainsString( '--formlyx-primary: var(--razor-theme-color-primary);', $css );
		$this->assertStringContainsString( '--formlyx-button-bg: var(--razor-theme-button-background);', $css );
		$this->assertStringContainsString( '--formlyx-button-text: var(--razor-theme-button-color);', $css );
		$this->assertStringNotContainsString( '--formlyx-error', $css );
		$this->assertStringNotContainsString( '#b91c1c', $css );
		$this->assertStringNotContainsString( '#0f766e', $css );

		$matched = Renderer::render_shortcode(
			array(
				'id'    => 'clh0contact00000000000001',
				'theme' => 'match',
			)
		);
		$this->assertSame(
			'<div class="formlyx-embed" data-form-id="clh0contact00000000000001" data-theme="match"></div>',
			$matched
		);
		$this->assertArrayHasKey( 'razor-theme-adapter', $GLOBALS['formlyx_test_styles'] );
		$this->assertNotEmpty( $GLOBALS['formlyx_test_styles']['razor-theme-adapter']['inline'] );
		$this->assertStringContainsString( '--razor-theme-color-primary:', $GLOBALS['formlyx_test_styles']['razor-theme-adapter']['inline'][0] );
		$this->assertSame( Embed::SRC, $GLOBALS['formlyx_test_scripts'][ Embed::SCRIPT ]['src'] );

		$GLOBALS['formlyx_test_styles'] = array();
		$light                          = Renderer::render_block(
			array(
				'formId' => 'clh0contact00000000000001',
				'theme'  => 'light',
			)
		);
		$this->assertStringContainsString( 'data-theme="light"', $light );
		$this->assertArrayNotHasKey( 'razor-theme-adapter', $GLOBALS['formlyx_test_styles'] );
	}

	/**
	 * A match form rendered after the footer still prints the adapter style.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_match_mode_prints_after_the_footer(): void {
		$GLOBALS['formlyx_test_did_action']['wp_footer'] = 1;
		Renderer::markup( 'clh0contact00000000000001', 'match' );
		$this->assertContains( 'razor-theme-adapter', $GLOBALS['formlyx_test_printed_styles'] );
		$this->assertContains( Embed::STYLE, $GLOBALS['formlyx_test_printed_styles'] );
		$this->assertContains( Embed::SCRIPT, $GLOBALS['formlyx_test_printed_scripts'] );
	}

	/**
	 * Another plugin can load its own copy of the same adapter version.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_coexistence_with_another_bundle(): void {
		$script = dirname( __DIR__, 3 ) . '/bin/check-theme-adapter-coexistence.php';
		$output = array();
		$code   = 1;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Separate process so a second bundle does not stay loaded.
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ), $output, $code );
		$this->assertSame( 0, $code, implode( "\n", $output ) );
	}

	/**
	 * Block theme snapshot shaped like the adapter's reader output.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function block_snapshot(): array {
		return array(
			'stylesheet'        => 'twentytwentyfour',
			'name'              => 'Twenty Twenty-Four',
			'version'           => '1.3',
			'block'             => true,
			'theme_json'        => true,
			'use_global_styles' => true,
			'palette'           => array(
				'base'     => array(
					'value'  => '#ffffff',
					'origin' => 'theme',
				),
				'contrast' => array(
					'value'  => '#111111',
					'origin' => 'theme',
				),
				'accent-1' => array(
					'value'  => '#9b51e0',
					'origin' => 'theme',
				),
			),
			'color'             => array(
				'background' => 'var(--wp--preset--color--base)',
				'text'       => '#111111',
			),
			'typography'        => array(
				'fontFamily' => 'var(--wp--preset--font-family--body)',
				'fontSize'   => '1.125rem',
				'fontWeight' => '400',
				'lineHeight' => '1.6',
			),
			'border'            => array(
				'radius' => '6px',
				'width'  => '1px',
				'color'  => '#e0e0e0',
				'style'  => 'solid',
			),
			'button'            => array(
				'background' => 'var(--wp--preset--color--accent-1)',
				'color'      => '#ffffff',
				'radius'     => '4px',
			),
		);
	}

	/**
	 * Classic theme snapshot shaped like the adapter's reader output.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function classic_snapshot(): array {
		return array(
			'stylesheet'         => 'twentytwenty',
			'name'               => 'Twenty Twenty',
			'version'            => '2.0',
			'block'              => false,
			'theme_json'         => false,
			'use_global_styles'  => false,
			'classic_palette'    => array(
				'primary'   => array(
					'value'  => '#c8102e',
					'origin' => 'classic',
				),
				'secondary' => array(
					'value'  => '#1a1a1a',
					'origin' => 'classic',
				),
			),
			'classic_background' => 'f7f7f7',
			'classic_text'       => '222222',
		);
	}
}
