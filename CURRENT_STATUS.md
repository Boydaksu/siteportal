# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-05, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.0.0; F03 / V1.1 IN_PROGRESS (F00, F01 ve F02 ana fazları DONE; F03.01, F03.02, F03.03, F03.04 ve F03.05 DONE, F03.06 başlatılıyor).
- **Son onay:** F03.05 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #26 merge ve F03.06 başlangıcını onayladı.
- **Önceki teslim:** [PR #26](https://github.com/Boydaksu/siteportal/pull/26) birleştirildi (`048f4819fc8433ad8cd9ebfe418e0cf29a882f03`).
- **Aktif alt faz / durum:** F03.06 — Çok sayfalı rapor/CSV / IN_PROGRESS.
- **Dal:** `main` (çalışma dalı `phase/F03.06-multipage-report-csv` açılacak).
- **Aktif PR / kanıt commit:** Çalışma dalı açılıyor.
- **Rapor:** Hazırlanıyor (`docs/reports/F03.06.md`).
- **Yerel sonuç:** PASS; PHP lint (266 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 458 test / 3060 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F03.01 - F03.05 tamamlandı (URL Frontier, XMLReader sitemap parser, iç/dış link grafiği ve BFS derinlik mekanizması, site çapı duplicate metadata, sitemap/canonical çelişkisi, yetim sayfa kalkanı, per-host concurrency=1, Retry-After ve adaptif bütçe nezaketi). Sıradaki geliştirme: çok sayfalı denetim raporu presenter'ı, URL ve bulgu bazlı CSV dışa aktarımı, sayfalama ve filtreleme.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F03.07 — Dağıtık tarama kalkanı & V1.1 kapanışı.
- **Bir sonraki işlem:** `phase/F03.06-multipage-report-csv` dalının açılması, çok sayfalı rapor presenter'ı ve streaming/bom CSV dışa aktarıcısının geliştirilmesi.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
