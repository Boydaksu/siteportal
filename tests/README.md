# TEST harness ve fixture kapsamı

Bu dizin yalnız F00.04 kalite/test altyapısıdır. Uygulama, crawler, gerçek API, SSRF bariyeri veya DB henüz yoktur. Hiçbir test dış siteye istek göndermez.

- [FixtureContractTest.php](Unit/FixtureContractTest.php): onaylı Finding alan/kanıt biçimi, UNKNOWN durumunda sahte puan/kanıt olmaması ve tr/en anahtar-parametre eşliği. Kaynakları [DEMO örnek](../examples/analysis-response.example.json), [şema](../examples/finding.schema.json), [UNKNOWN TEST fixture](Fixtures/unknown-analysis.json) ve locale örnekleridir. Tam JSON Schema motoru veya gerçek skor algoritması testi değildir.
- [unknown-analysis.json](Fixtures/unknown-analysis.json): `_demo:true` ve `test-fixture` kaynağıyla açıkça işaretlenmiştir; gerçek tarama değildir.
- [Olumsuz kalite kapısı testleri](../scripts/test_quality_gates.py): ayrı geçici TEST dosyalarında bilerek bozuk sözdizimi, PSR-12 ihlali, yanlış return tipi ve yanlış assertion oluşturur; dört aracın sıfır dışı kod ve beklenen tanıyla reddetmesini doğrular. Bu beklenen reddetmeler CI arızası değildir.

Koşullar ve komutlar: [TESTING](../docs/process/TESTING.md). İleride gerçek uygulama birim/entegrasyon/güvenlik testleri ilgili fazın modülüne eklenir; bu üç fixture testi bütün platformun çalıştığı anlamına gelmez.
