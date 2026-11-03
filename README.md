# SitePortal — SEO Analiz Platformu | Ana Geliştirme Dosyası

> **Durum:** F00.01 — READY_FOR_REVIEW; envanter teslimi hazır, kullanıcı kabulü bekleniyor. Uygulama kodu yok.
> **Tarih:** 2026-11-03 (Europe/Istanbul). **Plan:** blueprint-0.1.0. **Aktif faz:** F00 / V0.0.
> **Son onaylanan alt faz:** YOK. **Son hazırlanan alt faz:** F00.01. **Sonraki:** F00.02, açık onay bekler.
> **Dal:** `phase/F00.01-repo-inventory`. **PR:** [#1](https://github.com/Boydaksu/siteportal/pull/1). **Kanıt commit:** `611ad26740e4d2d984f56810f924726cc8496827` (PR ayrıca teslim metadata commit’i içerir).
> **Kontrol:** 9/65 faz, 84 kural, 44 Markdown; manifest, CSV/JSON, TR/EN ve belge kontrolleri PASS. CI yok.
> **Rapor:** [F00.01](docs/reports/F00.01.md). **Durum kaydı:** [project-status.json](project-status.json).
> Başlangıç README içeriği `# siteportal` idi; başlık ve özgün içerik Git geçmişinde ve envanterde korunur.

## Proje
SitePortal genel, düşük bakım gerektiren web araçları platformudur. **SEO Analyzer** ilk bağımsız ürün modülüdür; hosting şirketi yazılımı değildir. Kullanıcılar URL girerek kanıtlanabilir teknik SEO analizi alır; daha derin analiz, hesap/alan adı doğrulama ve entegrasyonlarla gelişir.

## Başka bir yapay zekâ bu depoyu devralıyorsa
1. **Önce** [AGENTS.md](AGENTS.md), [START_HERE.md](START_HERE.md) ve [CURRENT_STATUS.md](CURRENT_STATUS.md) dosyalarını oku.
2. [docs/roadmap/ROADMAP.md](docs/roadmap/ROADMAP.md) ve [docs/process/WORKFLOW.md](docs/process/WORKFLOW.md) içinde sıradaki alt fazı bul.
3. **Sadece bir alt faz** gerçekleştir; testleri çalıştır, kanıtları kaydet, değişiklikleri ilgili GitHub dalına gönder ve **DUR**.
4. Kullanıcı açıkça onaylamadan sonraki alt faza geçme, PR birleştirme, üretime çıkma, mevcut sistemleri silme.
5. Bitirirken bu README üstündeki son durum satırını; `CURRENT_STATUS.md`, `project-status.json`, `CHANGELOG.md`, `docs/reports/` içindeki raporu ve ilgili faz işaretlerini güncelle.
6. Eksik yetki/anahtar/erişim durumunda uydurma başarı raporu yazma: `BLOCKED` olarak kaydet.

## Sürüm / faz tablosu
| Faz | Hedef sürüm | Konu | Başlangıç durumu |
|---|---|---|---|
| F00 | V0.0 | Proje sözleşmesi, repo ve doğrulama altyapısı | IN_PROGRESS |
| F01 | V0.1 | Güvenli PHP çekirdek ve tarama kuyruğu | BEKLİYOR |
| F02 | V1.0 | Üyeliksiz tek URL SEO analizi | BEKLİYOR |
| F03 | V1.1 | Kontrollü çok sayfalı site taraması | BEKLİYOR |
| F04 | V1.2 | Performans, yapılandırılmış veri, mobil | BEKLİYOR |
| F05 | V2.0 | Üyelik, domain doğrulama, Search Console | BEKLİYOR |
| F06 | V2.1 | İçerik, AI hazırlık, sektörel modüller | BEKLİYOR |
| F07 | V3.0 | Ajans, abonelik, API, white-label | BEKLİYOR |
| F08 | V4.0 | Ölçeklenebilirlik, yaygın çoklu dil ve olgunluk | BEKLİYOR |

## Tasarımın değişmez kuralları
- **Türkçe önce, tüm kullanıcı metinleri çeviri anahtarlarından**; İngilizce temel desteği mimaride ilk günden.
- **Modüler PHP 8.4+**, MySQL 8+, sunucuda render edilen HTML ve hafif JS; kalıcı daemon zorunlu değil. VPS ilk tercih. Browser render izole opsiyonel worker.
- **Gerçek SEO verisi ile öneri ayrı tutulur.** Google'ın resmî bir 0-100 SEO sıralama puanı yoktur. Kendi puanımızın ağırlıkları açıkça açıklanır.
- **SSRF koruması olmadan dış URL isteği yok.** Yönlendirme, DNS, IP, port, boyut ve hız kontrolü bütün HTTP yollarında geçerli.
- **Sayfa sayısı kapsamdır; 'tüm site' iddiası yalnızca doğrulanmış kapsamda mümkündür.** Eksik ölçüm hata sayılmaz.
- **Her alt fazın sonunda durakla, raporla, onay bekle.** Faz bitişinde kapsamlı rapor zorunludur.

## Tek dosyalık ana şartname
- [MASTER_PLAN_TR.md](MASTER_PLAN_TR.md) — ürün, mimari, kurallar ve bütün fazları birleştiren okunabilir Türkçe belge. Modüler kaynaklar değiştiğinde `python3 scripts/build_master_plan.py` ile tekrar üretilir.

## Rehber haritası
- Ürün kapsamı: [docs/requirements/PRODUCT.md](docs/requirements/PRODUCT.md)
- Analiz testleri: [docs/requirements/RULES.md](docs/requirements/RULES.md)
- Skor: [docs/requirements/SCORING.md](docs/requirements/SCORING.md)
- Mimari: [docs/architecture/SYSTEM.md](docs/architecture/SYSTEM.md)
- API ve veritabanı: [docs/architecture/API.md](docs/architecture/API.md), [docs/architecture/DATABASE.md](docs/architecture/DATABASE.md)
- Güvenlik: [docs/architecture/SECURITY.md](docs/architecture/SECURITY.md)
- Test ve onay: [docs/process/QA.md](docs/process/QA.md), [docs/process/WORKFLOW.md](docs/process/WORKFLOW.md)
- Resmî kaynaklar: [docs/reference/SOURCES.md](docs/reference/SOURCES.md)
- GitHub ve yayın: [docs/operations/GITHUB.md](docs/operations/GITHUB.md)

## Güncelleme protokolü (zorunlu)
Her alt faz tesliminde: `README` durum satırı + `CURRENT_STATUS.md` + `project-status.json` + `CHANGELOG.md` + `docs/reports/Fxx.xx.md` + commit SHA/PR adresi güncellenecek. Ana faz sonunda ayrıca `docs/reports/Fxx-PHASE-SUMMARY.md` ve kabul kararı hazırlanacak.

## Çalıştırma durumu
Bu ZIP **çalışan uygulama değil, sürümlendirilmiş uygulama şartnamesi ve yapay zekâ devir paketidir**. Çalıştırılabilir uygulama F01'den itibaren parça parça üretilecek. Kurulum adımları F01.02'de gerçek komutlarla doğrulanıp eklenecek. Şu anda çalışma veya test geçişi iddia edilmez.

## F00.01 başlangıç kaydı
Mevcut plan paketi `docs/operations/GITHUB.md` içindeki ilk paket aktarımı talimatıyla bu PR'a alınmıştır. F00.02'nin belge standardizasyonu ve sıfır bağlamlı devir testi yapılmadı. F00.02 ve sonraki işler NOT_STARTED.

`BUNDLE_INFO.json` orijinal paketin üretim anını anlatır; güncel push/onay durumu `project-status.json` içindedir. `FILE_MANIFEST.sha256` etkin dosyaları, `docs/reports/F00.01-original-manifest.sha256` orijinal paket özetlerini taşır. Yereldeki `siteportal_seo_masterplan/` özgün kopyadır; etkin çalışma kök dizindedir.
