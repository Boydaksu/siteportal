# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F02 / V1.0 IN_PROGRESS (F00 ve F01 ana fazları DONE).
- **Son onay:** F02.01 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #14 merge ve F02.02 başlangıcını onayladı.
- **Önceki teslim:** [PR #14](https://github.com/Boydaksu/siteportal/pull/14) birleştirildi; merge `1bb0a6f5767fbaace7a42a9d2f691b1c20f60255`.
- **Aktif alt faz / durum:** F02.01 — URL formu ve analiz yaşam döngüsü / DONE.
- **Dal:** `main`.
- **Aktif PR / kanıt commit:** [PR #14](https://github.com/Boydaksu/siteportal/pull/14); merge `1bb0a6f5767fbaace7a42a9d2f691b1c20f60255`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41809068886) SUCCESS.
- **Rapor:** [F02.01](docs/reports/F02.01.md).
- **Yerel sonuç:** PASS; PHP lint (131 dosya), PSR-12, PHPStan seviye 8, PHPUnit 237 test / 1651 assertion PASS, 4 olumsuz kapı, 7 link TEST, blueprint/mimari kontrolleri PASS.
- **Uygulama testleri:** Responsive URL formu (masaüstü/mobil), CSRF korumalı AJAX ve normal form gönderimi, HTTP 202 Accepted asenkron analiz oluşturma, REST API (/api/v1/analyses, /status, /progress), strictly sunucu/DB durumundan türetilen 4 aşamalı canlı takip (sahte timer YOK), ARIA erişilebilirlik (role="progressbar"), anonim kullanıcı IP kota kilitlemesi (5 istek / 600s, HTTP 429 Retry-After), SSRF koruması ve URL normalizasyonu.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki ana faz / alt faz:** F02.02 — HTML parser ve metadata; BAŞLATILIYOR.
- **Bir sonraki işlem:** F02.01 kapatıldı ve PR #14 main dalına birleştirildi. F02.02 için çalışma dalı açılacak ve geliştirme başlatılacaktır.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
