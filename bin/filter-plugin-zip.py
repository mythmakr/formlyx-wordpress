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
BLOCKED_NAMES = {
    ".gitkeep",
    "package.json",
    "README.md",
}


def allowed(name: str) -> bool:
    filename = name.rsplit("/", 1)[-1]
    if filename in BLOCKED_NAMES or name.endswith("/") or filename.endswith(".map"):
        return False
    if name in ALLOWED_FILES:
        return True
    return name.startswith(ALLOWED_PREFIXES)


def main() -> int:
    if not ARCHIVE.is_file():
        print("formlyx.zip was not created.", file=sys.stderr)
        return 1

    temporary = ARCHIVE.with_suffix(".filtered.zip")
    kept = 0
    with zipfile.ZipFile(ARCHIVE) as source, zipfile.ZipFile(
        temporary, "w", compression=zipfile.ZIP_DEFLATED
    ) as target:
        for info in source.infolist():
            if not allowed(info.filename):
                continue
            target.writestr(info, source.read(info.filename))
            kept += 1

    if kept == 0:
        print("Plugin archive has no runtime files.", file=sys.stderr)
        return 1

    temporary.replace(ARCHIVE)
    print(f"Filtered {ARCHIVE} to {kept} runtime files.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
