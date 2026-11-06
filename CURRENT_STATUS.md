# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F04.06-selective-js-rendering`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.1.0; F04 / V1.2 IN_PROGRESS (F00, F01, F02 ve F03 ana fazları DONE; F04.01-F04.05 DONE, F04.06 READY_FOR_REVIEW).
- **Son onay:** F04.05 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #33 merge ve F04.06 başlangıcını onayladı; PR #33 main dalına squash-merge edildi (`160d040a3a9f0fadd61360a726ce00efa62312b4`).
- **Önceki teslim:** [PR #33](https://github.com/Boydaksu/siteportal/pull/33) birleştirildi (`160d040a3a9f0fadd61360a726ce00efa62312b4`).
- **Aktif alt faz / durum:** F04.06 — Selektif JS rendering tasarımı / READY_FOR_REVIEW.
- **Dal:** `phase/F04.06-selective-js-rendering`.
- **Aktif PR / kanıt commit:** PR açılacak (PR #34).
- **Rapor:** `docs/reports/F04.06.md`.
- **Yerel sonuç:** PASS; PHP lint (328 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 601 test / 3993 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F04.06 Selektif JS rendering mimarisi (`RenderRequest`, `RenderResponse`, `BlockedResource`, `RenderWorkerInterface`, `DomDiffResult`, `DomDiffCalculator`, `RenderService`), SSRF koruması (`UrlValidator`), graceful degradation (worker yokken statik analiz çalışır, sahte ceza yok), 2 yeni kural (`TECH_JS_CONTENT_DELTA`, `TECH_JS_BLOCKED_RESOURCES`), V1.2 registry (42 kural), TR/EN çeviriler ve 29 yeni test (%100 PASS) tamamlandı.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F04.07 — V1.2 regresyon ve kapanış (kullanıcı açık onayı bekleniyor; DUR kuralı).
- **Bir sonraki işlem:** PR #34 açılarak kullanıcı incelemesine sunulması ve açık onayın beklenmesi.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
