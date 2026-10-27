<?php
/**
 * Reads the active theme through supported WordPress APIs.
 *
 * Block themes and classic themes that ship theme.json use global settings
 * and global styles. Other classic themes use theme supports and theme mods.
 * Core default colors are not treated as the theme's own palette.
 *
 * @package RazorThemeAdapter
 */

declare(strict_types=1);

namespace RazorSoft\ThemeAdapter\V0_1_0;

if (!defined('ABSPATH') && !defined('RAZOR_THEME_ADAPTER_TEST')) {
	exit;
}

final class Reader {
	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array {
		$theme = $this->theme();
		$block = function_exists('wp_is_block_theme') && wp_is_block_theme();
		$theme_json = function_exists('wp_theme_has_theme_json') && wp_theme_has_theme_json();
		$global = $block || $theme_json;
		$styles = $global ? $this->styles([]) : [];
		$button = $global && is_array($styles['elements']['button'] ?? null) ? $styles['elements']['button'] : [];
		$color = is_array($styles['color'] ?? null) ? $styles['color'] : [];
		$typography = is_array($styles['typography'] ?? null) ? $styles['typography'] : [];
		$border = is_array($styles['border'] ?? null) ? $styles['border'] : [];
		$link = '';
		if ($global && is_array($styles['elements']['link']['color'] ?? null)) {
			$link = $this->string($styles['elements']['link']['color']['text'] ?? null);
		}
		return [
			'stylesheet' => $theme['stylesheet'],
			'name' => $theme['name'],
			'version' => $theme['version'],
			'block' => $block,
			'theme_json' => $theme_json,
			'use_global_styles' => $global,
			'palette' => $global ? $this->palette() : [],
			'classic_palette' => $global ? [] : $this->classic_palette(),
			'classic_background' => $global ? '' : $this->background_mod(),
			'classic_text' => $global ? '' : $this->header_text(),
			'color' => [
				'background' => $this->string($color['background'] ?? null),
				'text' => $this->string($color['text'] ?? null),
			],
			'typography' => [
				'fontFamily' => $this->string($typography['fontFamily'] ?? null),
				'fontSize' => $this->string($typography['fontSize'] ?? null),
				'fontWeight' => $typography['fontWeight'] ?? '',
				'lineHeight' => $typography['lineHeight'] ?? '',
			],
			'border' => [
				'radius' => $border['radius'] ?? '',
				'width' => $border['width'] ?? '',
				'color' => $this->string($border['color'] ?? null),
				'style' => $this->string($border['style'] ?? null),
			],
			'spacing' => $this->spacing(is_array($styles['spacing'] ?? null) ? $styles['spacing'] : []),
			'link' => $link,
			'button' => [
				'background' => $this->string(is_array($button['color'] ?? null) ? ($button['color']['background'] ?? null) : null),
				'color' => $this->string(is_array($button['color'] ?? null) ? ($button['color']['text'] ?? null) : null),
				'borderColor' => $this->string(is_array($button['border'] ?? null) ? ($button['border']['color'] ?? null) : null),
				'borderWidth' => is_array($button['border'] ?? null) ? ($button['border']['width'] ?? '') : '',
				'borderStyle' => $this->string(is_array($button['border'] ?? null) ? ($button['border']['style'] ?? null) : null),
				'radius' => is_array($button['border'] ?? null) ? ($button['border']['radius'] ?? '') : '',
				'padding' => is_array($button['spacing'] ?? null) ? ($button['spacing']['padding'] ?? '') : '',
				'fontFamily' => $this->string(is_array($button['typography'] ?? null) ? ($button['typography']['fontFamily'] ?? null) : null),
				'fontSize' => $this->string(is_array($button['typography'] ?? null) ? ($button['typography']['fontSize'] ?? null) : null),
				'fontWeight' => is_array($button['typography'] ?? null) ? ($button['typography']['fontWeight'] ?? '') : '',
				'lineHeight' => is_array($button['typography'] ?? null) ? ($button['typography']['lineHeight'] ?? '') : '',
			],
		];
	}

	/**
	 * @return array{stylesheet: string, name: string, version: string}
	 */
	private function theme(): array {
		$stylesheet = '';
		$name = '';
		$version = '';
		if (function_exists('wp_get_theme')) {
			$theme = wp_get_theme();
			if (is_object($theme)) {
				if (method_exists($theme, 'get_stylesheet')) {
					$stylesheet = (string) $theme->get_stylesheet();
				}
				if (method_exists($theme, 'get')) {
					$name = (string) $theme->get('Name');
					$version = (string) $theme->get('Version');
				}
			}
		}
		return [
			'stylesheet' => $stylesheet,
			'name' => $name,
			'version' => $version,
		];
	}

	/**
	 * @return array<string, array{value: string, origin: string}>
	 */
	private function palette(): array {
		$merged = [];
		foreach (['default', 'theme', 'custom'] as $origin) {
			$items = $this->settings(['color', 'palette', $origin]);
			if (!is_array($items)) {
				continue;
			}
			foreach ($items as $item) {
				if (!is_array($item)) {
					continue;
				}
				$slug = Value::slug((string) ($item['slug'] ?? ''));
				$color = $this->string($item['color'] ?? null);
				if ($slug === '' || $color === '') {
					continue;
				}
				$merged[$slug] = [
					'value' => $color,
					'origin' => $origin === 'default' ? 'default' : ($origin === 'custom' ? 'custom' : 'theme'),
				];
			}
		}
		return $merged;
	}

	/**
	 * @return array<string, array{value: string, origin: string}>
	 */
	private function classic_palette(): array {
		if (!function_exists('get_theme_support')) {
			return [];
		}
		$support = get_theme_support('editor-color-palette');
		$items = is_array($support) && is_array($support[0] ?? null) ? $support[0] : [];
		$palette = [];
		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}
			$slug = Value::slug((string) ($item['slug'] ?? ''));
			$color = $this->string($item['color'] ?? null);
			if ($slug === '' || $color === '') {
				continue;
			}
			$palette[$slug] = ['value' => $color, 'origin' => 'classic'];
		}
		return $palette;
	}

	private function background_mod(): string {
		if (!function_exists('get_background_color')) {
			return '';
		}
		$color = get_background_color();
		return is_string($color) ? $color : '';
	}

	private function header_text(): string {
		if (!function_exists('get_header_textcolor')) {
			return '';
		}
		$color = get_header_textcolor();
		if (!is_string($color) || strtolower($color) === 'blank') {
			return '';
		}
		return $color;
	}

	/**
	 * @param array<int|string, mixed> $path
	 * @return array<string, mixed>
	 */
	private function styles(array $path): array {
		if (!function_exists('wp_get_global_styles')) {
			return [];
		}
		$value = wp_get_global_styles($path);
		return is_array($value) ? $value : [];
	}

	/**
	 * @param array<int, string> $path
	 */
	private function settings(array $path): mixed {
		if (!function_exists('wp_get_global_settings')) {
			return null;
		}
		return wp_get_global_settings($path);
	}

	/**
	 * @param array<string, mixed> $spacing
	 * @return array{block: string, inline: string}
	 */
	private function spacing(array $spacing): array {
		$gap = $spacing['blockGap'] ?? '';
		if (is_array($gap)) {
			return [
				'block' => $this->string($gap['top'] ?? null),
				'inline' => $this->string($gap['left'] ?? ($gap['right'] ?? null)),
			];
		}
		return [
			'block' => $this->string($gap),
			'inline' => '',
		];
	}

	private function string(mixed $value): string {
		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}
		return is_string($value) ? trim($value) : '';
	}
}
