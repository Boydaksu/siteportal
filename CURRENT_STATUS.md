# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F02.01-url-form-analysis-lifecycle`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F02 / V1.0 IN_PROGRESS (F00 ve F01 ana fazları DONE).
- **Son onay:** F01.07 ve F01 Ana Fazı DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #13 merge ve F01 ana faz kapanışını onayladı.
- **Önceki teslim:** [PR #13](https://github.com/Boydaksu/siteportal/pull/13) birleştirildi; merge `99a19171ad20a970bba9c434b08ea8a5b18a3ace`.
- **Aktif alt faz / durum:** F02.01 — URL formu ve analiz yaşam döngüsü / READY_FOR_REVIEW.
- **Dal:** `phase/F02.01-url-form-analysis-lifecycle`.
- **Aktif PR / kanıt commit:** PR açılacak; CI kontrolü bekleniyor.
- **Rapor:** [F02.01](docs/reports/F02.01.md).
- **Yerel sonuç:** PASS; PHP lint (131 dosya), PSR-12, PHPStan seviye 8, PHPUnit 237 test / 1630 assertion PASS, 4 olumsuz kapı, 7 link TEST, blueprint/mimari kontrolleri PASS.
- **Uygulama testleri:** Responsive URL formu (masaüstü/mobil), CSRF korumalı AJAX ve normal form gönderimi, HTTP 202 Accepted asenkron analiz oluşturma, REST API (/api/v1/analyses, /status, /progress), strictly sunucu/DB durumundan türetilen 4 aşamalı canlı takip (sahte timer YOK), ARIA erişilebilirlik (role="progressbar"), anonim kullanıcı IP kota kilitlemesi (5 istek / 600s, HTTP 429 Retry-After), SSRF koruması ve URL normalizasyonu.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki ana faz / alt faz:** F02.02 — HTML parser ve metadata; NOT_STARTED.
- **Bir sonraki işlem:** Kullanıcı F02.01 teslim raporunu inceleyecek; açık onay verirse PR merge edilecek ve F02.02'ye geçilecektir (DUR).

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
