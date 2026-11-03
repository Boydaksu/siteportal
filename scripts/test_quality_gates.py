#!/usr/bin/env python3
"""TEST: Dört kalite kapısının gerçek hatalı girdide başarısız olduğunu doğrula."""
from pathlib import Path
import subprocess
import tempfile
import unittest
from php_cli import php_command

root = Path(__file__).resolve().parents[1]


class QualityGateTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix=".quality-probe-", dir=root)
        self.probe = Path(self.temp.name)
        if self.probe.resolve().parent != root.resolve():
            raise RuntimeError("TEST dizini çalışma klasörü dışında")
        self.addCleanup(self.temp.cleanup)
        self.php = php_command()

    def rejected(self, filename, content, arguments, diagnostic):
        target = self.probe / filename
        target.write_text(content, encoding="utf-8", newline="\n")
        result = subprocess.run(self.php + arguments + [str(target)], cwd=root, capture_output=True, text=True, encoding="utf-8")
        output = result.stdout + result.stderr
        self.assertNotEqual(0, result.returncode, output)
        self.assertIn(diagnostic, output, output)

    def test_php_lint_rejects_syntax_error(self):
        self.rejected("broken.php", "<?php function TEST_broken( {\n", ["-l"], "error")

    def test_formatter_rejects_non_psr12_input(self):
        self.rejected("format.php", "<?php function TEST_format(){return 1;}\n", ["vendor/bin/phpcs", "--standard=PSR12"], "ERROR")

    def test_static_analysis_rejects_incorrect_return_type(self):
        self.rejected("return.php", "<?php\ndeclare(strict_types=1);\nfunction TEST_return(): int { return 'TEST'; }\n", ["vendor/bin/phpstan", "analyse", "--no-progress", "--debug", "--error-format=raw"], "should return int but returns string")

    def test_test_runner_rejects_failed_assertion(self):
        code = "<?php\nfinal class ExpectedFailureTest extends \\PHPUnit\\Framework\\TestCase { public function testExpectedFailure(): void { self::assertSame(1, 2); } }\n"
        self.rejected("ExpectedFailureTest.php", code, ["vendor/bin/phpunit", "--no-configuration", "--bootstrap", "vendor/autoload.php"], "FAILURES!")


if __name__ == "__main__":
    unittest.main(verbosity=2)
