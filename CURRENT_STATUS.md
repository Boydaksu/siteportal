# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.2.0; F05 / V2.0 IN_PROGRESS (F00, F01, F02, F03 ve F04 ana fazları DONE; F05.01, F05.02, F05.03, F05.04, F05.05 DONE; F05.06 READY_FOR_REVIEW).
- **Son onay:** F05.05 DONE; kullanıcı “onaylıyorum” ile teslim kabulünü, PR #40 merge ve F05.06 başlangıcını onayladı; PR #40 main dalına squash-merge edildi (`ec784ff9455c245c306a1cbcd047eb68d4e5bfc1`).
- **Önceki teslim:** [PR #40](https://github.com/Boydaksu/siteportal/pull/40) birleştirildi (`ec784ff9455c245c306a1cbcd047eb68d4e5bfc1`).
- **Aktif alt faz / durum:** F05.06 — READY_FOR_REVIEW; Geçmiş taramalar, skor diff ve regresyon takibi tamamlandı, PR açılıyor.
- **Dal:** `phase/F05.06-crawl-history-diff`.
- **Aktif PR / kanıt commit:** [PR #41](https://github.com/Boydaksu/siteportal/pull/41) (`861b3d1d0111e0f5a7b5a97219e263f2a3dc74b4`).
- **Rapor:** `docs/reports/F05.06.md` ve kontrol özeti `docs/reports/F05.06-checks.txt`.
- **Yerel sonuç:** PASS; PHP lint, PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 755 test / 5376 assertion PASS (%100, 0 notice, 0 warning), 13 güvenlik testi, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** Geçmiş taramaların listelenmesi, iki tarama arasındaki genel ve kategori bazlı skor deltaları, kural kümesi uyuşmazlığı tespiti (`RULE_VERSION_MISMATCH`), bulguların `NEW`, `RESOLVED`, `PERSISTING` olarak sınıflandırılması ve yaşam döngüsü takibi (`first_seen_at`, `resolved_at`), zaman serisi trendi (`improving`, `declining`, `stable`), `analysis_diffs` önbelleklemesi, koruma adedini (`keepCount`) gözeten veri saklama (retention) temizliği ve 306 anahtarlı TR/EN dil eşliği (%100 PASS).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F05.07 — PDF ve rapor paylaşımı.
- **Bir sonraki işlem:** F05.06 commit, push, PR açılışı ve kullanıcı onayı (DUR kuralı).

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
