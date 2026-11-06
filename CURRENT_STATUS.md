# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F05.02-domain-verification`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.2.0; F05 / V2.0 IN_PROGRESS (F00, F01, F02, F03 ve F04 ana fazları DONE; F05.01 DONE; F05.02 READY_FOR_REVIEW).
- **Son onay:** F05.01 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #36 merge ve F05.02 başlangıç hazırlığını onayladı; PR #36 main dalına squash-merge edildi (`c95f56e47054b991631f99218097dc8f7420d931`).
- **Önceki teslim:** [PR #36](https://github.com/Boydaksu/siteportal/pull/36) birleştirildi (`c95f56e47054b991631f99218097dc8f7420d931`).
- **Aktif alt faz / durum:** F05.02 — Domain doğrulama / READY_FOR_REVIEW.
- **Dal:** `phase/F05.02-domain-verification`.
- **Aktif PR / kanıt commit:** PR açılacak.
- **Rapor:** `docs/reports/F05.02.md` ve kontrol özeti `docs/reports/F05.02-checks.txt`.
- **Yerel sonuç:** PASS; PHP lint (373 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 671 test / 4892 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** DNS TXT token kontrolü (apex & subdomain), HTTP dosya doğrulama (`.well-known/siteportal-verify.txt`), SafeHttpClient SSRF kalkanı, token zaman aşımı (7 gün TTL), replay ve duplicate token koruması, mülkiyet gasbı engeli (cross-tenant hijacking guard) ve periyodik yeniden doğrulama (`recheckVerification`) ile 253 anahtarlı TR/EN dil eşliği (%100 PASS).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F05.03 — OAuth güvenliği (Google OAuth PKCE, encrypted tokens).
- **Bir sonraki işlem:** PR açılarak kullanıcı incelemesine sunulması ve DUR kuralı.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
