<?php
/**
 * Form listing, REST, and embed rendering tests.
 *
 * @package Formlyx
 */

declare(strict_types=1);

namespace Formlyx\Tests\Unit;

use Formlyx\Api;
use Formlyx\Blocks;
use Formlyx\Embed;
use Formlyx\FormCatalog;
use Formlyx\FormsEndpoint;
use Formlyx\Http;
use Formlyx\Plugin;
use Formlyx\Privacy;
use Formlyx\Renderer;
use Formlyx\Storage;
use Formlyx\ThemeAdapter;
use Formlyx\TokenManager;
use PHPUnit\Framework\TestCase;

/**
 * Covers published-form filtering, the editor route, and shared markup.
 *
 * @since 1.0.0
 */
final class EmbedTest extends TestCase {

	/**
	 * Reset stubs before each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['formlyx_test_options']               = array();
		$GLOBALS['formlyx_test_transients']            = array();
		$GLOBALS['formlyx_test_transient_expirations'] = array();
		$GLOBALS['formlyx_http_log']                   = array();
		$GLOBALS['formlyx_http_queue']                 = array();
		$GLOBALS['formlyx_test_scripts']               = array();
		$GLOBALS['formlyx_test_styles']                = array();
		$GLOBALS['formlyx_test_script_enqueues']       = array();
		$GLOBALS['formlyx_test_script_data']           = array();
		$GLOBALS['formlyx_test_printed_scripts']       = array();
		$GLOBALS['formlyx_test_printed_styles']        = array();
		$GLOBALS['formlyx_test_did_action']            = array();
		$GLOBALS['formlyx_test_caps']                  = array();
		$GLOBALS['formlyx_test_blocks']                = array();
		$GLOBALS['formlyx_test_routes']                = array();
		$GLOBALS['formlyx_test_shortcodes']            = array();
		$GLOBALS['formlyx_test_script_translations']   = array();
		$GLOBALS['formlyx_test_inline_scripts']        = array();
		Embed::reset();
		Http::set_transport(
			static function ( $method, $url, $args ) {
				$GLOBALS['formlyx_http_log'][] = array( $method, $url, $args );
				$next                          = array_shift( $GLOBALS['formlyx_http_queue'] );
				return is_array( $next ) ? $next : new \WP_Error( 'http', 'missing response' );
			}
		);
	}

	/**
	 * Only published forms with public embedding are returned.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_filters_to_published_embeddable_forms(): void {
		$forms = FormCatalog::embeddable( $this->api_forms() );
		$this->assertCount( 1, $forms );
		$this->assertSame(
			array(
				'id'          => 'clh0contact00000000000001',
				'name'        => 'Contact us',
				'description' => 'Say hello',
			),
			$forms[0]
		);
		$this->assertArrayNotHasKey( 'notificationEmail', $forms[0] );
		$this->assertArrayNotHasKey( 'fields', $forms[0] );
	}

	/**
	 * A successful list is cached and does not call Formlyx again.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_form_list_is_cached(): void {
		$this->seed_connection();
		$this->queue_forms();
		$first = FormCatalog::list( false );
		$this->assertTrue( $first['ok'] );
		$this->assertCount( 1, $first['forms'] );
		$this->assertSame( 300, $GLOBALS['formlyx_test_transient_expirations'][ Storage::TRANSIENT_FORMS ] );
		$second = FormCatalog::list( false );
		$this->assertSame( $first['forms'], $second['forms'] );
		$this->assertCount( 1, $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * Refresh bypasses the transient.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_refresh_bypasses_the_cache(): void {
		$this->seed_connection();
		$this->queue_forms();
		FormCatalog::list( false );
		$this->queue_forms();
		FormCatalog::list( true );
		$this->assertCount( 2, $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * Disconnect and a new token drop the cached list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_connection_changes_clear_the_cache(): void {
		set_transient( Storage::TRANSIENT_FORMS, array( array( 'id' => 'clh0contact00000000000001' ) ), 300 );
		TokenManager::clear_tokens();
		$this->assertFalse( get_transient( Storage::TRANSIENT_FORMS ) );

		set_transient( Storage::TRANSIENT_FORMS, array( array( 'id' => 'clh0contact00000000000001' ) ), 300 );
		$saved = TokenManager::store_token_response(
			array(
				'access_token'  => 'flx_new_access',
				'refresh_token' => 'flx_new_refresh',
				'token_type'    => 'Bearer',
				'expires_in'    => 3600,
				'scope'         => 'forms:read',
			)
		);
		$this->assertTrue( $saved['ok'] );
		$this->assertFalse( get_transient( Storage::TRANSIENT_FORMS ) );
	}

	/**
	 * API failures are not cached.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_api_failure_is_not_cached(): void {
		$this->seed_connection();
		$GLOBALS['formlyx_http_queue'][] = new \WP_Error( 'http', 'down' );
		$result                          = FormCatalog::list( false );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'unreachable', $result['notice'] );
		$this->assertFalse( get_transient( Storage::TRANSIENT_FORMS ) );
	}

	/**
	 * A disconnected site does not call the forms endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_disconnected_site_does_not_request_forms(): void {
		$result = FormCatalog::list( false );
		$this->assertFalse( $result['ok'] );
		$this->assertSame( 'disconnected', $result['notice'] );
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );
	}

	/**
	 * The editor payload and cache omit the access token and private fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_form_list_does_not_expose_tokens(): void {
		$this->seed_connection();
		$this->queue_forms();
		$GLOBALS['formlyx_test_caps']['edit_posts'] = true;
		$result                                     = FormsEndpoint::response( false );
		$encoded                                    = (string) wp_json_encode( $result );
		$cached                                     = (string) wp_json_encode( get_transient( Storage::TRANSIENT_FORMS ) );
		$this->assertStringNotContainsString( 'flx_secret_access', $encoded );
		$this->assertStringNotContainsString( 'secret@example.com', $encoded );
		$this->assertStringNotContainsString( 'flx_secret_access', $cached );
		$this->assertStringNotContainsString( 'secret@example.com', $cached );
		$this->assertStringContainsString( 'Bearer flx_secret_access', (string) wp_json_encode( $GLOBALS['formlyx_http_log'] ) );
	}

	/**
	 * Visitors cannot read the form list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_rest_permission_requires_an_editor(): void {
		$this->assertFalse( FormsEndpoint::permission() );
		$GLOBALS['formlyx_test_caps']['edit_pages'] = true;
		$this->assertTrue( FormsEndpoint::permission() );
	}

	/**
	 * The route is registered for cookie-authenticated editors.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_rest_route_registration(): void {
		FormsEndpoint::register();
		$this->assertSame( 'formlyx/v1', $GLOBALS['formlyx_test_routes'][0][0] );
		$this->assertSame( '/forms', $GLOBALS['formlyx_test_routes'][0][1] );
		$this->assertSame( 'GET', $GLOBALS['formlyx_test_routes'][0][2]['methods'] );
		$this->assertSame( array( FormsEndpoint::class, 'permission' ), $GLOBALS['formlyx_test_routes'][0][2]['permission_callback'] );
	}

	/**
	 * An expired connection becomes a REST error without the API body.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_expired_connection_rest_error(): void {
		$this->seed_connection();
		$GLOBALS['formlyx_http_queue'][] = array(
			'code' => 401,
			'body' => (string) wp_json_encode(
				array(
					'success' => false,
					'error'   => array(
						'code'    => 'INVALID_GRANT',
						'message' => 'token leaked in message',
					),
				)
			),
		);
		$error                           = FormsEndpoint::response( true );
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'formlyx_expired', $error->get_error_code() );
		$this->assertStringNotContainsString( 'token leaked', $error->message );
		$this->assertSame( 401, $error->get_error_data()['status'] );
	}

	/**
	 * Block metadata matches the editor contract.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_block_metadata(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local block metadata, not a remote request.
		$metadata = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/block.json' ), true );
		$this->assertIsArray( $metadata );
		$this->assertSame( 'formlyx/form', $metadata['name'] );
		$this->assertSame( 'widgets', $metadata['category'] );
		$this->assertSame( 'Formlyx Form', $metadata['title'] );
		$this->assertSame( '', $metadata['attributes']['formId']['default'] );
		$this->assertSame( 'auto', $metadata['attributes']['theme']['default'] );
	}

	/**
	 * Init registers the block, shortcode, and editor translations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_block_and_shortcode_registration(): void {
		Blocks::register_content();
		$this->assertSame( array( Renderer::class, 'render_block' ), $GLOBALS['formlyx_test_blocks'][0][1]['render_callback'] );
		$this->assertArrayHasKey( 'formlyx', $GLOBALS['formlyx_test_shortcodes'] );
		$this->assertSame( 'formlyx', $GLOBALS['formlyx_test_script_translations'][0][1] );
		$this->assertStringNotContainsString( 'access_token', $GLOBALS['formlyx_test_inline_scripts'][0][1] );
	}

	/**
	 * Markup matches the hosted embed and escapes attributes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_markup_and_escaping(): void {
		$html = Renderer::markup( 'clh0contact00000000000001', 'dark' );
		$this->assertSame(
			'<div class="formlyx-embed" data-form-id="clh0contact00000000000001" data-theme="dark"></div>',
			$html
		);
		$this->assertSame( '', Renderer::markup( '"><script>', 'auto' ) );
		$this->assertSame( '', Renderer::markup( '', 'auto' ) );
		$this->assertStringContainsString( 'data-theme="auto"', Renderer::markup( 'clh0contact00000000000001', '"><script>' ) );
		$this->assertStringNotContainsString( 'script', Renderer::markup( 'clh0contact00000000000001', '"><script>' ) );
	}

	/**
	 * Accepted theme values follow embed.js.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_theme_values(): void {
		$this->assertSame( 'auto', Renderer::theme( 'formlyx' ) );
		$this->assertSame( 'light', Renderer::theme( 'LIGHT' ) );
		$this->assertSame( 'unstyled', Renderer::theme( 'unstyled' ) );
		$this->assertSame( 'match', Renderer::theme( 'MATCH' ) );
		$this->assertSame( 'auto', Renderer::theme( 'styled' ) );
	}

	/**
	 * Several embeds enqueue the hosted script once.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_multiple_forms_enqueue_the_script_once(): void {
		$block    = Renderer::render_block(
			array(
				'formId' => 'clh0contact00000000000001',
				'theme'  => 'auto',
			)
		);
		$again    = Renderer::render_shortcode(
			array(
				'id'    => 'clh0contact00000000000001',
				'theme' => 'inherit',
			)
		);
		$other    = Renderer::render_shortcode(
			array(
				'id'    => 'clh0other0000000000000001',
				'theme' => 'auto',
			)
		);
		$combined = $block . $again . $other;
		$this->assertSame( 2, substr_count( $combined, 'clh0contact00000000000001' ) );
		$this->assertSame( 1, substr_count( $combined, 'clh0other0000000000000001' ) );
		$this->assertStringNotContainsString( ' id=', $combined );
		$this->assertSame( Embed::SRC, $GLOBALS['formlyx_test_scripts'][ Embed::SCRIPT ]['src'] );
		$this->assertTrue( $GLOBALS['formlyx_test_scripts'][ Embed::SCRIPT ]['footer'] );
		$this->assertSame( FORMLYX_VERSION, $GLOBALS['formlyx_test_scripts'][ Embed::SCRIPT ]['ver'] );
		$this->assertSame( 'defer', $GLOBALS['formlyx_test_script_data'][ Embed::SCRIPT ]['strategy'] );
		$this->assertCount( 1, array_unique( $GLOBALS['formlyx_test_script_enqueues'] ) );
	}

	/**
	 * A form rendered after the footer still prints the script once.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_script_prints_when_the_footer_already_ran(): void {
		$GLOBALS['formlyx_test_did_action']['wp_footer'] = 1;
		Renderer::markup( 'clh0contact00000000000001', 'auto' );
		Renderer::markup( 'clh0other0000000000000001', 'auto' );
		$this->assertSame( array( Embed::SCRIPT ), $GLOBALS['formlyx_test_printed_scripts'] );
	}

	/**
	 * Public markup does not require OAuth.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_public_embed_does_not_call_formlyx_api(): void {
		$html = Renderer::render_shortcode(
			array(
				'id'    => 'clh0contact00000000000001',
				'theme' => 'auto',
			)
		);
		$this->assertNotSame( '', $html );
		$this->assertSame( array(), $GLOBALS['formlyx_http_log'] );
		$this->assertStringNotContainsString( 'Bearer', $html );
	}

	/**
	 * A shortcode without an id does not print a container.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_shortcode_without_an_id_is_empty(): void {
		$this->assertSame( '', Renderer::render_shortcode( array() ) );
		$this->assertSame( '', Renderer::render_block( array() ) );
		$this->assertSame( array(), $GLOBALS['formlyx_test_script_enqueues'] );
	}

	/**
	 * Runtime classes and the block source are present for packaging.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_runtime_files_exist(): void {
		$root = dirname( __DIR__, 3 );
		foreach ( array(
			'includes/FormCatalog.php',
			'includes/Renderer.php',
			'includes/Embed.php',
			'includes/FormsEndpoint.php',
			'includes/Blocks.php',
			'includes/embed.css',
			'includes/ThemeAdapter.php',
			'vendor/theme-adapter/load.php',
			'vendor/theme-adapter-source.json',
			'src/block.json',
			'src/index.js',
			'src/edit.js',
		) as $file ) {
			$this->assertFileExists( $root . '/' . $file );
		}
	}

	/**
	 * The forms URL is the v1 resource.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_forms_url(): void {
		$this->assertSame( 'https://api.formlyx.com/v1/forms', Api::forms_url() );
	}

	/**
	 * Hook registration is idempotent for the plugin boot test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_blocks_hooks_are_registered(): void {
		Plugin::init();
		$hooks = array();
		foreach ( $GLOBALS['formlyx_test_actions'] as $action ) {
			$hooks[] = $action[0];
		}
		$this->assertContains( 'init', $hooks );
		$this->assertContains( 'rest_api_init', $hooks );
		$this->assertContains( 'admin_init', $hooks );
	}

	/**
	 * The privacy screen receives the external-service disclosure.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_privacy_policy_mentions_the_embed_and_not_tokens(): void {
		$GLOBALS['formlyx_test_privacy'] = array();
		Privacy::register();
		$this->assertSame( 'Formlyx Forms', $GLOBALS['formlyx_test_privacy'][0][0] );
		$this->assertStringContainsString( 'https://www.formlyx.com/embed.js', $GLOBALS['formlyx_test_privacy'][0][1] );
		$this->assertStringContainsString( 'https://www.formlyx.com/privacy', $GLOBALS['formlyx_test_privacy'][0][1] );
		$this->assertStringNotContainsString( 'flx_', $GLOBALS['formlyx_test_privacy'][0][1] );
	}

	/**
	 * The picker keeps a bounded number of forms and clips long text.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_form_list_is_bounded(): void {
		$forms = array();
		for ( $index = 0; $index < FormCatalog::MAX_FORMS + 5; $index++ ) {
			$forms[] = array(
				'id'                 => 'form' . $index,
				'displayTitle'       => str_repeat( 'N', 250 ),
				'description'        => str_repeat( 'D', 400 ),
				'status'             => 'published',
				'publicEmbedEnabled' => true,
			);
		}

		$selected = FormCatalog::embeddable( $forms );
		$this->assertCount( FormCatalog::MAX_FORMS, $selected );
		$this->assertSame( 200, strlen( $selected[0]['name'] ) );
		$this->assertSame( 300, strlen( $selected[0]['description'] ) );
	}

	/**
	 * Seed a current access token.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function seed_connection(): void {
		Storage::write(
			Storage::OPTION_CONNECTION,
			array(
				'access_token'       => 'flx_secret_access',
				'refresh_token'      => 'flx_secret_refresh',
				'access_expires_at'  => time() + 3600,
				'refresh_expires_at' => time() + 2592000,
				'scope'              => 'forms:read',
				'workspace_id'       => 'user-1',
				'workspace_name'     => 'Ada',
				'workspace_email'    => 'ada@example.com',
				'checked_at'         => time(),
				'status'             => 'connected',
			)
		);
	}

	/**
	 * Queue a wrapped form list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function queue_forms(): void {
		$GLOBALS['formlyx_http_queue'][] = array(
			'code' => 200,
			'body' => (string) wp_json_encode(
				array(
					'success' => true,
					'data'    => $this->api_forms(),
				)
			),
		);
	}

	/**
	 * Sample list payload using the Formlyx field names.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function api_forms(): array {
		return array(
			array(
				'id'                 => 'clh0contact00000000000001',
				'name'               => 'Contact',
				'displayTitle'       => 'Contact us',
				'description'        => 'Say hello',
				'status'             => 'published',
				'publicEmbedEnabled' => true,
				'notificationEmail'  => 'secret@example.com',
				'fields'             => array(
					array( 'label' => 'Email' ),
				),
				'userId'             => 'user-1',
			),
			array(
				'id'                 => 'clh0draft0000000000000001',
				'name'               => 'Draft',
				'displayTitle'       => 'Draft',
				'description'        => 'Hidden',
				'status'             => 'draft',
				'publicEmbedEnabled' => true,
			),
			array(
				'id'                 => 'clh0private00000000000001',
				'name'               => 'Private',
				'displayTitle'       => 'Private',
				'description'        => 'Hidden',
				'status'             => 'published',
				'publicEmbedEnabled' => false,
			),
			array(
				'id'                 => 'clh0archived0000000000001',
				'name'               => 'Archived',
				'status'             => 'archived',
				'publicEmbedEnabled' => true,
			),
		);
	}
}
