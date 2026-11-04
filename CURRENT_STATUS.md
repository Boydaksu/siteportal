# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F02 / V1.0 IN_PROGRESS (F00 ve F01 ana fazları DONE).
- **Son onay:** F02.05 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #18 merge ve F02.06 başlangıcını onayladı.
- **Önceki teslim:** [PR #18](https://github.com/Boydaksu/siteportal/pull/18) birleştirildi (`172881425df9736e3135c74bf835239e56ad26eb`).
- **Aktif alt faz / durum:** F02.05 — Kural kayıt sistemi ve skorlama / DONE.
- **Dal:** `main`.
- **Aktif PR / kanıt commit:** [PR #18](https://github.com/Boydaksu/siteportal/pull/18); [CI](https://github.com/Boydaksu/siteportal/actions/runs/41829632603) SUCCESS.
- **Rapor:** [F02.05](docs/reports/F02.05.md).
- **Yerel sonuç:** PASS; PHP lint (192 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 328 test / 2425 assertion PASS (%100), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** Birleşik kural fabrikası `V1RuleRegistryFactory` (32 kural), `AuditEngineInterface` ve `AuditEngine`, deterministik `ScoringEngineInterface` ve `ScoringEngine`, değişmez `AuditScore` veri nesnesi, UNKNOWN/N/A durumlarının sıfır sayılmaması ve paydaya katılmaması matematiksel doğrulaması, Profil A (Mükemmel), Profil B (Kusurlu) ve Profil C (Kısmi/Bilinmeyen) snapshot testleri, asgari %20 kapsam güvenlik bariyeri, V1.0 kategori normalizasyonu.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F02.06 — Rapor UI ve çözüm önerisi; BAŞLATILIYOR.
- **Bir sonraki işlem:** F02.05 kapatıldı ve PR #18 main dalına birleştirildi. F02.06 için çalışma dalı açılacak ve geliştirme başlatılacaktır.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
