<?php
/**
 * Strict CSS values for theme tokens. Theme data is rejected rather than escaped into a declaration.
 *
 * @package RazorThemeAdapter
 */

declare(strict_types=1);

namespace RazorSoft\ThemeAdapter\V0_1_0;

if (!defined('ABSPATH') && !defined('RAZOR_THEME_ADAPTER_TEST')) {
	exit;
}

final class Value {
	public static function color(string $value): ?string {
		$value = trim($value);
		if ($value === '' || strlen($value) > 160) {
			return null;
		}
		if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) === 1) {
			return strtolower($value);
		}
		$keyword = strtolower($value);
		if (in_array($keyword, ['transparent', 'currentcolor', 'canvas', 'canvastext', 'buttonface', 'buttontext', 'inherit'], true)) {
			return $keyword;
		}
		if (preg_match('/^var\(--(?:wp--preset--color|razor-theme-color)--[a-z0-9-]{1,40}\)$/', $value) === 1) {
			return $value;
		}
		if (preg_match('/^(?:rgb|hsl)a?\(\s*[0-9.%\s,\/]+\)$/', $value) === 1 && !preg_match('/[^0-9.%\s,\/()rgbahsl]/', $value)) {
			return preg_replace('/\s+/', ' ', $value) ?? $value;
		}
		return null;
	}

	public static function length(string $value): ?string {
		$value = trim($value);
		if ($value === '0') {
			return '0';
		}
		if (preg_match('/^(?:[0-9]|[1-9][0-9]{0,3})(?:\.[0-9]{1,4})?(?:px|rem|em|%|vh|vw|ch|ex)$/', $value) === 1) {
			return $value;
		}
		if (preg_match('/^var\(--wp--preset--(?:spacing|font-size)--[a-z0-9-]{1,40}\)$/', $value) === 1) {
			return $value;
		}
		if (preg_match('/^clamp\(\s*([0-9.]+(?:px|rem|em|%|vh|vw))\s*,\s*([0-9.]+(?:px|rem|em|%|vh|vw))(?:\s+\+\s+([0-9.]+(?:px|rem|em|%|vh|vw)))?\s*,\s*([0-9.]+(?:px|rem|em|%|vh|vw))\s*\)$/', $value, $match) === 1) {
			$middle = $match[2] . (isset($match[3]) && $match[3] !== '' ? ' + ' . $match[3] : '');
			return 'clamp(' . $match[1] . ', ' . $middle . ', ' . $match[4] . ')';
		}
		return null;
	}

	public static function lengths(string $value, int $max = 4): ?string {
		$parts = preg_split('/\s+/', trim($value)) ?: [];
		if ($parts === [] || count($parts) > $max) {
			return null;
		}
		$safe = [];
		foreach ($parts as $part) {
			$length = self::length($part);
			if ($length === null) {
				return null;
			}
			$safe[] = $length;
		}
		return implode(' ', $safe);
	}

	public static function font_family(string $value): ?string {
		$value = trim($value);
		if ($value === '' || strlen($value) > 240) {
			return null;
		}
		if (strtolower($value) === 'inherit') {
			return 'inherit';
		}
		if (preg_match('/^var\(--wp--preset--font-family--[a-z0-9-]{1,40}\)$/', $value) === 1) {
			return $value;
		}
		$parts = str_getcsv($value, ',', '"', '\\');
		if ($parts === [] || count($parts) > 8) {
			return null;
		}
		$safe = [];
		foreach ($parts as $part) {
			$family = trim((string) $part);
			if ($family === '') {
				return null;
			}
			if (
				(str_starts_with($family, '"') && str_ends_with($family, '"'))
				|| (str_starts_with($family, "'") && str_ends_with($family, "'"))
			) {
				$inner = substr($family, 1, -1);
				if ($inner === '' || preg_match('/["\'\\\\;{}<>]/', $inner) === 1) {
					return null;
				}
				$safe[] = '"' . $inner . '"';
				continue;
			}
			if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $family) !== 1 && !in_array(strtolower($family), ['sans-serif', 'serif', 'monospace', 'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'ui-rounded'], true)) {
				return null;
			}
			$safe[] = strtolower($family) === 'sans-serif' || str_contains(strtolower($family), ' ')
				? $family
				: $family;
		}
		return implode(', ', $safe);
	}

	public static function font_weight(string $value): ?string {
		$value = strtolower(trim($value));
		if (in_array($value, ['normal', 'bold', 'inherit'], true)) {
			return $value;
		}
		if (preg_match('/^[1-9]00$/', $value) === 1) {
			return $value;
		}
		return null;
	}

	public static function line_height(string $value): ?string {
		$value = trim($value);
		if (in_array(strtolower($value), ['normal', 'inherit'], true)) {
			return strtolower($value);
		}
		if (preg_match('/^(?:[0-9]|[1-9][0-9]?)(?:\.[0-9]{1,4})?$/', $value) === 1) {
			return $value;
		}
		return self::length($value);
	}

	public static function border_style(string $value): ?string {
		$value = strtolower(trim($value));
		return in_array($value, ['none', 'solid', 'dashed', 'dotted', 'double'], true) ? $value : null;
	}

	public static function slug(string $value): string {
		$value = strtolower(trim($value));
		return preg_match('/^[a-z0-9-]{1,80}$/', $value) === 1 ? $value : '';
	}

	/**
	 * @return array{0:int,1:int,2:int}|null
	 */
	public static function hex_rgb(string $color): ?array {
		$color = self::color($color);
		if ($color === null || !str_starts_with($color, '#')) {
			return null;
		}
		$hex = substr($color, 1);
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if (strlen($hex) < 6) {
			return null;
		}
		return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
	}

	public static function contrast(string $color): ?string {
		$rgb = self::hex_rgb($color);
		if ($rgb === null) {
			return null;
		}
		$channels = [];
		foreach ($rgb as $channel) {
			$unit = $channel / 255;
			$channels[] = $unit <= 0.03928 ? $unit / 12.92 : (($unit + 0.055) / 1.055) ** 2.4;
		}
		$luminance = (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
		return $luminance > 0.55 ? '#1a1a1a' : '#ffffff';
	}
}
