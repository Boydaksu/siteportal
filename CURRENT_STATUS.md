# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), main.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F01 / V0.1 IN_PROGRESS, F00 ana fazı kapatıldı.
- **Son onay:** F01.06 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #12 merge ve F01.07 başlangıcını onayladı.
- **Önceki teslim:** [PR #12](https://github.com/Boydaksu/siteportal/pull/12) birleştirildi; merge `a651144288cc542228e710c9331a19e0ce885970`. Kabul commit'i `6f84767222472d8cf4b6da154ee42fa41249b437`.
- **Aktif alt faz / durum:** F01.07 — F01 güvenlik testi ve kapanış / READY_FOR_REVIEW.
- **Dal:** `phase/F01.07-security-testing-closure`.
- **Aktif PR / kanıt commit:** [PR #13](https://github.com/Boydaksu/siteportal/pull/13); kanıt commit `b7e7d21583df6225a78467d8fda8ca36d843a6d9`.
- **Rapor:** [F01.07](docs/reports/F01.07.md), [F01 Ana Faz Kapanış Özeti](docs/reports/F01-PHASE-SUMMARY.md).
- **Yerel sonuç:** PASS; PHP lint (123 dosya), PSR-12, PHPStan seviye 8, PHPUnit 214 test / 1456 assertion PASS (2 skipped [sandbox döngüsel ağ]), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** SSRF savunma matrisi (34 IP, 19 URL, 27 atlatma vektörü ve DNS rebinding koruması), kuyruk yarış ve çift işleme önleme (20 paralel iş, 0 çakışma), transaction rollback sıfır yetim veri, kiracılar arası izolasyon, brute-force IP kilidi, session fixation engeli, cross-session CSRF koruması, /health ve /health?detailed=1 alt sistem kontrolleri, CLI operasyon rehberi.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz / ana faz:** F02.01 — HTML Ayrıştırıcı ve DOM Ağacı; NOT_STARTED (F01 ana faz onayı bekleniyor).
- **Bir sonraki işlem:** F01.07 teslimi ve F01 ana faz kapanış onayı bekleniyor; kullanıcı açık onayı olmadan F02 başlatılamaz (**DUR**).

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
