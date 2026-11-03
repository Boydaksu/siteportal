# SitePortal — SEO Analiz Platformu | Ana Geliştirme Dosyası

> **Durum:** F01 — IN_PROGRESS; F01.03 READY_FOR_REVIEW.
> **Tarih:** 2026-11-04 (Europe/Istanbul). **Plan:** blueprint-0.1.0. **Aktif faz/sürüm:** F01 / V0.1.
> **Son onaylanan alt faz:** F01.02 — DONE; F01.02 teslimi onaylandı.
> **Aktif dal:** `phase/F01.03-ssrf-protection`.
> **Son kontrol:** PHP lint (60 dosya), PSR-12, PHPStan seviye 8, PHPUnit (82 test / 585 assertions) PASS.
> **Rapor:** [F01.03](docs/reports/F01.03.md). **Sonraki:** F01.04 — HTTP istemcisi ve robots uyumu; onay bekleniyor.
> **Risk:** Main koruması önerildi, uygulanmadı; yönetim erişimi yok. [Politika](docs/operations/GITHUB.md).
> **Test rehberi:** [TESTING](docs/process/TESTING.md). [Q-004 lisansı](docs/process/OPEN_QUESTIONS.md) açık.
> **Başlangıç:** [START_HERE](START_HERE.md) → [belge haritası](docs/README.md).

## Proje
SitePortal genel, düşük bakım gerektiren web araçları platformudur. **SEO Analyzer** ilk bağımsız ürün modülüdür; hosting şirketi yazılımı değildir. Kullanıcılar URL girerek kanıtlanabilir teknik SEO analizi alır; daha derin analiz, hesap/alan adı doğrulama ve entegrasyonlarla gelişir.

## Başka bir yapay zekâ bu depoyu devralıyorsa
[Sabit okuma sırasını](START_HERE.md#sabit-okuma-sırası) izle. Güncel durum [CURRENT_STATUS](CURRENT_STATUS.md) ve [project-status.json](project-status.json) içindedir. Aynı bilgiyi GitHub main/aktif PR ile karşılaştır. Yalnız izin verilen tek alt fazı gerçekleştir; [iş akışı](docs/process/WORKFLOW.md) gereği raporla ve DUR. Kullanıcı kabulü, PR merge ve yeni alt faz başlangıcı ayrı kaydedilir.

## Sürüm / faz tablosu
| Faz | Hedef sürüm | Konu | Başlangıç durumu |
|---|---|---|---|
| F00 | V0.0 | Proje sözleşmesi, repo ve doğrulama altyapısı | DONE |
| F01 | V0.1 | Güvenli PHP çekirdek ve tarama kuyruğu | GELİŞTİRİLİYOR |
| F02 | V1.0 | Üyeliksiz tek URL SEO analizi | BEKLİYOR |
| F03 | V1.1 | Kontrollü çok sayfalı site taraması | BEKLİYOR |
| F04 | V1.2 | Performans, yapılandırılmış veri, mobil | BEKLİYOR |
| F05 | V2.0 | Üyelik, domain doğrulama, Search Console | BEKLİYOR |
| F06 | V2.1 | İçerik, AI hazırlık, sektörel modüller | BEKLİYOR |
| F07 | V3.0 | Ajans, abonelik, API, white-label | BEKLİYOR |
| F08 | V4.0 | Ölçeklenebilirlik, yaygın çoklu dil ve olgunluk | BEKLİYOR |

## Tasarımın değişmez kuralları
- **Türkçe önce, tüm kullanıcı metinleri çeviri anahtarlarından**; uygulanan kullanıcı metinlerinin İngilizce anahtarları kendi fazında tamamlanır.
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
Depo çalışan uygulama değil, sürümlendirilmiş geliştirme şartnamesi ve devir kayıtları içerir. Uygulama F01'den itibaren üretilecek; kurulum adımları F01.02'de gerçek komutlarla doğrulanacak. Belge kontrolleri uygulamanın çalıştığı anlamına gelmez.

## Başlangıç paketi ve kayıtlar
F00.01, ilk plan paketini envantere aldı; [rapor](docs/reports/F00.01.md) kullanıcı tarafından kabul edildi. F00.02 rehberleri, bakım standardını ve devir doğrulamasını tamamladı ve kabul edildi. F00.03 teknik kararları/sınırları belgeledi ve kabul edildi. F00.04 yalnız test/CI araçlarını kurar; F00.05 ve sonrası başlamadı.

`BUNDLE_INFO.json` orijinal paket üretim anını anlatır; canlı durum JSON'dadır. `FILE_MANIFEST.sha256` etkin dosyaları, [özgün manifest](docs/reports/F00.01-original-manifest.sha256) ilk paketi doğrular. Yerel `siteportal_seo_masterplan/` eski korunmuş kopyadır; etkin çalışma kök dizindedir.

## Mimari ve tedarik zinciri kayıtları
[ADR dizini](docs/adr/README.md), [modül sınırları](docs/architecture/MODULES.md), [bağımlılık politikası](docs/process/DEPENDENCIES.md) ve [tehdit modeli](docs/architecture/THREAT_MODEL.md) uygulanacak sınırları açıklar; runtime veya güvenlik testi geçişi değildir.

## Kalite ve test çalıştırma
[TESTING](docs/process/TESTING.md) temiz checkout/kurulum, `python scripts/check_quality.py`, belge/envanter komutları ve gerçek test kapsamını açıklar. 27 paket yalnız geliştirme araçlarıdır; uygulama bağımlılığı yoktur. CI sonucu kendi commit/run kanıtıyla raporlanır; fixture testleri çalışan SEO ürünü iddiası değildir.
