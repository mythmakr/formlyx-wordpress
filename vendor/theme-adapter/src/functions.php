<?php
/**
 * Caller-scoped wrappers around the bundle that belongs to the calling plugin.
 *
 * These function bodies must stay compatible across releases. The first bundle
 * to load defines them; later bundles reuse them and dispatch through Registry.
 *
 * @package RazorThemeAdapter
 */

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RAZOR_THEME_ADAPTER_TEST')) {
	exit;
}

if (!function_exists('razor_theme_adapter')) {
	function razor_theme_adapter(): object {
		return \RazorSoft\ThemeAdapter\Registry::resolve();
	}
}

if (!function_exists('razor_theme_tokens')) {
	/**
	 * Normalized tokens for the active theme, or for a supplied source in tests.
	 *
	 * @param array<string, mixed>|null $source
	 * @return array<string, mixed>
	 */
	function razor_theme_tokens(?array $source = null): array {
		$runtime = \RazorSoft\ThemeAdapter\Registry::resolve();
		if (!method_exists($runtime, 'tokens')) {
			throw new \RazorSoft\ThemeAdapter\AmbiguousAdapter('The resolved Theme Adapter cannot build tokens.');
		}
		$tokens = $runtime->tokens($source);
		return is_array($tokens) ? $tokens : [];
	}
}

if (!function_exists('razor_theme_css')) {
	/**
	 * @param array<string, mixed>|null $tokens
	 */
	function razor_theme_css(?array $tokens = null): string {
		$runtime = \RazorSoft\ThemeAdapter\Registry::resolve();
		if (!method_exists($runtime, 'css')) {
			throw new \RazorSoft\ThemeAdapter\AmbiguousAdapter('The resolved Theme Adapter cannot build CSS.');
		}
		$css = $runtime->css($tokens);
		return is_string($css) ? $css : '';
	}
}

if (!function_exists('razor_theme_enqueue')) {
	function razor_theme_enqueue(): void {
		try {
			$runtime = \RazorSoft\ThemeAdapter\Registry::resolve();
		} catch (\RazorSoft\ThemeAdapter\AmbiguousAdapter $error) {
			$runtimes = \RazorSoft\ThemeAdapter\Registry::runtimes();
			if ($runtimes === []) {
				throw $error;
			}
			foreach ($runtimes as $runtime) {
				if (method_exists($runtime, 'enqueue')) {
					$runtime->enqueue();
				}
			}
			return;
		}
		if (method_exists($runtime, 'enqueue')) {
			$runtime->enqueue();
		}
	}
}
