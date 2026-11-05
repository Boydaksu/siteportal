# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-05, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.1.0; F04 / V1.2 IN_PROGRESS (F00, F01, F02 ve F03 ana fazları DONE; F04.01 DONE, F04.02 IN_PROGRESS).
- **Son onay:** F04.01 DONE; kullanıcı “Onaylıyorum” ile teslim kabulü, PR #29 merge ve F04.02 başlangıcını onayladı; PR #29 main dalına squash-merge edildi (`390bcd9449e0997bc144ef64fc2b032a3f2e0b8d`).
- **Önceki teslim:** [PR #29](https://github.com/Boydaksu/siteportal/pull/29) birleştirildi (`390bcd9449e0997bc144ef64fc2b032a3f2e0b8d`).
- **Aktif alt faz / durum:** F04.02 — CrUX gerçek kullanıcı verisi / IN_PROGRESS.
- **Dal:** `phase/F04.02-crux-field-data`.
- **Aktif PR / kanıt commit:** Geliştirme aşamasında.
- **Rapor:** `docs/reports/F04.01.md` (son tamamlanan rapor).
- **Yerel sonuç:** PASS; PHP lint (287 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 490 test / 3276 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F04.01 PageSpeed Insights API v5 bağlayıcısı tamamlandı ve main'e merge edildi. F04.02 CrUX (Chrome User Experience Report) gerçek kullanıcı verisi bağlayıcısı (75th percentile, 28 günlük pencere, veri yokluğunda UNKNOWN, lab vs field ayrımı) geliştiriliyor.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F04.03 — CWV rapor deneyimi.
- **Bir sonraki işlem:** F04.02 CrUX bağlayıcısı implementasyonu, testleri ve raporu.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
