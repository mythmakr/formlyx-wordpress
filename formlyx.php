<?php
/**
 * Plugin Name:       Formlyx Forms
 * Plugin URI:        https://www.formlyx.com
 * Description:       Connect Formlyx to WordPress and embed hosted forms.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            RazorSoft, Inc.
 * Author URI:        https://razorsoft.us
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       formlyx
 * Domain Path:       /languages
 * Tested up to:      7.1
 *
 * @package Formlyx
 */

declare(strict_types=1);

namespace Formlyx;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FORMLYX_VERSION' ) ) {
	define( 'FORMLYX_VERSION', '1.0.0' );
}

if ( ! defined( 'FORMLYX_PLUGIN_FILE' ) ) {
	define( 'FORMLYX_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'FORMLYX_PLUGIN_DIR' ) ) {
	define( 'FORMLYX_PLUGIN_DIR', plugin_dir_path( FORMLYX_PLUGIN_FILE ) );
}

if ( ! defined( 'FORMLYX_PLUGIN_URL' ) ) {
	define( 'FORMLYX_PLUGIN_URL', plugin_dir_url( FORMLYX_PLUGIN_FILE ) );
}

require_once FORMLYX_PLUGIN_DIR . 'includes/Storage.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Pkce.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Api.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Http.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Redirect.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/ClientRegistration.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/TokenManager.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Authorization.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/ConnectionStatus.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/AdminPage.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/FormCatalog.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Embed.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Renderer.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/FormsEndpoint.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/SiteOrigin.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/EmbedAuthorization.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/EmbedOriginEndpoint.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Privacy.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Blocks.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Uninstaller.php';
require_once FORMLYX_PLUGIN_DIR . 'includes/Plugin.php';

add_action( 'plugins_loaded', array( Plugin::class, 'init' ) );
