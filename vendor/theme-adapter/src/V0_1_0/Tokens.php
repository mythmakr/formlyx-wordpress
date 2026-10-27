<?php
/**
 * Turns a theme snapshot into stable design tokens and CSS custom properties.
 *
 * The snapshot is plain data. WordPress API calls live in Reader.
 *
 * @package RazorThemeAdapter
 */

declare(strict_types=1);

namespace RazorSoft\ThemeAdapter\V0_1_0;

if (!defined('ABSPATH') && !defined('RAZOR_THEME_ADAPTER_TEST')) {
	exit;
}

final class Tokens {
	/**
	 * @return array<string, string>
	 */
	public static function properties(): array {
		return [
			'color-primary' => '--razor-theme-color-primary',
			'color-primary-contrast' => '--razor-theme-color-primary-contrast',
			'color-secondary' => '--razor-theme-color-secondary',
			'color-secondary-contrast' => '--razor-theme-color-secondary-contrast',
			'color-background' => '--razor-theme-color-background',
			'color-text' => '--razor-theme-color-text',
			'color-border' => '--razor-theme-color-border',
			'color-link' => '--razor-theme-color-link',
			'font-family' => '--razor-theme-font-family',
			'font-size' => '--razor-theme-font-size',
			'font-weight' => '--razor-theme-font-weight',
			'line-height' => '--razor-theme-line-height',
			'radius' => '--razor-theme-radius',
			'border-width' => '--razor-theme-border-width',
			'spacing-block' => '--razor-theme-spacing-block',
			'spacing-inline' => '--razor-theme-spacing-inline',
			'button-background' => '--razor-theme-button-background',
			'button-color' => '--razor-theme-button-color',
			'button-border-color' => '--razor-theme-button-border-color',
			'button-border-width' => '--razor-theme-button-border-width',
			'button-border-style' => '--razor-theme-button-border-style',
			'button-radius' => '--razor-theme-button-radius',
			'button-padding-block' => '--razor-theme-button-padding-block',
			'button-padding-inline' => '--razor-theme-button-padding-inline',
			'button-font-family' => '--razor-theme-button-font-family',
			'button-font-size' => '--razor-theme-button-font-size',
			'button-font-weight' => '--razor-theme-button-font-weight',
			'button-line-height' => '--razor-theme-button-line-height',
		];
	}

	/**
	 * @param array<string, mixed> $source
	 * @return array<string, mixed>
	 */
	public static function normalize(array $source): array {
		$global = !empty($source['use_global_styles']);
		$palette = self::palette(is_array($source['palette'] ?? null) ? $source['palette'] : []);
		$classic_palette = self::palette(is_array($source['classic_palette'] ?? null) ? $source['classic_palette'] : []);
		$button = is_array($source['button'] ?? null) ? $source['button'] : [];
		$color = is_array($source['color'] ?? null) ? $source['color'] : [];
		$typography = is_array($source['typography'] ?? null) ? $source['typography'] : [];
		$border = is_array($source['border'] ?? null) ? $source['border'] : [];
		$spacing = is_array($source['spacing'] ?? null) ? $source['spacing'] : [];

		$background = $global
			? self::color_choice($color['background'] ?? null, $palette, ['base', 'background'])
			: self::classic_hex($source['classic_background'] ?? null, $classic_palette, ['base', 'background']);
		$text = $global
			? self::color_choice($color['text'] ?? null, $palette, ['contrast', 'foreground', 'text'])
			: self::classic_hex($source['classic_text'] ?? null, $classic_palette, ['contrast', 'foreground', 'text']);
		$primary = $global
			? self::color_choice($button['background'] ?? null, $palette, ['primary', 'accent', 'accent-1'])
			: self::palette_choice($classic_palette, ['primary', 'accent', 'accent-1']);
		if ($primary === null && $global) {
			$primary = self::color_choice($source['link'] ?? null, $palette, ['link', 'accent']);
		}
		if ($primary === null && !$global) {
			$primary = self::palette_choice($classic_palette, ['link']);
		}
		$primary_contrast = $global
			? self::color_choice($button['color'] ?? null, $palette, ['base', 'background'])
			: null;
		$secondary = $global
			? self::palette_choice($palette, ['secondary', 'accent-2', 'accent-3'])
			: self::palette_choice($classic_palette, ['secondary', 'accent-2']);
		$link = $global
			? self::color_choice($source['link'] ?? null, $palette, ['link', 'accent', 'primary'])
			: self::palette_choice($classic_palette, ['link', 'accent', 'primary']);

		$tokens = [];
		$tokens['color-background'] = $background ?? self::fallback_color('canvas');
		$tokens['color-text'] = $text ?? self::fallback_color('canvastext');
		$tokens['color-primary'] = $primary ?? self::fallback_color('canvastext');
		$tokens['color-primary-contrast'] = $primary_contrast
			?? self::derived_contrast($tokens['color-primary']['value'])
			?? self::fallback_color('canvas');
		$tokens['color-secondary'] = $secondary ?? self::derived('color-mix(in srgb, var(--razor-theme-color-text) 8%, var(--razor-theme-color-background))');
		$tokens['color-secondary-contrast'] = self::copy_or_text($secondary, $tokens['color-text']);
		$tokens['color-link'] = $link ?? $tokens['color-primary'];
		$border_color = $global ? self::direct_color($border['color'] ?? null) : null;
		$tokens['color-border'] = $border_color ?? self::derived('color-mix(in srgb, var(--razor-theme-color-text) 22%, transparent)');

		$tokens['font-family'] = self::font($typography['fontFamily'] ?? null) ?? self::fallback_font();
		$tokens['font-size'] = self::length_token($typography['fontSize'] ?? null) ?? self::keyword('inherit', 'font-size');
		$tokens['font-weight'] = self::weight($typography['fontWeight'] ?? null) ?? self::keyword('inherit', 'font-weight');
		$tokens['line-height'] = self::line($typography['lineHeight'] ?? null) ?? self::keyword('inherit', 'line-height');

		$radius = self::radius_token($global ? ($border['radius'] ?? null) : null);
		$tokens['radius'] = $radius ?? self::fallback_length('0.25rem', true);
		$tokens['border-width'] = self::length_token($global ? ($border['width'] ?? null) : null) ?? self::fallback_length('1px', false);
		[$block, $inline] = self::spacing_pair($global ? ($spacing['block'] ?? null) : null, $global ? ($spacing['inline'] ?? null) : null);
		$tokens['spacing-block'] = $block;
		$tokens['spacing-inline'] = $inline;

		$button_background = $global ? self::direct_color($button['background'] ?? null) : null;
		$tokens['button-background'] = $button_background ?? self::refine_copy($tokens['color-primary'], !$global);
		$button_color = $global ? self::direct_color($button['color'] ?? null) : null;
		$tokens['button-color'] = $button_color ?? self::refine_copy($tokens['color-primary-contrast'], !$global);
		$tokens['button-border-color'] = ($global ? self::direct_color($button['borderColor'] ?? null) : null) ?? $tokens['color-border'];
		$tokens['button-border-width'] = self::length_token($button['borderWidth'] ?? null) ?? self::fallback_length('1px', !$global && $button_background === null);
		$tokens['button-border-style'] = self::style_token($button['borderStyle'] ?? null) ?? self::keyword('solid', 'button-border-style', !$global && $button_background === null);
		$tokens['button-radius'] = self::radius_token($button['radius'] ?? null) ?? self::refine_copy($tokens['radius'], !$global);
		[$pad_block, $pad_inline] = self::padding_pair($button['padding'] ?? null);
		$tokens['button-padding-block'] = $pad_block ?? self::fallback_length('0.6em', $global === false);
		$tokens['button-padding-inline'] = $pad_inline ?? self::fallback_length('1em', $global === false);
		$tokens['button-font-family'] = self::font($button['fontFamily'] ?? null) ?? $tokens['font-family'];
		$tokens['button-font-size'] = self::length_token($button['fontSize'] ?? null) ?? self::keyword('inherit', 'button-font-size', !$global);
		$tokens['button-font-weight'] = self::weight($button['fontWeight'] ?? null) ?? self::keyword('600', 'button-font-weight', false);
		$tokens['button-line-height'] = self::line($button['lineHeight'] ?? null) ?? self::keyword('1.2', 'button-line-height', false);

		if (!$global) {
			$tokens['button-background']['refine'] = true;
			$tokens['button-color']['refine'] = true;
			$tokens['button-radius']['refine'] = true;
			$tokens['button-padding-block']['refine'] = true;
			$tokens['button-padding-inline']['refine'] = true;
			$tokens['radius']['refine'] = $tokens['radius']['source'] !== 'theme';
		}

		$kind = !empty($source['block']) ? 'block' : ($global || $classic_palette !== [] || !empty($source['classic_background']) || !empty($source['classic_text']) ? 'classic' : 'fallback');
		if (!$global && empty($source['block']) && $classic_palette === [] && empty($source['classic_background']) && empty($source['classic_text'])) {
			$kind = 'fallback';
		}
		$tokens = self::sanitize_token_map($tokens);
		return [
			'kind' => $kind,
			'stylesheet' => Value::slug((string) ($source['stylesheet'] ?? '')),
			'name' => self::label($source['name'] ?? ''),
			'version' => self::label($source['version'] ?? ''),
			'block' => !empty($source['block']),
			'themeJson' => !empty($source['theme_json']),
			'tokens' => $tokens,
			'gaps' => self::gaps($tokens),
		];
	}

	/**
	 * Drops any token a filter or theme value could not prove safe.
	 *
	 * @param array<string, mixed> $tokens
	 * @return array<string, array{value: string, source: string, refine: bool}>
	 */
	public static function seal(array $tokens): array {
		return self::sanitize_token_map($tokens);
	}

	/**
	 * @param array<string, mixed> $tokens
	 */
	public static function stylesheet(array $tokens): string {
		$map = self::properties();
		$values = is_array($tokens['tokens'] ?? null) ? self::sanitize_token_map($tokens['tokens']) : [];
		$lines = [':root {'];
		foreach ($map as $name => $property) {
			if (!isset($values[$name]['value']) || !is_string($values[$name]['value'])) {
				continue;
			}
			$lines[] = '  ' . $property . ': ' . $values[$name]['value'] . ';';
		}
		$stylesheet = Value::slug((string) ($tokens['stylesheet'] ?? ''));
		if ($stylesheet !== '') {
			$lines[] = '  --razor-theme-stylesheet: "' . $stylesheet . '";';
		}
		$lines[] = '}';
		return implode("\n", $lines) . "\n";
	}

	/**
	 * @param array<string, array{value?: string, source?: string, refine?: bool}> $tokens
	 * @return array<int, string>
	 */
	public static function gaps(array $tokens): array {
		$gaps = [];
		foreach (self::properties() as $name => $property) {
			unset($property);
			$token = $tokens[$name] ?? null;
			if (!is_array($token)) {
				continue;
			}
			if (($token['source'] ?? '') === 'fallback' || !empty($token['refine'])) {
				$gaps[] = $name;
			}
		}
		return $gaps;
	}

	/**
	 * @param array<string, mixed> $palette
	 * @return array<string, array{value: string, source: string, refine: bool}>
	 */
	private static function palette(array $palette): array {
		$clean = [];
		foreach ($palette as $slug => $item) {
			$key = Value::slug((string) $slug);
			if ($key === '') {
				continue;
			}
			$value = is_array($item) ? ($item['value'] ?? null) : $item;
			$color = is_string($value) ? Value::color($value) : null;
			if ($color === null) {
				continue;
			}
			$origin = is_array($item) ? (string) ($item['origin'] ?? 'theme') : 'theme';
			$source = in_array($origin, ['theme', 'custom', 'classic'], true) ? ($origin === 'custom' ? 'theme' : $origin) : 'fallback';
			$clean[$key] = self::token($color, $source, $source === 'fallback');
		}
		return $clean;
	}

	/**
	 * @param array<string, array{value: string, source: string, refine: bool}> $palette
	 * @param array<int, string> $slugs
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function palette_choice(array $palette, array $slugs): ?array {
		foreach ($slugs as $slug) {
			if (isset($palette[$slug])) {
				return $palette[$slug];
			}
		}
		return null;
	}

	/**
	 * @param array<string, array{value: string, source: string, refine: bool}> $palette
	 * @param array<int, string> $slugs
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function color_choice(mixed $direct, array $palette, array $slugs): ?array {
		$chosen = self::direct_color($direct);
		return $chosen ?? self::palette_choice($palette, $slugs);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function direct_color(mixed $value): ?array {
		if (!is_string($value)) {
			return null;
		}
		$color = Value::color($value);
		if ($color === null) {
			return null;
		}
		return self::token($color, 'theme', false);
	}

	/**
	 * @param array<string, array{value: string, source: string, refine: bool}> $palette
	 * @param array<int, string> $slugs
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function classic_hex(mixed $hex, array $palette, array $slugs): ?array {
		if (is_string($hex)) {
			$color = Value::color(self::hash_hex($hex));
			if ($color !== null && $color !== 'blank') {
				return self::token($color, 'classic', false);
			}
		}
		return self::palette_choice($palette, $slugs);
	}

	private static function hash_hex(string $value): string {
		$value = trim($value);
		if ($value === '' || strtolower($value) === 'blank') {
			return '';
		}
		return str_starts_with($value, '#') ? $value : '#' . $value;
	}

	/**
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function font(mixed $value): ?array {
		if (!is_string($value)) {
			return null;
		}
		$font = Value::font_family($value);
		return $font === null ? null : self::token($font, 'theme', false);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function length_token(mixed $value): ?array {
		if (is_array($value)) {
			$value = self::uniform_box($value);
		}
		if (!is_string($value) && !is_int($value) && !is_float($value)) {
			return null;
		}
		$text = is_string($value) ? $value : (string) $value;
		if (is_numeric($text) && (float) $text >= 0 && (float) $text <= 50) {
			$text .= 'px';
		}
		$length = Value::lengths($text, 1);
		return $length === null ? null : self::token($length, 'theme', false);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function radius_token(mixed $value): ?array {
		if (is_array($value)) {
			$corners = [];
			foreach (['topLeft', 'topRight', 'bottomRight', 'bottomLeft', 'top', 'right', 'bottom', 'left'] as $key) {
				if (isset($value[$key]) && is_string($value[$key]) && trim($value[$key]) !== '') {
					$corners[] = trim($value[$key]);
				}
			}
			$value = $corners === [] ? self::uniform_box($value) : implode(' ', array_slice($corners, 0, 4));
		}
		if (!is_string($value)) {
			return null;
		}
		$radius = Value::lengths($value, 4);
		return $radius === null ? null : self::token($radius, 'theme', false);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function weight(mixed $value): ?array {
		if (is_int($value) || is_float($value)) {
			$value = (string) (int) $value;
		}
		if (!is_string($value)) {
			return null;
		}
		$weight = Value::font_weight($value);
		return $weight === null ? null : self::token($weight, 'theme', false);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function line(mixed $value): ?array {
		if (is_int($value) || is_float($value)) {
			$value = (string) $value;
		}
		if (!is_string($value)) {
			return null;
		}
		$line = Value::line_height($value);
		return $line === null ? null : self::token($line, 'theme', false);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function style_token(mixed $value): ?array {
		if (!is_string($value)) {
			return null;
		}
		$style = Value::border_style($value);
		return $style === null ? null : self::token($style, 'theme', false);
	}

	/**
	 * @return array{0: array{value: string, source: string, refine: bool}|null, 1: array{value: string, source: string, refine: bool}|null}
	 */
	private static function padding_pair(mixed $padding): array {
		if (is_string($padding)) {
			$parts = Value::lengths($padding, 4);
			if ($parts === null) {
				return [null, null];
			}
			$list = explode(' ', $parts);
			$block = $list[0];
			$inline = $list[1] ?? $list[0];
			if (count($list) === 4) {
				$inline = $list[1];
			}
			return [self::token($block, 'theme', false), self::token($inline, 'theme', false)];
		}
		if (!is_array($padding)) {
			return [null, null];
		}
		$top = self::length_token($padding['top'] ?? null);
		$right = self::length_token($padding['right'] ?? null);
		return [$top, $right ?? $top];
	}

	/**
	 * @return array{0: array{value: string, source: string, refine: bool}, 1: array{value: string, source: string, refine: bool}}
	 */
	private static function spacing_pair(mixed $block, mixed $inline): array {
		$block_token = self::length_token($block) ?? self::fallback_length('1rem', true);
		$inline_token = self::length_token($inline) ?? $block_token;
		if (is_string($block) && str_contains($block, ' ')) {
			$parts = Value::lengths($block, 2);
			if ($parts !== null) {
				$list = explode(' ', $parts);
				$block_token = self::token($list[0], 'theme', false);
				$inline_token = self::token($list[1] ?? $list[0], 'theme', false);
			}
		}
		return [$block_token, $inline_token];
	}

	/**
	 * @param array<string, mixed> $box
	 */
	private static function uniform_box(array $box): ?string {
		$values = [];
		foreach ($box as $value) {
			if (is_string($value) && trim($value) !== '') {
				$values[] = trim($value);
			}
		}
		if ($values === []) {
			return null;
		}
		return count(array_unique($values)) === 1 ? $values[0] : null;
	}

	/**
	 * @param array{value: string, source: string, refine: bool} $primary
	 * @return array{value: string, source: string, refine: bool}|null
	 */
	private static function derived_contrast(string $color): ?array {
		$contrast = Value::contrast($color);
		return $contrast === null ? null : self::derived($contrast);
	}

	/**
	 * @param array{value: string, source: string, refine: bool}|null $secondary
	 * @param array{value: string, source: string, refine: bool} $text
	 * @return array{value: string, source: string, refine: bool}
	 */
	private static function copy_or_text(?array $secondary, array $text): array {
		if ($secondary === null) {
			return $text;
		}
		$contrast = Value::contrast($secondary['value']);
		return $contrast === null ? $text : self::derived($contrast);
	}

	/**
	 * @param array{value: string, source: string, refine: bool} $token
	 * @return array{value: string, source: string, refine: bool}
	 */
	private static function refine_copy(array $token, bool $refine): array {
		$copy = $token;
		if ($refine) {
			$copy['refine'] = true;
		}
		return $copy;
	}

	/**
	 * @return array{value: string, source: string, refine: bool}
	 */
	private static function fallback_color(string $keyword): array {
		return self::token($keyword, 'fallback', true);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}
	 */
	private static function fallback_font(): array {
		return self::token('inherit', 'fallback', true);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}
	 */
	private static function fallback_length(string $value, bool $refine): array {
		return self::token($value, 'fallback', $refine);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}
	 */
	private static function keyword(string $value, string $name, bool $refine = false): array {
		unset($name);
		return self::token($value, $refine ? 'fallback' : 'derived', $refine);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}
	 */
	private static function derived(string $value): array {
		return self::token($value, 'derived', false);
	}

	/**
	 * @return array{value: string, source: string, refine: bool}
	 */
	private static function token(string $value, string $source, bool $refine): array {
		return [
			'value' => $value,
			'source' => $source,
			'refine' => $refine,
		];
	}

	/**
	 * @param array<string, mixed> $tokens
	 * @return array<string, array{value: string, source: string, refine: bool}>
	 */
	private static function sanitize_token_map(array $tokens): array {
		$clean = [];
		foreach (self::properties() as $name => $property) {
			unset($property);
			$item = $tokens[$name] ?? null;
			if (!is_array($item) || !is_string($item['value'] ?? null)) {
				continue;
			}
			$value = self::safe_declaration($name, $item['value']);
			if ($value === null) {
				continue;
			}
			$source = (string) ($item['source'] ?? 'fallback');
			if (!in_array($source, ['theme', 'classic', 'derived', 'fallback'], true)) {
				$source = 'fallback';
			}
			$clean[$name] = [
				'value' => $value,
				'source' => $source,
				'refine' => $source === 'fallback' || !empty($item['refine']),
			];
		}
		return $clean;
	}

	private static function safe_declaration(string $name, string $value): ?string {
		if (str_contains($value, '<') || str_contains($value, '>') || str_contains($value, ';') || str_contains($value, '{') || str_contains($value, '}') || str_contains($value, '/*')) {
			return null;
		}
		if (str_starts_with($name, 'color-') || str_contains($name, 'background') || str_ends_with($name, '-color')) {
			if (str_starts_with($value, 'color-mix(')) {
				return preg_match('/^color-mix\(in srgb, var\(--razor-theme-color-(?:text|primary|background)\) (?:[1-9][0-9]?|100)%, (?:var\(--razor-theme-color-(?:text|primary|background)\)|transparent)\)$/', $value) === 1
					? $value
					: null;
			}
			return Value::color($value);
		}
		if (str_contains($name, 'font-family')) {
			return Value::font_family($value);
		}
		if (str_contains($name, 'font-weight')) {
			return Value::font_weight($value);
		}
		if (str_contains($name, 'line-height')) {
			return Value::line_height($value);
		}
		if (str_ends_with($name, 'style')) {
			return Value::border_style($value);
		}
		if ($value === 'inherit') {
			return 'inherit';
		}
		return str_contains($name, 'radius') ? Value::lengths($value, 4) : Value::lengths($value, 1);
	}

	private static function label(mixed $value): string {
		if (!is_string($value)) {
			return '';
		}
		$value = trim(strip_tags($value));
		$value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
		return strlen($value) > 80 ? substr($value, 0, 80) : $value;
	}
}
