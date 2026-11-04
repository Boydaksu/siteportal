# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), main.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F01 / V0.1 IN_PROGRESS, F00 ana fazı kapatıldı.
- **Son onay:** F01.05 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #11 merge ve F01.06 başlangıcını onayladı.
- **Önceki teslim:** [PR #10](https://github.com/Boydaksu/siteportal/pull/10) birleştirildi; merge `a07395218cabf9399134bf7798132246884326a3`. Kabul commit'i `2f2be5484aa697f2aa25b29b4ef52b662369408b`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41730270573) SUCCESS.
- **Aktif alt faz / durum:** F01.05 — DB queue / worker scheduler / DONE.
- **Dal:** `phase/F01.05-db-queue-worker`; başlangıç main SHA `a07395218cabf9399134bf7798132246884326a3`.
- **Aktif PR / kanıt commit:** [PR #11](https://github.com/Boydaksu/siteportal/pull/11); kanıt commit `3024ab945ebf15645844b46335a4a659d69eb5a3`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41735408217) SUCCESS.
- **Rapor:** [F01.05](docs/reports/F01.05.md).
- **Yerel sonuç:** PASS; PHP lint (99 dosya), PSR-12, PHPStan seviye 8, PHPUnit 147 test / 991 assertion PASS (2 skipped [MySQL opsiyonel, sandbox döngüsel ağ]), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/toolchain kontrolleri PASS.
- **Uygulama testleri:** DatabaseQueue atomik claim (MySQL SKIP LOCKED & SQLite locking), idempotent push, bounded retries ve backoff+jitter, lease ve heartbeat watchdog kurtarması, concurrency çakışmasızlık ve CLI worker/scheduler process testleri PASS.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F01.06 — Gözlemlenebilirlik, kotalar, oturum; NOT_STARTED.
- **Bir sonraki işlem:** F01.05 kabul edildi, PR #11 main dalına birleştirilecek ve F01.06 başlatılacaktır.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
