# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), main.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F01 / V0.1 IN_PROGRESS, F00 ana fazı kapatıldı.
- **Son onay:** F01.05 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #11 merge ve F01.06 başlangıcını onayladı.
- **Önceki teslim:** [PR #11](https://github.com/Boydaksu/siteportal/pull/11) birleştirildi; merge `8303f57e130f85f1a07ccbc8b437901e4ede7447`. Kabul commit'i `737ed9f5ae422bb9dfbaec3ea897a44fbc764506`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41735642860) SUCCESS.
- **Aktif alt faz / durum:** F01.06 — Gözlemlenebilirlik, kotalar, oturum / READY_FOR_REVIEW.
- **Dal:** `phase/F01.06-observability-quotas-auth`; başlangıç main SHA `8303f57e130f85f1a07ccbc8b437901e4ede7447`.
- **Aktif PR / kanıt commit:** PR açılacak; [PR #12](https://github.com/Boydaksu/siteportal/pull/12).
- **Rapor:** [F01.06](docs/reports/F01.06.md).
- **Yerel sonuç:** PASS; PHP lint (120 dosya), PSR-12, PHPStan seviye 8, PHPUnit 195 test / 1231 assertion PASS (2 skipped [MySQL opsiyonel, sandbox döngüsel ağ]), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/toolchain kontrolleri PASS.
- **Uygulama testleri:** LogMasker secret redaksiyonu (şifre, Bearer/Basic auth, URL içi sırlar, query param sırlar, PEM anahtarlar, kredi kartları), correlation ID & request ID zinciri, MetricsCollector (Prometheus uyumlu), RateLimiter (sliding window + Retry-After), RequestBudget (istek ve bayt kotaları), Csrf (kriptografik 256-bit token ve timing-safe doğrulama), Session (HttpOnly, SameSite, Secure, fixasyon önleme), AdminAuth (brute-force kilitleme, güvenli oturum).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F01.07 — F01 güvenlik testi ve kapanış; NOT_STARTED.
- **Bir sonraki işlem:** F01.06 tamamlandı, kullanıcı onayı bekleniyor. Onay sonrası PR #12 main dalına birleştirilecek ve F01.07 başlatılacaktır.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
