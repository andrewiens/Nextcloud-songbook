#!/usr/bin/env python3
"""Build a deterministic, unsigned development archive; never publish or sign."""
# SPDX-License-Identifier: AGPL-3.0-or-later

import gzip
import hashlib
import io
from pathlib import Path
import tarfile
import xml.etree.ElementTree as ET


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    required = ["appinfo/info.xml", "appinfo/routes.php", "js/songbook-main.js",
                "css/songbook-main.css", "LICENSE", "THIRD_PARTY_LICENSES.txt"]
    missing = [name for name in required if not (root / name).is_file()]
    if missing:
        raise SystemExit("Missing build files: " + ", ".join(missing))
    version = ET.parse(root / "appinfo/info.xml").getroot().findtext("version")
    if not version or any(character not in "0123456789abcdefghijklmnopqrstuvwxyz.-" for character in version):
        raise SystemExit("Invalid version for archive filename")

    paths = []
    for directory in ("appinfo", "lib", "templates", "img", "js", "css", "docs"):
        paths.extend(path for path in (root / directory).rglob("*") if path.is_file())
    paths.extend(root / name for name in ("LICENSE", "README.md"))
    notices = root / "THIRD_PARTY_LICENSES.txt"
    if notices.is_file():
        paths.append(notices)

    output = root / "dist"
    output.mkdir(exist_ok=True)
    destination = output / f"songbook-{version}-unsigned.tar.gz"
    with destination.open("wb") as raw:
        with gzip.GzipFile(filename="", mode="wb", fileobj=raw, mtime=0) as compressed:
            with tarfile.open(fileobj=compressed, mode="w", format=tarfile.PAX_FORMAT) as archive:
                for path in sorted(paths):
                    if path.is_symlink():
                        raise SystemExit(f"Symlink refused: {path.relative_to(root)}")
                    relative = path.relative_to(root)
                    if relative.name == "signature.json" or relative.suffix in {".map", ".key", ".pem", ".csr"}:
                        continue
                    content = path.read_bytes()
                    entry = tarfile.TarInfo(f"songbook/{relative.as_posix()}")
                    entry.size = len(content)
                    entry.mode = 0o644
                    entry.uid = entry.gid = entry.mtime = 0
                    entry.uname = entry.gname = ""
                    archive.addfile(entry, io.BytesIO(content))
    digest = hashlib.sha256(destination.read_bytes()).hexdigest()
    destination.with_suffix(destination.suffix + ".sha256").write_text(
        f"{digest}  {destination.name}\n", encoding="utf-8")
    print(f"Unsigned development archive: {destination}")
    print(f"SHA-256: {digest}")


if __name__ == "__main__":
    main()
