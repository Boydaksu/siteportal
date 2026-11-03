# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih:** 2026-11-03, Europe/Istanbul
- **Repo:** [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), özel; varsayılan dal main
- **Plan / ürün:** blueprint-0.1.0 / çalışan ürün sürümü yok
- **Aktif ana faz / sürüm:** F00 / V0.0; ana faz kabulü yok
- **Son onaylanan alt faz:** F00.03 — DONE
- **Önceki PR:** [#3](https://github.com/Boydaksu/siteportal/pull/3), F00.03 kabulü + merge + F00.04 başlangıcı sorusuna “devam edelim” yanıtıyla onaylandı ve birleştirildi
- **Main başlangıç / merge SHA:** `74c56bc9ddfa9471b168cf7908cbb49e07aca857`
- **Aktif alt faz / durum:** F00.04 — Test ve CI iskeleti / IN_PROGRESS
- **Aktif dal:** `phase/F00.04-test-ci`
- **Aktif PR / kanıt commit:** Henüz oluşturulmadı
- **Son rapor:** [F00.04](docs/reports/F00.04.md)
- **Yerel kontroller:** PHP lint, PSR-12, PHPStan seviye 8, PHPUnit 3 TEST/74 assertion ve 4 olumsuz kalite kapısı TEST'i PASS. Son belge/envanter/araç, source/master ve 7 bağlantı TEST kontrolü PASS.
- **CI:** PENDING; workflow yazıldı, gerçek temiz runner sonucu henüz yok
- **Uygulama testleri:** SSRF/DB/queue/ACL/UI/E2E N/A; uygulama yok
- **Bağımlılık:** 3 kesin doğrudan/27 toplam dev paket; uygulama paketleri boş. İlk Composer audit exit=0, advisory/abandoned yok; kaynak kapsamı ve lisans kanıtları [raporda](docs/reports/F00.04-dependency-review.json).
- **Aktif engeller:** Yok; CI başarı kriteri henüz doğrulanmadı
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı. Composer kök lisans uyarısı biliniyor; değer uydurulmadı.
- **Sonraki tek alt faz:** F00.05 — Repo ve PR politikası; NOT_STARTED, başlangıç onayı yok
- **Bir sonraki işlem:** Yalnız F00.04 yerel/CI doğrulamasını tamamla, PR/rapor sun ve DUR. F00.05 başlama.

[TESTING](docs/process/TESTING.md) gerçek kapsam ve komutları gösterir. F00.03 Q-001–003 kararları onaylıdır; F00.04 teslim kabulü değildir. Eski raporlar tarihsel snapshot'tır; güncel durum bu dosya/JSON/aktif PR'dadır. Repo koruma ayarları ve üretim ortamı değiştirilmedi.
