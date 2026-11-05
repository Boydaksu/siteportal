# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-05, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.1.0; F04 / V1.2 IN_PROGRESS (F00, F01, F02 ve F03 ana fazları DONE; F04.01 başlatılıyor).
- **Son onay:** F03 ana faz kapanışı ve F03.07 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #28 merge ve F03 kapanışını onayladı; PR #28 main dalına squash-merge edildi (`33cca85b45b8bf4a8c219645fda506453f742aa0`).
- **Önceki teslim:** [PR #28](https://github.com/Boydaksu/siteportal/pull/28) birleştirildi (`33cca85b45b8bf4a8c219645fda506453f742aa0`).
- **Aktif alt faz / durum:** F04.01 — Çoklu URL/tarama diff modeli / IN_PROGRESS.
- **Dal:** `main` (çalışma dalı `phase/F04.01-multiurl-crawl-diff` açılacak).
- **Aktif PR / kanıt commit:** Çalışma dalı açılıyor.
- **Rapor:** `docs/reports/F03-PHASE-SUMMARY.md` tamamlandı; `docs/reports/F04.01.md` hazırlanacak.
- **Yerel sonuç:** PASS; PHP lint (276 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 476 test / 3198 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F00, F01, F02 ve F03 eksiksiz tamamlandı. Sıradaki geliştirme: Çoklu URL ve tarama snapshot diff modeli, metrik/skor/kural karşılaştırma matrisi (`F04.01`).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F04.02 — Regresyon kuralları ve puan sapması.
- **Bir sonraki işlem:** `phase/F04.01-multiurl-crawl-diff` dalının açılması, diff modeli ve karşılaştırma mantığının kodlanması.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
