# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-03, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), main.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F01 / V0.1 IN_PROGRESS, F00 ana fazı kapatıldı.
- **Son onay:** F01.02 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #8 merge ve F01.03 başlangıcını onayladı.
- **Aktif teslim:** [PR #8](https://github.com/Boydaksu/siteportal/pull/8); kanıt commit `e191134b22c7a232f05bc54b9d5c416757b32873`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41716450679) SUCCESS.
- **Aktif alt faz / durum:** F01.02 — Kurulum ve migration motoru / DONE.
- **Dal:** `phase/F01.02-setup-migration`; başlangıç main SHA `39f63de8418b31728d4050c82249533d8e32a8db`.
- **Rapor:** [F01.02](docs/reports/F01.02.md).
- **Yerel sonuç:** PASS; PHP lint (40 dosya), PSR-12, PHPStan seviye 8, PHPUnit 49 test / 311 assertion PASS, 4 olumsuz kapı, 7 link TEST, blueprint/mimari/toolchain kontrolleri PASS.
- **Uygulama testleri:** PDO bağlantısı, çift yönlü (reversible) migration motoru, 84 kural tohumlaması, geliştirme fixture'ları, CLI `bin/migrate.php` ve idempotency testleri PASS.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F01.03 — URL doğrulama / SSRF koruması; NOT_STARTED.
- **Bir sonraki işlem:** PR #8 birleştirilecek ve güncel `main` üzerinden F01.03 geliştirme dalı açılacak.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
