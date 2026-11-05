# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-05, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.0.0; F03 / V1.1 IN_PROGRESS (F00, F01 ve F02 ana fazları DONE; F03.01, F03.02, F03.03 ve F03.04 DONE; F03.05 READY_FOR_REVIEW).
- **Son onay:** F03.04 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #25 merge ve F03.05 başlangıcını onayladı.
- **Önceki teslim:** [PR #25](https://github.com/Boydaksu/siteportal/pull/25) birleştirildi (`101a3912a5fb8deb6680dd1e047b7c18d8d8ee88`).
- **Aktif alt faz / durum:** F03.05 — Tarama bütçesi ve host nezaketi / READY_FOR_REVIEW.
- **Dal:** `phase/F03.05-crawl-budget-politeness`.
- **Aktif PR / kanıt commit:** PR açılıyor.
- **Rapor:** Hazır (`docs/reports/F03.05.md`).
- **Yerel sonuç:** PASS; PHP lint (266 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 458 test / 3060 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F03.05 tamamlandı (per-host concurrency=1 kilidi, Retry-After ve üstel geri çekilme, sunucu gecikmesine göre adaptif gecikme ölçeklemesi, devre kesici / crawl-stop, API kotası ve bant genişliği bütçesi katı koordinasyonu, round-robin adil zamanlayıcı). Toplam 458 test, 3060 assertion %100 PASS.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F03.06 — Çok sayfalı rapor/CSV.
- **Bir sonraki işlem:** Kullanıcı teslim onayı, PR merge ve F03.06'ya geçiş için açık onay bekleniyor (DUR).

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
