# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F02.05-rule-registry-scoring`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F02 / V1.0 IN_PROGRESS (F00 ve F01 ana fazları DONE).
- **Son onay:** F02.04 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #17 merge ve F02.05 başlangıcını onayladı.
- **Önceki teslim:** [PR #17](https://github.com/Boydaksu/siteportal/pull/17) birleştirildi (`9b3580ac40b8cdecb5e01b61da5e7e643d9ff871`).
- **Aktif alt faz / durum:** F02.05 — Kural kayıt sistemi ve skorlama / READY_FOR_REVIEW.
- **Dal:** `phase/F02.05-rule-registry-scoring`.
- **Aktif PR / kanıt commit:** [PR #18](https://github.com/Boydaksu/siteportal/pull/18); kanıt commit `19f5ebb`.
- **Rapor:** [F02.05](docs/reports/F02.05.md).
- **Yerel sonuç:** PASS; PHP lint (192 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 328 test / 2425 assertion PASS (%100), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** Birleşik kural fabrikası `V1RuleRegistryFactory` (32 kural), `AuditEngineInterface` ve `AuditEngine`, deterministik `ScoringEngineInterface` ve `ScoringEngine`, değişmez `AuditScore` veri nesnesi, UNKNOWN/N/A durumlarının sıfır sayılmaması ve paydaya katılmaması matematiksel doğrulaması, Profil A (Mükemmel), Profil B (Kusurlu) ve Profil C (Kısmi/Bilinmeyen) snapshot testleri, asgari %20 kapsam güvenlik bariyeri, V1.0 kategori normalizasyonu.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F02.06 — Rapor UI ve çözüm önerisi.
- **Bir sonraki işlem:** F02.05 teslimi için PR açılacak, kontroller kullanıcıya sunularak açık onay beklenecektir.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
