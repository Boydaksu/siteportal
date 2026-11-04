# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F02 / V1.0 IN_PROGRESS (F00 ve F01 ana fazları DONE).
- **Son onay:** F02.04 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #17 merge ve F02.05 başlangıcını onayladı.
- **Önceki teslim:** [PR #17](https://github.com/Boydaksu/siteportal/pull/17) birleştirildi (`9b3580ac40b8cdecb5e01b61da5e7e643d9ff871`).
- **Aktif alt faz / durum:** F02.04 — On-page kuralları / DONE.
- **Dal:** `main`.
- **Aktif PR / kanıt commit:** [PR #17](https://github.com/Boydaksu/siteportal/pull/17); [CI](https://github.com/Boydaksu/siteportal/actions/runs/41827790176) SUCCESS.
- **Rapor:** [F02.04](docs/reports/F02.04.md).
- **Yerel sonuç:** PASS; PHP lint (185 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 316 test / 2255 assertion PASS (%100), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** 13 On-Page SEO kuralı (`TitleMissingRule`, `TitleGenericRule`, `TitleTruncationRiskRule`, `MetaDescriptionMissingRule`, `H1EmptyRule`, `HeadingHierarchyRule`, `HtmlLangRule`, `CharsetRule`, `OgMetadataRule`, `MetaKeywordsRule`, `AnchorEmptyRule`, `RelNofollowRule`, `ImageAltRule`), `OnPageRuleFactory`, `tr-TR` ve `en-US` kural fix i18n anahtarları, mutlak title/h1 cezası uygulanmadığının doğrulanması, WCAG standartlarında `alt=""` dekoratif görsel uyumluluğu, Q-001 `data_source` ve `evidence` nesne dizisi sözleşme uyumu.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F02.05 — Kural kayıt sistemi ve skorlama; BAŞLATILIYOR.
- **Bir sonraki işlem:** F02.04 kapatıldı ve PR #17 main dalına birleştirildi. F02.05 için çalışma dalı açılacak ve geliştirme başlatılacaktır.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
