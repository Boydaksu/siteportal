"""PHP CLI yolunu Windows wrapper'ından güvenli, yerel argümanlarla çöz."""
from pathlib import Path
import os
import shutil
import subprocess


def php_command():
    configured = os.environ.get("SITEPORTAL_PHP_BINARY")
    located = configured or shutil.which("php")
    if not located:
        raise SystemExit("FAIL: PHP CLI bulunamadı; PHP 8.4+ PATH'te olmalı.")
    path = Path(located)
    if path.suffix.lower() in {".cmd", ".bat"}:
        # Sabit kod; kullanıcı dosya adları hiçbir shell komutuna eklenmez.
        result = subprocess.run([str(path), "-r", "echo PHP_BINARY, PHP_EOL, php_ini_loaded_file();"], capture_output=True, text=True, check=True)
        lines = result.stdout.strip().splitlines()
        binary = Path(lines[0])
        if not binary.is_file():
            raise SystemExit("FAIL: PHP wrapper gerçek executable yolu döndürmedi.")
        return [str(binary)] + (["-c", lines[1]] if len(lines) > 1 and Path(lines[1]).is_file() else [])
    return [str(path)]
