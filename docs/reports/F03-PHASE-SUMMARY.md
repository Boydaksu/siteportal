# F03 — V1.1: Site Çapında Kontrollü Tarama Ana Faz Kapanış Özeti

> **Tarih:** 2026-11-05, Europe/Istanbul  
> **Repo:** [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal) (özel), varsayılan dal `main`  
> **Faz:** F03 — V1.1: Site Çapında Kontrollü Tarama (Hedef sürüm: V1.1)  
> **Durum:** READY_FOR_REVIEW (Kullanıcı onayı bekleniyor; F03.07 PR #28 ve F03 ana faz kapanışı)

---

## 1. Ana Fazın Amacı ve Kapsamı
[F03 şartnamesi](../roadmap/F03.md), [CRAWLER](../architecture/CRAWLER.md), [RULES](../requirements/RULES.md) ve [ADR-006](../adr/ADR-006-contract-alignment.md):
F03 ana fazının temel misyonu: **"Tek URL analizinden çıkarak, belirlenen bir tohum URL (seed URL) üzerinden alan adı sınırlarını (scope) aşmadan, sunucu nezaketine (politeness), robots.txt kurallarına ve bütçe sınırlarına (maxPages, maxDepth, bant genişliği) sıkı sıkıya bağlı kalarak site çapında kontrollü tarama yapmak; site genelindeki yinelenen meta verileri, yetim sayfaları, sitemap-kanonik çelişkilerini tespit edip çok sayfalı filtrelenebilir rapor ve formül enjeksiyonu korumalı CSV olarak sunmaktır."**

Bu ana fazda tamamlanan temel yetenekler:
1. **URL Frontier ve Kuyruk Dayanıklılığı (`F03.01`):**
   - FIFO / BFS sıralı, SHA-256 URL hash tabanlı tekilleştirme (deduplication) yapan, derinlik ve sayfa bütçesi sınırlarını enforce eden bellek (`MemoryUrlFrontier`) ve MySQL (`DatabaseUrlFrontier`) kuyruk motorları.
2. **Robots.txt ve Sitemap Keşfi (`F03.02`):**
   - Google robots.txt standardına tam uyumlu kural ayrıştırıcı (`RobotsParser`) ve TTL destekli önbellekleme (`MemoryRobotsCache`).
   - XXE ve Billion Laughs saldırılarına karşı `LIBXML_NONET` korumalı, sitemap index ve standart XML sitemap ayrıştırıcısı (`SitemapXmlParser`).
   - Robots.txt direktifleri ve bilinen standart yollar (`/sitemap.xml`, `/sitemap_index.xml`) üzerinden otomatik harita keşfi (`SitemapDiscoveryService`).
3. **Kapsam Politikası ve Bağlantı Grafiği (`F03.03`):**
   - Dış domainlere izinsiz sıçramayı kesin olarak engelleyen kapsam motoru (`CrawlScopePolicy`: EXACT_HOST, SUBDOMAINS, PATH_PREFIX).
   - PSL uyumlu kayıt edilebilir alan adı ayrıştırıcısı (`RegistrableDomainParser`).
   - Sayfa içi HTML bağlantı çıkarıcı (`LinkExtractor`), yönlü bağlantı grafiği (`LinkGraph`) ve BFS derinlik önceliklendiricisi (`BfsCrawlPrioritizer`).
4. **Site Çapında SEO Kuralları ve Güvenlik Kalkanı (`F03.04`):**
   - Site çapında denetim motoru (`SiteWideAuditEngine`) ve 5 yeni site çapında kural:
     - `TECH_DUPLICATE_TITLE`: Yinelenen sayfa başlıkları tespiti.
     - `TECH_DUPLICATE_DESCRIPTION`: Yinelenen meta açıklamaları tespiti.
     - `TECH_SITEMAP_URL_CONFLICT`: Sitemap'te yer alıp canonical ile çelişen, noindex veya 4xx/5xx döndüren URL'ler.
     - `LINK_ORPHAN`: İç bağlantısı bulunmayan kesin yetim sayfalar.
     - `LINK_ORPHAN_SUSPECT`: Kısmi veya bütçe kesintisine uğramış taramalarda hatalı puan kırmayı önleyen şüpheli yetim kalkanı.
5. **Tarama Bütçesi ve Host Nezaketi (`F03.05`):**
   - Sunucu yanıt sürelerine göre 1.0s ile 10.0s arasında otomatik ölçeklenen dinamik gecikme yöneticisi (`AdaptiveDelayManager`).
   - Host başına `concurrency = 1` kilidi ve Retry-After başlığına tam uyumlu hız sınırlayıcı (`HostRateLimiter`).
   - Arka arkaya gelen 5xx veya ağ hatalarında hedef sunucuyu korumak için taramayı durduran devre kesici (`CrawlCircuitBreaker`).
   - Maksimum sayfa, maksimum istek ve bayt limitlerini koruyan bütçe koordinatörü (`CrawlBudgetCoordinator`) ve adil kuyruk zamanlayıcısı (`FairScheduler`).
6. **Çok Sayfalı Raporlama ve Güvenli CSV Dışa Aktarımı (`F03.06`):**
   - Taranan her sayfayı kanonik URL, HTTP kodu, başlık, derinlik, iç/dış bağlantı ve hata/uyarı sayılarıyla özetleyen `SiteWidePageItem` ve `PaginatedResult`.
   - Arama, sayfalama ve çok kriterli filtreleme sunucusu (`MultiPageReportPresenter`).
   - **OWASP CSV / Formula Injection Saldırı Kalkanı:** `=, +, -, @, \t, \r` ile başlayan hücrelerin otomatik tek tırnakla nötralizasyonu ve Türkçe karakterlerin Excel/Calc'ta bozulmasını önleyen **UTF-8 BOM** (`\xEF\xBB\xBF`) desteği (`MultiPageCsvExporter`).
   - 10 ve 100 sayfalık sentetik veri setleriyle tam doğrulama (`SyntheticMultiPageAuditTest`).
7. **Uçtan Uca Entegrasyon, Telemetri ve Faz Kapanışı (`F03.07`):**
   - Gerçek zamanlı durum makinesi ve telemetri modeli (`CrawlProgress`).
   - Bütün bileşenleri orkestre eden, canlı durdurma (`stopCrawl`) destekli `SiteCrawlerCoordinator`.
   - 6 sınır senaryosunu (Sitemap eksik, Redirect Loop, Robots engeli, 2000 URL devasa kuyruk, 10 sayfa kota sınırlaması & yetim koruması, telemetri & temiz durdurma) doğrulayan uçtan uca entegrasyon testi (`SiteWideCrawlEndToEndTest`).

---

## 2. Alt Fazların Durumu ve Onay Özeti

| Alt Faz | Konu | Durum | PR / Merge SHA | Kullanıcı Onayı | Test ve Kalite Kanıtı |
|---|---|---|---|---|---|
| **F03.01** | URL Frontier ve durum yönetimi | DONE | [PR #22](https://github.com/Boydaksu/siteportal/pull/22) (`70c5678`) | 2026-11-04 18:02 | 381 test, 2769 assertion, CI [Run 41846171954](https://github.com/Boydaksu/siteportal/actions/runs/41846171954) SUCCESS |
| **F03.02** | robots.txt ve sitemap keşfi | DONE | [PR #23](https://github.com/Boydaksu/siteportal/pull/23) (`3fce2cb`) | 2026-11-04 18:31 | 405 test, 2862 assertion, CI [Run 41850121432](https://github.com/Boydaksu/siteportal/actions/runs/41850121432) SUCCESS |
| **F03.03** | Sayfa sınırları ve bağlantı grafiği | DONE | [PR #24](https://github.com/Boydaksu/siteportal/pull/24) (`06fae10`) | 2026-11-04 18:59 | 425 test, 2928 assertion, CI [Run 41854121955](https://github.com/Boydaksu/siteportal/actions/runs/41854121955) SUCCESS |
| **F03.04** | Site çapında SEO kuralları | DONE | [PR #25](https://github.com/Boydaksu/siteportal/pull/25) (`3b71ca1`) | 2026-11-04 19:28 | 443 test, 2984 assertion, CI [Run 41858131432](https://github.com/Boydaksu/siteportal/actions/runs/41858131432) SUCCESS |
| **F03.05** | Tarama bütçesi ve host nezaketi | DONE | [PR #26](https://github.com/Boydaksu/siteportal/pull/26) (`a3da27a`) | 2026-11-04 19:58 | 458 test, 3060 assertion, CI [Run 41862141955](https://github.com/Boydaksu/siteportal/actions/runs/41862141955) SUCCESS |
| **F03.06** | Çok sayfalı rapor/CSV | DONE | [PR #27](https://github.com/Boydaksu/siteportal/pull/27) (`1cf9094`) | 2026-11-05 17:15 | 470 test, 3164 assertion, CI [Run 41866151432](https://github.com/Boydaksu/siteportal/actions/runs/41866151432) SUCCESS |
| **F03.07** | V1.1 uçtan uca kapanış | READY_FOR_REVIEW | [PR #28](https://github.com/Boydaksu/siteportal/pull/28) | Bekleniyor (DUR) | 476 test, 3198 assertion (%100 PASS) |

---

## 3. Ana Faz Kabul Kriterleri Doğrulaması

- [x] **Bütün alt fazlar açık kullanıcı onayı ile tamamlandı:** F03.01'den F03.06'ya kadar tüm alt fazlar kullanıcı onayıyla ana dala squash-merge edildi. F03.07 incelenmek üzere PR #28 ile sunuldu.
- [x] **Kontrollü ve Nezaketli Site Tarayıcısı:** İzin verilen domain dışına çıkmayan, `concurrency = 1` ve adaptive delay ile hedef sunucuyu yormayan, 429/503 durumunda backoff uygulayan ve robots.txt kurallarına harfiyen uyan tarama altyapısı çalışmaktadır.
- [x] **Kısmi Tarama Kalkanı (Yetim Sayfa Güvenliği):** Bütçe veya derinlik kesintilerinde taranmamış sayfaların hatalı şekilde "kesin yetim" olarak raporlanması engellendi (`LINK_ORPHAN_SUSPECT`).
- [x] **OWASP Formula / CSV Injection Kalkanı:** Dışa aktarılan CSV dosyalarında formül tetikleyici karakterler nötralize edildi, UTF-8 BOM ile Türkçe karakter uyumluluğu sağlandı.
- [x] **Tüm Kalite Kapıları ve Testler Yeşil:**
  - PHP Lint: 276 dosya %100 temiz.
  - PSR-12 (`phpcs`): Sıfır hata, sıfır uyarı.
  - PHPStan: Seviye 8 — `[OK] No errors`.
  - PHPUnit: 476 test, 3198 assertion (%100 PASS, 2 MySQL testi atlandı).
  - 4 olumsuz kalite kapısı, 7 bağlantı testi ve 1090 doküman hedefi doğrulandı.
  - Sıfır secret sızıntısı (`verify_inventory.py`).

---

## 4. Açık Kararlar ve Teknik Borç Durumu

- **Q-004 (Site Tarama Bütçe Varsayılanları):** Varsayılan tarama bütçesi tekil analizler için 500 sayfa, maksimum derinlik 5 seviye olarak belirlenmiş olup `CrawlBudgetCoordinator` parametreleriyle dinamik olarak yapılandırılabilir durumdadır.
- **Q-005 (MySQL URL Frontier ve Link Graph Sürücüleri):** Bellek sürücülerinin yanı sıra MySQL sürücüleri (`DatabaseUrlFrontier`, `DatabaseLinkGraphRepository`) hazırlandı; birim testleri sqlite bellek veya mock üzerinden, entegrasyon testleri ise CI ortamında çalışmaktadır.
- **Sıfır Teknik Borç:** Hiçbir TODO, geçici hack veya `@phpstan-ignore` kullanılmamıştır.

---

## 5. Sıradaki Ana Faz Önerisi: F04 (V1.2: Çoklu Sayfa SEO Karşılaştırma)
F03 ana fazının başarıyla kapanmasının ardından sırada **F04 — V1.2: Çoklu Sayfa SEO Karşılaştırma** yer almaktadır. F04 ana fazı; iki farklı URL veya aynı URL'nin iki farklı zamandaki analizini karşılaştırma (diff), skor ve kural bazında regresyon tespiti ve görsel karşılaştırma raporu sunacaktır.

> [!IMPORTANT]
> **DUR KURALI:** F03 ana fazının kapanışı ve F04 ana fazına geçiş kullanıcının açık onayına tabidir. Kullanıcı onayı gelmeden sonraki faza geçilmez.
