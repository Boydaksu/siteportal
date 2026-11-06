# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.2.0; F05 / V2.0 IN_PROGRESS (F00, F01, F02, F03 ve F04 ana fazları DONE; F05.01, F05.02 ve F05.03 DONE).
- **Son onay:** F05.03 DONE; kullanıcı “onaylıyorum” ile teslim kabulünü, PR #38 merge ve F05.04 başlangıç hazırlığını onayladı; PR #38 main dalına squash-merge edildi (`6a0195bb80f0ee3916523b9e235c5de5c31e5127`).
- **Önceki teslim:** [PR #38](https://github.com/Boydaksu/siteportal/pull/38) birleştirildi (`6a0195bb80f0ee3916523b9e235c5de5c31e5127`).
- **Aktif alt faz / durum:** F05.03 — OAuth güvenliği / DONE (F05.04 hazırlığı).
- **Dal:** `main`.
- **Aktif PR / kanıt commit:** [PR #38](https://github.com/Boydaksu/siteportal/pull/38) merge commit: `6a0195bb80f0ee3916523b9e235c5de5c31e5127`.
- **Rapor:** `docs/reports/F05.03.md` ve kontrol özeti `docs/reports/F05.03-checks.txt`.
- **Yerel sonuç:** PASS; PHP lint (382 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 697 test / 5005 assertion PASS (%100, 0 notice, 0 warning), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** RFC 7636 PKCE S256 test vektörü uyumluluğu, state CSRF & replay kalkanı, cross-tenant state hijacking engeli, veritabanında AES-256-GCM ile authenticated at-rest şifreleme, süresi dolan tokenların otomatik refresh_token ile yenilenmesi, minimum read-only scope zorunluluğu, Google revoke endpoint çağrısı ve yerel tokenların sıfırlanması ile 266 anahtarlı TR/EN dil eşliği (%100 PASS).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F05.04 — Search Analytics entegrasyonu.
- **Bir sonraki işlem:** F05.04 şartname analizi, plan ve kullanıcı onayıyla `phase/F05.04-search-analytics` dalına geçiş.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
