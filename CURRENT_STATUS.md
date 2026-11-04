# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F02.07-sharing-lifecycle`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F02 / V1.0 IN_PROGRESS (F00 ve F01 ana fazları DONE).
- **Son onay:** F02.06 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #19 merge ve F02.07 başlangıcını onayladı.
- **Önceki teslim:** [PR #19](https://github.com/Boydaksu/siteportal/pull/19) birleştirildi (`948c767d456c27a1dacfd66684603f17179fc6e6`).
- **Aktif alt faz / durum:** F02.07 — Anonim paylaşım ve yaşam süresi / READY_FOR_REVIEW.
- **Dal:** `phase/F02.07-sharing-lifecycle`.
- **Aktif PR / kanıt commit:** [PR #20](https://github.com/Boydaksu/siteportal/pull/20); [CI](https://github.com/Boydaksu/siteportal/actions) bekleniyor.
- **Rapor:** [F02.07](docs/reports/F02.07.md).
- **Yerel sonuç:** PASS; PHP lint (204 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 357 test / 2635 assertion PASS (%100), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** `AnalysisSharingService` (32-byte kriptografik hex token üretimi, token iptali, public/unlisted/private yetki matrisi, TTL aşımı), `AnalysisLifecycleService` (`startAnalysis` unlisted & 7 gün TTL atama, `pruneExpired` süresi dolan kayıtları cascade silme), `AnalysisController` (özel rapor kimliksiz erişimde HTTP 403 Forbidden, süresi dolan raporda HTTP 410 Gone, `X-Robots-Tag: noindex, nofollow, noarchive`, `Cache-Control: private, no-cache, no-store, must-revalidate`, `<meta name="robots" content="noindex, nofollow, noarchive">`, `/api/v1/analyses/{id}/share` ve `/visibility` rotaları), `bin/prune.php` CLI budama betiği.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F02.08 — V1.0 uçtan uca ve kapanış; kullanıcı onayı bekleniyor.
- **Bir sonraki işlem:** F02.07 teslimi için kullanıcı onayı beklenmektedir. Onay alındığında PR #20 merge edilecek ve F02.08 başlatılacaktır.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
