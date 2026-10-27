<?php
/**
 * RazorSoft WordPress Theme Adapter.
 *
 * Reads the active theme with supported WordPress APIs and exposes design
 * tokens as CSS custom properties. This is a library, not a plugin. A host
 * plugin bundles these files and calls razor_theme_enqueue(), or calls the
 * runtime returned by this file.
 *
 * Loading this file always registers the copy in this directory. It does not
 * skip itself because another plugin already loaded a different copy.
 *
 * @package RazorThemeAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RAZOR_THEME_ADAPTER_TEST')) {
	exit;
}

require_once __DIR__ . '/src/Registry.php';
require_once __DIR__ . '/src/functions.php';

$razor_theme_adapter_class = RazorSoft\ThemeAdapter\V0_1_0\Adapter::class;
if (!class_exists($razor_theme_adapter_class, false)) {
	require_once __DIR__ . '/src/V0_1_0/Value.php';
	require_once __DIR__ . '/src/V0_1_0/Tokens.php';
	require_once __DIR__ . '/src/V0_1_0/Reader.php';
	require_once __DIR__ . '/src/V0_1_0/Adapter.php';
}

return RazorSoft\ThemeAdapter\Registry::register(
	RazorSoft\ThemeAdapter\V0_1_0\Adapter::VERSION,
	__DIR__,
	static function (array $meta) use ($razor_theme_adapter_class): object {
		return new $razor_theme_adapter_class($meta);
	},
);
