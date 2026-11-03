# F00.04 — Test/CI birincil kaynakları

Kontrol tarihi 2026-11-03. Bu aşamada ilgili sayfalar ve kilitli upstream metadata/License dosyaları okundu; tam kod veya hukuk denetimi iddiası değildir.

- [PHPUnit 12.5 kurulumu](https://docs.phpunit.de/en/12.5/installation.html): PHP/extension gereksinimleri ve test aracı kapsamı.
- [PHPStan başlangıç](https://phpstan.org/user-guide/getting-started): yalnız projenin kendi kodunda statik analiz.
- [PHP_CodeSniffer upstream](https://github.com/PHPCSStandards/PHP_CodeSniffer): tokenize edilen dosyalarda kod standardı; bu teslimde PSR-12.
- [GitHub Actions secure use](https://docs.github.com/en/actions/reference/security/secure-use): minimum token yetkisi, tam SHA ile action sabitleme ve güvenilmeyen PR sınırı.
- [checkout v7.0.1](https://github.com/actions/checkout/tree/3d3c42e5aac5ba805825da76410c181273ba90b1), [MIT LICENSE](https://github.com/actions/checkout/blob/3d3c42e5aac5ba805825da76410c181273ba90b1/LICENSE).
- [setup-php sabit kaynak](https://github.com/shivammathur/setup-php/tree/eb7c497e18156a6bbabfef1d3a82760b9eda3962), [MIT LICENSE](https://github.com/shivammathur/setup-php/blob/eb7c497e18156a6bbabfef1d3a82760b9eda3962/LICENSE).

Packagist resmî paket metadatası ve composer.lock ile 3 doğrudan/27 toplam dev paket çözüldü; lisans dosyaları paketler kurulup çalıştırılmadan önce indirilen arşivlerden okundu. 3 MIT ve 24 BSD-3-Clause kayıt var. Copyright/koşullar/disclaimer ve BSD endorsement sınırı korunur; vendor ve tool paketleri repo kaynağına eklenmez. [İnceleme kayıtları](../reports/F00.04-dependency-review.json) kesin dist reference, arşiv/License SHA256 ve kaynak URL'lerini içerir.
