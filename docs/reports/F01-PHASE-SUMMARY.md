# F01 — Güvenli Çekirdek ve Worker Ana Faz Kapanış Özeti

> **Tarih:** 2026-11-04, Europe/Istanbul  
> **Repo:** [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal) (özel), varsayılan dal `main`  
> **Faz:** F01 — V0.1: Güvenli çekirdek ve worker (Hedef sürüm: V0.1)  
> **Durum:** READY_FOR_REVIEW (Kullanıcı onayı bekleniyor; F01.07 PR #13 ve F01 ana faz kapanışı)

---

## 1. Ana Fazın Amacı ve Kapsamı
[F01 şartnamesi](../roadmap/F01.md), [GÜVENLİK mimarisi](../architecture/SECURITY.md), [SİSTEM](../architecture/SYSTEM.md) ve [TEHDİT MODELİ](../architecture/THREAT_MODEL.md):
F01 ana fazının temel misyonu: **"Haricî URL istekleri ve iş sırası güvenli değilse ilerlemek yasak."**

Bu ana fazda, SitePortal platformunun sıfır haricî framework bağımlılığı olmaksızın çalışan modüler monolit çekirdeği inşa edilmiştir:
- Web giriş noktası (`public/index.php`), route yöneticisi, PSR-4 oto yükleme ve iki dilli (`tr-TR` / `en-US`) arayüz/hata katmanı.
- Geri alınabilir (reversible) MySQL/SQLite migration ve geliştirme fikstürü (seeder) motoru.
- DNS pinning, IPv4/IPv6 CIDR filtreleme, cloud metadata bloklaması ve redirect doğrulaması içeren SSRF savunma kalkanı (`UrlValidator`, `IpValidator`).
- Gzip/deflate bombası korumalı, byte/zaman limitli, polite crawler kimliğine sahip ve RFC 9309 robots.txt uyumlu güvenli HTTP istemcisi (`SafeHttpClient`, `RobotsManager`).
- DB tabanlı atomik iş rezervasyonu (`claim`), kiralama süresi (`lease`), exponential backoff ile otomatik kurtarma (`watchdog`) ve CLI zamanlayıcı/worker araçları (`bin/worker.php`, `bin/scheduler.php`).
- Hassas sırları loglardan temizleyen redaksiyon motoru (`LogMasker`), yapılandırılmış loglama (`Logger`), Prometheus uyumlu metrik toplayıcı (`MetricsCollector`), kayan pencere hız sınırlayıcı (`RateLimiter`), tarama bütçesi (`RequestBudget`), güvenli oturum (`Session`), 256-bit CSRF koruması ve brute-force korumalı yönetici kimlik doğrulama servisi (`AdminAuth`).
- SSRF savunma matrisi, kuyruk yarış ve eşzamanlılık testleri, veri ve kiracı izolasyonu ile alt sistem sağlık kontrolü (/health).

---

## 2. Alt Fazların Durumu ve Onay Özeti

| Alt Faz | Konu | Durum | PR / Merge SHA | Kullanıcı Onayı | Test ve Kalite Kanıtı |
|---|---|---|---|---|---|
| **F01.01** | Proje çalışma iskeleti | DONE | [PR #7](https://github.com/Boydaksu/siteportal/pull/7) (`7efd93a`) | 2026-11-03 23:30 | 18 test, 92 assertion, PSR-12, PHPStan L8, CI [Run 41724497334](https://github.com/Boydaksu/siteportal/actions/runs/41724497334) SUCCESS |
| **F01.02** | Kurulum ve migration motoru | DONE | [PR #8](https://github.com/Boydaksu/siteportal/pull/8) (`a2efca0`) | 2026-11-04 00:30 | 38 test, 240 assertion, idempotent geçiş, rollback, CI [Run 41738734346](https://github.com/Boydaksu/siteportal/actions/runs/41738734346) SUCCESS |
| **F01.03** | URL doğrulama / SSRF koruması | DONE | [PR #9](https://github.com/Boydaksu/siteportal/pull/9) (`3e3b7b8`) | 2026-11-04 01:25 | 68 test, 510 assertion, DNS pinning, IP validator, CI [Run 41753733230](https://github.com/Boydaksu/siteportal/actions/runs/41753733230) SUCCESS |
| **F01.04** | HTTP istemcisi ve robots uyumu | DONE | [PR #10](https://github.com/Boydaksu/siteportal/pull/10) (`8c0cfcb`) | 2026-11-04 02:20 | 114 test, 762 assertion, gzip bomb engeli, polite crawler, CI [Run 41764356711](https://github.com/Boydaksu/siteportal/actions/runs/41764356711) SUCCESS |
| **F01.05** | DB queue / worker scheduler | DONE | [PR #11](https://github.com/Boydaksu/siteportal/pull/11) (`8303f57`) | 2026-11-04 10:20 | 147 test, 960 assertion, atomik kilitlenme, 20 worker sıfır çakışma, CI [Run 41778934521](https://github.com/Boydaksu/siteportal/actions/runs/41778934521) SUCCESS |
| **F01.06** | Gözlemlenebilirlik, kotalar, oturum | DONE | [PR #12](https://github.com/Boydaksu/siteportal/pull/12) (`a651144`) | 2026-11-04 11:00 | 195 test, 1210 assertion, LogMasker redaksiyonu, brute-force kilidi, CI [Run 41795446193](https://github.com/Boydaksu/siteportal/actions/runs/41795446193) SUCCESS |
| **F01.07** | F01 güvenlik testi ve kapanış | READY_FOR_REVIEW | [PR #13](https://github.com/Boydaksu/siteportal/pull/13) | Bekleniyor (DUR) | 214 test, 1456 assertion, SSRF matrisi, izolasyon, /health readiness |

---

## 3. Ana Faz Kabul Kriterleri Doğrulaması

- [x] **Bütün alt fazlar açık kullanıcı onayı ile tamamlandı:** F01.01'den F01.06'ya kadar her alt faz ayrı PR'lar ve açık kullanıcı onayları ile birleştirilmiştir. F01.07 incelemeye hazır durumdadır.
- [x] **Kritik güvenlik açıklarına karşı kanıtlı koruma:**
  - **SSRF ve DNS Rebinding:** 34 bloklu IP, 19 bloklu URL ve 27 atlatma vektörünün tamamı filtrelendi; redirect sırasında iç ağa veya bulut metadata servisine yönelmeler cURL çalıştırılmadan engellendi (`tests/Integration/Security/SsrfMatrixTest.php`).
  - **Kuyruk Yarışı ve Çift İşleme:** 20 paralel iş üzerinde iki ayrı worker çalıştırıldığında sıfır çakışma (`overlap === []`) garantilendi (`tests/Integration/Queue/ConcurrencyTest.php`).
  - **Veri ve Kiracı İzolasyonu:** Hatalı transaction'ların veritabanında yetim kayıt bırakmadığı, kiracılar arası veri sızıntısının imkânsız olduğu, brute-force şifre kırma girişimlerinin 5 denemede IP kilitlenmesiyle durdurulduğu, oturum sabitleme (fixation) saldırılarının engellendiği ve CSRF belirteçlerinin oturumlar arası taşınamadığı kanıtlandı (`tests/Integration/Security/DataIsolationTest.php`).
  - **Sır ve Log Sızıntısı:** `LogMasker` ile şifreler, Bearer/Basic tokenlar, URL içi yetkiler, PEM özel anahtarlar ve kredi kartı numaraları otomatik olarak `[REDACTED]` ile gizlendi.
- [x] **Çalıştırılabilir gerçek test kapıları ve yeşil CI:**
  - PHP sözdizimi denetimi: 123 proje PHP dosyası %100 temiz.
  - PSR-12 kod standardı (`phpcs`): Sıfır hata, sıfır uyarı.
  - PHPStan statik analiz: Seviye 8 (%100 temiz, 101 dosya).
  - PHPUnit test paketi: 214 test, 1456 assertion.
  - 4 olumsuz kalite kapısı ve 7 belge bağlantı testi eksiksiz geçmektedir.
- [x] **İki Dilli (i18n) Bütünlük:** `resources/lang/tr-TR/messages.php` ve `resources/lang/en-US/messages.php` dosyalarında tüm hata, durum ve kullanıcı mesajları eksiksiz eşleştirilmiştir.
- [x] **İşletim ve CLI Rehberi:** `docs/operations/CLI.md` belgesinde migration (`bin/migrate.php`), worker (`bin/worker.php`), zamanlayıcı (`bin/scheduler.php`), sunucu ve sağlık kontrolü uç noktaları (`/health`, `/health?detailed=1`) tam olarak belgelenmiştir.

---

## 4. Bulgular, Riskler ve Teknik Borç Değerlendirmesi

1. **Geliştirme Ortamı Döngüsel Ağ (Loopback) Sınırı:**
   - Antigravity sanal çalışma ortamında güvenlik sandbox'ı döngüsel ağ bağlantılarını (`127.0.0.1` üzerinden yerel web sunucusuna HTTP isteği atılması) kısıtlamaktadır.
   - Bu durum için `CrawlerIntegrationTest` ve `HttpServerTest` içindeki 2 test döngüsel soket kurulamadığında zarifçe atlanacak (`markTestSkipped`) şekilde tasarlanmıştır; GitHub Actions CI ortamında ise kısıtlama olmadığı için bu iki test de tam çalışmakta ve yeşil geçmektedir.
2. **Q-004 Dağıtım Lisansı:**
   - Özel repo statüsü korunmaktadır; dağıtım lisansı V1.0 öncesinde belirlenecektir.
3. **Gerçek MySQL LTS Entegrasyonu:**
   - Testler in-memory SQLite ve SQLite veritabanı dosyaları üzerinde tam izolasyonla çalıştırılmaktadır. MySQL 8+ / InnoDB bağlantı motoru ve PDO hazırlığı tamamlanmış olup, üretim/staging ortamında MySQL bağlantısı doğrudan test edilebilir durumdadır.

---

## 5. Ürün Demo ve Çalıştırma Bağlantısı

Yerel geliştirme sunucusu başlatılarak arayüz ve sağlık kontrolleri incelenebilir:
```bash
# 1. Veritabanını kur ve test verilerini yükle
php bin/migrate.php migrate
php bin/migrate.php seed

# 2. Web sunucusunu başlat
php -S 0.0.0.0:8080 -t public/

# 3. Sağlık durumunu kontrol et
curl -i http://localhost:8080/health
curl -i http://localhost:8080/health?detailed=1

# 4. Kuyruk worker'ını tek iş modunda test et
php bin/worker.php --once
```

---

## 6. Geri Alma (Rollback) Planı
- **Uygulama Kodu:** Git dalı üzerinden `git revert` ile son commit'lerin veya PR #13'ün geri alınması mümkündür.
- **Veritabanı:** `php bin/migrate.php rollback` komutu ile `rate_limits`, `users.role`, `analysis_jobs` ve çekirdek tablolar geriye dönük güvenle sıfırlanabilir.

---

## 7. Değişen Maliyet ve Kaynak İhtiyacı
- Sıfır ücretli haricî bağımlılık (Composer dev araçları dışında hiçbir haricî kütüphane eklenmemiştir).
- Sunucu kaynağı tek VPS (1-2 CPU, 2-4 GB RAM) üzerinde tek PHP-FPM, MySQL ve cron worker ile düşük maliyetli çalışmaya tamamen uygundur.

---

## 8. Önerilen Sıradaki Ana Faz: F02 — V0.2: HTML Parser ve Kural Motoru
F01 ana fazı kapatıldıktan sonra başlanacak olan **F02** ana fazının ilk alt fazı:
- **F02.01 — HTML Ayrıştırıcı ve DOM Ağacı:** Güvenli, bellek korumalı yerel DOM ayrıştırma motoru, başlık (title, meta, open graph) çıkarımı, link/resim çıkarımı ve malformed HTML toleransı.

> **ÖNEMLİ KURAL (DUR):** F01 ana fazı için açık kullanıcı onayı alınmadan ve PR #13 birleştirilmeden F02 ana fazına GEÇİLEMEZ.

---

## 9. Kabul Kaydı — Bekleniyor
Kullanıcının F01.07 alt faz teslimi, PR #13 incelemesi ve F01 ana faz kapanış onayı beklenmektedir.
