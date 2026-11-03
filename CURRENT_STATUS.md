# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih:** 2026-11-03, Europe/Istanbul
- **Repo:** [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), özel; varsayılan dal main
- **Plan / ürün:** blueprint-0.1.0 / çalışan ürün sürümü yok
- **Aktif ana faz / sürüm:** F00 / V0.0; ana faz kabulü yok
- **Son onaylanan alt faz:** F00.03 — DONE
- **Önceki PR:** [#2](https://github.com/Boydaksu/siteportal/pull/2), F00.02 kabulü + merge + F00.03 başlangıcı sorusuna “devam edelim” yanıtıyla onaylandı ve birleştirildi
- **Main başlangıç / merge SHA:** `8ed972e5e74806ea62f5dd50e7f7953570472e4c`
- **Aktif alt faz / durum:** F00.03 — İlk ADR ve bağımlılık politikası / DONE
- **Aktif dal:** `phase/F00.03-architecture-policy`
- **Aktif PR / kanıt commit:** [#3](https://github.com/Boydaksu/siteportal/pull/3) (açık, birleştirilmedi) / `0e4206400f34bda67d0358ce1fc05b57dd7ae77d`; son teslim metadata commit’i PR head içinde olabilir
- **Son rapor:** [F00.03](docs/reports/F00.03.md)
- **Kontroller:** ADR/sözleşme ve belge/envanter kontrolleri PASS; 7/7 bağlantı TEST, Python syntax, master byte eşliği ve boşluk kontrolü PASS; [gerçek log](docs/reports/F00.03-checks.txt)
- **CI / uygulama testleri:** NOT_CONFIGURED / N/A; PHP/DB/crawler uygulaması yok
- **Aktif engeller:** Yok
- **Ayrı onaylanan kararlar:** Q-001 data_source/evidence dizisi, Q-002 analysis_jobs, Q-003 her uygulanan özellikte tam tr/en; kullanıcı “Üç öneriyi de onaylıyorum” dedi. [ADR kayıtları](docs/adr/README.md)
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md), proje dağıtım lisansı; yayın öncesi işletmeci seçimi gerekir. Prod ortamı/saklama/provider kararları ilgili sonraki fazlarda kalır.
- **Sonraki tek alt faz:** F00.04 — Test ve CI iskeleti; NOT_STARTED; kullanıcı başlangıç onayı verdi
- **Bir sonraki işlem:** F00.03 kabul/merge ve F00.04 başlangıç onayı alındı; onaylı PR #3’ü birleştir, main’den F00.04 dalını aç.

Karar onayı F00.03 teslim kabulü değildir; PR birleştirmesi ve sonraki alt faz ayrıca onay gerektirir. Eski raporlar ve devir testi snapshot'ları tarihsel kayıttır; güncel durumu bu dosya/JSON/aktif PR gösterir. [Bakım standardı](docs/process/MAINTENANCE.md).
