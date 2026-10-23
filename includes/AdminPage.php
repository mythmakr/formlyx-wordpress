<?php
/**
 * Settings → Formlyx.
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
 * WordPress administration screen for the Formlyx connection.
 *
 * @since 1.0.0
 */
final class AdminPage {

	/**
	 * Register the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		add_options_page(
			__( 'Formlyx', 'formlyx' ),
			__( 'Formlyx', 'formlyx' ),
			'manage_options',
			'formlyx',
			array( self::class, 'render' )
		);
	}

	/**
	 * Render the page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render(): void {
		self::require_admin();
		$connection = TokenManager::connection();
		$connected  = in_array( $connection['status'], array( 'connected', 'unreachable', 'expired' ), true ) && ( '' !== $connection['access_token'] || '' !== $connection['refresh_token'] || 'expired' === $connection['status'] );
		$notice     = '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display code from this plugin's own redirect.
		if ( isset( $_GET['formlyx_notice'] ) && is_string( $_GET['formlyx_notice'] ) ) {
			$notice = sanitize_text_field( wp_unslash( $_GET['formlyx_notice'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Formlyx', 'formlyx' ) . '</h1>';
		self::render_notice( $notice );

		if ( ! ClientRegistration::is_allowed_callback( ClientRegistration::callback_url() ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'This site needs HTTPS before it can connect to Formlyx. Local development can use HTTP on localhost.', 'formlyx' ) . '</p></div>';
		}

		echo '<div class="card" style="max-width:40rem;padding:1.25rem 1.5rem;">';
		if ( ! $connected ) {
			self::render_disconnected();
		} else {
			self::render_connected( $connection );
		}
		echo '</div></div>';
	}

	/**
	 * Start the Formlyx connection.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_connect(): void {
		self::require_admin();
		check_admin_referer( 'formlyx_connect' );
		$entry = isset( $_POST['formlyx_entry'] ) ? sanitize_key( wp_unslash( (string) $_POST['formlyx_entry'] ) ) : 'authorize';
		$entry = 'signup' === $entry ? 'signup' : 'authorize';
		$start = Authorization::begin( get_current_user_id(), $entry );
		if ( ! $start['ok'] ) {
			Redirect::away( self::settings_url( $start['notice'] ) );
		}
		Redirect::away( $start['url'] );
	}

	/**
	 * Ask Formlyx to confirm the saved connection.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_check(): void {
		self::require_admin();
		check_admin_referer( 'formlyx_check' );
		$notice = ConnectionStatus::check();
		if ( 'connected' === $notice ) {
			$notice = 'checked';
		}
		Redirect::away( self::settings_url( $notice ) );
	}

	/**
	 * Disconnect this site and attempt revocation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_disconnect(): void {
		self::require_admin();
		check_admin_referer( 'formlyx_disconnect' );
		$result = TokenManager::revoke();
		TokenManager::clear_tokens();
		$notice = $result['confirmed'] ? 'disconnected' : 'disconnect_unconfirmed';
		if ( ! $result['reachable'] ) {
			$notice = 'disconnect_unconfirmed';
		}
		Redirect::away( self::settings_url( $notice ) );
	}

	/**
	 * Settings page URL, optionally with a notice code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $notice Notice code.
	 * @return string
	 */
	public static function settings_url( string $notice = '' ): string {
		$url = admin_url( 'options-general.php?page=formlyx' );
		if ( '' === $notice ) {
			return $url;
		}
		return add_query_arg( 'formlyx_notice', $notice, $url );
	}

	/**
	 * Stop when the current user cannot manage options.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function require_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage Formlyx.', 'formlyx' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Visitor-facing text for a notice code.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code Notice code.
	 * @return string
	 */
	public static function notice_text( string $code ): string {
		switch ( $code ) {
			case 'connected':
				return __( 'Your site is connected to Formlyx.', 'formlyx' );
			case 'checked':
				return __( 'The Formlyx connection is working.', 'formlyx' );
			case 'disconnected':
				return __( 'This site is disconnected, and Formlyx confirmed the access was removed.', 'formlyx' );
			case 'disconnect_unconfirmed':
				return __( 'This site is disconnected. Formlyx did not confirm that access was removed. If the connection still appears in your Formlyx account, remove it there.', 'formlyx' );
			case 'denied':
				return __( 'The connection was cancelled. Nothing was changed.', 'formlyx' );
			case 'expired_link':
				return __( 'That connection attempt expired. Start again.', 'formlyx' );
			case 'invalid_link':
				return __( 'That connection attempt is no longer valid. Start again.', 'formlyx' );
			case 'invalid_issuer':
				return __( 'The connection response did not come from Formlyx. Nothing was saved.', 'formlyx' );
			case 'https_required':
				return __( 'Connect from an HTTPS site. HTTP is only available on localhost.', 'formlyx' );
			case 'registration_failed':
				return __( 'Formlyx could not start the connection. Try again in a few minutes.', 'formlyx' );
			case 'missing_scope':
				return __( 'Formlyx did not allow this site to read forms. Connect again and allow access.', 'formlyx' );
			case 'expired':
				return __( 'Formlyx needs you to connect again. The previous approval is no longer valid.', 'formlyx' );
			case 'unreachable':
				return __( 'Formlyx could not be reached. Your connection is still saved.', 'formlyx' );
			case 'refresh_busy':
				return __( 'Formlyx is updating the connection. Try again in a moment.', 'formlyx' );
			case 'connect_failed':
				return __( 'Formlyx could not finish connecting. Try again.', 'formlyx' );
			default:
				return '';
		}
	}

	/**
	 * Print a notice when the code is known.
	 *
	 * @since 1.0.0
	 *
	 * @param string $code Notice code.
	 * @return void
	 */
	private static function render_notice( string $code ): void {
		$text = self::notice_text( $code );
		if ( '' === $text ) {
			return;
		}
		$class = in_array( $code, array( 'connected', 'checked', 'disconnected' ), true ) ? 'notice-success' : 'notice-warning';
		if ( in_array( $code, array( 'invalid_issuer', 'expired', 'connect_failed', 'registration_failed' ), true ) ) {
			$class = 'notice-error';
		}
		echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Disconnected content.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function render_disconnected(): void {
		echo '<h2>' . esc_html__( 'Connect your site', 'formlyx' ) . '</h2>';
		echo '<p>' . esc_html__( 'Connect your WordPress website to Formlyx to select and embed your published forms.', 'formlyx' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'formlyx_connect' );
		echo '<input type="hidden" name="action" value="formlyx_connect" />';
		echo '<p>';
		echo '<button type="submit" class="button button-primary" name="formlyx_entry" value="authorize">' . esc_html__( 'Connect to Formlyx', 'formlyx' ) . '</button> ';
		echo '<button type="submit" class="button" name="formlyx_entry" value="signup">' . esc_html__( 'Create a Formlyx account', 'formlyx' ) . '</button>';
		echo '</p></form>';
	}

	/**
	 * Connected content.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $connection Connection record.
	 * @return void
	 */
	private static function render_connected( array $connection ): void {
		echo '<h2>' . esc_html__( 'Connected to Formlyx', 'formlyx' ) . '</h2>';
		echo '<p><strong>' . esc_html__( 'Connection status', 'formlyx' ) . ':</strong> ' . esc_html( self::status_label( (string) $connection['status'] ) ) . '</p>';

		if ( '' !== $connection['workspace_name'] ) {
			echo '<p><strong>' . esc_html__( 'Workspace', 'formlyx' ) . ':</strong> ' . esc_html( (string) $connection['workspace_name'] ) . '</p>';
		}
		if ( '' !== $connection['workspace_email'] ) {
			echo '<p><strong>' . esc_html__( 'Account email', 'formlyx' ) . ':</strong> ' . esc_html( (string) $connection['workspace_email'] ) . '</p>';
		}

		if ( (int) $connection['checked_at'] > 0 ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %s: date and time of the last successful check. */
					__( 'Last successful connection check: %s', 'formlyx' ),
					wp_date( 'Y-m-d H:i', (int) $connection['checked_at'] )
				)
			) . '</p>';
		}

		if ( 'expired' === $connection['status'] ) {
			echo '<p>' . esc_html( self::notice_text( 'expired' ) ) . '</p>';
		} elseif ( 'unreachable' === $connection['status'] ) {
			echo '<p>' . esc_html( self::notice_text( 'unreachable' ) ) . '</p>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:0.5rem;">';
		wp_nonce_field( 'formlyx_connect' );
		echo '<input type="hidden" name="action" value="formlyx_connect" />';
		echo '<input type="hidden" name="formlyx_entry" value="authorize" />';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Reconnect', 'formlyx' ) . '</button>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:0.5rem;">';
		wp_nonce_field( 'formlyx_check' );
		echo '<input type="hidden" name="action" value="formlyx_check" />';
		echo '<button type="submit" class="button">' . esc_html__( 'Check connection', 'formlyx' ) . '</button>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
		wp_nonce_field( 'formlyx_disconnect' );
		echo '<input type="hidden" name="action" value="formlyx_disconnect" />';
		echo '<button type="submit" class="button">' . esc_html__( 'Disconnect', 'formlyx' ) . '</button>';
		echo '</form>';
	}

	/**
	 * Status label.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Stored status.
	 * @return string
	 */
	private static function status_label( string $status ): string {
		switch ( $status ) {
			case 'connected':
				return __( 'Connected', 'formlyx' );
			case 'unreachable':
				return __( 'Temporarily unreachable', 'formlyx' );
			case 'expired':
				return __( 'Needs reconnecting', 'formlyx' );
			default:
				return __( 'Not connected', 'formlyx' );
		}
	}
}
