<?php
/**
 * Uninstall Formlyx.
 *
 * @package Formlyx
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Storage.php';
require_once __DIR__ . '/includes/Uninstaller.php';

\Formlyx\Uninstaller::uninstall();
