# Veritabanı ve Migration Motoru (F01.02)

Bu dizin SitePortal'ın MySQL 8+ InnoDB (ve test için SQLite) veritabanı şema ve tohumlama dosyalarını içerir.

## İlkeler
- **MySQL 8+ PDO:** Prepared statements, `ATTR_EMULATE_PREPARES => false`, `utf8mb4` karakter seti ve strict mod zorunludur.
- **Çift Yönlü (Reversible):** Her migration `up()` ve `down()` metotlarına sahiptir. Yapılan tüm DDL değişiklikleri eksiksiz geri alınabilir.
- **Idempotent:** Boş veritabanına sorunsuz kurulur ve `migrate` komutu ardı ardına çalıştırıldığında hiçbir hata üretmez.
- **Geliştirme Tohumları (Seeders):** 84 kuralın tohumlanması (`schemas/rule-registry.seed.json`) ve örnek geliştirme/test verilerini (`sites`, `analyses`, `analysis_jobs`) içerir.

## Dizin Yapısı
```text
/database/
  migrations/   # Sıralı ve numaralı migration sınıfları
  seeders/      # Tohumlama sınıfları
  README.md     # Bu dosya
/bin/
  migrate.php   # CLI migration ve seeder aracı
```

## CLI Komutları
Tüm komutlar `bin/migrate.php` üzerinden çalıştırılır:

```bash
# Migration durumunu listele
php bin/migrate.php status

# Bekleyen migration'ları uygula
php bin/migrate.php up

# Son migration paketini (batch) geri al (rollback)
php bin/migrate.php rollback

# Tüm migration'ları sıfırla
php bin/migrate.php reset

# Kural ve geliştirme tohumlarını çalıştır
php bin/migrate.php seed
```

## Ortam Değişkenleri (`.env`)
```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=siteportal
DB_USER=siteportal
DB_PASSWORD=secret
```
İzole test ve bellek içi çalıştırma için:
```ini
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```
