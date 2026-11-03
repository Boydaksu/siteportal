#!/usr/bin/env python3
"""TEST: Belge bağlantı denetiminin gerçek bozuk hedefleri reddetmesi."""
from pathlib import Path
import tempfile
import unittest
from document_links import anchors, validate_documents


class DocumentLinksTests(unittest.TestCase):
    def setUp(self):
        workspace = Path(__file__).resolve().parents[1]
        self.temp = tempfile.TemporaryDirectory(prefix=".document-link-test-", dir=workspace)
        self.root = Path(self.temp.name)
        if self.root.resolve().parent != workspace.resolve():
            raise RuntimeError("TEST geçici dizini çalışma klasörü dışında")
        self.addCleanup(self.temp.cleanup)
        self.source = self.root / "README.md"
        (self.root / "hedef.md").write_text("# Türkçe bakım\n## Tekrar\n## Tekrar\n", encoding="utf-8")

    def errors(self, content):
        self.source.write_text(content, encoding="utf-8")
        return validate_documents(self.root, [self.source])[0]

    def test_turkish_and_duplicate_heading_targets(self):
        self.assertEqual([], self.errors("[TR](hedef.md#türkçe-bakım) [tekrar](hedef.md#tekrar-1)"))

    def test_missing_file_is_rejected(self):
        self.assertIn("yerel hedef yok", self.errors("[bozuk](yok.md)")[0])

    def test_missing_fragment_is_rejected(self):
        self.assertIn("başlık hedefi yok", self.errors("[bozuk](hedef.md#yok)")[0])

    def test_code_fences_do_not_create_links_or_headings(self):
        content = "# Gerçek\n```md\n# Yanlış\n[örnek](yok.md)\n```\n[başlık](#gerçek)"
        self.assertEqual([], self.errors(content))
        self.assertEqual({"gerçek"}, anchors(content))

    def test_url_encoded_local_target(self):
        (self.root / "bakım notu.md").write_text("# Bakım", encoding="utf-8")
        self.assertEqual([], self.errors("[not](bak%C4%B1m%20notu.md#bakım)"))

    def test_external_url_format_without_network_request(self):
        self.assertEqual([], self.errors("[kaynak](https://example.com/docs#x)"))
        self.assertTrue(self.errors("[bozuk](https:///docs)"))

    def test_repository_escape_is_rejected(self):
        self.assertIn("depo dışına", self.errors("[dış](../başka.md)")[0])


if __name__ == "__main__":
    unittest.main(verbosity=2)
