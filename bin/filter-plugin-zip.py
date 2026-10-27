#!/usr/bin/env python3
"""Keep only runtime plugin files in the archive produced by wp-scripts."""

import sys
import zipfile
from pathlib import Path

ARCHIVE = Path("formlyx.zip")
ROOT = "formlyx/"
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


def main() -> int:
    if not ARCHIVE.is_file():
        print("formlyx.zip was not created.", file=sys.stderr)
        return 1

    temporary = ARCHIVE.with_suffix(".filtered.zip")
    kept = 0
    with zipfile.ZipFile(ARCHIVE) as source, zipfile.ZipFile(
        temporary, "w", compression=zipfile.ZIP_DEFLATED
    ) as target:
        present: set[str] = set()
        for info in source.infolist():
            if not allowed(info.filename):
                continue
            target.writestr(info, source.read(info.filename))
            present.add(info.filename)
            kept += 1
        for name, path in adapter_files():
            if name in present:
                continue
            target.write(path, name)
            kept += 1

    if kept == 0:
        print("Plugin archive has no runtime files.", file=sys.stderr)
        return 1

    temporary.replace(ARCHIVE)
    print(f"Filtered {ARCHIVE} to {kept} runtime files.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
