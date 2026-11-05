# Changelog

## 2026-11-05 | F04.02 | READY_FOR_REVIEW
- CrUX Gerçek Kullanıcı Verisi Bağlayıcısı (`docs/reports/F04.02.md`) tamamlandı.
- `config/crux.php` eklendi: CrUX API anahtarı, endpoint, 15s zaman aşımı, 24 saatlik önbellek yaşam süresi ve varsayılan form faktörü yapılandırması.
- `CruxFormFactor` (`app/Core/Performance/CruxFormFactor.php`) eklendi (`ALL_FORM_FACTORS`, `PHONE`, `DESKTOP`, `TABLET`).
- `CruxStatus` (`app/Core/Performance/CruxStatus.php`) eklendi (`AVAILABLE`, `NO_DATA`, `UNAVAILABLE`).
- `CruxCollectionPeriod` (`app/Core/Performance/CruxCollectionPeriod.php`) geliştirildi:
  - 28 günlük yuvarlanan pencere başlangıç/bitiş tarihleri ve yerelleştirilmiş formatlama desteği.
- `CruxMetricItem` (`app/Core/Performance/CruxMetricItem.php`) geliştirildi:
  - 75. yüzdelik dilim (p75) değeri, gösterim metni, dağılım histogramı ve metrik durumu.
- `CruxFieldMetrics` (`app/Core/Performance/CruxFieldMetrics.php`) geliştirildi:
  - LCP, INP, CLS, FCP, TTFB, FID metrikleri için 75. yüzdelik dilim eşik değerlendirmesi.
  - Genel Core Web Vitals değerlendirmesi (`PASSED`, `NEEDS_IMPROVEMENT`, `FAILED`, `UNKNOWN`).
  - PSI `loadingExperience` adaptörü (`fromPageSpeedMetrics`).
- `CruxResult` (`app/Core/Performance/CruxResult.php`) geliştirildi:
  - Sonuç değer nesnesi: URL, form faktörü, durum, saha metrikleri, 28 günlük toplama penceresi, mesaj, veri kaynağı (`data_source: 'chrome_ux_report'`), saha verisi (`is_field_data: true`) ve lab ayrımı (`is_lab_data: false`).
  - Yetersiz trafikte ve HTTP 404 durumunda `CruxResult::noData()` ile sahte 0 puan verilmemesi ve `UNKNOWN` döndürülmesi.
  - Kesintilerde `CruxResult::unavailable()` ile fail-safe güvenlik kalkanı.
- `CruxCacheInterface` ve `MemoryCruxCache` (`app/Core/Performance/MemoryCruxCache.php`) geliştirildi:
  - TTL destekli bellek önbelleği ile URL/Origin ve form faktörü bazında kota koruması.
- `CruxClient` (`app/Core/Performance/CruxClient.php`) geliştirildi:
  - Google Chrome UX Report API POST entegrasyonu (`queryUrl`, `queryOrigin`, `fromPageSpeedLoadingExperience`).
  - HTTP 404, 429 kota aşımı ve 5xx sunucu kesintilerinde fail-safe hata yönetimi.
- `SafeHttpClient` (`app/Core/Http/SafeHttpClient.php`) geliştirildi: POST istekleri için `CURLOPT_POSTFIELDS` desteği eklendi.
- Çeviriler (`resources/lang/tr-TR/messages.php`, `resources/lang/en-US/messages.php`) güncellendi (`crux.field_data_label`, `crux.no_field_data`, vb.).
- 17 yeni birim ve entegrasyon testi eklendi; toplam test sayısı 507 test, 3406 assertion (%100 PASS).
- F04.02 alt faz teslimi ve inceleme onayı bekleniyor; kullanıcı onayı olmadan bir sonraki alt faza geçilmez (DUR).

## 2026-11-05 | F04.01 kabulü ve PR #29 merge
- Kullanıcı “Onaylıyorum” ile F04.01 teslim kabulünü, PR #29 birleştirmesini ve F04.02 başlangıcını onayladı; PR #29 main dalına squash-merge edildi (`390bcd9449e0997bc144ef64fc2b032a3f2e0b8d`).
- F04.01 alt fazı (PageSpeed Insights API bağlayıcısı) resmen DONE durumuna getirildi.
- Sıradaki alt faz F04.02 (CrUX gerçek kullanıcı verisi) başlatılacaktır.

## 2026-11-05 | F04.01 | READY_FOR_REVIEW
- PageSpeed Insights Bağlayıcısı (`docs/reports/F04.01.md`) tamamlandı.
- `config/pagespeed.php` eklendi: API anahtarı, endpoint, zaman aşımı ve önbellek yaşam süresi yapılandırması.
- `PageSpeedStrategy` (`app/Core/Performance/PageSpeedStrategy.php`) eklendi (`MOBILE`, `DESKTOP`).
- `PageSpeedMetricStatus` (`app/Core/Performance/PageSpeedMetricStatus.php`) eklendi (`GOOD`, `NEEDS_IMPROVEMENT`, `POOR`, `UNKNOWN`).
- `PageSpeedLabMetrics` (`app/Core/Performance/PageSpeedLabMetrics.php`) geliştirildi:
  - FCP, LCP, TBT, CLS, Speed Index ve TTI için ham metrik (`raw_value`), gösterim değeri (`display_value`), Lighthouse puanı (`score`: 0-100) ve standart Core Web Vitals eşiklerine göre derecelendirme (`status`).
  - Scored vs raw metrics ayrımı uygulandı.
- `PageSpeedResult` (`app/Core/Performance/PageSpeedResult.php`) geliştirildi:
  - Sonuç değer nesnesi: URL, strateji, başarı durumu, Lighthouse performans skoru (0-100), lab metrikleri, hata nedeni ve zaman damgası.
  - Veri kaynağı şeffaflığı: `data_source: 'google_pagespeed_api'` ve `is_lab_data: true` etiketleri.
  - API arızalarında `PageSpeedResult::unavailable()` ile fail-safe güvenlik kalkanı.
- `PageSpeedCacheInterface` ve `MemoryPageSpeedCache` (`app/Core/Performance/MemoryPageSpeedCache.php`) geliştirildi:
  - TTL destekli bellek önbelleği ile URL ve strateji bazında API kotası koruması.
- `PageSpeedClient` (`app/Core/Performance/PageSpeedClient.php`) geliştirildi:
  - Google PageSpeed Insights API v5 bağlantısı, mobil/masaüstü stratejileri, HTTP 429 kota aşımı ve 5xx sunucu hatalarında SEO raporunu bozmama (fail-safe).
- Çeviriler (`resources/lang/tr-TR/messages.php`, `resources/lang/en-US/messages.php`) güncellendi.
- 14 yeni birim ve entegrasyon testi eklendi; toplam test sayısı 490 test, 3276 assertion (%100 PASS).
- F04.01 alt faz teslimi ve inceleme onayı bekleniyor; kullanıcı onayı olmadan bir sonraki alt faza geçilmez (DUR).

## 2026-11-05 | F03 ana faz kapanışı ve F03.07 kabulü
- Kullanıcı “onaylıyorum” ile F03.07 teslimini, PR #28 birleştirmesini ve F03 — V1.1 Ana Faz Kapanışını onayladı; PR #28 main dalına squash-merge edildi (`33cca85b45b8bf4a8c219645fda506453f742aa0`).
- F03 ana fazı (V1.1: Site Çapında Kontrollü Tarama) resmen DONE durumuna getirildi.
- Sıradaki ana faz F04 (V1.2: Çoklu Sayfa SEO Karşılaştırma) ve F04.01 (Çoklu URL/tarama diff modeli) başlatılacaktır.

## 2026-11-05 | F03.07 | READY_FOR_REVIEW
- V1.1 Uçtan Uca Kapanış (`docs/reports/F03.07.md`) ve F03 Ana Faz Kapanış Özeti (`docs/reports/F03-PHASE-SUMMARY.md`) tamamlandı.
- `CrawlProgress` (`app/Core/Crawler/CrawlProgress.php`) geliştirildi:
  - Gerçek zamanlı tarama durumu (`pending`, `running`, `stopped`, `completed`, `failed`), taranan sayfa sayısı, tüketilen bayt, hata sayısı, geçen süre, durma nedeni ve kuyruk boyutu telemetri modeli (`toArray`).
- `SiteCrawlerCoordinator` (`app/Core/Crawler/SiteCrawlerCoordinator.php`) geliştirildi:
  - URL Frontier, Link Graph, Crawl Scope Policy, Adaptive Delay, Host Rate Limiter, Circuit Breaker ve Crawl Budget bileşenlerini koordine eden üst seviye orkestratör.
  - Canlı durdurma mekanizması (`stopCrawl`) ile temiz sonlandırma ve telemetri güncelleme desteği.
  - Taranan başarılı HTML sayfalarından iç bağlantıları otomatik çıkarma (`LinkExtractor`), grafiğe ekleme (`LinkGraph`) ve BFS sıralı önceliklendirme (`BfsCrawlPrioritizer`).
- `SiteWideCrawlEndToEndTest` (`tests/Integration/Crawler/SiteWideCrawlEndToEndTest.php`) entegrasyon test paketi yazıldı:
  - Senaryo 1: Eksik sitemap (404/yok) durumunda crawler'ın iç bağlantılarla taramaya devam etmesi ve sitemap çelişki kuralının `NOT_APPLICABLE` üretmesi.
  - Senaryo 2: Yönlendirme döngüsünde (Redirect loop) crawler'ın askıda kalmadan döngüyü izole etmesi ve `TECH_REDIRECT_LOOP` FAIL bulgusu üretmesi.
  - Senaryo 3: Robots.txt engellemesinde fail-closed kalkanının devreye girmesi ve `TECH_ROBOTS_DISALLOW` WARN/HIGH bulgusu üretmesi.
  - Senaryo 4: 2.000 URL içeren gövdede bellek taşması olmadan BFS önceliklendirmesi ile tam bütçe sınırında durulması.
  - Senaryo 5: 10 sayfa kota sınırında taramanın durması ve kısmi tarama güvenlik kalkanı ile `OrphanPageAnalyzer`'ın kesin yetim yerine şüpheli (`LINK_ORPHAN_SUSPECT`) üretmesi.
  - Senaryo 6: Devam eden taramanın temiz durdurulması (`stopped`), süre ve telemetri doğrulaması.
- Toplam test sayısı 476 test, 3198 assertion (%100 PASS).
- F03.07 alt faz teslimi ve F03 ana faz kapanış onayı bekleniyor; kullanıcı açık onayı olmadan bir sonraki faza geçilmez (DUR).

## 2026-11-05 | F03.06 kabulü
- Kullanıcı “onaylıyorum” ile F03.06 teslimi, PR #27 birleştirmesi ve F03.07 başlangıcını onayladı; PR #27 main dalına birleştirildi (`89af2b43783c0ad3cbb31ae12de842e323e3d673`). F03.07 (V1.1 uçtan uca kapanış) başlatılacaktır.

## 2026-11-05 | F03.06 | READY_FOR_REVIEW
- Çok Sayfalı Rapor/CSV (`docs/reports/F03.06.md`) tamamlandı.
- `SiteWidePageItem` (`app/SeoAnalyzer/Presentation/SiteWidePageItem.php`) geliştirildi:
  - Taranan her sayfa için kanonik URL, HTTP durum kodu, başlık, meta açıklama, indekslenebilirlik, BFS derinliği, iç/dış bağlantı sayıları, sitemap ve yetim sayfa durumu ile sayfa bazlı bulgu sayıları.
- `PaginatedResult` (`app/SeoAnalyzer/Presentation/PaginatedResult.php`) tip güvenli sayfalama modeli geliştirildi (`totalPages`, `hasNextPage`, `hasPreviousPage`).
- `MultiPageReportPresenter` (`app/SeoAnalyzer/Presentation/MultiPageReportPresenter.php`) geliştirildi:
  - `SiteCrawlContext` ve `SiteWideAuditResult` üzerinden otomatik sayfa listesi ve bulgu ilişkilendirmesi (`fromCrawlContext`).
  - URL ve sayfa başlığı içinde metin arama (`search`).
  - HTTP durum kodu (`status_code`, `status_group`), indekslenebilirlik, sorunlu sayfalar, yetim sayfalar, sitemap varlığı ve derinlik filtreleri (`filter`).
  - Çok alanlı sıralama (`sort`) ve sayfalama (`paginate`).
- `MultiPageCsvExporter` (`app/SeoAnalyzer/Export/MultiPageCsvExporter.php`) geliştirildi:
  - **Kritik Güvenlik Kalkanı (OWASP CSV Injection Mitigation):** Hücre başındaki `=, +, -, @, \t, \r` karakterlerinin tek tırnakla nötralize edilmesi (`sanitizeCellValue`).
  - Türkçe karakterler ve Excel/Numbers uyumu için dosya başında **UTF-8 BOM (`\xEF\xBB\xBF`)** garantisi.
  - Sayfa listesi CSV'si (`exportPagesCsv`), bulgular CSV'si (`exportFindingsCsv`) ve bellek tasarruflu akış (`exportPagesToStream`) desteği.
- `PageSnapshot` sınıfına `isIndexable()` metodu eklendi (`app/SeoAnalyzer/Domain/PageSnapshot.php`).
- `LinkGraph` sınıfına `getNodeDepth()` metodu eklendi (`app/Core/Crawler/Graph/LinkGraph.php`).
- **Kritik Kabul Kriteri Doğrulaması:** 10 ve 100 sayfalık sentetik veri setleri ile rapor sayfalama, filtreleme, CSV dökümü, streaming ve injection nötralizasyonu %100 doğrulandı (`SyntheticMultiPageAuditTest`).
- Toplam test sayısı 470 test, 3164 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F03.07'ye geçilmez (DUR).

## 2026-11-05 | F03.05 kabulü
- Kullanıcı “onaylıyorum” ile F03.05 teslimi, PR #26 birleştirmesi ve F03.06 başlangıcını onayladı; PR #26 main dalına birleştirildi (`048f4819fc8433ad8cd9ebfe418e0cf29a882f03`). F03.06 (Çok sayfalı rapor/CSV) başlatılacaktır.

## 2026-11-05 | F03.05 | READY_FOR_REVIEW
- Tarama Bütçesi ve Host Nezaketi (`docs/reports/F03.05.md`) tamamlandı.
- `HostRateLimiter` (`app/Core/Crawler/Politeness/HostRateLimiter.php`) geliştirildi:
  - Host başına eşzamanlılık kısıtı (`maxConcurrencyPerHost = 1`) katı kilit mekanizması (`acquireLock`, `releaseLock`, `isLocked`).
  - HTTP 429 ve 503 yanıtlarında `Retry-After` süresine riayet (`recordRateLimit`); başlık yoksa üstel geri çekilme (`baseBackoff * 2^(attempt-1)`).
  - Host soğuma (`cooldown`) süresi takibi (`isHostInCooldown`, `getRemainingCooldown`).
- `AdaptiveDelayManager` (`app/Core/Crawler/Politeness/AdaptiveDelayManager.php`) geliştirildi:
  - Sunucu yanıt süresi (latency) ve son 10 istek hareketli ortalamasına göre adaptif gecikme ölçeklemesi.
  - HTTP 429 ve 503 yanıtlarında gecikmeyi 2 katına çıkarma kuralı (`recordResponse`).
  - Robots.txt `Crawl-delay` direktifinin alt sınır olarak tanınması (`getEffectiveDelay`).
- `CrawlCircuitBreaker` (`app/Core/Crawler/Politeness/CrawlCircuitBreaker.php`) geliştirildi:
  - Hedef host için ardışık hata eşiği (varsayılan: 5 ardışık 429/503/timeout hatası) aşıldığında veya kritik hata oranına ulaşıldığında devreyi açma ve taramayı güvenle durdurma (`Crawl stopped`).
- `CrawlBudgetCoordinator` (`app/Core/Crawler/Budget/CrawlBudgetCoordinator.php`) geliştirildi:
  - **Kritik Kabul Kriteri:** Dış API bütçesi (`maxApiQuotaUnits`) hiçbir koşulda aşılmadan katı olarak sınırlandırıldı. Kota tükenince istekler güvenle reddedildi (`api_quota_exhausted`).
  - Maksimum sayfa (`maxPages`), maksimum istek (`maxRequests`) ve maksimum bant genişliği (`maxBytes`) limitlerinin katı takibi.
- `FairScheduler` (`app/Core/Crawler/Politeness/FairScheduler.php`) geliştirildi:
  - Çoklu host kuyruklarında round-robin adaletli seçim; soğumadaki veya kilitli host'ları atlayarak hazır host'lara öncelik tanıma.
- `PoliteCrawlManager` ve `CrawlDecision` (`app/Core/Crawler/Politeness/PoliteCrawlManager.php`) üst seviye orkestrasyonu geliştirildi.
- Kapsamlı birim testleri (6 yeni test dosyası, 20 test, 68 assertion) eklendi. Toplam test sayısı 458 test, 3060 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F03.06'ya geçilmez (DUR).

## 2026-11-05 | F03.04 kabulü
- Kullanıcı “onaylıyorum” ile F03.04 teslimi, PR #25 birleştirmesi ve F03.05 başlangıcını onayladı; PR #25 main dalına birleştirildi (`101a3912a5fb8deb6680dd1e047b7c18d8d8ee88`). F03.05 (Tarama bütçesi ve host nezaketi) başlatılacaktır.

## 2026-11-05 | F03.04 | READY_FOR_REVIEW
- Site Çapı Metadata ve Canonical Analizleri (`docs/reports/F03.04.md`) tamamlandı.
- `SiteCrawlContext` (`app/SeoAnalyzer/SiteWide/SiteCrawlContext.php`) geliştirildi; snapshot'lar, link grafiği, sitemap URL'leri ve örnekleme/bütçe durumu birleştirildi.
- `SiteWideMetadataAnalyzer` (`app/SeoAnalyzer/SiteWide/SiteWideMetadataAnalyzer.php`) geliştirildi:
  - Mükerrer başlık analizi (`ONPAGE_TITLE_DUPLICATE`): Normalize edilmiş başlıklar üzerinden sayfa gruplama ve kanıt sunumu.
  - Mükerrer meta açıklama analizi (`ONPAGE_META_DESC_DUPLICATE`).
  - Mükerrer/yakın kopya H1 etiketleri (`CONTENT_NEAR_DUPLICATE`).
- `SitemapCanonicalConflictAnalyzer` (`app/SeoAnalyzer/SiteWide/SitemapCanonicalConflictAnalyzer.php`) geliştirildi (`TECH_SITEMAP_URL_CONFLICT`):
  - Sitemap'te yer alıp `noindex` direktifi taşıyan sayfaların tespiti (Hard Failure).
  - Sitemap'te yer alıp kendisinden farklı bir canonical hedefi gösteren (non-self canonical) sayfaların tespiti (Warning).
  - Sitemap'te yer alıp HTTP 3xx/4xx/5xx dönen sayfaların tespiti.
- `OrphanPageAnalyzer` (`app/SeoAnalyzer/SiteWide/OrphanPageAnalyzer.php`) geliştirildi (`LINK_ORPHAN_SUSPECT`):
  - **Kritik Kabul Kriteri Kalkanı:** Tarama bütçe/derinlik sınırı nedeniyle kısmi/örneklemeli kaldığında (`isPartialCrawl() === true`), gelen bağlantısı olmayan sayfalar **kesin yetim sayfa (`confirmed orphan`) olarak işaretlenmez!**
  - Şüpheli yetim sayfa (`suspected_orphan`, `Confidence::LOW`) olarak işaretlenir ve kanıtta taranmamış sayfalardan bağlantı alma ihtimali vurgulanır.
  - Tam taramada (`isPartialCrawl() === false`) kesin yetim sayfa tespiti yapılır. Tohum URL kök giriş noktası olduğu için yetim sayılmaz.
- `SiteWideAuditEngine` ve `SiteWideAuditResult` orkestrasyonu geliştirildi.
- Kapsamlı birim testleri (4 yeni test dosyası, 14 test, 62 assertion) eklendi. Toplam test sayısı 438 test, 2992 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F03.05'e geçilmez (DUR).

## 2026-11-05 | F03.03 kabulü
- Kullanıcı “onaylıyorum” ile F03.03 teslimi, PR #24 birleştirmesi ve F03.04 başlangıcını onayladı; PR #24 main dalına birleştirildi (`165de977a3b1783aff97589d3c4d7f2c9d2ee2c3`). F03.04 (Site çapı metadata ve canonical analizleri) başlatılacaktır.

## 2026-11-04 | F03.03 | READY_FOR_REVIEW
- İç Link Grafiği ve Derinlik (`docs/reports/F03.03.md`) tamamlandı.
- `LinkEdge` (`app/Core/Crawler/Graph/LinkEdge.php`) yönlendirilmiş bağlantı modeli geliştirildi (`sourceUrl`, `targetUrl`, `targetUrlHash`, `rawHref`, `anchorText`, `isInternal`, `isNofollow`, `depth`, `httpStatus`, `isBroken`).
- `LinkGraph` (`app/Core/Crawler/Graph/LinkGraph.php`) yönlendirilmiş çizge motoru geliştirildi:
  - In-memory gelen bağlantılar (`getInlinks`), giden bağlantılar (`getOutlinks`).
  - Kırık iç ve dış link tespiti (`getBrokenLinks`, `getInternalBrokenLinks`, `getExternalBrokenLinks`).
  - Tıklama derinliği histogramı (`getDepthDistribution`) ve maksimum derinlik hesabı (`getMaxDepth`).
  - Yetim sayfa (orphan page) tespiti (`getOrphanPages`).
- `LinkExtractor` (`app/Core/Crawler/Graph/LinkExtractor.php`) geliştirildi:
  - Bağıl URL çözümleme ve `UrlNormalizer` ile kanonikleştirme.
  - Web dışı şemaların (`mailto:`, `tel:`, `javascript:`, `data:`) güvenli elenmesi.
  - `CrawlScopePolicy` ile iç/dış domain ayrımı ve `rel="nofollow"` tespiti.
  - Sayfa içi mükerrer bağlantıların tekilleştirilmesi.
- `BfsCrawlPrioritizer` (`app/Core/Crawler/Graph/BfsCrawlPrioritizer.php`) geliştirildi:
  - BFS (Breadth-First Search) tıklama derinliği sıralamasıyla tarama kuyruğu (`UrlFrontierInterface`) önceliklendirmesi.
  - Dış domainlerin kuyruğa girmesinin kesin olarak engellenmesi (`!$edge->isInternal()`).
  - Bütçe (`maxPages`) ve derinlik (`maxDepth`) sınırlarının katı işletimi.
- `DatabaseLinkGraphRepository` (`app/Core/Crawler/Graph/DatabaseLinkGraphRepository.php`) ve migrasyon `0007_create_page_links_table.php` geliştirildi.
- Kapsamlı birim ve entegrasyon testleri (4 yeni test dosyası, 13 test, 34 assertion) eklendi. Toplam test sayısı 424 test, 2930 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F03.04'e geçilmez (DUR).

## 2026-11-04 | F03.02 kabulü
- Kullanıcı “onaylıyorum” ile F03.02 teslimi, PR #23 birleştirmesi ve F03.03 başlangıcını onayladı; PR #23 main dalına birleştirildi (`54f9bc81fde308ea601711043e534ac566268601`). F03.03 (İç link grafiği ve derinlik) başlatılacaktır.

## 2026-11-04 | F03.02 | READY_FOR_REVIEW
- Sitemap Index ve XML Güvenliği (`docs/reports/F03.02.md`) tamamlandı.
- `SitemapXmlParser` (`app/Core/Crawler/Sitemap/SitemapXmlParser.php`) geliştirildi:
  - `XMLReader` ile akışlı (streaming) okuma ($O(1)$ bellek tüketimi).
  - GZIP sıkıştırılmış sitemap dosyalarının otomatik tespiti ve açılması (`gzdecode`).
  - XXE ve DTD enjeksiyon koruması (`LIBXML_NONET`, `LOADDTD = false`, `<!DOCTYPE` / `<!ENTITY` tespiti).
  - XML Bomb / Billion laughs saldırı kalkanı.
  - 50.000 URL (`MAX_URLS = 50000`) ve 50 MB (`MAX_BYTES = 52428800`) standart sitemap sınırları.
  - Bozuk / tanımsız kök etiketlerinde güvenli hata yönetimi (`MalformedSitemapException`).
- `SitemapDiscoveryService` (`app/Core/Crawler/Sitemap/SitemapDiscoveryService.php`) geliştirildi:
  - `robots.txt` (`RobotsParser`) direktiflerinden ve standart bilinen yollardan (`/sitemap.xml`, `/sitemap_index.xml`) sitemap keşfi.
  - Sınır denetimi: Dış domainlere (off-domain) işaret eden sitemap bağlantılarının elenmesi (`CrawlScopePolicy`).
  - `sitemapindex` içindeki alt sitemap'lerin özyinelemeli (recursive) çözümlenmesi ve iç içe derinlik sınırı (`maxIndexDepth = 2`) ile döngü kalkanı.
  - Keşfedilen sitemap URL'lerinin `feedFrontier` ile doğrudan tarama kuyruğuna aktarılması.
- Kapsamlı birim testleri (2 yeni test dosyası, 13 test, 42 assertion) eklendi. Toplam test sayısı 411 test, 2896 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F03.03'e geçilmez (DUR).

## 2026-11-04 | F03.01 kabulü
- Kullanıcı “onaylıyorum” ile F03.01 teslimi, PR #22 birleştirmesi ve F03.02 başlangıcını onayladı; PR #22 main dalına birleştirildi (`e98989378693b8d599d298a6046f801fd55e3af7`). F03.02 (Sitemap index ve XML güvenliği) başlatılacaktır.

## 2026-11-04 | F03.01 | READY_FOR_REVIEW
- URL Frontier ve Normalize Keşif (`docs/reports/F03.01.md`) tamamlandı.
- `UrlNormalizer` (`app/Core/Crawler/Normalizer/UrlNormalizer.php`) geliştirildi:
  - RFC 3986 nokta segmenti çözümü, fragment temizleme, şema/host küçük harfe çevirme, standart portları kaldırma.
  - Anlamlı query parametrelerini koruma ve deterministik alfabetik anahtar sıralama (`ksort`).
  - İzleme parametrelerini (`utm_*`, `fbclid`, `gclid` vb.) opsiyonel temizleme.
  - Tekilleştirme için 64 karakter SHA-256 hash (`normalized_url_hash`) üretimi.
- `RegistrableDomainParser` (`app/Core/Crawler/Scope/RegistrableDomainParser.php`) eTLD+1 motoru geliştirildi; `.com.tr`, `.org.tr`, `.co.uk`, `.com.au` gibi çok parçalı alan adlarını sıfır harici bağımlılıkla doğru çözümler.
- `CrawlScopePolicy` (`app/Core/Crawler/Scope/CrawlScopePolicy.php`) ve `ScopeMode` (`EXACT_HOST`, `SUBDOMAINS`, `PATH_PREFIX`) geliştirildi; dış domain sızıntılarını ve protokol uyumsuzluklarını engeller.
- `FrontierState`, `FrontierItem` ve `FrontierEnqueueResult` modelleri oluşturuldu (`DISCOVERED`, `QUEUED`, `PROCESSING`, `CRAWLED`, `FAILED`, `SKIPPED`).
- `MemoryUrlFrontier` ve `DatabaseUrlFrontier` (`pages` tablosu destekli transactional frontier) geliştirildi.
- Kapsamlı birim ve entegrasyon testleri (5 yeni test dosyası, 35 test, 161 assertion) eklendi. Toplam test sayısı 398 test, 2854 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F03.02'ye geçilmez (DUR).

## 2026-11-04 | F02.08 kabulü ve F02 Ana Faz Kapanışı
- Kullanıcı “onaylıyorum” ile F02.08 teslimini, PR #21 birleştirmesini ve F02 (V1.0: Üyeliksiz tek sayfa SEO analizi) ana faz kapanışını onayladı; PR #21 başarıyla main dalına birleştirildi (`73c35318a3eac96ffd6b83fcac48e4a232662ada`).
- V1.0 sürümü (32 kural, asenkron tarama pipeline'ı, deterministik skorlama, anonim güvenli paylaşım, noindex ve TTL yaşam döngüsü) tamamlandı.
- F03 (V1.1: Kullanıcı hesabı, geçmiş ve site yönetimi) ana fazı ve F03.01 alt fazının başlatılması için kullanıcı onayı bekleniyor.

## 2026-11-04 | F02.08 | READY_FOR_REVIEW
- V1.0 Uçtan Uca ve Kapanış (`docs/reports/F02.08.md`) ile F02 Ana Faz Kapanış Özeti (`docs/reports/F02-PHASE-SUMMARY.md`) tamamlandı.
- `SinglePageAuditJobHandler` (`app/Core/Analysis/SinglePageAuditJobHandler.php`) boru hattı (pipeline) geliştirildi:
  - Analiz işini kuyruktan alıp aşama ilerlemesini atomik güncelleme (crawling %25 -> analyzing %60 -> completed %100).
  - Güvenli HTTP taraması, `HtmlParser` ile belge ayrıştırma ve I/O'suz `PageSnapshot` inşası.
  - `AuditEngine` (32 V1 kuralı) ve `ScoringEngine` deterministik icrası.
  - Bulguları (`findings`) ve skorları (`analyses`) atomik veritabanı işlemlerinde kalıcı depolama.
  - DNS çözümlenememesi (`DNS_ERROR`), zaman aşımı (`TIMEOUT`), SSRF engeli (`SSRF_BLOCKED`), sunucu hatası (`SERVER_ERROR`) ve dış kesintilerde fail-safe hata yönetimi.
- `bin/worker.php` ve `bin/scheduler.php` araçlarına `single_page_audit` iş tipi bağlandı.
- `ScoringEngine` (`app/SeoAnalyzer/Scoring/ScoringEngine.php`) güncellendi; başarılı (`PASS`) kuralların orijinal kural önemi katsayısı (`rule->getDefaultSeverity()`) baz alınarak kategori skorlarına ve ölçülen ağırlığa tam etki etmesi sağlandı (`resolveSeverityWeight`).
- `report.php` arayüzüne yatay kaydırılabilir sekmeler (`overflow-x-auto`) eklenerek mobil uyum güçlendirildi.
- Kapsamlı uçtan uca entegrasyon testleri (1 yeni test dosyası, 6 test, 58 assertion) eklendi (`V1EndToEndAuditTest`). Toplam test sayısı 363 test, 2693 assertion (%100 PASS).
- F02 Ana Faz Kapanış Raporu (`docs/reports/F02-PHASE-SUMMARY.md`) oluşturuldu; kullanıcı teslim kabulü ve açık ana faz kapanış onayı bekleniyor (DUR).

## 2026-11-04 | F02.07 kabulü
- Kullanıcı “onaylıyorum” ile F02.07 teslimi, PR #20 birleştirmesi ve F02.08 başlangıcını onayladı; kabul kaydı main dalına birleştirildi. F02.08 (V1.0 uçtan uca ve kapanış) başlatılacaktır.

## 2026-11-04 | F02.07 | READY_FOR_REVIEW
- Anonim Paylaşım ve Yaşam Süresi (`docs/reports/F02.07.md`) tamamlandı.
- `analyses` tablosuna `visibility`, `share_token`, `share_token_expires_at`, `expires_at` ve `is_revoked` sütunları ile arama/temizlik indekslerini ekleyen veritabanı migration'ı (`database/migrations/0006_add_sharing_and_lifecycle_to_analyses.php`) oluşturuldu.
- `AnalysisSharingService` (`app/Core/Analysis/AnalysisSharingService.php`) servisi geliştirildi:
  - Kriptografik rastgele 32-byte hex (64 karakter) tahmin edilemez paylaşım token üretimi ve URL inşası.
  - Paylaşım tokenı iptal mekanizması (`revokeShareToken`, `is_revoked = 1`, `share_token = NULL`).
  - Görünürlük modu yönetimi (`public`, `unlisted`, `private`).
  - T04 IDOR kalkanı ve erişim denetimi (`canAccess`): TTL aşımı, özel rapor gizliliği ve `hash_equals` ile güvenli token doğrulaması.
- `AnalysisLifecycleService` (`app/Core/Analysis/AnalysisLifecycleService.php`) güncellendi:
  - Yeni analiz oluşturulurken `visibility: 'unlisted'` ve varsayılan 7 günlük TTL (`expires_at: NOW + 7 gün`) atandı.
  - `pruneExpired(?string $beforeDate): int` metodu ile süresi dolmuş analizlerin bulguları, sayfaları ve kuyruk işleriyle birlikte atomik silinmesi sağlandı.
- CLI / cron budama betiği `bin/prune.php` oluşturuldu (`--dry-run` bayrağı destekli).
- `AnalysisController` (`app/Controllers/AnalysisController.php`) kontrolcüsüne:
  - Yetkisiz private erişimde HTTP 403 Forbidden, süresi dolmuş analizlerde HTTP 410 Gone yanıtları.
  - Arama motoru kalkanı: `X-Robots-Tag: noindex, nofollow, noarchive`, `Cache-Control: private, no-cache, no-store, must-revalidate` ve `Pragma: no-cache` başlıkları.
  - Paylaşım API endpoint'leri (`POST /api/v1/analyses/{id}/share`, `DELETE /api/v1/analyses/{id}/share`, `POST /api/v1/analyses/{id}/visibility`).
- Kullanıcı arayüzüne (`resources/views/analysis/report.php`) güvenlik ve gizlilik rozeti (`noindex / noarchive`), kalan saklama süresi göstergesi ve tek tıkla bağlantı kopyalama modalı eklendi.
- Özel HTTP 403 (`resources/views/errors/403.php`) ve HTTP 410 (`resources/views/errors/410.php`) hata sayfaları oluşturuldu.
- `tr-TR` ve `en-US` dil dosyalarına 403/410 hata iletileri, paylaşım modal metinleri, noindex rozetleri ve TTL süre bildirimleri eklendi.
- Kapsamlı birim ve entegrasyon testleri (2 yeni test dosyası, 16 test, 73 assertion) eklendi (`AnalysisSharingServiceTest`, `SharingAndLifecycleIntegrationTest`). Toplam test sayısı 357 test, 2635 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F02.08'e geçilmez (DUR).
- Kullanıcı “onaylıyorum” ile F02.06 teslimi, PR #19 birleştirmesi ve F02.07 başlangıcını onayladı; kabul kaydı main dalına birleştirildi. F02.07 (Anonim paylaşım ve yaşam süresi) başlatılacaktır.

## 2026-11-04 | F02.06 | READY_FOR_REVIEW
- Rapor UI ve Çözüm Önerisi (`docs/reports/F02.06.md`) tamamlandı.
- Rapor Sunum Katmanı `ReportPresenter` (`app/SeoAnalyzer/Presentation/ReportPresenter.php`) geliştirildi; bulguları kategorilere ayırma, durum/önem sınıfları ve rozetleri atama, eyleme dönüştürülebilir çözüm önerileri (`remediation`) ve kod parçacıkları (`code_snippet`) bağlama işlevleri eklendi.
- `analyses` tablosuna genel skor, kategori skorları, kapsam oranı ve skor dökümünü ekleyen veritabanı migration'ı (`database/migrations/0005_add_scores_to_analyses.php`) oluşturuldu.
- `AnalysisLifecycleService` (`app/Core/Analysis/AnalysisLifecycleService.php`) servisine `saveFindings`, `getFindings`, `saveScore` ve `getScore` metotları eklenerek denetim bulgularının ve skorunun kalıcı depolanması sağlandı.
- `AnalysisController` (`app/Controllers/AnalysisController.php`) kontrolcüsüne HTML rapor görünümü (`viewReport`, GET `/analysis/{id}/report`) ve JSON rapor API endpoint'i (`reportApi`, GET `/api/v1/analyses/{id}/report`) eklendi. Tamamlanmamış analizlerin rapor sayfasına erişimi engellendi (302 ile canlı ilerleme ekranına yönlendirme).
- Modern ve erişilebilir Rapor Görünümü (`resources/views/analysis/report.php`) geliştirildi:
  - Hero genel skor kartı (0-100), renk kodlaması, kapsam yüzdesi ve yasal feragatname.
  - 4 kategori skor kartı (Teknik SEO, On-Page SEO, Bağlantılar, Erişilebilirlik).
  - İstemci tarafı hızlı filtreleme ve ARIA sekmeleri (Kategori sekmeleri, Durum butonları, Severity dropdown).
  - Kaynak, tespit zamanı, etkilenen URL listesi, kanıt detayları (`<details>`), çözüm önerisi ve örnek kod parçası içeren bulgu kartları.
  - **%100 Güvenli HTML Escaping (Fail-Closed XSS koruması)** `View::escape` ile uygulandı.
- `tr-TR` ve `en-US` dil dosyalarına rapor başlıkları, özet sayaçları, sekme ve filtre etiketleri, feragatname ve buton metinleri eklendi.
- Kapsamlı birim ve entegrasyon testleri (2 yeni test dosyası, 13 test, 87 assertion) eklendi (`ReportPresenterTest`, `ReportUiTest`). Toplam test sayısı 341 test, 2551 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F02.07'ye geçilmez (DUR).

## 2026-11-04 | F02.05 kabulü
- Kullanıcı “onaylıyorum” ile F02.05 teslimi, PR #18 birleştirmesi ve F02.06 başlangıcını onayladı; kabul kaydı main dalına birleştirildi. F02.06 (Rapor UI ve çözüm önerisi) başlatılacaktır.

## 2026-11-04 | F02.05 | READY_FOR_REVIEW
- Kural Kayıt Sistemi ve Skorlama Motoru (`docs/reports/F02.05.md`) tamamlandı.
- Birleşik kural kayıt fabrikası `V1RuleRegistryFactory` (`app/SeoAnalyzer/Engine/V1RuleRegistryFactory.php`) geliştirildi; 19 Teknik SEO kuralı ve 13 On-Page SEO kuralı (toplam 32 kural) tek bir kayıt defterinde (`RuleRegistryInterface`) birleştirildi.
- Denetim motoru arayüzü `AuditEngineInterface` ve denetim motoru `AuditEngine` (`app/SeoAnalyzer/Engine/AuditEngine.php`) geliştirildi; sayfa snapshot'ı üzerinde tüm kuralların sıfır harici I/O ile deterministik icrası sağlandı.
- `SCORING.md` ilkeleri ve formüllerine tam uyumlu deterministik `ScoringEngineInterface` ve `ScoringEngine` (`app/SeoAnalyzer/Scoring/ScoringEngine.php`) geliştirildi:
  - Durum katsayıları: PASS = 1.0, WARN = 0.6, FAIL = 0.0.
  - Kabul kriteri: **UNKNOWN ve NOT_APPLICABLE asla sıfır sayılmaz**, ölçülen paydaya dahil edilmez.
  - Önem ağırlıkları: CRITICAL = 10, HIGH = 5, MEDIUM = 3, LOW = 1, INFO = 0.
  - V1.0 kategori normalizasyonu: Ölçülmeyen kategoriler (CWV, mobil, structured data) yok sayılır; global puan ölçülen kategoriler (`technical` %40, `onpage` %30, `links` %20, `accessibility` %10) arasında normalize edilir.
  - Sıfır puan güvenlik bariyeri: Kapsam (%20) altında kaldığında sahte sıfır puan üretilmez (`isScorable: false`, `overallScore: null`).
- Değişmez skor veri nesnesi `AuditScore` (`app/SeoAnalyzer/Scoring/AuditScore.php`) oluşturuldu.
- Kapsamlı birim ve snapshot testleri (1 yeni test dosyası, 12 test, 170 assertion) eklendi (`ScoringEngineTest`). Toplam test sayısı 328 test, 2425 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F02.06'ya geçilmez (DUR).

## 2026-11-04 | F02.04 kabulü
- Kullanıcı “onaylıyorum” ile F02.04 teslimi, PR #17 birleştirmesi ve F02.05 başlangıcını onayladı; kabul kaydı main dalına birleştirildi. F02.05 (Kural kayıt sistemi ve skorlama) başlatılacaktır.

## 2026-11-04 | F02.04 | READY_FOR_REVIEW
- On-Page SEO Kuralları Motoru (`docs/reports/F02.04.md`) tamamlandı.
- 13 kritik On-Page SEO kuralı (`app/SeoAnalyzer/Rules/OnPage/`) geliştirildi:
  - `TitleMissingRule` (`ONPAGE_TITLE_MISSING`): `<title>` etiketi eksik veya boşluk durumları tespiti (FAIL/PASS/UNKNOWN).
  - `TitleGenericRule` (`ONPAGE_TITLE_GENERIC`): "Home", "Ana Sayfa", "Untitled", "Default page" gibi jenerik başlıklar tespiti (WARN/PASS/NOT_APPLICABLE/UNKNOWN).
  - `TitleTruncationRiskRule` (`ONPAGE_TITLE_TRUNCATION_RISK`): Başlık > 60 karakter SERP kesilme riski tespiti; **mutlak ceza uygulanmaz, yalnızca görünüm uyarısı (WARN low) verilir** (WARN/PASS/NOT_APPLICABLE/UNKNOWN).
  - `MetaDescriptionMissingRule` (`ONPAGE_META_DESC_MISSING`): Meta açıklama eksikliği tespiti (WARN/PASS/UNKNOWN).
  - `H1EmptyRule` (`ONPAGE_H1_EMPTY`): Sayfada geçerli en az bir H1 başlığı bulunmaması tespiti; **HTML5 standardında birden fazla H1 geçerli kabul edilir, mutlak ceza verilmez** (WARN/PASS/UNKNOWN).
  - `HeadingHierarchyRule` (`ONPAGE_HEADING_HIERARCHY`): Başlık seviyesi atlamaları örn. H1 -> H3 tespiti (WARN/PASS/NOT_APPLICABLE/UNKNOWN).
  - `HtmlLangRule` (`ONPAGE_HTML_LANG`): `<html lang="...">` dil etiketi eksikliği tespiti (WARN/PASS/UNKNOWN).
  - `CharsetRule` (`ONPAGE_CHARSET`): Karakter seti eksikliği veya HTTP ve HTML meta charset çakışması tespiti (WARN/PASS/UNKNOWN).
  - `OgMetadataRule` (`ONPAGE_OG_METADATA`): Sosyal medya Open Graph (og:title, og:image) etiketleri eksikliği tespiti (WARN/PASS/UNKNOWN).
  - `MetaKeywordsRule` (`ONPAGE_META_KEYWORDS`): Google tarafından meta keywords etiketinin dikkate alınmadığı bilgisi (PASS info/UNKNOWN).
  - `AnchorEmptyRule` (`LINK_ANCHOR_EMPTY`): Boş metinli bağlantıların tespiti (WARN/PASS/NOT_APPLICABLE/UNKNOWN).
  - `RelNofollowRule` (`LINK_REL_NOFOLLOW`): Dahili (iç) bağlantılarda nofollow kullanımı tespiti (WARN/PASS/NOT_APPLICABLE/UNKNOWN).
  - `ImageAltRule` (`A11Y_IMAGE_ALT`): Sayfadaki görsellerin alt metinleri kontrolü; **WCAG standardına göre `alt=""` boş metin dekoratif görsel kabul edilir ve eksik sayılmaz, yalnızca `alt === null` eksik sayılır** (WARN/PASS/NOT_APPLICABLE/UNKNOWN).
- Kural fabrikası `OnPageRuleFactory` (`app/SeoAnalyzer/Rules/OnPageRuleFactory.php`) oluşturuldu.
- `tr-TR` ve `en-US` dil dosyalarına 13 On-Page kuralı için çözüm önerisi (`rules.ONPAGE_*.fix`, `rules.LINK_*.fix`, `rules.A11Y_*.fix`) anahtarları eklendi.
- Kapsamlı birim testleri (1 yeni test dosyası, 37 test, 223 assertion) eklendi (`OnPageRulesTest`). Toplam test sayısı 316 test, 2255 assertion (%100 PASS).
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F02.05'e geçilmez (DUR).

## 2026-11-04 | F02.03 kabulü
- Kullanıcı “onaylıyorum” ile F02.03 teslimi, PR #16 birleştirmesi ve F02.04 başlangıcını onayladı; kabul kaydı main dalına birleştirildi. F02.04 (On-page kuralları) başlatılacaktır.

## 2026-11-04 | F02.03 | READY_FOR_REVIEW
- Teknik SEO Kuralları Motoru (`docs/reports/F02.03.md`) tamamlandı.
- Kural ve bulgu sözleşmesi geliştirildi: `FindingStatus`, `FindingSeverity`, `Confidence` enum sınıfları ve `Finding` (`app/SeoAnalyzer/Domain/Finding.php`) immutable değer nesnesi oluşturuldu (`examples/finding.schema.json` ve Q-001 uyumlu: `data_source` string ve `evidence` nesneler dizisi).
- Değişmez analiz bağlamı nesnesi `PageSnapshot` (`app/SeoAnalyzer/Domain/PageSnapshot.php`) ve `RuleInterface` sözleşmesi (`app/SeoAnalyzer/Domain/RuleInterface.php`) geliştirildi. Kuralların herhangi bir harici I/O (ağ/DB/dosya) yapması engellendi.
- Kural kayıt defteri `RuleRegistry` (`app/SeoAnalyzer/Domain/RuleRegistry.php`) ve `RuleRegistryInterface` oluşturuldu.
- 19 kritik teknik SEO kuralı (`app/SeoAnalyzer/Rules/Technical/`) geliştirildi:
  - `HttpStatusRule` (`TECH_HTTP_STATUS`): HTTP 200 (PASS), 3xx (WARN), 4xx (FAIL HIGH), 5xx (FAIL CRITICAL), yanıtsız (UNKNOWN).
  - `RedirectChainRule` (`TECH_REDIRECT_CHAIN`): 0-1 yönlendirme (PASS), 2 adım (WARN), 3+ adım aşırı zincir (FAIL).
  - `RedirectLoopRule` (`TECH_REDIRECT_LOOP`): Yönlendirme döngüsü veya max hops aşımı tespiti (FAIL/PASS).
  - `HttpsRule` (`TECH_HTTPS`): HTTPS protokolü ve karma içerik (mixed content) tespiti (PASS/WARN/FAIL).
  - `HttpsDowngradeRule` (`TECH_HTTPS_DOWNGRADE`): HTTPS'ten HTTP'ye güvensiz düşüş tespiti (FAIL/PASS/NOT_APPLICABLE).
  - `RobotsDisallowRule` (`TECH_ROBOTS_DISALLOW`): robots.txt tarama engeli denetimi (WARN/PASS/UNKNOWN).
  - `RobotsUnavailableRule` (`TECH_ROBOTS_UNAVAILABLE`): robots.txt 5xx sunucu hatasında Google standartlarında tarama durdurma tespiti (UNKNOWN/PASS).
  - `MetaNoindexRule` (`TECH_META_NOINDEX`): HTML meta robots `noindex` veya `none` yönergesi tespiti (WARN/PASS/UNKNOWN).
  - `XRobotsNoindexRule` (`TECH_X_ROBOTS_NOINDEX`): HTTP yanıt başlığında `X-Robots-Tag: noindex` veya `none` tespiti (WARN/PASS/UNKNOWN).
  - `RobotsNoindexConflictRule` (`TECH_ROBOTS_NOINDEX_CONFLICT`): robots.txt engeli nedeniyle HTML noindex yönergesinin görülememe çelişkisinin tespiti (FAIL/PASS).
  - `CanonicalMissingRule` (`TECH_CANONICAL_MISSING`): Kanonik bağlantı eksikliği tespiti (WARN info/PASS/UNKNOWN).
  - `CanonicalEmptyRule` (`TECH_CANONICAL_EMPTY`): Boş veya geçersiz canonical href tespiti (FAIL/PASS/NOT_APPLICABLE).
  - `CanonicalMultiConflictRule` (`TECH_CANONICAL_MULTI_CONFLICT`): HTML ve HTTP Link başlıklarında birden fazla çelişkili kanonik URL tespiti (FAIL/PASS).
  - `CanonicalRedirectRule` (`TECH_CANONICAL_REDIRECT`): Kanonik URL'in yönlendirilen eski bir adrese işaret etmesi tespiti (WARN/PASS/NOT_APPLICABLE).
  - `CanonicalOutOfScopeRule` (`TECH_CANONICAL_OUT_OF_SCOPE`): Cross-domain canonical tespiti (WARN/PASS/NOT_APPLICABLE).
  - `Soft404SuspectRule` (`TECH_SOFT_404_SUSPECT`): HTTP 200 yanıtı fakat hata mesajı veya zayıf içerik tespiti (WARN heuristic/PASS/NOT_APPLICABLE).
  - `IndexabilityInferenceRule` (`TECH_INDEXABILITY_INFERENCE`): Teknik sinyallere göre indekslenebilirlik sentezi (PASS/WARN/UNKNOWN; Google sıralama garantisi iddiası içermez).
  - `SitemapMissingRule` (`TECH_SITEMAP_MISSING`): `/sitemap.xml` veya robots.txt içinde sitemap bulunamaması tespiti (WARN info/PASS/UNKNOWN).
  - `SitemapStatusRule` (`TECH_SITEMAP_STATUS`): Keşfedilen sitemap dosyalarının 4xx/5xx hata durumu tespiti (FAIL/PASS/NOT_APPLICABLE/UNKNOWN).
- Kural fabrikası `TechnicalRuleFactory` (`app/SeoAnalyzer/Rules/TechnicalRuleFactory.php`) oluşturuldu.
- `tr-TR` ve `en-US` dil dosyalarına 19 kural için çözüm önerisi (`rules.TECH_*.fix`) anahtarları eklendi.
- Kapsamlı birim testleri (4 yeni test dosyası, 27 yeni test, 216 yeni assertion) eklendi (`FindingTest`, `PageSnapshotTest`, `RuleRegistryTest`, `TechnicalRulesTest`).
- Toplam test sayısı 279 teste, assertion sayısı 2020'ye ulaştı; %100 PSR-12, PHPStan Level 8 ve sıfır sır sızıntısı korundu.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F02.04'e geçilmez (DUR).

## 2026-11-04 | F02.02 kabulü
- Kullanıcı “onaylıyorum” ile F02.02 teslimi, PR #15 birleştirmesi ve F02.03 başlangıcını onayladı; kabul kaydı main dalına birleştirildi. F02.03 (Teknik SEO kuralları) başlatılacaktır.

## 2026-11-04 | F02.02 | READY_FOR_REVIEW
- HTML Parser ve Metadata Motoru (`docs/reports/F02.02.md`) tamamlandı.
- `HtmlParserInterface` ve `HtmlParser` (`app/Core/Parser/HtmlParser.php`) geliştirildi; `DOMDocument` ve `DOMXPath` ile HTML belgelerinden sayfa başlığı, meta açıklaması, başlık hiyerarşisi (H1-H6), sayfa dili, kanonik bağlantılar, Open Graph ve Twitter Card meta verileri, görsel ve bağlantı listeleri çıkarıldı.
- Tip güvenli ve değişmez (immutable) veri yapısı `ParsedDocument` (`app/Core/Parser/ParsedDocument.php`) oluşturuldu (`title`, `metaDescription`, `canonical`, `headings`, `robotsDirectives`, `openGraph`, `twitterCards`, `images`, `links`, `charset`, `textLength`, `wordCount`, `parseTimeMs`, `isMalformed`, `toArray()`).
- T03 tehdit koruması: `LIBXML_NONET` bayrağı ile XXE ve dış varlık yükleme saldırıları (`file:///`, `http://169.254.169.254`) kesin olarak engellendi.
- DoS ve bellek koruması: Varsayılan 5 MB azami HTML gövde sınırı ve `HtmlTooLargeException` eklendi.
- Türkçe karakter ve UTF-8 tam uyumluluğu sağlandı; ISO-8859-9 ve Windows-1254 karakter kümeleri UTF-8'e normalize edildi, libxml ayrıştırmasında `<?xml encoding="UTF-8">` öneki ve meta charset eşlemesiyle Türkçe karakterlerin (`ş, ğ, ı, ö, ü, ç, İ, Ğ, Ü, Ş, Ö, Ç`) sıfır bozulma ile korunması garanti altına alındı.
- Tek geçişli (single-pass) meta çıkarımı ile description, robots, googlebot, og:* ve twitter:* etiketleri tek döngüde ayrıştırıldı.
- WCAG standartlarına uygun eksik alt tespiti uygulandı; boş `alt=""` dekoratif görsel olarak geçerli kabul edildi ve eksik sayılmadı, yalnızca `alt === null` eksik alt metni olarak işaretlendi.
- RFC 3986 göreceli URL çözücü (`resolveUrl`) geliştirildi; kök-göreli, protokol-göreli (`//`), dizin-göreli (`../`), sorgu dizgeli (`?query`), çapalı (`#hash`) bağlantılar ve `<base href="...">` etiketi standartlara uygun çözümlendi.
- İç/dış bağlantı ayrımı ve `rel="nofollow"` tespiti tamamlandı.
- Görünmeyen içerik (`<script>`, `<style>`, `<noscript>`, `<template>`, `<svg>`, `<canvas>`) metin sayımından elendi; Unicode uyumlu kelime ve karakter sayımı yapıldı.
- HTML5 semantik etiketlerine (`header`, `nav`, `main`, `section`, `footer`) ait libxml HTML4 uyarıları filtrelenerek yalnızca gerçek sözdizimi hatalarında `isMalformed = true` işaretlenmesi sağlandı; bozuk HTML'de sıfır çökme toleransı kanıtlandı.
- Zengin test fixture'ları (`tests/Fixtures/HtmlFixtures.php`) ve 15 yeni birim testi (`tests/Unit/Parser/HtmlParserTest.php`, 153 assertion) eklendi.
- CsrfTest flaky rastlantısal token arızası giderildi (`tests/Unit/Security/CsrfTest.php`).
- Toplam 252 test, 1804 assertion ile %100 PSR-12, PHPStan Level 8 ve sıfır sır sızıntısı sağlandı.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F02.03'e geçilmez (DUR).
- Kullanıcı “onaylıyorum” ile F02.01 teslimi, PR #14 birleştirmesi ve F02.02 başlangıcını onayladı; kabul kaydı main dalına birleştirildi. F02.02 (HTML parser ve metadata) başlatılacaktır.

## 2026-11-04 | F02.01 | READY_FOR_REVIEW
- URL Formu ve Analiz Yaşam Döngüsü (`docs/reports/F02.01.md`) tamamlandı.
- Responsive, modern ve erişilebilir (ARIA) ana sayfa URL analiz formu (`resources/views/home.php`) geliştirildi; istemci ve sunucu tarafı doğrulama, CSRF kalkanı, progressive enhancement desteği ve loading spinner entegre edildi.
- Çift yönlü migration (`database/migrations/0004_enhance_analyses_table.php`) ile `analyses` tablosuna `target_url`, `progress_percent`, `error_code`, `error_message` alanları ve indeks eklendi.
- Analiz yaşam döngüsü motoru (`app/Core/Analysis/AnalysisLifecycleService.php`) geliştirildi; URL SSRF doğrulaması (fail-closed), idempotency anahtarı yönetimi, `sites` ilişkisi kurma, `analyses` kaydı oluşturma ve `analysis_jobs` kuyruğuna asenkron iş ekleme tamamlandı.
- Sahte yüzdelik (fake timer) kesinlikle kullanılmadı; ilerleme yüzdesi ve aşama durumları (`queued`, `fetching`, `rules`, `reporting`) doğrudan veritabanı durumundan hesaplandı.
- Anonim kullanıcı analiz kotaları (`config/analysis.php`) uygulandı (IP başına 600 saniyede azami 5 analiz; aşımda `AnalysisRateLimitedException`, HTTP 429 ve `Retry-After` başlığı).
- REST API uç noktaları (`app/Controllers/AnalysisController.php`) eklendi:
  - `POST /api/v1/analyses`: Asenkron analiz başlatır (HTTP 202 Accepted ve `Location` başlığı).
  - `GET /api/v1/analyses/{id}`: Analiz özet durumunu döndürür (HTTP 200).
  - `GET /api/v1/analyses/{id}/progress`: Gerçek aşamaları ve veritabanı kaynaklı ilerlemeyi döndürür.
  - `POST /analyze`: Web form gönderimi (HTML redirect veya AJAX 202).
  - `GET /analysis/{id}`: Canlı analiz ilerleme sayfası (`resources/views/analysis/progress.php`).
- İstemci tarafı canlı takip scripti (`public/js/analysis.js`) eklendi; yapay timer olmadan sunucudan 1.5 saniyede bir polling yaparak ARIA progress bar ve aşama ikonlarını günceller.
- Özel router parametrik rota desteği (`{param}` sözdizimi, `getRouteParam`) `app/Core/Router.php` ve `app/Core/Request.php` içerisine eklendi.
- `tr-TR` ve `en-US` dil dosyalarına 18 yeni analiz ve aşama çevirisi eklendi.
- 22 yeni birim ve entegrasyon testi eklendi (`tests/Unit/Analysis/AnalysisLifecycleTest.php`, `tests/Integration/Analysis/AnalysisApiTest.php`); toplam 237 test ve 1630 assertion ile %100 PSR-12, PHPStan Level 8 ve sıfır sır sızıntısı sağlandı.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F02.02'ye geçilmez (DUR).

## 2026-11-04 | F01.07 ve F01 Ana Faz Kapanış Kabulü
- Kullanıcı “onaylıyorum” ile F01.07 alt faz teslimi, PR #13 birleştirmesi ve F01 (V0.1: Güvenli çekirdek ve worker) ana fazının resmen başarıyla kapatılmasını onayladı; kabul kaydı main dalına birleştirildi. F02.01 başlatılacaktır.

## 2026-11-04 | F01.07 | READY_FOR_REVIEW
- F01 Güvenlik Testi ve Ana Faz Kapanışı (`docs/reports/F01.07.md`, `docs/reports/F01-PHASE-SUMMARY.md`) tamamlandı.
- Kapsamlı SSRF savunma matrisi entegrasyon testi (`tests/Integration/Security/SsrfMatrixTest.php`, 10 test, 186 assertion) eklendi; 34 bloklu IP, 6 genel IP, 19 bloklu URL ve 27 atlatma vektörünün tamamı, ayrıca yönlendirmelerde DNS rebinding ve loop ataklarının cURL çalıştırılmadan engellendiği kanıtlandı.
- Veri ve kiracı izolasyonu entegrasyon testi (`tests/Integration/Security/DataIsolationTest.php`, 6 test, 37 assertion) eklendi; transaction rollback sıfır yetim veri, kiracı/domain izolasyonu, 5 hatalı denemede brute-force IP kilidi, session fixation engeli, cross-session CSRF sızıntısı engeli ve bağımsız hız sınırlama kotaları kanıtlandı.
- Sağlık kontrolü ve canlılık testi (`tests/Integration/Security/HealthCheckTest.php`, 3 test, 22 assertion) ve `app/Controllers/HealthController.php` güncellemesi yapıldı; liveness (`/health`) ve readiness (`/health?detailed=1`, veritabanı `SELECT 1` kontrolü) uç noktaları HTTP 200 ve HTTP 503 senaryolarıyla doğrulandı.
- `app/Core/Request.php` ve `app/Core/Response.php` altyapı iyileştirmeleri tamamlandı (query string otomatik ayrıştırma ve `withHeader` metodu).
- Operasyonel kullanım ve CLI rehberi (`docs/operations/CLI.md`) oluşturuldu; `docs/architecture/OPERATIONS.md` ve `docs/adr/ADR-001-stack.md` referansları güncellendi.
- Toplam 214 test ve 1456 assertion ile %100 PSR-12, PHPStan Level 8 ve sıfır sır sızıntısı sağlandı.
- Kullanıcı alt faz teslim kabulü, PR #13 incelemesi ve F01 ana faz kapanış onayı bekleniyor; F02 ana fazına geçilmez (DUR).

## 2026-11-04 | F01.06 kabulü
- Kullanıcı “onaylıyorum” ile F01.06 teslimi, PR #12 birleştirmesi ve F01.07 başlangıcını onayladı; kabul kaydı main dalına birleştirildi.

## 2026-11-04 | F01.06 | READY_FOR_REVIEW
- Gözlemlenebilirlik, log maskeleme ve metrik motoru (`app/Core/Observability/LogMasker.php`, `Logger.php`, `LoggerInterface.php`, `MetricsCollector.php`) geliştirildi.
- `LogMasker` ile şifreler, token'lar, Bearer/Basic auth, URL içi kimlik bilgileri, query parametreleri, PEM özel anahtarlar ve kredi kartları hem log mesajlarında hem de iç içe context dizilerinde otomatik olarak maskelendi (`[REDACTED]`); sıfır sır sızıntısı güvenceye alındı.
- `Request` ve `Response` katmanına `X-Correlation-Id` zinciri eklendi; tüm istek ve hatalar correlation ID ve request ID ile eşleştirildi.
- `MetricsCollector` ile Prometheus uyumlu sayaç (counter), gösterge (gauge) ve süre (timing/histogram) takibi sağlandı.
- Hız sınırlama ve API throttling motoru (`app/Core/Security/RateLimiter.php`) sliding window algoritması, bellek ve SQLite/MySQL `rate_limits` tablosu desteği ve `availableIn()` ile RFC 7231 `Retry-After` hesaplamasıyla hayata geçirildi.
- Tarama ve analiz kaynak bütçesi (`app/Core/Security/RequestBudget.php`) ile analiz başına azami istek, azami bayt ve host başına azami istek kotaları denetim altına alındı.
- Güvenli oturum motoru (`app/Core/Security/Session.php`) ile `HttpOnly`, `SameSite: Lax/Strict`, `Secure` bayrakları, hareketsizlik zaman aşımı ve oturum sabitleme (fixation) koruması (`regenerate`) geliştirildi.
- Kriptografik CSRF koruması (`app/Core/Security/Csrf.php`) 256-bit rastgele token ve zamanlama saldırılarına dayanıklı `hash_equals` ile tamamlandı.
- Yönetici kimlik doğrulama servisi (`app/Core/Auth/AdminAuth.php`) ile `users` tablosundaki admin kullanıcılar için `password_verify` doğrulaması, kaba kuvvet (brute-force) kilitlemesi ve güvenli oturum açma sağlandı.
- Çift yönlü migration (`database/migrations/0003_add_user_role.php`) ile `users` tablosuna `role` kolonu ve `rate_limits` tablosu eklendi; `0002_DevelopmentFixtureSeeder.php` güncellendi.
- `tr-TR` ve `en-US` dillerinde yeni hata ve oturum mesajları tanımlandı.
- 48 yeni test eklenerek toplam 195 test ve 1231 assertion ile %100 PSR-12 ve PHPStan Level 8 uyumluluğu sağlandı.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F01.07'ye geçilmez (DUR).

## 2026-11-04 | F01.05 kabulü
- Kullanıcı “onaylıyorum” ile F01.05 teslimi, PR #11 birleştirmesi ve F01.06 başlangıcını onayladı; kabul kaydı main dalına birleştirilecek.

## 2026-11-04 | F01.05 | READY_FOR_REVIEW
- Veritabanı kuyruk ve zamanlayıcı motoru (`app/Core/Queue/DatabaseQueue.php`), kuyruk sözleşmesi (`app/Core/Queue/QueueInterface.php`) ve iş veri nesnesi (`app/Core/Queue/Job.php`) geliştirildi.
- Çift yönlü migration (`database/migrations/0002_enhance_analysis_jobs.php`) ile `analysis_jobs` tablosuna `priority`, `last_error` alanları ve indeksler eklendi.
- MySQL 8+ `FOR UPDATE SKIP LOCKED` ve SQLite işlem kilidi (immediate transaction) ile atomik claim uygulandı; iki worker'ın aynı işi alması kesin olarak engellendi.
- `idempotency_key` ile mükerrer iş eklemeleri önlendi (idempotent push).
- Üstel geri çekilme ve rastgele dalgalanma motoru (`app/Core/Queue/BackoffCalculator.php`) ile thundering herd korumalı yeniden deneme (bounded retries, attempts >= max_attempts durumunda failed/dead-letter) hayata geçirildi.
- İş kiralama (lease) ve heartbeat altyapısı kuruldu; süresi dolmuş veya çöken worker işleri için watchdog kurtarma mekanizması (`recoverStaleLeases()`) geliştirildi.
- CLI worker daemon servisi (`app/Core/Queue/Worker.php`, `bin/worker.php`) ve crontab periyodik tetikleyicisi (`app/Core/Queue/WorkerScheduler.php`, `bin/scheduler.php`) yazıldı.
- İki dilli hata mesajları (`tr-TR` ve `en-US`) eşitlendi; eş zamanlılık, yarış durumu, çökme kurtarması ve CLI process testlerini içeren 26 yeni test ile toplam 147 test ve 991 assertion başarıyla doğrulandı.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F01.06'ya geçilmez (DUR).

## 2026-11-04 | F01.04 kabulü
- Kullanıcı “onaylıyorum” ile F01.04 teslimi, PR #10 birleştirmesi ve F01.05 başlangıcını onayladı; kabul kaydı main dalına birleştirilecek.

## 2026-11-04 | F01.04 | READY_FOR_REVIEW
- Güvenli dış ağ istemcisi (`app/Core/Http/SafeHttpClient.php`), sözleşmesi (`app/Core/Http/HttpClientInterface.php`) ve HTTP yanıt nesnesi (`app/Core/Http/HttpResponse.php`) geliştirildi.
- DNS Pinning (`CURLOPT_RESOLVE`), `CURLOPT_FOLLOWLOCATION => false` ile manuel yönlendirme sekmesi ve döngü denetimi (`RedirectValidator`), her sekmede iç IP pivot engeli uygulandı.
- Gzip/açma bombası koruması geliştirildi; `CURLOPT_WRITEFUNCTION` içerisinde chunk bazlı decompress sayacı ile `max_decompressed_bytes` (8MB) aşımında bağlantı derhal kesildi (`BodyTooLargeException`, `BODY_TOO_LARGE`).
- Ham gövde indirme boyutu sınırı (`max_response_bytes`, 2MB) ve `Content-Length` koruması eklendi.
- Nazik tarayıcı kimliği (`config/crawler.php`, `app/Core/Crawler/PoliteCrawlerPolicy.php`) ve istek başlıkları (`User-Agent: SitePortalBot/0.1`, `Accept`, `Accept-Encoding`, `Accept-Language`) tanımlandı.
- RFC 9309 Robots Exclusion Protocol uyumlu robots parser (`app/Core/Crawler/RobotsParser.php`) yazıldı; `User-agent` gruplaması, özel bot önceliği, prefix/wildcard `*`/end anchor `$` yol eşleşmesi, en uzun kural ve eşitlikte Allow önceliği uygulandı.
- Origin bazlı bellek robots önbelleği (`app/Core/Crawler/MemoryRobotsCache.php`) ve robots yöneticisi (`app/Core/Crawler/RobotsManager.php`) geliştirildi; 404 conditional grant, 5xx/ağ hatası fail-closed kuralı (`RobotsUnavailableException`), `assertCanCrawl()` engel denetimi, sitemap ve crawl-delay tespiti sağlandı.
- Hız sınırlaması (HTTP 429) ve sunucu hataları (HTTP 5xx) için RFC 7231 `Retry-After` (saniye ve HTTP-date) çözümü ve `HttpRateLimitedException` entegre edildi.
- İki dilli hata anahtarları (`tr-TR` ve `en-US`) tanımlandı; birim ve entegrasyon testleriyle toplam 121 test, 704 assertion ve Seviye 8 PHPStan ile doğrulandı.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F01.05'e geçilmez (DUR).

## 2026-11-04 | F01.03 kabulü
- Kullanıcı “onaylıyorum” ile F01.03 teslimi, PR #9 birleştirmesi ve F01.04 başlangıcını onayladı; kabul kaydı CI sonrasında main dalına birleştirilecek.

## 2026-11-04 | F01.03 | READY_FOR_REVIEW
- Dış ağ güvenlik politikası (`app/Core/Security/UrlSecurityPolicy.php`) ve yapılandırması (`config/security.php`) geliştirildi; `http`/`https` şema kısıtı, 80/443 port kısıtı, engellenen TLD'ler ve RFC 1918/3927/loopback/cloud metadata/ULA/IPv4-mapped IPv6 CIDR blokları tanımlandı.
- İkili `inet_pton` tabanlı yüksek performanslı IP doğrulayıcısı (`app/Core/Security/IpValidator.php`) ve `filter_var` canonical adres kontrolü eklendi; sekizlik, onluk tamsayı ve onaltılık IP atlatma girişimleri engellendi.
- DNS çözümleme arayüzü (`app/Core/Security/DnsResolverInterface.php`), sistem çözümleyicisi (`app/Core/Security/SystemDnsResolver.php`) ve testler için `MockDnsResolver` yazıldı; tüm çözümlenen IP'lerin fail-closed denetimi uygulandı.
- DNS Pinning motoru (`app/Core/Security/PinnedTarget.php`) geliştirildi; doğrulanan IP adresi sabitlenerek cURL `CURLOPT_RESOLVE` entegrasyonu sağlandı ve DNS Rebinding (TOCTOU) saldırıları önlendi.
- HTTP yönlendirme doğrulayıcısı (`app/Core/Security/RedirectValidator.php`) yazılarak mutlak/göreli adres çözümlemesi (`/`, `//`, `?`, `../`), yönlendirme döngüsü tespiti, maksimum sekme sayısı kontrolü ve hedef URL'nin tam döngü yeniden doğrulaması (kamusal sunucudan iç ağa pivot engelleme) sağlandı.
- Birim ve entegrasyon testleri (`tests/Unit/Security`, `tests/Integration/Security`) eklenerek toplam 82 test, 585 assertion ve PSR-12/PHPStan Seviye 8 ile tam doğrulama sağlandı.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F01.04'e geçilmez (DUR).

## 2026-11-03 | F01.02 kabulü
- Kullanıcı “onaylıyorum” ile F01.02 teslimi, PR #8 birleştirmesi ve F01.03 başlangıcını onayladı; kabul kaydı CI’dan sonra birleştirilecek.

## 2026-11-03 | F01.02 | READY_FOR_REVIEW
- MySQL 8+ / SQLite PDO veritabanı bağlantı katmanı (`app/Core/Database/Connection.php`) ve prepared statement sorgu sarmalayıcısı geliştirildi.
- Çift yönlü (reversible) migration motoru (`app/Core/Database/MigrationRunner.php`) ve çekirdek veritabanı şeması (`database/migrations/0001_create_core_tables.php`) yazıldı; `users`, `sites`, `rules`, `analyses`, `analysis_jobs` (Q-002 kuyruk tablosu), `pages` ve `findings` tabloları tanımlandı.
- Geliştirme tohumlama motoru (`app/Core/Database/SeederRunner.php`), 84 kural tohumu (`database/seeders/0001_RuleRegistrySeeder.php`) ve örnek fixture'lar (`database/seeders/0002_DevelopmentFixtureSeeder.php`) uygulandı.
- CLI migration ve seeder yönetim aracı (`bin/migrate.php`) ve kurulum dokümanı (`database/README.md`) eklendi.
- Birim ve entegrasyon testleri (`tests/Unit/Database`, `tests/Integration/Database`) yazılarak boş veritabanına kurulum, idempotency, rollback, re-migration ve CLI komutları toplam 49 test / 317 assertion ile doğrulandı.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F01.03'e geçilmez (DUR).

## 2026-11-03 | F01.01 kabulü
- Kullanıcı “devam edelim” ile F01.01 teslimi, PR #7 birleştirmesi ve F01.02 başlangıcını onayladı; kabul kaydı CI’dan sonra birleştirilecek.

## 2026-11-03 | F01.01 | READY_FOR_REVIEW
- PHP 8.4 tabanlı modüler monolit çalışma iskeleti (`app/Core`, `app/Controllers`, `public`, `config`, `resources`) kuruldu; haricî framework yükü olmadan PSR-4 otopaketi bağlandı.
- Front Controller (`public/index.php`), Request-ID takibi (`X-Request-Id` ve Correlation ID), dot-notasyonlu Config loader (`app/Core/Config.php`), ErrorHandler (`app/Core/ErrorHandler.php`), View motoru (`app/Core/View.php`), Router (`app/Core/Router.php`) ve iki dilli I18n çeviri altyapısı (`app/Core/I18n.php`) geliştirildi.
- Health JSON API (`/health`), kullanıcı arayüzü ana sayfası (`/`), `robots.txt`, `sitemap.xml` ile güvenli 404 ve 500 hata sayfaları (`debug=false` durumunda sızıntısız genel hata; `debug=true` durumunda geliştirici detayları) hazırlandı.
- Türkçe (`tr-TR`) ve İngilizce (`en-US`) metin anahtarları birebir ve yer tutucu parametreleri `{param}` kontrolüyle eksiksiz eşitlendi.
- Birim ve entegrasyon testleri (`tests/Unit/Core`, `tests/Integration`) yazılarak toplam 36 test, 242 assertion ve gerçek PHP sunucu HTTP çağrıları ile doğrulandı.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F01.02'ye geçilmez (DUR).

## 2026-11-03 | F00.06 kabulü ve F00 ana faz kapanışı
- Kullanıcı “onaylıyorum” ile F00.06 teslimi, PR #6 birleştirmesi ve F00 ana faz kapanışını onayladı; F01 ana fazına geçiş izni alındı.

## 2026-11-03 | F00.06 | READY_FOR_REVIEW
- Onaylı PR #5 birleştirildi; merge SHA `411643464e08b16d6bf660978ecec79d6f0472e5`; güncel main üzerinden F00.06 faz kapanış dalı açıldı.
- F00.01'den F00.05'e tüm alt faz kanıtları, test sonuçları ve onayları derlendi; F00 ana faz kapanış özeti (`F00-PHASE-SUMMARY.md`) hazırlandı.
- Açık kararlar (Q-004), main koruma önerisi ve uygulama sınırları belgelendi; F00 ana faz kabulü ve PR #6 incelemeye sunuldu.

## 2026-11-03 | F00.05 kabulü
- Kullanıcı “onaylıyorum” ile F00.05 teslimi, PR #5 birleştirmesi ve F00.06 başlangıcını onayladı; kabul kaydı CI’dan sonra birleştirilecek.

## 2026-11-03 | F00.05 | READY_FOR_REVIEW
- Onaylı PR #4 CI sonrasında birleştirildi; güncel main üzerinden repo politikası dalı açıldı.
- PR/issue/inceleme ve geri alma yönergesi, secret kontrol listesi ve main koruma önerisi hazırlandı.
- Main koruması uygulanmadı: entegrasyon yönetim erişimi 403, UI URL doğrulaması durdu. Ayrı ayar onayı yok.

## 2026-11-03 | F00.04 kabulü
- Kullanıcı “evet onaylıyorum” ile F00.04 teslimi, PR #4 birleştirmesi ve F00.05 başlangıcını onayladı; kabul kaydı CI’dan sonra birleştirilecek.

## 2026-11-03 | F00.04 | READY_FOR_REVIEW | PR #4
- Onaylı PR #3 birleştirildi; merge SHA `74c56bc9ddfa9471b168cf7908cbb49e07aca857`; güncel main'den F00.04 dalı açıldı.
- PHPUnit 12.5.43, PHPStan 2.3.5, PHP_CodeSniffer 4.0.4 ve 27 toplam dev paket exact/lock, lisans ve advisory kaydıyla eklendi. Uygulama paketi yok.
- Yerel lint/PSR-12/statik analiz/3 fixture TEST (74 assertion) ve dört beklenen hata kapısı geçti. İlk Windows wrapper/dosya erişimi sorunu giderildi.
- Tam SHA pinli, contents:read CI workflow ve temiz checkout komutları yazıldı; [Gerçek temiz checkout CI](https://github.com/Boydaksu/siteportal/actions/runs/41666506494) success; içerik SHA `06b3e12fd122eeef24dbc748c956d79ff4ed85bb`, runtime PHP 8.4.27/Composer 2.10.2.
- TESTING, fixture/sınırlı kontrol kapsamı, lisans/audit kanıtı, envanter ve durum dosyaları güncellendi. F00.05 başlamadı; F00.04 kullanıcı teslim kabulü bekleniyor.

## 2026-11-03 | F00.03 kabulü
- Kullanıcı “devam edelim” ile F00.03 teslimi, PR #3 birleştirmesi ve F00.04 başlangıcını onayladı; bağımlılık politikası kabul kaydı güncellendi.

## 2026-11-03 | F00.03 | READY_FOR_REVIEW | PR #3
- Onaylı F00.02 PR #2 birleştirildi; merge SHA `8ed972e5e74806ea62f5dd50e7f7953570472e4c`.
- Güncel main'den F00.03 dalı açıldı; teknik ADR gerekçeleri, modül sınırları, bağımlılık/lisans/sürüm politikası ve başlangıç tehdit modeli hazırlandı.
- Q-001–003 somut önerilerine ayrı “Üç öneriyi de onaylıyorum” onayı alındı. data_source/evidence dizisi, analysis_jobs ve her uygulanan özellikte tam tr/en kapsamı belge/örneklerde uzlaştırıldı.
- Yeni paket, framework, PHP/DB uygulaması, migration veya CI kurulmadı. Proje dağıtım lisansı Q-004 olarak yayın öncesi açık kaldı.
- İlgili belge/envanter/sözleşme kontrolleri ve 7/7 bağlantı TEST PASS; rapor/log ve [PR #3](https://github.com/Boydaksu/siteportal/pull/3) güncel. Kanıt commit `0e4206400f34bda67d0358ce1fc05b57dd7ae77d`; teslim metadata kaydı sonraki commit’te. F00.04 başlamadı; kullanıcı teslim kabulü bekleniyor.

## 2026-11-03 | F00.02 kabulü
- Kullanıcının “devam edelim” yanıtıyla F00.02 teslimi, PR #2 birleştirmesi ve F00.03 başlangıcı onaylandı.

## 2026-11-03 | F00.02 | READY_FOR_REVIEW | PR #2
- F00.01 kullanıcı kabulü ve ayrı merge izniyle PR #1 birleştirildi; merge SHA: `09716d298587dc29ea5d9092127a65d0f24a4c07`.
- Güncel main üzerinden `phase/F00.02-docs-contract` açıldı.
- Başlangıç rehberleri ve belge haritası düzenlendi; Türkçe bakım standardı ve açık karar kayıtları eklendi.
- Belge denetimi aktif alt fazdan bağımsızlaştırıldı; yerel dosya/başlık ve master bağlantı kontrolleri eklendi.
- Uygulama/CI/ADR geliştirilmedi; F00.03 başlamadı. Belge/envanter kontrolleri ve 7/7 TEST PASS; ilk devir FAIL düzeltildi, ikinci sıfır bağlamlı ajan temiz sabit HEAD üzerinde PASS verdi. Kanıtlar F00.02 raporu, kontrol günlüğü ve devir kaydında. Kullanıcı kabulü bekleniyor.

## 2026-11-03 | F00.01 kabulü
- Kullanıcı “devam edelim” ile teslimi kabul etti ve F00.02 başlangıcını onayladı.
- Ayrı “Evet, PR #1’i birleştir” yanıtıyla PR #1 merge onayı alındı; onay kaydı commit’e eklendi.

## 2026-11-03 | F00.01 | READY_FOR_REVIEW | PR #1 | kanıt 611ad26740e4d2d984f56810f924726cc8496827
- GitHub main, gerçek dosya içeriği, başlangıç commit'i, tek dal, açık PR ve yazma izinleri doğrulandı.
- 57 dosyalık plan paketi envanteri ve orijinal manifest korundu; başlangıç paketi geliştirme dalına alındı.
- Durum dosyaları, envanter raporu ve tekrar çalıştırılabilir belge/envanter kontrolü eklendi.
- Uygulama geliştirilmedi; F00.02 başlamadı; kullanıcı kabulü bekleniyor.
- Push başarılı; PR: https://github.com/Boydaksu/siteportal/pull/1. Belge/envanter kontrolleri PASS; CI yok. PR ayrıca bu teslim metadata kaydını içerir. Kullanıcı kabulü alınmadı.

## Blueprint 0.1.0 — 2026-11-03
- Proje şartnamesi, faz/alt faz, kabul koşulları, iş akışı, güvenlik, SEO kuralları, API ve veritabanı taslakları hazırlandı.
- **Uygulama kodu ve faz tamamlaması yok.** Bu yalnızca plan paketinin başlangıç sürümüdür.

## Sonraki değişiklik kayıt formatı
`YYYY-MM-DD | Fxx.yy | commit SHA | PR # | Added/Changed/Fixed/Tested | onay durumu`
