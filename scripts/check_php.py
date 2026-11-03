#!/usr/bin/env python3
"""Takip edilen ve yeni proje PHP dosyalarında gerçek php -l çalıştır."""
from pathlib import Path
import subprocess
import sys
from php_cli import php_command

root = Path(__file__).resolve().parents[1]
names = subprocess.check_output(["git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"], cwd=root).decode("utf-8").split("\0")
files = sorted({name for name in names if name.endswith(".php")})
if not files:
    raise SystemExit("FAIL: PHP lint kapsamı boş; TEST harness dosyası bekleniyor.")
php = php_command()
for name in files:
    result = subprocess.run(php + ["-l", str(root / name)], cwd=root)
    if result.returncode:
        sys.exit(result.returncode)
print(f"PASS: {len(files)} proje PHP dosyası lint kontrolünden geçti; vendor/ignore edilen kopyalar kapsam dışı.")
