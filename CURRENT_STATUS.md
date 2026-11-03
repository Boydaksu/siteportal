# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), main.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F01 / V0.1 IN_PROGRESS, F00 ana fazı kapatıldı.
- **Son onay:** F01.03 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #9 merge ve F01.04 başlangıcını onayladı.
- **Önceki teslim:** [PR #9](https://github.com/Boydaksu/siteportal/pull/9) birleştirildi; merge `aa31d3cd425bb355383095e3506dc6c162992d68`. Kabul commit'i `ba9063c19b06830732df378b8bc92d1a3c7dd500`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41725872996) SUCCESS.
- **Aktif alt faz / durum:** F01.04 — HTTP istemcisi ve robots uyumu / READY_FOR_REVIEW.
- **Dal:** `phase/F01.04-http-client-robots`; başlangıç main SHA `aa31d3cd425bb355383095e3506dc6c162992d68`.
- **Aktif PR / kanıt commit:** PR açılıyor.
- **Rapor:** [F01.04](docs/reports/F01.04.md).
- **Yerel sonuç:** PASS; PHP lint (82 dosya), PSR-12, PHPStan seviye 8, PHPUnit 121 test / 704 assertion PASS (2 skipped [MySQL opsiyonel, sandbox döngüsel ağ]), 4 olumsuz kapı, 7 link TEST, blueprint/mimari/toolchain kontrolleri PASS.
- **Uygulama testleri:** SafeHttpClient DNS pinning (CURLOPT_RESOLVE), redirect hop ve loop doğrulaması, chunk bazlı decompress/gzip bomb sınırı, ham boyut sınırı, nazik User-Agent/crawler başlıkları, RFC 9309 robots parser, user-agent grupları ve önbellek, Retry-After ve 429/503 yavaşlatma, fail-closed robots denetimi PASS.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F01.05 — DB queue / worker scheduler; NOT_STARTED.
- **Bir sonraki işlem:** Kullanıcının F01.04 teslim kabulü ve açık onayı bekleniyor; onay gelmeden F01.05'e geçilmez (DUR).

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
