# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.2.0; F05 / V2.0 IN_PROGRESS (F00, F01, F02, F03 ve F04 ana fazları DONE; F05.01 DONE).
- **Son onay:** F05.01 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #36 merge ve F05.02 başlangıç hazırlığını onayladı; PR #36 main dalına squash-merge edildi (`c95f56e47054b991631f99218097dc8f7420d931`).
- **Önceki teslim:** [PR #36](https://github.com/Boydaksu/siteportal/pull/36) birleştirildi (`c95f56e47054b991631f99218097dc8f7420d931`).
- **Aktif alt faz / durum:** F05.01 — Hesap ve RBAC / DONE (F05.02 hazırlığı).
- **Dal:** `main`.
- **Aktif PR / kanıt commit:** [PR #36](https://github.com/Boydaksu/siteportal/pull/36) merge commit: `c95f56e47054b991631f99218097dc8f7420d931`.
- **Rapor:** `docs/reports/F05.01.md` ve kontrol özeti `docs/reports/F05.01-checks.txt`.
- **Yerel sonuç:** PASS; PHP lint (372 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 642 test / 4764 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** E-posta doğrulama token akışı (`EmailVerificationService`), şifre sıfırlama (`PasswordResetService`), RFC 6238 TOTP MFA ve kurtarma kodları (`TotpMfaService`), brute-force kalkanı (`AccountLockedException`), rol tabanlı erişim kontrolü (`RbacManager`: admin, owner, member), misafir analiz aktarımı (`GuestAnalysisMigrationService`) ve 237 anahtarlı TR/EN dil eşliği (%100 PASS).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F05.02 — Domain doğrulama (DNS TXT token ve HTTP dosya).
- **Bir sonraki işlem:** F05.02 şartname analizi, plan ve kullanıcı onayıyla `phase/F05.02-domain-verification` dalına geçiş.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
