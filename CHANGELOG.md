# Changelog

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
