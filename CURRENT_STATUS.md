# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), main.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F01 / V0.1 IN_PROGRESS, F00 ana fazı kapatıldı.
- **Son onay:** F01.03 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #9 merge ve F01.04 başlangıcını onayladı.
- **Önceki teslim:** [PR #8](https://github.com/Boydaksu/siteportal/pull/8) birleştirildi; merge `e93038cabbb1e26c63890c501e07fd0776303540`. Aktif teslim PR #9 merge ediliyor.
- **Aktif alt faz / durum:** F01.03 — URL doğrulama / SSRF koruması / DONE (kullanıcı kabul etti, merge ediliyor).
- **Dal:** `phase/F01.03-ssrf-protection`; başlangıç main SHA `e93038cabbb1e26c63890c501e07fd0776303540`.
- **Aktif PR / kanıt commit:** [PR #9](https://github.com/Boydaksu/siteportal/pull/9); kanıt commit `8c6ac3e9447432f8eeea0ce41a7776472d733e8b`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41725872996) SUCCESS.
- **Rapor:** [F01.03](docs/reports/F01.03.md).
- **Yerel sonuç:** PASS; PHP lint (60 dosya), PSR-12, PHPStan seviye 8, PHPUnit 82 test / 585 assertion PASS (1 skipped [MySQL opsiyonel]), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/toolchain kontrolleri PASS.
- **Uygulama testleri:** Allowlist şema/port, RFC 1918/RFC 3927/loopback/cloud metadata/ULA/IPv4-mapped IPv6 engelleri, DNS pinning (TOCTOU rebinding koruması), göreli/mutlak yönlendirme ve döngü denetimleri PASS.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F01.04 — HTTP istemcisi ve robots uyumu; başlama onayı alındı.
- **Bir sonraki işlem:** PR #9 main dalına birleştirilecek, F01.04 dalı açılarak SafeHttpClient ve robots uyumu geliştirilecek.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
