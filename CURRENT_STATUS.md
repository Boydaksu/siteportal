# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-03, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), main.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F01 / V0.1 IN_PROGRESS, F00 ana fazı kapatıldı.
- **Son onay:** F01.01 DONE; kullanıcı “devam edelim” ile teslim kabulü, PR #7 merge ve F01.02 başlangıcını onayladı.
- **Önceki teslim:** [PR #7](https://github.com/Boydaksu/siteportal/pull/7) birleştirildi; merge `39f63de8418b31728d4050c82249533d8e32a8db`. Kabul commit'i `1da5bb77b1c5c5106c2902431111b1b1b4cd8b7d`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41713764555) SUCCESS.
- **Aktif alt faz / durum:** F01.02 — Kurulum ve migration motoru / READY_FOR_REVIEW.
- **Dal:** `phase/F01.02-setup-migration`; başlangıç main SHA `39f63de8418b31728d4050c82249533d8e32a8db`.
- **Aktif PR / kanıt commit:** [PR #8](https://github.com/Boydaksu/siteportal/pull/8); kanıt commit `5e7f47442fe41b5b018b71465b881c757fa176c7`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41716027572) SUCCESS.
- **Rapor:** [F01.02](docs/reports/F01.02.md).
- **Yerel sonuç:** PASS; PHP lint (40 dosya), PSR-12, PHPStan seviye 8, PHPUnit 49 test / 317 assertion PASS, 4 olumsuz kapı, 7 link TEST, blueprint/mimari/toolchain kontrolleri PASS.
- **Uygulama testleri:** PDO bağlantısı, çift yönlü (reversible) migration motoru, 84 kural tohumlaması, geliştirme fixture'ları, CLI `bin/migrate.php` ve idempotency testleri PASS.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F01.03 — URL doğrulama / SSRF koruması; NOT_STARTED.
- **Bir sonraki işlem:** Kullanıcının F01.02 teslim kabulü ve açık onayı bekleniyor; onay gelmeden F01.03'e geçilmez (DUR).

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
