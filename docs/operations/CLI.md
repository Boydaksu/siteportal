# SitePortal CLI ve Operasyonel Komutlar Rehberi

Bu belge, SitePortal modüler çekirdeği tarafından sağlanan komut satırı (CLI) araçlarını, parametrelerini, kullanım senaryolarını ve [ADR-001](../adr/ADR-001-stack.md) kapsamında belirlenen operasyonel standartları açıklar.

## 1. Veritabanı ve Migration Motoru (`bin/migrate.php`)

Veritabanı şema geçişlerini (migrations) ve geliştirme fikstürlerini (seeders) güvenli ve iki yönlü (reversible) olarak yönetir.

### Kullanım Biçimi
```bash
php bin/migrate.php [aksiyon]
```

### Desteklenen Aksiyonlar
| Aksiyon | Açıklama |
|---|---|
| `migrate` veya `up` | Henüz uygulanmamış tüm bekleyen migration'ları sırayla uygular (idempotent). |
| `rollback` | En son uygulanan migration grubunu güvenli şekilde geri alır. |
| `reset` | Uygulanmış tüm migration'ları ters sırada geri alarak veritabanını temizler. |
| `status` (varsayılan) | Uygulanan ve bekleyen tüm migration dosyalarının durumunu listeler. |
| `seed` | Geliştirme ve test ortamı için başlangıç fikstürlerini veritabanına yazar. |

### Örnek Kullanım
```bash
# Migration durumunu kontrol et
php bin/migrate.php status

# Bekleyen migration'ları uygula
php bin/migrate.php migrate

# Son geçişi geri al
php bin/migrate.php rollback

# Test verilerini yükle
php bin/migrate.php seed
```

---

## 2. Kuyruk Worker Aracı (`bin/worker.php`)

Kuyruktaki (`analysis_jobs`) işleri atomik transaction kilidi (`claim`) ile alır, kiralama süresi (`lease`) içinde işler ve sonucuna göre `completed` veya `failed` durumuna geçirir.

### Kullanım Biçimi
```bash
php bin/worker.php [seçenekler]
```

### Desteklenen Parametreler
| Parametre | Varsayılan | Açıklama |
|---|---|---|
| `--worker-id=<id>` | Otomatik üretilir (`worker_<pid>_<random>`) | Worker sürecinin benzersiz kimliği. |
| `--max-jobs=<adet>` | Sınırsız (`0`) | Belirtilen iş adedine ulaşıldığında worker temiz şekilde sonlanır. |
| `--time-limit=<sn>` | Sınırsız (`0`) | Belirtilen saniye dolduğunda worker sonlanır (bellek sızıntısı önleme). |
| `--lease=<sn>` | Config (`queue.lease_seconds`, 60) | Bir işin bu worker'a kilitlendiği süre. |
| `--sleep=<sn>` | `1` | Kuyruk boşken yeni iş kontrolünden önce beklenecek süre. |
| `--once` | - | Kuyruktan yalnız 1 iş alır, işler ve hemen çıkar (CI/test için ideal). |
| `--help` | - | Komut yardım çıktısını görüntüler. |

### Örnek Kullanım
```bash
# Tek bir iş çalıştır ve çık (test / cron)
php bin/worker.php --once

# Bellek korumalı sürekli worker: 100 iş veya 1 saat sonra çık
php bin/worker.php --max-jobs=100 --time-limit=3600

# Özel kiralama süresiyle (120 sn) worker başlat
php bin/worker.php --worker-id=crawler-node-01 --lease=120
```

---

## 3. Zamanlayıcı ve Watchdog Aracı (`bin/scheduler.php`)

Kuyrukta süresi dolmuş veya çöken worker'lar nedeniyle askıda kalan (`stale lease`) işleri kurtarır (`watchdog`), zamanı gelmiş işleri (`available_at`) serbest bırakır ve cron tabanlı ortamlar için worker görevi görür.

### Kullanım Biçimi
```bash
php bin/scheduler.php [seçenekler]
```

### Desteklenen Parametreler
| Parametre | Varsayılan | Açıklama |
|---|---|---|
| `--max-jobs=<adet>` | `50` | Tek bir cron turunda işlenecek maksimum iş adedi. |
| `--time-limit=<sn>` | `55` | 1 dakikalık cron periyotları için güvenli zaman sınırı. |
| `--grace=<sn>` | `0` | Watchdog kurtarması için tolerans payı saniyesi. |
| `--watchdog-only` | - | İş çalıştırmaz; sadece kilitli kalmış bayat işleri tespit edip sıfırlar. |
| `--json` | - | İşlem istatistiklerini JSON formatında çıktı verir (otomasyon/izleme). |
| `--help` | - | Komut yardım çıktısını görüntüler. |

### Örnek Kullanım
```bash
# Standart cron çalıştırması (her dakika)
* * * * * cd /path/to/siteportal && php bin/scheduler.php --max-jobs=50 --time-limit=55 >> /var/log/siteportal/scheduler.log 2>&1

# Sadece askıda kalan işleri kurtar (watchdog)
php bin/scheduler.php --watchdog-only --json
```

---

## 4. Web Sunucusu ve Canlılık/Sağlık Uç Noktaları

Uygulamanın web arayüzü ve API uç noktaları tek giriş noktası olan `public/index.php` üzerinden sunulur.

### Yerel Geliştirme Sunucusu Başlatma
```bash
php -S 0.0.0.0:8080 -t public/
```

### Sağlık Kontrolü Uç Noktaları
- **Temel Sağlık Kontrolü (`/health`):**
  Liveness probu için kullanılır. JSON formatında `status: ok`, `app`, `version`, `environment`, `request_id` ve `timestamp` döner.
  ```bash
  curl -i http://localhost:8080/health
  ```
- **Detaylı Alt Bileşen Sağlık Kontrolü (`/health?detailed=1`):**
  Readiness probu için kullanılır. Veritabanı ve kuyruk bağlantısını kontrol eder. Tüm sistemler sağlıklıysa HTTP 200 `status: ok`, bir bileşen arızalıysa HTTP 503 `status: degraded` döner.
  ```bash
  curl -i http://localhost:8080/health?detailed=1
  ```

---

## 5. Mimari Sınırlar ve Güvenlik Hatırlatmaları

- **Root Yetkisi Yasaktır:** Worker ve scheduler komutları asla `root` yetkisiyle çalıştırılmamalıdır; sınırlı yetkili `www-data` veya `siteportal` kullanıcısı ile çalıştırılmalıdır.
- **SSRF İzolasyonu:** CLI üzerinden tetiklenen tüm crawler ve URL istekleri `SafeHttpClient` ağ sınırından geçmek zorundadır.
- **Gizli Bilgi Maskeleme:** Log çıktılarında token, şifre ve kimlik bilgileri `LogMasker` tarafından otomatik olarak `[REDACTED]` ile gizlenir.
