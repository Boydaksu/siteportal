# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F05.03-oauth-security`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.2.0; F05 / V2.0 IN_PROGRESS (F00, F01, F02, F03 ve F04 ana fazları DONE; F05.01 ve F05.02 DONE; F05.03 READY_FOR_REVIEW).
- **Son onay:** F05.02 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #37 merge ve F05.03 başlangıç hazırlığını onayladı; PR #37 main dalına squash-merge edildi (`bada501868bf2da8530d8813f7a0ff7ed2c8521b`).
- **Önceki teslim:** [PR #37](https://github.com/Boydaksu/siteportal/pull/37) birleştirildi (`bada501868bf2da8530d8813f7a0ff7ed2c8521b`).
- **Aktif alt faz / durum:** F05.03 — OAuth güvenliği / READY_FOR_REVIEW (PR ve kullanıcı onayı bekleniyor).
- **Dal:** `phase/F05.03-oauth-security`.
- **Aktif PR / kanıt commit:** PR açılacak (çalışma dalı commit edilecek).
- **Rapor:** `docs/reports/F05.03.md` ve kontrol özeti `docs/reports/F05.03-checks.txt`.
- **Yerel sonuç:** PASS; PHP lint (382 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 697 test / 5005 assertion PASS (%100, 0 notice, 0 warning), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** RFC 7636 PKCE S256 test vektörü uyumluluğu, state CSRF & replay kalkanı, cross-tenant state hijacking engeli, veritabanında AES-256-GCM ile authenticated at-rest şifreleme, süresi dolan tokenların otomatik refresh_token ile yenilenmesi, minimum read-only scope zorunluluğu, Google revoke endpoint çağrısı ve yerel tokenların sıfırlanması ile 266 anahtarlı TR/EN dil eşliği (%100 PASS).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F05.04 — Search Analytics entegrasyonu.
- **Bir sonraki işlem:** PR açılması, CI kontrolü ve kullanıcının açık onayı bekleniyor.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
