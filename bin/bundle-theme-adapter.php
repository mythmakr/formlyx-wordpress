<?php
/**
 * Bundle RazorSoft Theme Adapter v0.1.0 into Formlyx Forms.
 *
 * The WordPress plugin ships the generated directory at
 * vendor/theme-adapter. Site owners do not run this script and do not
 * install the package themselves.
 *
 * Source repository: https://github.com/razorsoftinc/theme-adapter
 * Release tag: v0.1.0
 * Commit: 02a1354e42a53c23ae0f3e2eb0c1e088f8619577
 *
 * From this repository:
 *
 *   php bin/bundle-theme-adapter.php
 *
 * Pass the Theme Adapter checkout when it is not the sibling of this
 * repository:
 *
 *   php bin/bundle-theme-adapter.php /path/to/theme-adapter
 *
 * The script checks that the checkout is exactly tag v0.1.0, that the
 * working tree is clean, and that HEAD is the commit above. It then removes
 * vendor/theme-adapter and runs packages/wordpress/bin/bundle.php. The
 * generated files are the bundler's copy. vendor/theme-adapter-source.json
 * records the tag and commit beside that directory.
 *
 * This script does not modify the Theme Adapter repository.
 *
 * @package Formlyx
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "bundle-theme-adapter.php is a command-line script.\n");
	exit(1);
}

const FORMLYX_THEME_ADAPTER_TAG = 'v0.1.0';
const FORMLYX_THEME_ADAPTER_VERSION = '0.1.0';
const FORMLYX_THEME_ADAPTER_COMMIT = '02a1354e42a53c23ae0f3e2eb0c1e088f8619577';

$plugin_dir = dirname(__DIR__);
$default_source = dirname($plugin_dir) . '/theme-adapter';
$source = $argv[1] ?? $default_source;
if (!is_string($source) || $source === '') {
	fwrite(STDERR, "Usage: php bin/bundle-theme-adapter.php [/path/to/theme-adapter]\n");
	exit(1);
}
if (!str_starts_with($source, '/')) {
	$source = getcwd() . '/' . $source;
}
$source = rtrim(str_replace('\\', '/', $source), '/');
$destination = $plugin_dir . '/vendor/theme-adapter';
$provenance = $plugin_dir . '/vendor/theme-adapter-source.json';
$bundler = $source . '/packages/wordpress/bin/bundle.php';

if (!is_file($source . '/packages/wordpress/load.php') || !is_file($bundler)) {
	fwrite(STDERR, "Theme Adapter checkout is missing packages/wordpress: {$source}\n");
	exit(1);
}

$tag = formlyx_bundle_git($source, 'describe --tags --exact-match HEAD');
$commit = formlyx_bundle_git($source, 'rev-parse HEAD');
$status = formlyx_bundle_git($source, 'status --porcelain');
if ($tag !== FORMLYX_THEME_ADAPTER_TAG || $commit !== FORMLYX_THEME_ADAPTER_COMMIT) {
	fwrite(STDERR, "Expected " . FORMLYX_THEME_ADAPTER_TAG . " at " . FORMLYX_THEME_ADAPTER_COMMIT . ", found {$tag} at {$commit}.\n");
	exit(1);
}
if ($status !== '') {
	fwrite(STDERR, "Theme Adapter checkout is not clean. Commit or stash it before bundling.\n");
	exit(1);
}

formlyx_bundle_remove_tree($destination);
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bundler) . ' ' . escapeshellarg($destination);
passthru($command, $bundle_code);
if ($bundle_code !== 0 || !is_file($destination . '/load.php')) {
	fwrite(STDERR, "Theme Adapter bundler failed.\n");
	exit(1);
}

$remote = formlyx_bundle_git($source, 'remote get-url origin');
$record = [
	'package' => 'razorsoft/wordpress-theme-adapter',
	'version' => FORMLYX_THEME_ADAPTER_VERSION,
	'tag' => FORMLYX_THEME_ADAPTER_TAG,
	'commit' => $commit,
	'repository' => formlyx_bundle_repository($remote),
	'bundler' => 'packages/wordpress/bin/bundle.php',
];
$encoded = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
	fwrite(STDERR, "Could not encode the source record.\n");
	exit(1);
}
if (!is_dir($plugin_dir . '/vendor') && !mkdir($plugin_dir . '/vendor', 0777, true) && !is_dir($plugin_dir . '/vendor')) {
	fwrite(STDERR, "Could not create the vendor directory.\n");
	exit(1);
}
if (file_put_contents($provenance, $encoded . "\n") === false) {
	fwrite(STDERR, "Could not write {$provenance}\n");
	exit(1);
}

echo "Recorded {$tag} ({$commit}) in {$provenance}\n";

/**
 * Run git in the Theme Adapter checkout.
 *
 * @param string $repo Repository path.
 * @param string $args Git arguments.
 * @return string
 */
function formlyx_bundle_git(string $repo, string $args): string {
	$command = 'git -C ' . escapeshellarg($repo) . ' ' . $args;
	$output = [];
	$code = 0;
	exec($command, $output, $code);
	if ($code !== 0) {
		fwrite(STDERR, "git {$args} failed in {$repo}\n");
		exit(1);
	}
	return trim(implode("\n", $output));
}

/**
 * Turn a git remote into an https GitHub URL when the remote is GitHub.
 *
 * @param string $remote Remote URL.
 * @return string
 */
function formlyx_bundle_repository(string $remote): string {
	if (preg_match('#^git@github\.com:([^/]+)/(.+?)(?:\.git)?$#', $remote, $matches) === 1) {
		return 'https://github.com/' . $matches[1] . '/' . $matches[2];
	}
	if (preg_match('#^https://github\.com/([^/]+)/(.+?)(?:\.git)?$#', $remote, $matches) === 1) {
		return 'https://github.com/' . $matches[1] . '/' . $matches[2];
	}
	return $remote;
}

/**
 * Remove a previously generated bundle.
 *
 * @param string $dir Destination directory.
 * @return void
 */
function formlyx_bundle_remove_tree(string $dir): void {
	$plugin_vendor = dirname(__DIR__) . '/vendor/theme-adapter';
	if ($dir !== $plugin_vendor) {
		fwrite(STDERR, "Refusing to remove {$dir}\n");
		exit(1);
	}
	if (!is_dir($dir)) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST,
	);
	foreach ($items as $item) {
		if (!$item instanceof SplFileInfo) {
			continue;
		}
		if ($item->isDir()) {
			rmdir($item->getPathname());
		} else {
			unlink($item->getPathname());
		}
	}
	rmdir($dir);
}
