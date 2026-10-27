<?php
/**
 * Enqueues the theme token stylesheet and, when values are missing, a small detector.
 *
 * @package RazorThemeAdapter
 */

declare(strict_types=1);

namespace RazorSoft\ThemeAdapter\V0_1_0;

if (!defined('ABSPATH') && !defined('RAZOR_THEME_ADAPTER_TEST')) {
	exit;
}

final class Adapter {
	public const VERSION = '0.1.0';

	/**
	 * @param array{version: string, dir: string, file: string, owner: string, owner_root: string} $meta
	 */
	public function __construct(private array $meta) {
	}

	public function version(): string {
		return $this->meta['version'];
	}

	public function packageDir(): string {
		return $this->meta['dir'];
	}

	/**
	 * WordPress style and script handle for this copy.
	 *
	 * A single loaded owner keeps the historical handle. Additional owners get
	 * their own handle so one plugin cannot take the registration from another.
	 */
	public function styleHandle(): string {
		if (\RazorSoft\ThemeAdapter\Registry::ownerCount() < 2) {
			return 'razor-theme-adapter';
		}
		return 'razor-theme-adapter-' . $this->ownerSlug();
	}

	/**
	 * @param array<string, mixed>|null $source
	 * @return array<string, mixed>
	 */
	public function tokens(?array $source = null): array {
		$snapshot = $source ?? (new Reader())->collect();
		$normalized = Tokens::normalize($snapshot);
		if (!function_exists('apply_filters')) {
			return $normalized;
		}
		$filtered = apply_filters('razor_theme_tokens', $normalized);
		if (!is_array($filtered) || !is_array($filtered['tokens'] ?? null)) {
			return $normalized;
		}
		$normalized['tokens'] = Tokens::seal($filtered['tokens']);
		$normalized['gaps'] = Tokens::gaps($normalized['tokens']);
		return $normalized;
	}

	/**
	 * @param array<string, mixed>|null $tokens
	 */
	public function css(?array $tokens = null): string {
		return Tokens::stylesheet($tokens ?? $this->tokens());
	}

	public function enqueue(): void {
		if (!function_exists('wp_register_style') || !function_exists('wp_add_inline_style') || !function_exists('wp_enqueue_style')) {
			return;
		}
		$tokens = $this->tokens();
		$css = $this->css($tokens);
		$handle = $this->styleHandle();
		$publish = \RazorSoft\ThemeAdapter\Registry::claimHandle($handle, $this->version());
		if ($publish) {
			if (wp_style_is($handle, 'registered') && function_exists('wp_deregister_style')) {
				wp_deregister_style($handle);
			}
			wp_register_style($handle, false, [], $this->version());
			wp_add_inline_style($handle, $css);
		}
		wp_enqueue_style($handle);
		$gaps = is_array($tokens['gaps'] ?? null) ? $tokens['gaps'] : [];
		if ($gaps === [] || !function_exists('wp_register_script') || !function_exists('wp_enqueue_script') || !function_exists('wp_add_inline_script') || !function_exists('plugins_url')) {
			return;
		}
		if ($publish) {
			if (function_exists('wp_script_is') && wp_script_is($handle, 'registered') && function_exists('wp_deregister_script')) {
				wp_deregister_script($handle);
			}
			wp_register_script(
				$handle,
				plugins_url('assets/detect.js', $this->meta['file']),
				[],
				$this->version(),
				true,
			);
			$payload = function_exists('wp_json_encode') ? wp_json_encode(['gaps' => array_values($gaps)]) : false;
			if (!is_string($payload)) {
				return;
			}
			wp_add_inline_script($handle, 'window.razorThemeAdapter=' . $payload . ';', 'before');
		}
		if (function_exists('wp_script_is') && wp_script_is($handle, 'registered')) {
			wp_enqueue_script($handle);
		}
	}

	private function ownerSlug(): string {
		$root = strtolower(str_replace('\\', '/', $this->meta['owner_root']));
		$base = preg_replace('/[^a-z0-9]+/', '-', basename($root)) ?? 'bundle';
		$base = trim((string) $base, '-');
		if ($base === '') {
			$base = 'bundle';
		}
		return $base . '-' . substr(hash('sha256', $root), 0, 8);
	}
}
