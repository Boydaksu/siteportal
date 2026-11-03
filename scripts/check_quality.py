#!/usr/bin/env python3
"""Yerel ve CI için aynı lint/format/statik analiz/test kapıları."""
from pathlib import Path
import subprocess
import sys
from php_cli import php_command

root = Path(__file__).resolve().parents[1]
php = php_command()
commands = [
    [sys.executable, "scripts/check_php.py"],
    php + ["vendor/bin/phpcs"],
    php + ["vendor/bin/phpstan", "analyse", "--no-progress", "--memory-limit=512M"],
    php + ["vendor/bin/phpunit"],
    [sys.executable, "scripts/test_quality_gates.py"],
]
for command in commands:
    result = subprocess.run(command, cwd=root)
    if result.returncode:
        raise SystemExit(result.returncode)
print("PASS: PHP lint, PSR-12, PHPStan level 8, PHPUnit ve dört olumsuz kalite kapısı TEST'i.")
