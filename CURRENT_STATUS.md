# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.0.0; F03 / V1.1 IN_PROGRESS (F00, F01 ve F02 ana fazları DONE; F03.01 ve F03.02 DONE, F03.03 READY_FOR_REVIEW).
- **Son onay:** F03.02 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #23 merge ve F03.03 başlangıcını onayladı.
- **Önceki teslim:** [PR #23](https://github.com/Boydaksu/siteportal/pull/23) birleştirildi (`54f9bc81fde308ea601711043e534ac566268601`).
- **Aktif alt faz / durum:** F03.03 — İç link grafiği ve derinlik / READY_FOR_REVIEW.
- **Dal:** `phase/F03.03-link-graph-depth`.
- **Aktif PR / kanıt commit:** [PR #24](https://github.com/Boydaksu/siteportal/pull/24) / `7bdd4e699be31859784d6b605f31fa588434ea30`.
- **Rapor:** Hazırlandı (`docs/reports/F03.03.md`).
- **Yerel sonuç:** PASS; PHP lint (243 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 424 test / 2930 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F03.03 tamamlandı (LinkEdge, LinkGraph in-memory çizge, LinkExtractor bağıl URL ve nofollow çözümleme, BfsCrawlPrioritizer bütçe ve BFS önceliklendirme, DatabaseLinkGraphRepository ve 0007 migrasyonu). Dış domainlerin kuyruğa girmemesi ve kırık link tespiti fixture testleriyle doğrulandı.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F03.04 — Eşzamanlılık ve nazik tarama.
- **Bir sonraki işlem:** Kullanıcı teslim kabulü ve açık onayı bekleniyor; F03.04'e geçilmez (DUR).

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
