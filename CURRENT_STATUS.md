# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.2.0; F05 / V2.0 IN_PROGRESS (F00, F01, F02, F03 ve F04 ana fazları DONE; F05.01 ve F05.02 DONE).
- **Son onay:** F05.02 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #37 merge ve F05.03 başlangıç hazırlığını onayladı; PR #37 main dalına squash-merge edildi (`bada501868bf2da8530d8813f7a0ff7ed2c8521b`).
- **Önceki teslim:** [PR #37](https://github.com/Boydaksu/siteportal/pull/37) birleştirildi (`bada501868bf2da8530d8813f7a0ff7ed2c8521b`).
- **Aktif alt faz / durum:** F05.02 — Domain doğrulama / DONE (F05.03 hazırlığı).
- **Dal:** `main`.
- **Aktif PR / kanıt commit:** [PR #37](https://github.com/Boydaksu/siteportal/pull/37) merge commit: `bada501868bf2da8530d8813f7a0ff7ed2c8521b`.
- **Rapor:** `docs/reports/F05.02.md` ve kontrol özeti `docs/reports/F05.02-checks.txt`.
- **Yerel sonuç:** PASS; PHP lint (373 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 671 test / 4892 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** DNS TXT token kontrolü (apex & subdomain), HTTP dosya doğrulama (`.well-known/siteportal-verify.txt`), SafeHttpClient SSRF kalkanı, token zaman aşımı (7 gün TTL), replay ve duplicate token koruması, mülkiyet gasbı engeli (cross-tenant hijacking guard) ve periyodik yeniden doğrulama (`recheckVerification`) ile 253 anahtarlı TR/EN dil eşliği (%100 PASS).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F05.03 — OAuth güvenliği (Google OAuth PKCE, encrypted tokens).
- **Bir sonraki işlem:** F05.03 şartname analizi, plan ve kullanıcı onayıyla `phase/F05.03-oauth-security` dalına geçiş.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
