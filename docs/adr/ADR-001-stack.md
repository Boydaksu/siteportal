# ADR-001 — PHP, veritabanı ve frontend teknik sınırları

- Tarih: 2026-11-03, Europe/Istanbul.
- Durum: BASELINE_DOCUMENTED; F00.03 teslim incelemesi bekleniyor.
- Kapsam: Mevcut [SYSTEM](../architecture/SYSTEM.md), [OPERATIONS](../architecture/OPERATIONS.md) ve kullanıcı başlangıç talimatının gerekçeli kaydı; yeni framework veya çalışan sistem kararı değil.

## Problem
Türkçe öncelikli SEO Analyzer güvenli dış HTTP istekleri ve uzun süren analizler gerektirir. Düşük bakım maliyeti ve kademeli büyüme hedeflenir. Web isteğini uzun tarama boyunca açık tutmak veya kural motoruna ağ/DB yetkisi vermek sorumlulukları ve güvenlik kontrolünü dağıtır.

## Seçenekler
| Seçenek | Kazanç | Maliyet / değerlendirme |
|---|---|---|
| Modüler PHP monolit, PDO/MySQL, CLI/cron, SSR ve vanilla JS | Küçük işletim yüzeyi, tek repo, mevcut PHP hedefine uygunluk | Router/config/queue sınırları ve testleri proje tarafından açıkça kurulmalı; seçilen başlangıç planı |
| Laravel/Symfony gibi tam framework | Hazır auth/router/queue araçları | Kullanıcının framework bağımsız mimari sınırına uymaz; onaysız eklenmez |
| SPA/Node backend ve sürekli browser render | Zengin istemci deneyimi ve JS sayfa erişimi | Yeni runtime, paket ve işletim maliyeti; ilk ürün için gerekçelendirilmedi |
| Mikroservis/Redis/Kafka/Kubernetes | Bağımsız ölçeklenme | Ölçülmüş darboğaz yok; daha fazla servis, secret ve operasyon yüzeyi |

## Karar ve sınırlar
- PHP **8.4+** modüler monolit korunur. Web giriş noktası yalnız `public/`; PHP-FPM web ve CLI worker ayrı yürütme yollarıdır. PSR-4 autoload Composer ile gerektiği fazda kurulabilir; bu aşamada composer.json/lock veya PHP dosyası üretilmez.
- PHP sürüm numarası alt sınırdır; dağıtımda resmen desteklenen bir dal ve güncel yama gerekir. 2026-11-03 tarihli [PHP tablosunda](https://www.php.net/supported-versions.php) 8.4 aktif destek sonu 2026-12-31, güvenlik desteği sonu 2028-12-31 olarak listelenir. Sonraki uygulama fazında yeniden doğrulanır; bu rapor yerel PHP/extension uyumluluk testi değildir.
- PDO + **MySQL 8+ / InnoDB** mevcut sınırdır. CLI/cron veritabanı kuyruğu; ağ çağrısı uzun transaction içinde yapılmaz. Kesin server/yama sürümü F01.02 ortam kaydında sabitlenir. [MySQL LTS modeli](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html) doğrultusunda desteklenen LTS dalı değerlendirilir; 8.4 bir adaydır, burada kurulum veya sürüm yükseltme yapılmaz.
- HTML sunucuda üretilir; vanilla JavaScript yalnız gerekli etkileşimi sağlar. Başlangıç için SPA, JS framework, npm build zinciri veya üçüncü taraf CDN script'i zorunlu değildir. Responsive CSS ve erişilebilir HTML korunur; kullanıcı metinleri çeviri anahtarlarından gelir.
- Dış istekler yalnız SafeHttpClient üzerinden; entegrasyon adaptörü de bu ağ sınırını atlayamaz. Browser renderer ancak ilgili sonraki fazda, opsiyonel ve izole olarak değerlendirilir.
- V1.0 genel erişimden önce güvenli fetch/robots/queue/rapor yetkisi ve kaynaklı bulgu gerekir. API veya DB örneği çalışan endpoint/migration değildir.

## Gerekçe, etkiler ve alternatif maliyeti
Mevcut planı uygulamak teknoloji değişimini ve servis sayısını düşük tutar; bu yaklaşım kendiliğinden güvenlik sağlamaz. Özel altyapının parser, SSRF, erişim, queue ve rollback testleri F01/F02 kabul kapılarıdır. Performans iddiası ancak ölçümle yapılır. [Modül matrisi](../architecture/MODULES.md), [bağımlılık politikası](../process/DEPENDENCIES.md) ve [tehdit modeli](../architecture/THREAT_MODEL.md) bu sınırların doğrulama planıdır.

## Geri dönüş ve kullanıcı onayı
Bu ADR mevcut planı açıklar; yeni servis kurmaz. Teknoloji veya kritik sınır değişirse yeni superseding ADR, alternatif/maliyet/güvenlik analizi ve açık kullanıcı onayı gerekir. İlgili kod oluşunca geri dönüş önce feature flag/önceki uygulama sürümü, DB için testli migration/backup planıdır; otomatik üretim işlemi yok. F00.03 teslim kabulü henüz alınmadı. Q-001–003 ayrı ADR-003/006 kayıtlarında açık kullanıcı onayıyla uzlaştırılmıştır; runtime sınırları burada genişletilmez.
