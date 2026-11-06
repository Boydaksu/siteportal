# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F04.07-v12-regression-closure`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.1.0; F04 / V1.2 IN_PROGRESS (F00, F01, F02 ve F03 ana fazları DONE; F04.01-F04.06 DONE, F04.07 READY_FOR_REVIEW).
- **Son onay:** F04.06 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #34 merge ve F04.07 başlangıcını onayladı; PR #34 main dalına squash-merge edildi (`ca83fe434b3e19bb829948990253ece4d01256a8`).
- **Önceki teslim:** [PR #34](https://github.com/Boydaksu/siteportal/pull/34) birleştirildi (`ca83fe434b3e19bb829948990253ece4d01256a8`).
- **Aktif alt faz / durum:** F04.07 — V1.2 regresyon ve kapanış / READY_FOR_REVIEW.
- **Dal:** `phase/F04.07-v12-regression-closure`.
- **Aktif PR / kanıt commit:** [PR #35](https://github.com/Boydaksu/siteportal/pull/35) (`b5863ad`).
- **Rapor:** `docs/reports/F04.07.md` ve ana faz kapanış raporu `docs/reports/F04-PHASE-SUMMARY.md`.
- **Yerel sonuç:** PASS; PHP lint (329 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 610 test / 4592 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F04.07 V1.2 tam regresyon testi (`V12EndToEndRegressionTest`), harici API throttle / outage graceful fallback (CrUX 429, PageSpeed 500, bellek önbelleği ile bütçe koruması), yapılandırılmış veri matrisi (JSON-LD, Microdata, RDFa, sentaks hataları, eksik alanlar), mobil/erişilebilirlik matrisi (viewport, WCAG 1.4.4 zoom kısıtlaması, 5 katmanlı form etiketleri, dekoratif görseller, statik mod güvenliği), selektif JS rendering matrisi (SSRF koruması, ağır hydration uyarısı, kritik engellenen kaynaklar) ve 42 kuralın TR/EN yerelleştirme sunumu (%100 PASS) tamamlandı.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki ana faz:** F05 — V2.0: Üyelik, domain doğrulama, Search Console (kullanıcı açık ana faz onayı bekleniyor; DUR kuralı).
- **Bir sonraki işlem:** PR #35 açılarak F04.07 alt faz teslimi ve F04 ana faz kapanış incelemesine sunulması ve açık onayın beklenmesi.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
