<?php
/**
 * Tracks every bundled copy loaded in this request.
 *
 * This file is part of the stable loader. A later package version must keep
 * register(), resolve(), runtimes(), ownerCount(), and claimHandle() compatible,
 * because the first copy to load is the one whose class definition stays in memory.
 *
 * @package RazorThemeAdapter
 */

declare(strict_types=1);

namespace RazorSoft\ThemeAdapter;

if (!defined('ABSPATH') && !defined('RAZOR_THEME_ADAPTER_TEST')) {
	exit;
}

if (class_exists(Registry::class, false)) {
	return;
}

class AmbiguousAdapter extends \RuntimeException {
}

final class Registry {
	/**
	 * @var array<string, array{meta: array{version: string, dir: string, file: string, owner: string, owner_root: string}, factory: callable, runtime: object|null}>
	 */
	private static array $copies = [];

	/**
	 * Highest version that has claimed a WordPress handle during this request.
	 *
	 * @var array<string, string>
	 */
	private static array $handles = [];

	/**
	 * @param callable(array{version: string, dir: string, file: string, owner: string, owner_root: string}): object $factory
	 */
	public static function register(string $version, string $packageDir, callable $factory): object {
		$packageDir = rtrim(str_replace('\\', '/', $packageDir), '/');
		$requirer = self::requiringFile($packageDir) ?? $packageDir . '/load.php';
		$ownerRoot = self::ownerRoot($requirer);
		$meta = [
			'version' => $version,
			'dir' => $packageDir,
			'file' => $packageDir . '/load.php',
			'owner' => $ownerRoot,
			'owner_root' => $ownerRoot,
		];
		$id = $ownerRoot . "\0" . $version . "\0" . $packageDir;
		if (!isset(self::$copies[$id])) {
			self::$copies[$id] = [
				'meta' => $meta,
				'factory' => $factory,
				'runtime' => null,
			];
		}
		return self::runtime($id);
	}

	public static function resolve(): object {
		if (self::$copies === []) {
			throw new AmbiguousAdapter('Theme Adapter is not loaded.');
		}
		$id = self::matchingCopyId();
		if ($id === null) {
			throw new AmbiguousAdapter('More than one Theme Adapter bundle is loaded. Call the runtime returned by the calling plugin\'s load.php.');
		}
		return self::runtime($id);
	}

	/**
	 * One runtime per owner. An owner that loaded two copies contributes its newer copy.
	 *
	 * @return array<int, object>
	 */
	public static function runtimes(): array {
		$list = [];
		foreach (array_keys(self::grouped()) as $owner) {
			$id = self::preferredId($owner);
			if ($id !== null) {
				$list[] = self::runtime($id);
			}
		}
		return $list;
	}

	public static function ownerCount(): int {
		return count(self::grouped());
	}

	/**
	 * Let the newer runtime replace a handle an older runtime already published.
	 * An equal version keeps the existing claim.
	 */
	public static function claimHandle(string $handle, string $version): bool {
		$current = self::$handles[$handle] ?? null;
		if ($current !== null && version_compare($version, $current, '<=')) {
			return false;
		}
		self::$handles[$handle] = $version;
		return true;
	}

	public static function ownerRoot(string $path): string {
		$path = str_replace('\\', '/', $path);
		if (preg_match('#^(.*?/(?:plugins|mu-plugins|themes)/[^/]+)#', $path, $matches) === 1) {
			return $matches[1];
		}
		$dir = dirname($path);
		if (in_array(basename($dir), ['includes', 'src', 'admin', 'public', 'blocks', 'templates'], true)) {
			return dirname($dir);
		}
		return $dir;
	}

	private static function matchingCopyId(): ?string {
		$groups = self::grouped();
		if (count($groups) === 1) {
			return self::preferredId((string) array_key_first($groups));
		}
		$caller = self::callingFile();
		if ($caller === null) {
			return null;
		}
		$owner = self::ownerRoot($caller);
		if (!isset($groups[$owner])) {
			return null;
		}
		return self::preferredId($owner);
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	private static function grouped(): array {
		$groups = [];
		foreach (self::$copies as $id => $copy) {
			$groups[$copy['meta']['owner']][] = $id;
		}
		return $groups;
	}

	private static function preferredId(string $owner): ?string {
		$ids = self::grouped()[$owner] ?? [];
		if ($ids === []) {
			return null;
		}
		usort($ids, static function (string $left, string $right): int {
			$byVersion = version_compare(self::$copies[$right]['meta']['version'], self::$copies[$left]['meta']['version']);
			if ($byVersion !== 0) {
				return $byVersion;
			}
			return strcmp(self::$copies[$left]['meta']['dir'], self::$copies[$right]['meta']['dir']);
		});
		return $ids[0];
	}

	private static function runtime(string $id): object {
		if (self::$copies[$id]['runtime'] === null) {
			$created = (self::$copies[$id]['factory'])(self::$copies[$id]['meta']);
			if (!is_object($created)) {
				throw new \RuntimeException('Theme Adapter bundle did not return a runtime.');
			}
			self::$copies[$id]['runtime'] = $created;
		}
		return self::$copies[$id]['runtime'];
	}

	private static function requiringFile(string $packageDir): ?string {
		// The executing loader file may belong to a bundle that loaded earlier.
		// Skip every bundle directory so the owner is the plugin that required this copy.
		$dirs = [$packageDir];
		foreach (self::$copies as $copy) {
			$dirs[] = $copy['meta']['dir'];
		}
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
			$file = str_replace('\\', '/', $frame['file'] ?? '');
			if ($file === '' || self::insidePackage($file, $dirs)) {
				continue;
			}
			return $file;
		}
		return null;
	}

	private static function callingFile(): ?string {
		$dirs = [];
		foreach (self::$copies as $copy) {
			$dirs[] = $copy['meta']['dir'];
		}
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
			$file = str_replace('\\', '/', $frame['file'] ?? '');
			if ($file === '' || self::insidePackage($file, $dirs)) {
				continue;
			}
			return $file;
		}
		return null;
	}

	/**
	 * @param array<int, string> $dirs
	 */
	private static function insidePackage(string $file, array $dirs): bool {
		foreach ($dirs as $dir) {
			if (str_starts_with($file, $dir . '/')) {
				return true;
			}
		}
		return false;
	}
}
