# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-03, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), main.
- **Plan / ürün / ana faz:** blueprint-0.1.0; uygulama yok; F00 / V0.0 IN_PROGRESS, ana faz kabulü yok.
- **Son onay:** F00.05 DONE; kullanıcı “onaylıyorum” ile kabul, PR #5 merge ve F00.06 başlangıcını onayladı.
- **Önceki teslim:** [PR #4](https://github.com/Boydaksu/siteportal/pull/4) birleştirildi; merge `36a1f4b6c5b1d2ed9b2cf50b5e348950324d443d`. Kabul commit'i `23324dad1e3c7843f476fd73f7148dfa57070cfb`, [CI](https://github.com/Boydaksu/siteportal/actions/runs/41682655805) success.
- **Aktif alt faz / durum:** F00.05 — Repo ve PR politikası / DONE.
- **Dal:** `phase/F00.05-repo-policy`; başlangıç main SHA yukarıdaki merge'dir.
- **Aktif PR / kanıt commit:** [#5](https://github.com/Boydaksu/siteportal/pull/5) açık / `e7164369e45a6fbdd281250fcb8849a36bae7d25`; sonraki metadata head ayrıca kontrol edilir.
- **CI:** [Run 41684279984](https://github.com/Boydaksu/siteportal/actions/runs/41684279984) SUCCESS; [makine kanıtı](docs/reports/F00.05-ci.json).
- **Rapor:** [F00.05](docs/reports/F00.05.md).
- **Yerel sonuç:** PASS; PHP lint/PSR-12/PHPStan, 3 TEST/74 assertion, 4 olumsuz kapı, 7 link TEST, belge/envanter/mimari/toolchain/master ve ters patch kontrolü. Temiz checkout CI success.
- **Uygulama testleri:** SSRF/DB/queue/ACL/UI/E2E N/A; uygulama yok. Yeni kullanıcı arayüz metni veya bağımlılık yok.
- **Main koruması:** PROPOSED_NOT_APPLIED; gözlenen protected:false. Yönetim API 403; UI URL doğrulaması durdu, ayar değişmedi. Ayrı ayar onayı alınmadı. [Öneri](docs/operations/main-protection.proposal.json).
- **Native secret scanning:** Etkinliği doğrulanmadı; yerel kalıp kontrolü tam/geçmiş taraması değildir.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki tek alt faz:** F00.06 NOT_STARTED; kullanıcı başlangıç onayı verdi.
- **Bir sonraki işlem:** F00.05 kabul/merge ve F00.06 başlangıç onayı alındı; kabul kaydı commit’inin CI’sını doğrula, PR #5’i birleştir, main’den F00.06 dalını aç.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
