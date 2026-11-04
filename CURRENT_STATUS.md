# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.0.0; F03 / V1.1 IN_PROGRESS (F00, F01 ve F02 ana fazları DONE; F03.01 DONE, F03.02 başlatılıyor).
- **Son onay:** F03.01 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #22 merge ve F03.02 başlangıcını onayladı.
- **Önceki teslim:** [PR #22](https://github.com/Boydaksu/siteportal/pull/22) birleştirildi (`e98989378693b8d599d298a6046f801fd55e3af7`).
- **Aktif alt faz / durum:** F03.02 — Sitemap index ve XML güvenliği / IN_PROGRESS.
- **Dal:** `phase/F03.02-sitemap-xml`.
- **Aktif PR / kanıt commit:** Çalışma dalı açılıyor.
- **Rapor:** Hazırlanıyor (`docs/reports/F03.02.md`).
- **Yerel sonuç:** PASS; PHP lint (221 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 398 test / 2854 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F03.01 tamamlandı (frontier, scope, normalizer, registrable domain). Sıradaki geliştirme: sitemap index, robots sitemap discovery, streaming XMLReader, XXE ve 50k/50MB kalkanı.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F03.03 — İç link grafiği ve derinlik.
- **Bir sonraki işlem:** F03.02 dalının açılması, sitemap keşfi ve güvenli XML ayrıştırma motorunun geliştirilmesi.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
