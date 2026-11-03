# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), main.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F01 / V0.1 IN_PROGRESS, F00 ana fazı kapatıldı.
- **Son onay:** F01.02 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #8 merge ve F01.03 başlangıcını onayladı.
- **Önceki teslim:** [PR #8](https://github.com/Boydaksu/siteportal/pull/8) birleştirildi; merge `e93038cabbb1e26c63890c501e07fd0776303540`. Kabul commit'i `190bb787493a3887c4f4a3900ca89b3f36a538d6`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41717021276) SUCCESS.
- **Aktif alt faz / durum:** F01.03 — URL doğrulama / SSRF koruması / READY_FOR_REVIEW.
- **Dal:** `phase/F01.03-ssrf-protection`; başlangıç main SHA `e93038cabbb1e26c63890c501e07fd0776303540`.
- **Aktif PR / kanıt commit:** İncelemeye sunuldu (PR açılacak).
- **Rapor:** [F01.03](docs/reports/F01.03.md).
- **Yerel sonuç:** PASS; PHP lint (60 dosya), PSR-12, PHPStan seviye 8, PHPUnit 82 test / 585 assertion PASS (1 skipped [MySQL opsiyonel]), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/toolchain kontrolleri PASS.
- **Uygulama testleri:** Allowlist şema/port, RFC 1918/RFC 3927/loopback/cloud metadata/ULA/IPv4-mapped IPv6 engelleri, DNS pinning (TOCTOU rebinding koruması), göreli/mutlak yönlendirme ve döngü denetimleri PASS.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F01.04 — HTTP istemcisi ve robots uyumu; NOT_STARTED.
- **Bir sonraki işlem:** Kullanıcının F01.03 teslim kabulü ve açık onayı bekleniyor; onay gelmeden F01.04'e geçilmez (DUR).

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
