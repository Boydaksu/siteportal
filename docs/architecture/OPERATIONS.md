# Deployment, operasyon, yedek ve maliyet

## Geliştirme
PHP 8.4+ `curl`, `dom`, `xml`, `mbstring`, `intl`, `openssl`, `pdo_mysql`; MySQL 8+; Composer (gerekli küçük kütüphaneler); cron/CLI; modern browser. Env/DB yoksa uygulama yanlışlıkla production mode'a geçmez. `public/` tek webroot.

## Ortamlar
`local` -> `staging` -> `production`. Staging gerçek kullanıcı PII ve gerçek OAuth secret kopyası barındırmaz. DB migration ve rollback staging'de onay. Release tags `v1.0.0` biçiminde sadece kabulden sonra.

## VPS önerisi
Başlangıç tek VPS PHP-FPM + nginx/Apache + MySQL + CLI cron, minimum haklarla dosya sahibi, firewall ve TLS. Worker CLI cron kısa süreli batch çalıştırılır; sürekli daemon gerekmez. GitHub Actions CI ve manuel/izinli deploy script; özel bilgileri env/secrets store'da.

## Paylaşımlı hosting alternatifi
Temel HTML analiz modülü CLI+cron olan cPanel'de çalışabilir; yoğun crawler ve Browser renderer orada önerilmez. Tarama kotasını düşür ve uzman özellikleri haricî izole worker'a taşı. Her site taraması tek PHP web isteğinde yürütülmez.

## SLO ve alarm başlangıcı
Queue length, job age, job failures, PSI/CrUX error, per-host 429, DB slow query, disk usage, API spend, suspicious URL blocks, admin login failures izlenir. RPO/RTO değerleri üretim öncesi kullanıcı onayıyla somutlaşır. Bütçe barajı aşılırsa JS/AI/PSI modüllerini durduracak feature flag olmalı.

## Yedek ve geri dönüş
DB şifreli günlük backup + restore drill; reports object store varsa ayrı version/retention; secrets backup değil korumalı altyapı. Deploy rollback: önce uygulama önceki tag, sonra gerekirse reversible migration. Çalıştırılabilir komutlar yalnızca kod üretildiğinde ve gerçekten test edildiğinde yazılır.

## KVKK / gizlilik
IP/email ve OAuth erişimleri amaçla sınırlı; aydınlatma, uygun hukuki temel, çerez politikası, kullanıcı talebiyle silme, veri minimizasyonu, retention, DPA/üçüncü taraf API veri akışı hukuk danışmanıyla doğrulanır. Üçüncü taraf sitelerin kişisel içeriği gereksiz kalıcı saklanmaz.
