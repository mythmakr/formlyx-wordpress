#!/usr/bin/env python3
"""Keep only runtime plugin files in the archive produced by wp-scripts."""

import sys
import zipfile
from pathlib import Path

ARCHIVE = Path("formlyx-forms.zip")
ROOT = "formlyx-forms/"
ALLOWED_FILES = {
    ROOT + "formlyx.php",
    ROOT + "uninstall.php",
    ROOT + "readme.txt",
    ROOT + "license.txt",
}
ALLOWED_PREFIXES = (
    ROOT + "includes/",
    ROOT + "languages/",
    ROOT + "assets/",
    ROOT + "build/",
    ROOT + "src/",
)
ADAPTER_PREFIX = ROOT + "vendor/theme-adapter/"
ADAPTER_META = ROOT + "vendor/theme-adapter-source.json"
BLOCKED_NAMES = {
    ".gitkeep",
    "package.json",
    "README.md",
}


def allowed(name: str) -> bool:
    filename = name.rsplit("/", 1)[-1]
    if name.endswith("/") or filename.endswith(".map") or filename == ".gitkeep":
        return False
    if name == ADAPTER_META or name.startswith(ADAPTER_PREFIX):
        return True
    if filename in BLOCKED_NAMES:
        return False
    if name in ALLOWED_FILES:
        return True
    return name.startswith(ALLOWED_PREFIXES)


def adapter_files() -> list[tuple[str, Path]]:
    """Files wp-scripts omits because its default file list excludes vendor/."""
    bundle = Path("vendor/theme-adapter")
    record = Path("vendor/theme-adapter-source.json")
    if not (bundle / "load.php").is_file() or not record.is_file():
        print("Bundled Theme Adapter is missing.", file=sys.stderr)
        raise SystemExit(1)
    files = [(ADAPTER_META, record)]
    for path in sorted(bundle.rglob("*")):
        if not path.is_file():
            continue
        name = ROOT + path.as_posix()
        if not allowed(name):
            continue
        files.append((name, path))
    return files


def source_files() -> list[tuple[str, Path]]:
    """Readable Gutenberg source omitted by wp-scripts export-ignore rules."""
    files: list[tuple[str, Path]] = []
    for path in sorted(Path("src").rglob("*")):
        if not path.is_file():
            continue
        name = ROOT + path.as_posix()
        if not allowed(name):
            continue
        files.append((name, path))
    return files


def root_files() -> list[tuple[str, Path]]:
    """
    Root runtime files.

    wp-scripts discovers `{package-name}.php`. This plugin keeps `formlyx.php`
    inside the `formlyx-forms/` directory, matching the submitted 1.0.2 package.
    """
    files: list[tuple[str, Path]] = []
    for relative in (
        "formlyx.php",
        "uninstall.php",
        "readme.txt",
        "license.txt",
    ):
        path = Path(relative)
        if not path.is_file():
            print(f"Required plugin file missing: {relative}", file=sys.stderr)
            raise SystemExit(1)
        files.append((ROOT + relative, path))
    return files


def main() -> int:
    # wp-scripts names the archive from package.json; accept the previous name too.
    candidates = [ARCHIVE, Path("formlyx.zip")]
    source_archive = next((path for path in candidates if path.is_file()), None)
    if source_archive is None:
        print("Plugin zip was not created.", file=sys.stderr)
        return 1

    temporary = ARCHIVE.with_suffix(".filtered.zip")
    kept = 0
    with zipfile.ZipFile(source_archive) as source, zipfile.ZipFile(
        temporary, "w", compression=zipfile.ZIP_DEFLATED
    ) as target:
        present: set[str] = set()
        for info in source.infolist():
            name = info.filename
            if name.startswith("formlyx/") and not name.startswith(ROOT):
                name = ROOT + name[len("formlyx/") :]
            if not allowed(name):
                continue
            target.writestr(name, source.read(info.filename))
            present.add(name)
            kept += 1
        for name, path in root_files() + adapter_files() + source_files():
            if name in present:
                continue
            target.write(path, name)
            kept += 1

    if kept == 0:
        print("Plugin archive has no runtime files.", file=sys.stderr)
        return 1

    temporary.replace(ARCHIVE)
    if source_archive != ARCHIVE and source_archive.is_file():
        source_archive.unlink()
    print(f"Filtered {ARCHIVE} to {kept} runtime files.")
    return 0



if __name__ == "__main__":
    raise SystemExit(main())
