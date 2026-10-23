<?php
/**
 * Stubs the WordPress functions used by the plugin bootstrap.
 *
 * @package Formlyx
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['formlyx_test_actions']                 = array();
$GLOBALS['formlyx_test_did_actions']             = array();
$GLOBALS['formlyx_test_deleted_options']         = array();
$GLOBALS['formlyx_test_deleted_transients']      = array();
$GLOBALS['formlyx_test_deleted_site_transients'] = array();
$GLOBALS['formlyx_test_multisite']               = false;
$GLOBALS['formlyx_test_sites']                   = array();
$GLOBALS['formlyx_test_blog_id']                 = 1;

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Mirror WordPress plugin_dir_path().
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_dir_path( $file ) {
		return rtrim( dirname( $file ), '/\\' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * Mirror WordPress plugin_dir_url() with a fixed test URL.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_dir_url( $file ) {
		unset( $file );
		return 'http://example.test/wp-content/plugins/formlyx/';
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record add_action() calls.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted argument count.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['formlyx_test_actions'][] = array( $hook, $callback, $priority, $accepted_args );
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Record add_filter() calls.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback.
	 * @param int      $priority      Priority.
	 * @param int      $accepted_args Accepted argument count.
	 * @return true
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['formlyx_test_actions'][] = array( $hook, $callback, $priority, $accepted_args );
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Record do_action() calls.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	function do_action( $hook ) {
		$GLOBALS['formlyx_test_did_actions'][] = $hook;
	}
}

if ( ! defined( 'FORMLYX_UNIT_TESTS' ) ) {
	define( 'FORMLYX_UNIT_TESTS', true );
}

$GLOBALS['formlyx_test_options']    = array();
$GLOBALS['formlyx_test_user_id']    = 1;
$GLOBALS['formlyx_test_can_manage'] = true;
$GLOBALS['formlyx_test_nonce_ok']   = true;
$GLOBALS['formlyx_test_redirects']  = array();
$GLOBALS['formlyx_test_admin_base'] = 'https://example.test/wp-admin/';
$GLOBALS['formlyx_test_home_url']   = 'https://example.test/';

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Read the in-memory option store.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		if ( array_key_exists( $option, $GLOBALS['formlyx_test_options'] ) ) {
			return $GLOBALS['formlyx_test_options'][ $option ];
		}
		return $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Create an option once.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $value     Value.
	 * @param string $deprecated Unused.
	 * @param mixed  $autoload  Unused.
	 * @return bool
	 */
	function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
		unset( $deprecated );
		if ( array_key_exists( $option, $GLOBALS['formlyx_test_options'] ) ) {
			return false;
		}
		$GLOBALS['formlyx_test_options'][ $option ]  = $value;
		$GLOBALS['formlyx_test_autoload'][ $option ] = $autoload;
		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Replace an option.
	 *
	 * @param string $option   Option name.
	 * @param mixed  $value    Value.
	 * @param mixed  $autoload Unused.
	 * @return true
	 */
	function update_option( $option, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['formlyx_test_options'][ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Record delete_option() calls for the current test site.
	 *
	 * @param string $option Option name.
	 * @return true
	 */
	function delete_option( $option ) {
		$GLOBALS['formlyx_test_deleted_options'][] = array( $GLOBALS['formlyx_test_blog_id'], $option );
		unset( $GLOBALS['formlyx_test_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Record delete_transient() calls for the current test site.
	 *
	 * @param string $transient Transient name.
	 * @return true
	 */
	function delete_transient( $transient ) {
		$GLOBALS['formlyx_test_deleted_transients'][] = array( $GLOBALS['formlyx_test_blog_id'], $transient );
		unset( $GLOBALS['formlyx_test_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'delete_site_transient' ) ) {
	/**
	 * Record delete_site_transient() calls.
	 *
	 * @param string $transient Transient name.
	 * @return true
	 */
	function delete_site_transient( $transient ) {
		$GLOBALS['formlyx_test_deleted_site_transients'][] = array( $GLOBALS['formlyx_test_blog_id'], $transient );
		return true;
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * Read the test multisite flag.
	 *
	 * @return bool
	 */
	function is_multisite() {
		return (bool) $GLOBALS['formlyx_test_multisite'];
	}
}

if ( ! function_exists( 'get_sites' ) ) {
	/**
	 * Return the test site ids.
	 *
	 * @param array<string, mixed> $args Query arguments. Unused.
	 * @return int[]
	 */
	function get_sites( $args = array() ) {
		unset( $args );
		return $GLOBALS['formlyx_test_sites'];
	}
}

if ( ! function_exists( 'switch_to_blog' ) ) {
	/**
	 * Switch the recorded current site.
	 *
	 * @param int $blog_id Site id.
	 * @return true
	 */
	function switch_to_blog( $blog_id ) {
		$GLOBALS['formlyx_test_blog_id'] = (int) $blog_id;
		return true;
	}
}

if ( ! function_exists( 'restore_current_blog' ) ) {
	/**
	 * Restore the recorded current site to the test default.
	 *
	 * @return true
	 */
	function restore_current_blog() {
		$GLOBALS['formlyx_test_blog_id'] = 1;
		return true;
	}
}

require dirname( __DIR__, 2 ) . '/formlyx.php';

if ( ! function_exists( 'wpautop' ) ) {
	/**
	 * Wrap policy text in a paragraph for the privacy stub.
	 *
	 * @param string $text Text.
	 * @param bool   $br   Unused.
	 * @return string
	 */
	function wpautop( $text, $br = true ) {
		unset( $br );
		return '<p>' . $text . '</p>';
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Return policy HTML unchanged in unit tests.
	 *
	 * @param string $data HTML.
	 * @return string
	 */
	function wp_kses_post( $data ) {
		return (string) $data;
	}
}

if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
	/**
	 * Record suggested privacy policy content.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $policy_text Policy HTML.
	 * @return void
	 */
	function wp_add_privacy_policy_content( $plugin_name, $policy_text ) {
		$GLOBALS['formlyx_test_privacy'][] = array( (string) $plugin_name, (string) $policy_text );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Build a public site URL from the test base.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function home_url( $path = '' ) {
		$base = isset( $GLOBALS['formlyx_test_home_url'] ) ? (string) $GLOBALS['formlyx_test_home_url'] : 'https://example.test/';
		if ( '' === (string) $path || '/' === (string) $path ) {
			return $base;
		}
		return rtrim( $base, '/' ) . '/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Build an admin URL from the test base.
	 *
	 * @param string $path   Path.
	 * @param string $scheme Unused.
	 * @return string
	 */
	function admin_url( $path = '', $scheme = 'admin' ) {
		unset( $scheme );
		return $GLOBALS['formlyx_test_admin_base'] . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Parse a URL.
	 *
	 * @param string $url URL.
	 * @return array<string, mixed>|false
	 */
	function wp_parse_url( $url ) {
		$parsed = parse_url( (string) $url );
		return is_array( $parsed ) ? $parsed : false;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Append one query argument.
	 *
	 * @param string $key   Argument name.
	 * @param string $value Argument value.
	 * @param string $url   URL.
	 * @return string
	 */
	function add_query_arg( $key, $value, $url ) {
		$separator = false === strpos( (string) $url, '?' ) ? '?' : '&';
		return $url . $separator . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	/**
	 * Record a redirect.
	 *
	 * @param string $location Destination.
	 * @return true
	 */
	function wp_safe_redirect( $location ) {
		$GLOBALS['formlyx_test_redirects'][] = $location;
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Read the test capability flag.
	 *
	 * @param string $capability Capability.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		if ( isset( $GLOBALS['formlyx_test_caps'] ) && is_array( $GLOBALS['formlyx_test_caps'] ) && array_key_exists( $capability, $GLOBALS['formlyx_test_caps'] ) ) {
			return (bool) $GLOBALS['formlyx_test_caps'][ $capability ];
		}
		if ( 'manage_options' === $capability ) {
			return (bool) $GLOBALS['formlyx_test_can_manage'];
		}
		return false;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Read the test user id.
	 *
	 * @return int
	 */
	function get_current_user_id() {
		return (int) $GLOBALS['formlyx_test_user_id'];
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Stop with an exception.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	function wp_die( $message = '' ) {
		throw new RuntimeException( is_string( $message ) ? $message : 'wp_die' );
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Honor the test nonce flag.
	 *
	 * @param string $action    Action name.
	 * @param string $query_arg Nonce field.
	 * @return int
	 */
	function check_admin_referer( $action = '-1', $query_arg = '_wpnonce' ) {
		unset( $action, $query_arg );
		if ( empty( $GLOBALS['formlyx_test_nonce_ok'] ) ) {
			wp_die( 'nonce' );
		}
		return 1;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Remove slashes from a string.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Strip tags from a string.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Reduce a value to a key.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function sanitize_key( $value ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * Keep a simple email string.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function sanitize_email( $value ) {
		return filter_var( (string) $value, FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escape HTML.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escape an attribute.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Return an already absolute URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Return the source string.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Return escaped translated text.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Echo escaped translated text.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return void
	 */
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Print a nonce placeholder.
	 *
	 * @param string $action Action.
	 * @return string
	 */
	function wp_nonce_field( $action ) {
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( (string) $action ) . '" />';
		return $action;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	/**
	 * Format a timestamp.
	 *
	 * @param string $format    Format.
	 * @param int    $timestamp Timestamp.
	 * @return string
	 */
	function wp_date( $format, $timestamp = 0 ) {
		return gmdate( (string) $format, (int) $timestamp );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Encode JSON.
	 *
	 * @param mixed $data Data.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stand-in.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		public $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @param mixed  $data    Error data.
		 */
		public function __construct( $code = '', $message = '', $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * Error code.
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Error data.
		 *
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Whether a value is a WP_Error.
	 *
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Read a response code.
	 *
	 * @param mixed $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) && isset( $response['code'] ) ? (int) $response['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Read a response body.
	 *
	 * @param mixed $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) && isset( $response['body'] ) && is_string( $response['body'] ) ? $response['body'] : '';
	}
}

if ( ! function_exists( 'add_options_page' ) ) {
	/**
	 * Record the settings page registration.
	 *
	 * @param string   $page_title Page title.
	 * @param string   $menu_title Menu title.
	 * @param string   $capability Capability.
	 * @param string   $menu_slug  Slug.
	 * @param callable $callback   Callback.
	 * @return void
	 */
	function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback ) {
		$GLOBALS['formlyx_test_pages'][] = array( $page_title, $menu_title, $capability, $menu_slug, $callback );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Read a test transient.
	 *
	 * @param string $transient Transient name.
	 * @return mixed
	 */
	function get_transient( $transient ) {
		return isset( $GLOBALS['formlyx_test_transients'][ $transient ] ) ? $GLOBALS['formlyx_test_transients'][ $transient ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Store a test transient.
	 *
	 * @param string $transient  Transient name.
	 * @param mixed  $value      Value.
	 * @param int    $expiration Expiration.
	 * @return true
	 */
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['formlyx_test_transients'][ $transient ]             = $value;
		$GLOBALS['formlyx_test_transient_expirations'][ $transient ] = $expiration;
		return true;
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Merge shortcode attributes.
	 *
	 * @param array<string, mixed> $pairs     Defaults.
	 * @param mixed                $atts      Attributes.
	 * @param string               $shortcode Shortcode name.
	 * @return array<string, mixed>
	 */
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		unset( $shortcode );
		$out = $pairs;
		if ( is_array( $atts ) ) {
			foreach ( $pairs as $name => $default ) {
				unset( $default );
				if ( array_key_exists( $name, $atts ) ) {
					$out[ $name ] = $atts[ $name ];
				}
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Record a shortcode.
	 *
	 * @param string   $tag      Tag.
	 * @param callable $callback Callback.
	 * @return void
	 */
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['formlyx_test_shortcodes'][ $tag ] = $callback;
	}
}

if ( ! function_exists( 'register_block_type' ) ) {
	/**
	 * Record block registration.
	 *
	 * @param string              $path Path or name.
	 * @param array<string,mixed> $args Args.
	 * @return object
	 */
	function register_block_type( $path, $args = array() ) {
		$GLOBALS['formlyx_test_blocks'][] = array( $path, $args );
		$block                            = new stdClass();
		$block->editor_script_handles     = array( 'formlyx-form-editor-script' );
		return $block;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	/**
	 * Record a REST route.
	 *
	 * @param string              $namespace Namespace.
	 * @param string              $route     Route.
	 * @param array<string,mixed> $args      Args.
	 * @return true
	 */
	function register_rest_route( $namespace, $route, $args ) {
		$GLOBALS['formlyx_test_routes'][] = array( $namespace, $route, $args );
		return true;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	/**
	 * Record a script registration.
	 *
	 * @param string        $handle Handle.
	 * @param string        $src    Source.
	 * @param string[]      $deps   Dependencies.
	 * @param string|false  $ver    Version.
	 * @param bool          $footer Footer.
	 * @return true
	 */
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $footer = false ) {
		$GLOBALS['formlyx_test_scripts'][ $handle ] = array(
			'registered' => true,
			'src'        => $src,
			'ver'        => $ver,
			'footer'     => $footer,
			'deps'       => $deps,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Record a script enqueue.
	 *
	 * @param string $handle Handle.
	 * @return void
	 */
	function wp_enqueue_script( $handle ) {
		if ( ! isset( $GLOBALS['formlyx_test_scripts'][ $handle ] ) ) {
			$GLOBALS['formlyx_test_scripts'][ $handle ] = array( 'registered' => true );
		}
		$GLOBALS['formlyx_test_scripts'][ $handle ]['enqueued'] = true;
		$GLOBALS['formlyx_test_script_enqueues'][]              = $handle;
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	/**
	 * Whether a test script is registered or enqueued.
	 *
	 * @param string $handle Handle.
	 * @param string $status Status.
	 * @return bool
	 */
	function wp_script_is( $handle, $status = 'enqueued' ) {
		if ( ! isset( $GLOBALS['formlyx_test_scripts'][ $handle ] ) ) {
			return false;
		}
		if ( 'registered' === $status ) {
			return ! empty( $GLOBALS['formlyx_test_scripts'][ $handle ]['registered'] );
		}
		return ! empty( $GLOBALS['formlyx_test_scripts'][ $handle ]['enqueued'] );
	}
}

if ( ! function_exists( 'wp_script_add_data' ) ) {
	/**
	 * Record script data.
	 *
	 * @param string $handle Handle.
	 * @param string $key    Key.
	 * @param mixed  $value  Value.
	 * @return true
	 */
	function wp_script_add_data( $handle, $key, $value ) {
		$GLOBALS['formlyx_test_script_data'][ $handle ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	/**
	 * Build a plugin asset URL.
	 *
	 * @param string $path Path.
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugins_url( $path = '', $file = '' ) {
		unset( $file );
		return 'https://example.test/wp-content/plugins/formlyx/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	/**
	 * Record a style registration.
	 *
	 * @param string       $handle Handle.
	 * @param string       $src    Source.
	 * @param string[]     $deps   Dependencies.
	 * @param string|false $ver    Version.
	 * @return true
	 */
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
		$GLOBALS['formlyx_test_styles'][ $handle ] = array(
			'registered' => true,
			'src'        => $src,
			'ver'        => $ver,
			'deps'       => $deps,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Record a style enqueue.
	 *
	 * @param string $handle Handle.
	 * @return void
	 */
	function wp_enqueue_style( $handle ) {
		if ( ! isset( $GLOBALS['formlyx_test_styles'][ $handle ] ) ) {
			$GLOBALS['formlyx_test_styles'][ $handle ] = array( 'registered' => true );
		}
		$GLOBALS['formlyx_test_styles'][ $handle ]['enqueued'] = true;
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	/**
	 * Whether a test style is registered.
	 *
	 * @param string $handle Handle.
	 * @param string $status Status.
	 * @return bool
	 */
	function wp_style_is( $handle, $status = 'enqueued' ) {
		if ( ! isset( $GLOBALS['formlyx_test_styles'][ $handle ] ) ) {
			return false;
		}
		if ( 'registered' === $status ) {
			return ! empty( $GLOBALS['formlyx_test_styles'][ $handle ]['registered'] );
		}
		return ! empty( $GLOBALS['formlyx_test_styles'][ $handle ]['enqueued'] );
	}
}

if ( ! function_exists( 'wp_print_scripts' ) ) {
	/**
	 * Record a late script print.
	 *
	 * @param string $handle Handle.
	 * @return void
	 */
	function wp_print_scripts( $handle ) {
		$GLOBALS['formlyx_test_printed_scripts'][] = $handle;
	}
}

if ( ! function_exists( 'wp_print_styles' ) ) {
	/**
	 * Record a late style print.
	 *
	 * @param string $handle Handle.
	 * @return void
	 */
	function wp_print_styles( $handle ) {
		$GLOBALS['formlyx_test_printed_styles'][] = $handle;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	/**
	 * Read a test action count.
	 *
	 * @param string $hook Hook.
	 * @return int
	 */
	function did_action( $hook ) {
		return isset( $GLOBALS['formlyx_test_did_action'][ $hook ] ) ? (int) $GLOBALS['formlyx_test_did_action'][ $hook ] : 0;
	}
}

if ( ! function_exists( 'wp_set_script_translations' ) ) {
	/**
	 * Record script translation registration.
	 *
	 * @param string $handle Handle.
	 * @param string $domain Text domain.
	 * @param string $path   Language path.
	 * @return true
	 */
	function wp_set_script_translations( $handle, $domain = 'default', $path = '' ) {
		$GLOBALS['formlyx_test_script_translations'][] = array( $handle, $domain, $path );
		return true;
	}
}

if ( ! function_exists( 'wp_add_inline_script' ) ) {
	/**
	 * Record an inline script.
	 *
	 * @param string $handle   Handle.
	 * @param string $data     Script.
	 * @param string $position Position.
	 * @return true
	 */
	function wp_add_inline_script( $handle, $data, $position = 'after' ) {
		$GLOBALS['formlyx_test_inline_scripts'][] = array( $handle, $data, $position );
		return true;
	}
}
