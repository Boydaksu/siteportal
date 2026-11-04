# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F02.04-on-page-rules`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F02 / V1.0 IN_PROGRESS (F00 ve F01 ana fazları DONE).
- **Son onay:** F02.03 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #16 merge ve F02.04 başlangıcını onayladı.
- **Önceki teslim:** [PR #16](https://github.com/Boydaksu/siteportal/pull/16) birleştirildi (`c2047a3a66b5ea873b1f03ba8277d0f8b830af05`).
- **Aktif alt faz / durum:** F02.04 — On-page kuralları / READY_FOR_REVIEW.
- **Dal:** `phase/F02.04-on-page-rules`.
- **Aktif PR / kanıt commit:** Açılacak PR (#17).
- **Rapor:** [F02.04](docs/reports/F02.04.md).
- **Yerel sonuç:** PASS; PHP lint (185 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 316 test / 2255 assertion PASS (%100), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** 13 On-Page SEO kuralı (`TitleMissingRule`, `TitleGenericRule`, `TitleTruncationRiskRule`, `MetaDescriptionMissingRule`, `H1EmptyRule`, `HeadingHierarchyRule`, `HtmlLangRule`, `CharsetRule`, `OgMetadataRule`, `MetaKeywordsRule`, `AnchorEmptyRule`, `RelNofollowRule`, `ImageAltRule`), `OnPageRuleFactory`, `tr-TR` ve `en-US` kural fix i18n anahtarları, mutlak title/h1 cezası uygulanmadığının doğrulanması, WCAG standartlarında `alt=""` dekoratif görsel uyumluluğu, Q-001 `data_source` ve `evidence` nesne dizisi sözleşme uyumu.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F02.05 — Kural kayıt sistemi ve skorlama.
- **Bir sonraki işlem:** F02.04 teslimi için PR açılacak, kontroller kullanıcıya sunularak açık onay beklenecektir.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
