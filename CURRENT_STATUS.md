# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F03.01-url-frontier`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.0.0; F03 / V1.1 IN_PROGRESS (F00, F01 ve F02 ana fazları DONE; F03.01 READY_FOR_REVIEW).
- **Son onay:** F02 Ana Faz Kapanışı DONE; kullanıcı “onaylıyorum” ile F02 kapanışını ve F03.01 başlangıcını onayladı.
- **Önceki teslim:** [PR #21](https://github.com/Boydaksu/siteportal/pull/21) birleştirildi (`73c35318a3eac96ffd6b83fcac48e4a232662ada`).
- **Aktif alt faz / durum:** F03.01 — URL frontier ve normalize keşif / READY_FOR_REVIEW.
- **Dal:** `phase/F03.01-url-frontier`.
- **Aktif PR / kanıt commit:** [PR #22](https://github.com/Boydaksu/siteportal/pull/22); CI bekleniyor.
- **Rapor:** [F03.01](docs/reports/F03.01.md).
- **Yerel sonuç:** PASS; PHP lint (221 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 398 test / 2854 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** `UrlNormalizer` RFC 3986 kanonizasyonu, fragment temizliği, deterministik query sıralama (`ksort`), izleme parametresi filtresi; `RegistrableDomainParser` çok parçalı ccTLD eTLD+1 tespiti; `CrawlScopePolicy` exact host, subdomain ve path prefix sınırları; `MemoryUrlFrontier` ve `DatabaseUrlFrontier` (`pages` tablosu) dedup ve yaşam döngüsü doğrulaması.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F03.02 — Sitemap index ve XML güvenliği; kullanıcı onayı bekleniyor.
- **Bir sonraki işlem:** F03.01 alt faz teslimi için kullanıcı onayı beklenmektedir. Onay alındığında PR merge edilecek ve F03.02 başlatılacaktır.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
