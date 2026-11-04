# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.0.0; F03 / V1.1 IN_PROGRESS (F00, F01 ve F02 ana fazları DONE; F03.01 ve F03.02 DONE, F03.03 başlatılıyor).
- **Son onay:** F03.02 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #23 merge ve F03.03 başlangıcını onayladı.
- **Önceki teslim:** [PR #23](https://github.com/Boydaksu/siteportal/pull/23) birleştirildi (`54f9bc81fde308ea601711043e534ac566268601`).
- **Aktif alt faz / durum:** F03.03 — İç link grafiği ve derinlik / IN_PROGRESS.
- **Dal:** `phase/F03.03-link-graph-depth`.
- **Aktif PR / kanıt commit:** Çalışma dalı açılıyor.
- **Rapor:** Hazırlanıyor (`docs/reports/F03.03.md`).
- **Yerel sonuç:** PASS; PHP lint (233 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 411 test / 2896 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F03.01 ve F03.02 tamamlandı (frontier, XMLReader sitemap parser, XXE/bomb guards). Sıradaki geliştirme: link grafiği (source -> target), derinlik sayımı, BFS bütçe önceliklendirmesi ve dış domain filtrelemesi.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F03.04 — Eşzamanlılık ve nazik tarama.
- **Bir sonraki işlem:** F03.03 dalının açılması, iç link grafiği ve BFS derinlik mekanizmasının geliştirilmesi.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
