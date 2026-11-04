#!/usr/bin/env python3
"""Git'in takip ettiği dosyalardan SHA256 envanteri üret; kendisini dışla."""
from pathlib import Path
import hashlib
import subprocess

root = Path(__file__).resolve().parents[1]
names = subprocess.check_output(["git", "ls-files", "-z"], cwd=root).decode("utf-8").split("\0")
names = sorted(name for name in names if name and name != "FILE_MANIFEST.sha256")
lines = [f"{hashlib.sha256((root / name).read_bytes()).hexdigest()}  {name}\n" for name in names]
(root / "FILE_MANIFEST.sha256").write_text("".join(lines), encoding="utf-8")
print(f"PASS: SHA256 manifest üretildi; {len(names)} dosya (manifest kendisini dışlar).")
