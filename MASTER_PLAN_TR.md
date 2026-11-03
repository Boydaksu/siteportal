# SITEPORTAL SEO ANALYZER — TEK DOSYALIK TÜRKÇE ANA ŞARTNAME

> Bu dosya ZIP içindeki modüler belgelerden üretilmiş **okunabilir kopyadır**. Değişikliklerin tek gerçek kaynağı ilgili ayrı belgelerdir. Son durum için `CURRENT_STATUS.md` ve GitHub main kontrol edilir.

> Oluşturma: `python3 scripts/build_master_plan.py`. İlk hazırlanma tarihi: 2026-11-03.


---

<!-- KAYNAK BELGE: README.md -->
## BELGE: `README.md`

# SitePortal — SEO Analiz Platformu | Ana Geliştirme Dosyası

> **Durum:** F00.02 — READY_FOR_REVIEW; belge ve devir kontrolleri geçti, kullanıcı kabulü bekleniyor. Uygulama kodu yok.
> **Tarih:** 2026-11-03 (Europe/Istanbul). **Plan:** blueprint-0.1.0. **Aktif faz/sürüm:** F00 / V0.0.
> **Son onaylanan alt faz:** F00.01 — DONE; [PR #1](https://github.com/Boydaksu/siteportal/pull/1) kullanıcı onayıyla birleştirildi.
> **Aktif dal:** `phase/F00.02-docs-contract`. **Aktif PR:** [#2](https://github.com/Boydaksu/siteportal/pull/2) (açık, birleştirilmedi). **Devir kanıtı SHA:** `5bf77121efb203393d8e61eb7fbfa6f7e700f2e9`.
> **Son kontrol:** Belge/envanter, 7/7 TEST ve sıfır bağlamlı devir PASS. CI yok; uygulama testleri N/A.
> **Rapor:** [F00.02](docs/reports/F00.02.md). **Sonraki:** F00.03 — İlk ADR ve bağımlılık politikası; henüz onaylanmadı, başlamadı.
> **Açık kararlar:** [Q-001–003](docs/process/OPEN_QUESTIONS.md). Aktif işi durduran teknik engel yok.
> **Başlangıç:** [START_HERE](START_HERE.md) → [belge haritası](docs/README.md) → [bakım rehberi](docs/process/MAINTENANCE.md).

## Proje
SitePortal genel, düşük bakım gerektiren web araçları platformudur. **SEO Analyzer** ilk bağımsız ürün modülüdür; hosting şirketi yazılımı değildir. Kullanıcılar URL girerek kanıtlanabilir teknik SEO analizi alır; daha derin analiz, hesap/alan adı doğrulama ve entegrasyonlarla gelişir.

## Başka bir yapay zekâ bu depoyu devralıyorsa
[Sabit okuma sırasını](START_HERE.md#sabit-okuma-sırası) izle. Güncel durum [CURRENT_STATUS](CURRENT_STATUS.md) ve [project-status.json](project-status.json) içindedir. Aynı bilgiyi GitHub main/aktif PR ile karşılaştır. Yalnız izin verilen tek alt fazı gerçekleştir; [iş akışı](docs/process/WORKFLOW.md) gereği raporla ve DUR. Kullanıcı kabulü, PR merge ve yeni alt faz başlangıcı ayrı kaydedilir.

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
Depo çalışan uygulama değil, sürümlendirilmiş geliştirme şartnamesi ve devir kayıtları içerir. Uygulama F01'den itibaren üretilecek; kurulum adımları F01.02'de gerçek komutlarla doğrulanacak. Belge kontrolleri uygulamanın çalıştığı anlamına gelmez.

## Başlangıç paketi ve kayıtlar
F00.01, ilk plan paketini envantere aldı; [rapor](docs/reports/F00.01.md) kullanıcı tarafından kabul edildi. F00.02 rehberleri, bakım standardını ve devir doğrulamasını düzenler. F00.03 ve sonraki işler başlamadı.

`BUNDLE_INFO.json` orijinal paket üretim anını anlatır; canlı durum JSON'dadır. `FILE_MANIFEST.sha256` etkin dosyaları, [özgün manifest](docs/reports/F00.01-original-manifest.sha256) ilk paketi doğrular. Yerel `siteportal_seo_masterplan/` eski korunmuş kopyadır; etkin çalışma kök dizindedir.




---

<!-- KAYNAK BELGE: AGENTS.md -->
## BELGE: `AGENTS.md`

# AGENTS.md — SitePortal geliştirme ajanı bağlayıcı sözleşmesi

## Başlamadan önce
Bu dosya tüm AI kod ajanları için bağlayıcı proje sözleşmesidir. Sabit okuma sırası [START_HERE](START_HERE.md#sabit-okuma-sırası) içindedir: README → AGENTS → CURRENT_STATUS ve JSON → yol haritası/aktif faz/iş akışı → son rapor ve açık kararlar → ilgili mimari/kaynaklar. Aktif fazı dosyaya sabitleme; [project-status.json](project-status.json) kaydından bul. Kullanıcının en yeni açık talimatı önceliklidir. Mimari/sözleşme çelişkisini kaydet; önemli kararı sessizce değiştirme. [Belge haritası](docs/README.md), [bakım standardı](docs/process/MAINTENANCE.md) ve [açık sorular](docs/process/OPEN_QUESTIONS.md) devrin parçasıdır.

## Misyon
`Boydaksu/siteportal` deposunda modüler SitePortal web-utilities çatısı altında Türkçe öncelikli, çoklu dil destekli, kaynak ve kanıt gösteren SEO denetimi geliştir. İlk hedef güvenli ve kaliteli V1.0; sonra sürüm sıralamasını izle. Hosting işini bu projeye karıştırma.

## Çalışma protokolü — HER ALT FAZDA
1. İlgili Fxx.yy şartnamesini ve bağımlılıklarını oku; tamamlanan son fazı doğrula.
2. GitHub `main` güncel durumunu ve kendi çalışma dalını kontrol et. Diğer kişinin değişikliklerini ezme.
3. Alt faz için kısa uygulanabilir plan ve dokunulacak dosyaları belirt.
4. **Yalnızca mevcut alt fazın kapsamını** geliştir. Gizli özellik ekleme, bağımlılıkları rastgele artırma.
5. Statik analiz, birim/entegrasyon/güvenlik testleri ve gereken regresyonları çalıştır. Çalışmayan testleri dürüstçe göster.
6. Doküman ve durum dosyalarını aynı PR/commit içinde güncelle; `docs/reports/Fxx.yy.md` raporunu oluştur.
7. `phase/Fxx.yy-kisa-konu` dalına commit ve push yap, PR aç veya linkini sağla. GitHub yetkisi yoksa `BLOCKED`, yerel patch/diff bırak; push yaptım deme.
8. Kullanıcıya kapsam, değişen dosyalar, test kanıtları, riskler, PR/commit, sıradaki alt faz ve onay sorusuyla rapor ver.
9. **DUR. Kullanıcının açık onayını bekle.** Onay gelmeden sonraki alt faza veya ana faza geçme. Onay olmadan PR merge/production deployment yapma.
10. Fazın son alt aşamasından sonra ayrıca **ana faz kapanış raporu** üret, kabul kriterleri ve açık sorunlar üzerinden yeniden onay iste. Sıradaki ana faz kendiliğinden başlamaz.

## Sınırlar
- Kod, rapor ve testlerde sahte veri kullanılıyorsa `DEMO/TEST` diye işaretle; gerçek tarama iddiasında bulunma.
- GitHub'a hiçbir `.env`, token, müşteri verisi, OAuth refresh token'ı, ham özel rapor veya gizli konfigürasyon yükleme.
- Hiçbir koşulda canlı hedefi yoğun tarama, açık port tarama, kimlik bilgisi kullanma ya da robots kurallarını atlatma.
- SSRF bariyerlerini hiçbir alt faz esnetemez; güvenli varsayılanlar `fail closed`.
- Ölçüm dışı alanlara puan verme; `UNKNOWN` ve `NOT_APPLICABLE` durumları korunur.
- Google'ın sıralama faktörlerini, indeks durumunu veya AI görünürlük ihtimalini kesin biliyormuş gibi raporlama.
- Veritabanı migration'ları geri dönüşü planlanmadan uygulanmaz; silme/bozma riski taşıyan işlemler açık onaya tabidir.
- Paket bağımlılıkları sabitlenmiş sürümlerle ve lisans/güvenlik kontrolünden geçerek eklenir.
- UI metinlerini doğrudan kodun içine gömme; `tr-TR` ve `en-US` çevirileri tamamlanmalı (başka diller ileride).
- Yapılandırılmamış kaynak, yetkisiz API, yanlış kaynak atfı, gereksiz bağımlılık veya anlamsız SEO skor kuralı kullanma.

## Nihai teslim standardı
İlgili alt fazın tüm `Acceptance Criteria` maddeleri kanıtla geçtiyse `READY_FOR_REVIEW`; kullanıcı kabulünden sonra `DONE`. Bir veya daha fazla kritik kriter başarısızsa `BLOCKED` veya `IN_REVIEW`; asla `DONE` deme.

## Devir için sabit okuma sırası
[START_HERE](START_HERE.md#sabit-okuma-sırası) tek okuma sırasıdır. GitHub main son kabul/birleştirme durumunu, aktif PR kendi teslimini gösterir. `next_subphase` otomatik başlama izni değildir; faz başlangıcı, teslim kabulü ve PR merge izinlerini ayrı kaydet.




---

<!-- KAYNAK BELGE: START_HERE.md -->
## BELGE: `START_HERE.md`

# SitePortal — Buradan başla

Bu depo, Türkçe öncelikli SEO Analyzer için geliştirme sözleşmesi ve planları içerir. Çalışan uygulama henüz yoktur. Güncel durumun kaynağı [project-status.json](project-status.json) ve [CURRENT_STATUS](CURRENT_STATUS.md) dosyalarıdır.

## Sabit okuma sırası
1. [README](README.md): ilk 15 satırdaki durum paneli.
2. [AGENTS](AGENTS.md): bağlayıcı sınırlar ve onay kuralları.
3. [CURRENT_STATUS](CURRENT_STATUS.md) ve [project-status.json](project-status.json): son kabul, aktif iş, dal, PR, test ve açık engeller.
4. [Yol haritası](docs/roadmap/ROADMAP.md), JSON'daki aktif fazın belgesi ve [iş akışı](docs/process/WORKFLOW.md).
5. JSON'daki `last_report`, [rapor dizini](docs/reports/README.md), [CHANGELOG](CHANGELOG.md) ve [açık kararlar](docs/process/OPEN_QUESTIONS.md).
6. İşin gerektirdiği [ürün/mimari belgeleri](docs/README.md) ve [resmî kaynak envanteri](docs/reference/SOURCES.md).

## Devralma kontrolü
- `git status --short`, `git branch --show-current`, `git log -1 --oneline` ile yerel durumu gör. Kullanıcının dosyalarını koru.
- Yetkili bağlantıyla GitHub `main` ve aktif PR'ı kontrol et; yalnız dosyalardaki eski SHA'ya güvenme.
- `status=READY_FOR_REVIEW` ise mevcut teslim için kullanıcı kabulünü bekle. `DONE` kullanıcı kabulüdür; `previous_delivery.merged` birleştirmeyi ayrıca izler.
- Başlangıç izni, alt faz kabulü, PR birleştirme ve üretim dağıtımı ayrı yetkilerdir. Kabul olsa bile PR merge için ayrı açık onay gerekir.
- `next_subphase`, başlama izni değildir. Aktif iş bitmeden veya yeni açık onay gelmeden sonraki alt faza geçme.

## Çalışma ve doğrulama
Etkin belgeler depo kökündedir. `siteportal_seo_masterplan/` varsa korunmuş yerel paket kopyasıdır; yeniden depoya kopyalama veya güncel durum kaynağı olarak kullanma. ZIP'in bulunmaması repo üzerinden devralmayı engellemez; özgün dosya envanteri raporlardadır.

[Bakım rehberi](docs/process/MAINTENANCE.md) komutların sırasını, manifesti ve ana şartnamenin üretilmesini açıklar. Belge kontrolleri çalışan uygulama testi değildir. Erişim veya gerekli test yoksa gerçek engeli raporla; başarı uydurma.

Tek alt faz: kapsamı oku → planı bildir → değiştir → doğrula → durum/rapor/PR kaydını güncelle → Türkçe rapor sun → **DUR**. Yeni ajan yalnız repo ile son kabulü, aktif PR'ı, test sonucunu, sonraki tek alt fazı ve açık engelleri yanıtlayabilmelidir.




---

<!-- KAYNAK BELGE: CURRENT_STATUS.md -->
## BELGE: `CURRENT_STATUS.md`

# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih:** 2026-11-03, Europe/Istanbul
- **Repo:** [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), özel; varsayılan dal `main`
- **Plan / ürün:** blueprint-0.1.0 / çalışan ürün sürümü yok
- **Aktif ana faz / sürüm:** F00 / V0.0; ana faz kabulü henüz yok
- **Son onaylanan alt faz:** F00.01 — DONE; kabul: “devam edelim”
- **Önceki PR:** [#1](https://github.com/Boydaksu/siteportal/pull/1), ayrı “Evet, PR #1’i birleştir” onayıyla birleştirildi
- **Main başlangıç / merge SHA:** `09716d298587dc29ea5d9092127a65d0f24a4c07`
- **Aktif alt faz / durum:** F00.02 — Belgeleri ve faz sözleşmesini yerleştir / READY_FOR_REVIEW
- **Aktif dal:** `phase/F00.02-docs-contract`
- **Aktif PR / kanıt commit:** [#2](https://github.com/Boydaksu/siteportal/pull/2) (açık, birleştirilmedi) / `5bf77121efb203393d8e61eb7fbfa6f7e700f2e9`; PR head teslim kayıtlarıyla ilerleyebilir
- **Son rapor:** [F00.02](docs/reports/F00.02.md)
- **Test:** Belge/bağlantı/envanter PASS, 7/7 bağlantı TEST senaryosu PASS; sıfır bağlamlı devir tekrarı PASS; ilk FAIL ve düzeltme kanıtı [devir kaydında](docs/reports/F00.02-handoff.md)
- **CI / uygulama testleri:** NOT_CONFIGURED / N/A; uygulama yok
- **Aktif engeller:** Yok
- **Açık kararlar:** [Q-001, Q-002, Q-003](docs/process/OPEN_QUESTIONS.md); API bulgu biçimi, queue tablo adı, en-US takvimi; değiştirilmedi
- **Sonraki tek alt faz:** F00.03 — İlk ADR ve bağımlılık politikası; NOT_STARTED, başlangıç onayı alınmadı
- **Bir sonraki işlem:** DUR. F00.02 teslim kabulünü ve F00.03 için açık başlangıç onayını bekle. PR #2 merge ayrıca açık onay gerektirir.

Durum alanlarının anlamı ve komutlar: [bakım rehberi](docs/process/MAINTENANCE.md). Etkin kayıtlar köktedir; `siteportal_seo_masterplan/` ve BUNDLE_INFO üretim anına aittir. F00.02 başlangıç izni teslim kabulü değildir. PR #1 dışındaki birleştirmeler için yeni açık onay gerekir.




---

<!-- KAYNAK BELGE: docs/requirements/PRODUCT.md -->
## BELGE: `docs/requirements/PRODUCT.md`

# Ürün gereksinimleri — SEO Analyzer

## 1. Ürün sınırı
SitePortal birden çok araç barındırabilen, minimum bakım maliyetli web-utilities ürünüdür. SEO Analyzer ilk araçtır; **hosting şirketi CRM'i/WHMCS modülü değildir.** Proje Türkçe başlar, en-US mimarisi ilk gün hazırdır. `siteportal.net` alan adı veya üretim ortamı bağlantıları kesinleşmemiştir; hiçbir kuruluma onaysız production URL yazılmaz.

## 2. Persona ve kullanım senaryoları
- **Misafir:** URL girer, 1 sayfalık ücretsiz teknik inceleme başlatır, sonuçtaki kanıt ve çözümleri okur; üye olmaz.
- **Site sahibi:** 10 sayfaya kadar örnekleme yapar, sonra domain doğrulayarak 100+ sayfa, geçmiş ve gerçek Google verisi kullanır.
- **SEO uzmanı:** Kural, kanıt, HTTP zinciri, etkilenen URL, kapsam, CSV, karşılaştırma ister.
- **Ajans:** Birden fazla müşteri organizasyonu, yetkiler, white-label ve zamanlanmış rapor ister.
- **Admin:** Kuyruk, hata, bot abuse, API maliyeti, kayıtlar, kurallar ve kullanıcıları izler.

## 3. Ürün sözleşmesi
- Kullanıcı URL gönderince normal akışta `202 + analysis_id` döner, arayüz gerçek ilerlemeyi gösterir, sonuçlar partial/complete/failed olarak raporlanır.
- Her ölçüm `observed_at`, `data_source`, `scope` ve `status` (PASS / WARN / FAIL / UNKNOWN / NOT_APPLICABLE) içerir.
- Teknik hata ispatlanmadıkça öneri olarak işaretlenir. Uygulanmayan kural puan düşürmez; erişilemeyen testin yokluğu sahte başarı sayılmaz.
- İndeks durumu ancak yetkili Search Console verisiyle açıklanabilir; crawler sadece teknik indekslenebilirlik belirtir.
- Aynı domainde tarama kapsamı `site_verified`, `page_cap`, `discovered_urls`, `crawled_urls`, `failed_urls` ve `coverage_label` ile görünür.
- `robots.txt` sayfaları Google'dan çıkarmaz; `meta keywords` sıralama faktörü değildir; `llms.txt` Google AI için mecburi değildir.
- Title ve meta description için mutlak karakter üst sınırları SEO hatası değildir; metin alaka/tekrar/truncation yaklaşık tahmin sunulabilir.
- Her sürüm azami maliyet, maksimum URL, süre, request byte, API çağrısı sınırlarını açıkça gösterir.

## 4. V1.0 kesin dahil (P0)
Tek URL anonim tarama; URL doğrulama; robots/sitemap ilk kontrol; HTTP/canonical/meta/headings/images/links; rule engine; teknik puan + ölçüm kapsamı; açıklamalı kanıt, sınırlı cache, progress, kısa ömürlü özel rapor, HTTP API, temel admin, Türkçe UI, İngilizce çeviri şablonu, fail-closed SSRF, queue/worker/cron, test/CI ve dokümantasyon.

## 5. V1.0 kapsam dışı (sonraya)
500+ URL crawl, gerçek GSC, otomatik indeksleme, backlink veritabanı, rakip analizi, sabit AI sıralama puanı, mevzuat sertifikasyonu, bütün sitelerde JS render, abonelik tahsilatı, ajans white-label.

## 6. Sayfalar ve rotalar (önerilen)
- `/` — SitePortal ana sayfa; araçlar listesi
- `/seo-analizi` — ücretsiz SEO analiz giriş ekranı
- `/rapor/{id}` — erişim izni olan rapor; varsayılan noindex
- `/seo-araclari` — diğer spesifik araçlara giriş
- `/robots-txt-kontrol`, `/sitemap-kontrol`, `/canonical-kontrol`, `/meta-etiket-kontrol`, `/core-web-vitals` — gerçekten işe yarayan alt araçlar, aynı şablon kopyası değil
- `/giris`, `/kayit`, `/hesap`, `/siteler`, `/yonetim` — ilgili sonraki sürümlerde
- `/en/seo-audit` — çevrilen URL stratejisi F08 kararı; iç i18n motoru ilk sürümden
- `/api/v1/...` — sürümlü API

## 7. Misafir akışı
Form → URL doğrulama (şema/host) → server-side engelleme/kota → analiz kaydı → queue → gerçek progress → skor ve kapsam → FAIL/WARN/PASS/UNKNOWN/N/A sekmeleri → issue detail (kanıt, sebepler, kod/öneri, etkilenen adres) → rapor TTL / paylaşım kuralı.

## 8. Rapor bileşenleri
- Kapsam başlığı: URL, host, taranan sayfa, keşfedilen sayfa, kapsam türü, test zamanı, veri kaynağı.
- Ana skor ve kapsam güvenilirliği; kategori skorları; ölçülmeyen alan.
- Sorun öncelikleri: kritik/önemli/öneri, impacted_count ve etki tahmini.
- Her bulgu için rule_id, version, evidence, affected URLs, severity, confidence, fixes, docs, rule timestamp.
- Performans field/lab ayrı ve source label; AI/heuristic ölçümleri ayrı görsel bölümlerde.
- Eski sürümle karşılaştırırken ruleset değişikliğinin etkisi belirtilir.
- Erişilebilir UI: klavye, renk dışı durum göstergeleri, mobil, yüksek kontrast, erişilebilir tablolar.

## 9. Ölçülebilir kabul hedefleri (proje hedefi; gerçek garanti değil)
- Başarılı isteklerde analiz sonucu veya açık gerekçeli partial/failed durum.
- Onaylanmış test fixture setinde P0 kurallarda sıfır bilinen false-positive.
- Limitler: misafir 1 URL, ücretsiz 10, Pro 100, Business 500 (varsayılan öneri; admin değiştirilebilir).
- Kendi arayüzü hafif server-rendered, gereksiz front-end bundle yok.
- Güvenlik testleri ve maskeli loglar yapılmadan dış kullanıcıya açılmaz.

## 10. Ürün kabulünde asla iddia edilmeyecekler
'Google puanınız', 'Google kesin indeksler', 'Google sıralamanız X olacak', 'tüm siteyi taradık' (örneklem ise), 'AI aramalara kesin çıkarsınız', 'otomatik WCAG uyum sertifikası', 'kesin özgün içerik', 'SEO'yu tek tıkla düzelttik'.




---

<!-- KAYNAK BELGE: docs/requirements/FEATURE_MATRIX.md -->
## BELGE: `docs/requirements/FEATURE_MATRIX.md`

# Sürümlere göre özellik matrisi

| Özellik | V0.0/0.1 | V1.0 | V1.1 | V1.2 | V2.0 | V2.1 | V3.0 | V4.0 |
|---|---|---|---|---|---|---|---|---|
| Repo/CI/ADR/handoff | ● | ● | ● | ● | ● | ● | ● | ● |
| Güvenli URL/queue/robots/SSRF | ● | ● | ● | ● | ● | ● | ● | ● |
| Üyeliksiz tek URL HTML SEO | — | ● | ● | ● | ● | ● | ● | ● |
| Kanıtlı kural/score/rapor | ◐ | ● | ● | ● | ● | ● | ● | ● |
| Çoklu sayfa/sitemap/link graph | — | — | ● | ● | ● | ● | ● | ● |
| PageSpeed/CrUX field vs lab | — | — | — | ● | ● | ● | ● | ● |
| Structured data/mobil/a11y | — | — | — | ● | ● | ● | ● | ● |
| Hesap/domain ownership/GSC | — | — | — | — | ● | ● | ● | ● |
| İçerik/AI/Hreflang/e-commerce | — | — | — | — | — | ● | ● | ● |
| Ajans/ödeme/API/white-label | — | — | — | — | — | — | ● | ● |
| Yatay ölçek/DR/i18n tam üretim | — | — | — | — | — | — | ◐ | ● |

**●:** sürümde aktif ve testli; **◐:** mimari/altyapı hazırlığı; **—:** kapsam dışı. Bu bir **hedef planı**, mevcut ürün özellik tablosu değildir. İlk kod sürümü henüz yoktur.




---

<!-- KAYNAK BELGE: docs/requirements/PRIORITIES.md -->
## BELGE: `docs/requirements/PRIORITIES.md`

# Öncelikler, maliyet, kapsam dışı alanlar

## Önce P0 (kamuya açılmadan mecburi)
Güvenli URL parser/SSRF; DNS pinning; per-host polite crawler, robots; job queue; rate limiting; ISO timestamp; HTTP status/head/meta/canonical; evidence; testli scoring; erişim kontrollü rapor; minimal frontend; i18n key mekanizması; migrations; log/secret koruma; E2E test.

## Sonra P1 (temel ürün büyümesi)
Site haritası streaming index; dedup/link graph; site çapı kurallar; CSV; PageSpeed/CrUX farklı data source; structured data; mobile ve a11y; JS rendering opsiyonel; hesap ve domain sahipliği; GSC.

## Talep doğrulandıktan sonra P2
LLM ile açıklama, AI hazırlık, özel e-ticaret/yerel/international SEO, geçmiş karşılaştırmalar, markalı raporlar, abonelik, ajans, açık API ve bildirim sistemi.

## İlk sürümlerde KESİNLİKLE GEREKSİZ
- 1000+ skor testi diye pazarlama; ölçülemeyen şeyleri kural diye çoğaltma.
- Backlink scraping (gerçek link veri seti olmadan).
- 'Google rank estimator' veya yalnız HTML'den gerçek trafik/keyword tahmini.
- Her URL için sürekli Chromium; mikroservis/Kubernetes/Kafka zorunluluğu.
- Site sahibinin izni olmadan büyük domainlerde 10k crawl.
- Otomatik on-page edit/WordPress login ile kullanıcı sitesini değiştirmek.
- Google AI için uydurma metatag veya mecburi `llms.txt`.
- Her başlığa sabit 60 karakter, meta desc'e 160 karakter veya tek H1 dayatması.
- E-E-A-T'yi sahte kesinlikle 100 üzerinden skorlama.
- Herkese açık, indekslenebilir özel rapor URL'leri.

## Maliyet bütçesi
Her analiz için CPU saniyesi, bant genişliği, HTTP istek, render saniyesi, API çağrı sayısı, depolama artışı ve kullanıcı kotası ölçülmeli. Pahalı entegrasyonlarda feature flag ve quota vardır. İlk sürüm mümkün olduğunca küçük VPS; büyüme iddiasına göre değil, ölçülen darboğaza göre servis ayrıştırılır.

## Başarı KPI
Completion rate, p95 job latency, error/partial ratio, false-positive reviewer rate, cost per report, queue wait, user re-run, report opened/shared, SEO issue fix follow-up, free-to-paid. Vanity metric olarak 'kaç test var' tek başına hedef değildir.

## Ticari strateji
Misafir 1 URL ücretsiz, ücretsiz hesap 10, Pro 100, Business 500 ve Agency özel kota: başlangıç önerileri, kesin fiyat/plan değil. Ücretsiz içerikten alakalı reklam ve sponsor mümkün; skor ve teknik öneri asla sponsora göre değişmez. İstismar maliyetini önleyen quota ücretlendirmeden önce hazır olmalı.




---

<!-- KAYNAK BELGE: docs/requirements/UX.md -->
## BELGE: `docs/requirements/UX.md`

# Frontend / UX tam planı

## Görsel sistem
Lacivert `#101827`, mavi `#2563EB`, yeşil `#10B981`, amber `#F59E0B`, kırmızı `#EF4444`, açık arka plan `#F5F7FB`. CSS custom properties; dark mode bağımsız. Modern system font stack, Lucide SVG ikonlar. Değerler erişilebilir kontrast testine tabidir; yalnızca renkle uyarı verilmez.

## Anasayfa
Navbar → SEO URL girişli hero → güvenilir kaynak açıklaması → demo rapor → analiz özellikleri → nasıl çalışır → fiyat/limitler → SSS → gizlilik/iletişim footer. İlk ekranda URL girişi; mobil klavye uyumu. SEO içerik sayfaları gerçekten özgün bilgi sunar.

## URL formu
Domain veya `https://...` girişi, koşulsuz otomatik analiz yok; `https://` eksikse ve tek anlamlıysa kullanıcıya normalize adres önizlemesi; IDN unicode/punycode normalize; port ve kullanıcı bilgi bölümü kurallara tabi. Kullanıcı hata gördüğünde örnek anlaşılır mesaj.

## İşlem ekranı
Durum: `queued`, `validating`, `fetching`, `parsing`, `rules`, `finalizing`, `completed`, `partial`, `failed`, `cancelled`. Progress gerçekleştirilen işlerden hesaplanır; geri kalanı tahmin gibi sunulmaz. Uzun işlerde `analysis_id` ile sonra geri dönme mümkün; rastgele sürede done iddiası yok.

## Rapor ekranı
Üst kart: URL, kapsam, tarih, skor ve veri güveni. Tablar: Genel/Technical/On-page/Links/Performance/Schema/Mobile/AI/Pages/Recommendations. Olmayan modül tabı gizlenebilir, ancak ölçülmeyen kategorilerin yokluğu açık. Filtreler: severity, rule_id, affected URL, status. Sorun kartı evidence, fix, docs link, recheck ile genişler. PDF/CSV yalnızca yetkili planda.

## Durumlarda özel tasarımlar
- Site cevap vermiyorsa `UNKNOWN` veya `FAILED` gerekçesi ve yeniden dene; sahte SEO puanı yok.
- Robots engelleniyorsa dur, `robots_disallowed` raporu ve çözüm açıklaması göster.
- Bot koruması 403/429 döndürürse bunu SEO başarısızlığı değil erişim engeli/uyarı diye sınıflandır.
- GSC bağlı değilse kapalı alanlarda `Bağlantı gerekiyor` yaz.
- CrUX yoksa `Yeterli gerçek kullanıcı verisi bulunamadı` yaz.
- JavaScript worker yoksa render testleri `Not measured`.

## İçerik prensipleri
İlk dil Türkçe; tüm hata/öneriler sade bir cümle ve detay bağlantısı. Teknik etiketler kopyalanabilir. 'Kritik' yalnızca kanıtlanmış kırıklarda. Formun ve tabların klavye erişilebilirliği, aria-describedby, canlı durum duyuruları, boş durum mesajları ve görünür focus haleleri test edilir.

## Analytics
Analiz başlatma, hata görme, sorun açma, PDF/CSV, yeniden analiz olayları toplanabilir; minimum veri ilkesi, izin yönetimi ve KVKK aydınlatması şarttır. Özel rapor URL'si analytics'e açık metin gönderilmez.




---

<!-- KAYNAK BELGE: docs/requirements/RULES.md -->
## BELGE: `docs/requirements/RULES.md`

# SEO kural kataloğu — başlangıç teknik registry

Bu tablo **uygulama tamamlandı iddiası değildir**. Her satır için implementasyon aşamasında `RuleInterface`, uygulanabilirlik, fixture, kanıt çıkarma, docs, test, version ve false-positive değerlendirmesi zorunludur. `info` kategorisindeki kontroller skor düşürmez; sezgisel kontroller bağlama göre uyarıdır. Kaynak kodları `docs/reference/SOURCES.md` ile eşleştirilir.

- Toplam planlanan kural: **84**.
- Kaynak metni/sürümü 2026-11-03 itibarıyla kontrol edildi; mevzuat ve Google dokümanları sürüm bazında yeniden kontrol edilmeli.
- HTTP başarısızlığı, robots/metadata, cihaz verisi ve Google Search Console durumu **ayrı kaynaklar**.
- Kuralın test edildiği sürümden önce kullanıcıya varmış gibi gösterilmez.

| Kural ID | Kategori | İlk sürüm | Önem | Koşul / uyarı | Kaynak |
|---|---|---|---|---|---|
| `TECH_HTTP_STATUS` | technical | V1.0 | high | Ana URL HTTP 4xx/5xx; gerçek status chain saklanır | S1 |
| `TECH_REDIRECT_CHAIN` | technical | V1.0 | medium | 3xx hop sayısı ve yönlendirme zinciri | S1 |
| `TECH_REDIRECT_LOOP` | technical | V1.0 | high | Tekrarlanan Location veya max hop | S1 |
| `TECH_HTTPS` | technical | V1.0 | medium | HTTP erişimi ve HTTPS redirect / TLS erişimi | S1 |
| `TECH_HTTPS_DOWNGRADE` | technical | V1.0 | high | HTTPS sayfa HTTP hedefine düşüyor | S2 |
| `TECH_ROBOTS_DISALLOW` | technical | V1.0 | high | User-agent politikası ve hedef disallow | S3 |
| `TECH_ROBOTS_UNAVAILABLE` | technical | V1.0 | medium | Robots fetch status farklılıkları UNKNOWN ile ele alınır | S3 |
| `TECH_META_NOINDEX` | technical | V1.0 | high | Meta robots noindex gözlemi | S3 |
| `TECH_X_ROBOTS_NOINDEX` | technical | V1.0 | high | HTTP X-Robots-Tag noindex gözlemi | S3 |
| `TECH_ROBOTS_NOINDEX_CONFLICT` | technical | V1.0 | high | Disallow ve noindex birlikte; erişim engeli nedeniyle noindex görünmeyebilir | S3 |
| `TECH_CANONICAL_MISSING` | technical | V1.0 | info | Canonical yok; Google zorunlu kılmıyor | S2 |
| `TECH_CANONICAL_EMPTY` | technical | V1.0 | medium | Canonical href boş veya geçersiz | S2 |
| `TECH_CANONICAL_MULTI_CONFLICT` | technical | V1.0 | high | Birden çok çelişkili canonical | S2 |
| `TECH_CANONICAL_TARGET_4XX` | technical | V1.0 | high | Canonical hedefi GET ile 404/410 doğrulanmış | S2 |
| `TECH_CANONICAL_REDIRECT` | technical | V1.0 | medium | Canonical hedefi redirect; nihai hedef kaydı | S2 |
| `TECH_CANONICAL_OUT_OF_SCOPE` | technical | V1.0 | medium | Canonical başka host; kesin hata değil, kontrollü uyarı | S2 |
| `TECH_SOFT_404_SUSPECT` | technical | V1.0 | medium | 200 yanıt fakat hata mesajı/boş içerik benzerliği; sezgisel | S1 |
| `TECH_INDEXABILITY_INFERENCE` | technical | V1.0 | info | Teknik sinyallere göre indekslenebilir görünüyor, Google durumu değil | S1 |
| `TECH_SITEMAP_MISSING` | technical | V1.0 | info | Sitemap keşfedilemedi; zorunlu değil | S4 |
| `TECH_SITEMAP_STATUS` | technical | V1.0 | medium | Sitemap 4xx/5xx veya geçersiz içerik | S4 |
| `TECH_SITEMAP_LIMIT` | technical | V1.1 | medium | 50k URL/50MB aşımı | S4 |
| `TECH_SITEMAP_URL_CONFLICT` | technical | V1.1 | medium | Sitemap URL noindex/redirect/canonical ile çelişiyor | S4 |
| `TECH_SITEMAP_NESTED` | technical | V1.1 | medium | Index recursion/cycle/depth cap | S4 |
| `TECH_JS_CONTENT_DELTA` | technical | V1.2 | medium | Kaynak HTML ve render HTML ana içerik farkı | S5 |
| `TECH_JS_BLOCKED_RESOURCES` | technical | V1.2 | medium | Önemli JS/CSS robots engel ihtimali | S5 |
| `ONPAGE_TITLE_MISSING` | onpage | V1.0 | medium | HTML title yok veya boş | S6 |
| `ONPAGE_TITLE_GENERIC` | onpage | V1.0 | low | Genel/tek kelimelik title; heuristic | S6 |
| `ONPAGE_TITLE_DUPLICATE` | onpage | V1.1 | medium | Taranan URL'lerde aynı title | S6 |
| `ONPAGE_TITLE_TRUNCATION_RISK` | onpage | V1.0 | low | Piksel genişliği tahmini, kesin karakter üst sınırı değil | S6 |
| `ONPAGE_META_DESC_MISSING` | onpage | V1.0 | low | Meta açıklama yok; Google snippet'i içerikten oluşturabilir | S6 |
| `ONPAGE_META_DESC_DUPLICATE` | onpage | V1.1 | medium | Aynı description örnek sayfalarda | S6 |
| `ONPAGE_META_DESC_RELEVANCE` | onpage | V2.1 | low | Sayfa ile açıklamanın anlam uyumu sezgisi | S6 |
| `ONPAGE_H1_EMPTY` | onpage | V1.0 | low | H1 yok; zorunlu tek-H1 kuralı değil | S6 |
| `ONPAGE_HEADING_HIERARCHY` | onpage | V1.0 | low | Başlık sıralama atlamaları; bağlamsal | S6 |
| `ONPAGE_HTML_LANG` | onpage | V1.0 | info | HTML lang yok; Google dilini yalnız bu etikete göre belirlemez | S6 |
| `ONPAGE_CHARSET` | onpage | V1.0 | medium | Charset, meta ve HTTP çakışması | S6 |
| `ONPAGE_OG_METADATA` | onpage | V1.0 | info | OG title/image eksikliği sosyal paylaşım önerisi | S6 |
| `ONPAGE_META_KEYWORDS` | onpage | V1.0 | info | Meta keywords bilgi amaçlı; Google puanına etkisiz | S6 |
| `LINK_BROKEN_INTERNAL` | links | V1.1 | medium | İç href hedefinde doğrulanmış 4xx | S1 |
| `LINK_REDIRECT_INTERNAL` | links | V1.1 | low | İç link redirect'e gidiyor | S2 |
| `LINK_ANCHOR_EMPTY` | links | V1.0 | low | Anlamlı bağlantı metni yok; icon aria label olabilir | S6 |
| `LINK_DEPTH_HIGH` | links | V1.1 | medium | Önemli sayfa derinliği sayfaya ve site tipine göre | S1 |
| `LINK_ORPHAN_SUSPECT` | links | V1.1 | low | Sitemap'te var fakat örnek crawl grafında görülmedi; kesin değil | S4 |
| `LINK_PARAM_EXPLOSION` | links | V1.1 | medium | Çok sayıda query kombinasyonu | S1 |
| `LINK_REL_NOFOLLOW` | links | V1.0 | info | Nofollow gözlemi; otomatik hata değil | S1 |
| `LINK_EXTERNAL_BROKEN` | links | V1.1 | low | Dış link kontrolü opt-in ve sıkı limitli | S1 |
| `CWV_LCP` | performance | V1.2 | medium | Field P75 <=2.5s iyi; 2.5-4 gelişim; >4 zayıf | S7 |
| `CWV_INP` | performance | V1.2 | medium | Field P75 <=200ms iyi; 200-500 gelişim; >500 zayıf | S7 |
| `CWV_CLS` | performance | V1.2 | medium | Field P75 <=0.1 iyi; 0.1-0.25 gelişim; >0.25 zayıf | S7 |
| `PERF_LAB_LCP` | performance | V1.2 | medium | Lighthouse lab LCP, saha verisiyle birleştirilmez | S8 |
| `PERF_RENDER_BLOCKING` | performance | V1.2 | low | Lighthouse kaynak blokaj önerileri | S8 |
| `PERF_IMAGE_LCP` | performance | V1.2 | medium | LCP görseli büyük, öncelik veya boyut | S8 |
| `PERF_CACHE_HINTS` | performance | V1.2 | low | Cache-Control ve etag tavsiyeleri | S8 |
| `PERF_TTFB` | performance | V1.2 | low | Laboratuvar ve alan bilgisi ayrı kaydedilir | S8 |
| `SCHEMA_INVALID_JSONLD` | schema | V1.2 | medium | JSON-LD parse error | S9 |
| `SCHEMA_INVALID_CONTEXT` | schema | V1.2 | medium | Bozuk @context/@type | S9 |
| `SCHEMA_REQUIRED_FOR_TYPE` | schema | V1.2 | medium | Google desteklediği türde zorunlu alan eksik | S9 |
| `SCHEMA_VISIBLE_MISMATCH` | schema | V1.2 | medium | Yapılandırılmış veri ve görünür içerik çelişkisi | S9 |
| `SCHEMA_NO_RICH_TYPE` | schema | V1.2 | info | Rich-result türü yok, tüm sayfalar için gerekmez | S9 |
| `SCHEMA_MULTIPLE_CONFLICT` | schema | V1.2 | low | Aynı entity için çelişen alanlar | S9 |
| `MOBILE_VIEWPORT` | mobile | V1.2 | medium | Mobil viewport eksik | S10 |
| `MOBILE_OVERFLOW` | mobile | V1.2 | medium | Render ile doğrulanan yatay taşma | S10 |
| `A11Y_IMAGE_ALT` | accessibility | V1.2 | low | Anlamlı görselde alt yok; dekoratif boş alt uygundur | S10 |
| `A11Y_FORM_LABEL` | accessibility | V1.2 | medium | Form öğesinde erişilebilir etiket eksik | S10 |
| `A11Y_CONTRAST` | accessibility | V1.2 | low | Otomatik ölçülebilen kontrast; tam WCAG değil | S10 |
| `HREFLANG_CODE` | international | V2.1 | medium | Geçersiz hreflang language/region | S2 |
| `HREFLANG_RECIPROCAL` | international | V2.1 | medium | Karşılıklı hreflang eksik | S2 |
| `HREFLANG_CANONICAL` | international | V2.1 | medium | Hreflang ve canonical dil sürümü çakışması | S2 |
| `ECOM_PRODUCT_SCHEMA` | ecommerce | V2.1 | medium | Ürün sayfasında uygun Product/Offer alanları; bloga uygulanmaz | S9 |
| `ECOM_VARIANT_URL` | ecommerce | V2.1 | medium | Ürün varyantları duplicate/canonical çakışması | S2 |
| `ECOM_FACET_URLS` | ecommerce | V2.1 | medium | Filtre parametreleri crawl explosion ve canonical | S1 |
| `LOCAL_BUSINESS_ENTITY` | local | V2.1 | low | Yerel işletmede adres/iletişim schema ve görünür tutarlılık | S9 |
| `CONTENT_MAIN_TEXT` | content | V2.1 | low | Ana içerik yeterince anlaşılır mı; heuristic | S11 |
| `CONTENT_NEAR_DUPLICATE` | content | V2.1 | low | Taranan sayfalar arasında yüksek benzerlik; bağlamsal | S11 |
| `CONTENT_UNVERIFIABLE` | content | V2.1 | info | Kaynakların eksikliği ve yazar bilgisi yalnız öneri | S11 |
| `AI_READINESS_INDEXABLE` | ai | V2.1 | info | Google AI için temel arama gereksinimleri | S12 |
| `AI_READINESS_TEXT` | ai | V2.1 | low | Önemli içerik metinsel erişilebilir mi | S12 |
| `AI_READINESS_LLMS_TXT` | ai | V2.1 | info | llms.txt varlığı opsiyonel, yokluğu puan düşürmez | S12 |
| `AI_READINESS_CITATIONS` | ai | V2.1 | low | Bilgi kaynağı/atıf ve özgün katkı önerisi, garanti değil | S12 |
| `SEC_TLS_CERT` | site-health | V1.0 | medium | TLS sertifika geçerli mi; SEO kategorisi olmayan site sağlık kontrolü | S1 |
| `SEC_MIXED_CONTENT` | site-health | V1.2 | low | HTTPS sayfada HTTP alt kaynaklar; render kontrollü | S1 |
| `SEC_SECURITY_HEADERS` | site-health | V1.2 | info | Security headers genel site sağlığı, SEO sıralama puanı değil | S13 |
| `GSC_CLICK_PERFORMANCE` | search-console | V2.0 | info | Yetkili GSC clicks impressions CTR position; teknik denetim puanı dışı | S14 |
| `GSC_INDEX_INSPECTION` | search-console | V2.0 | info | Yetkili indexed status; canlı indeksleme testi değil | S15 |

## Kural implementasyon şablonu
- **applicable(context):** true/false/unknown.
- **gathered evidence:** kaynak, URL, observed_at, status code, HTML selector/headers (maskeli).
- **evaluate:** deterministik FAIL/WARN/PASS/UNKNOWN/NOT_APPLICABLE, rule_version.
- **explain(locale):** title, cause, impact, fix, kaynak URL; çeviri key parametreleri.
- **test:** positive, negative, not applicable, broken input, no data ve safety regression.
- **score:** yalnız applicable ve measured durumda skor motoruna katkı.

## Önemli yanlış pozitif uyarıları
1. Canonical yokluğu **kendiliğinden kritik değil**.
2. Title karakter sayısı için evrensel kesin SEO üst sınırı yok.
3. H1 birden fazla olması mutlak hata değil.
4. Meta description yokluğu sıralama engeli değil, Google snippet'i üretebilir.
5. `meta keywords` Google tarafından desteklenmez, puanı düşürme.
6. `robots.txt` disallow `noindex` değildir.
7. Schema.org geçerliliği Google rich result görünürlüğünü garanti etmez.
8. `llms.txt` özel Google AI şartı değildir.
9. `Core Web Vitals` gerçek saha verisi ölçülemiyorsa lab ile karıştırma.
10. E-E-A-T kesin 0-100 sinyali veya tekil sıralama faktörü değildir.




---

<!-- KAYNAK BELGE: docs/requirements/SCORING.md -->
## BELGE: `docs/requirements/SCORING.md`

# Açıklanabilir SEO puanı / önceliklendirme modeli

## Kesin ayrım
**Skor Google metrikleri değildir.** SitePortal ürününün tekniğe dayalı denetim puanıdır. `rule_set_version`, `scoring_version`, `coverage` tüm raporlarda görünür.

## Önerilen kategori ağırlıkları (ilk hipotez)
| Kategori | Ağırlık |
|---|---:|
| Teknik erişilebilirlik / indekslenebilirlik | 25 |
| On-page / açıklayıcı içerik yapısı | 20 |
| Performans / CWV | 20 |
| Site mimarisi ve linkler | 15 |
| Uygulanabilir structured data | 10 |
| Mobil kullanılabilirlik | 10 |

**Tek sayfalık V1.0'da** performans ve site mimarisi gibi henüz ölçülemeyen veya uygulanmayan kategoriler denominator'a katılmaz. Global ağırlıklar yalnızca ölçülebilen uygun kategoriler arasında yeniden normalize edilir. Örneğin performans ölçümü yapılmadığında o 20 puan kayıp değil; `NOT_MEASURED` ve ayrı kapsam yüzdesidir.

## Kural durumu
- `PASS`: Uygulanabilir, veri mevcut, kontrol başarılı.
- `WARN`: İncelemeye değer, kesin teknik arıza olmayabilir.
- `FAIL`: Gözlenen ve kanıtlanmış başarısızlık.
- `UNKNOWN`: İstek, yetki, eksik içerik, API kesintisi veya güven düzeyi yetersiz.
- `NOT_APPLICABLE`: Sayfanın türü/bağlamı için kontrol gerekli değil.

## Önerilen formül
Her uygun, ölçülebilir kural için `q_i` ∈ [0,1], `w_i` > 0. PASS=1, WARN varsayılan 0.6, FAIL=0; **UNKNOWN/N/A denominator'a dahil edilmez**. Kategori skoru = `100 * Σ(w_i*q_i) / Σ(w_i)`; toplam = uygulanabilir kategori ağırlıklarından normalize edilmiş ağırlıklı ortalama. Sonuç ancak ölçülen uygulama kapsamı yeterliyse sayısal gösterilsin; diğer hâlde `insufficient_evidence`.

## Kapsam ayrı gösterimi
`coverage = measured_applicable_weight / all_expected_applicable_weight` (teknik ölçülemeyeni temsil ettiği bilinen kural evreninde). Ölçülecek evren bilinmiyorsa hesaplanamaz; kapsam açıklaması ve ham sayaçlar kullanılır. Örnekleme 10 sayfaysa 10 sayfa olduğu açıkça yazılır. Ağırlık eşikleri ve confidence dereceleri sürümlenir.

## Etki önceliği skorla aynı değildir
`priority = severity_factor * breadth_factor * confidence_factor / effort_factor` yalnızca **issue sort** için. Site skoruna doğrudan iş gücü veya kullanıcı paketi girmez. Tekrarlayan bulgu kategori bazında capped ağırlıkla etkiler; 100 tekrar '100 kat ceza' olmaz.

## Kural sürümü
Kural imzası `rule_id`, `rule_version`, `category`, `severity`, `evidence`, `applicability`, `confidence`, `fix_template` içerir. Sürüm değişince eski analiz tekrar hesaplanmadıkça eski puan değişmez. Cross-version comparison 'doğrudan kıyaslanamaz' etiketiyle sunulur.

## Sıfır puan güvenlik bariyeri
Erişim kapalı, robots blocked, HTTP timeout, CrUX no-data olduğunda rastgele 0/100 yazılmaz. Arıza gerçek teknik eksi ise FAIL, ölçüm imkânsızsa UNKNOWN. Sistem yöneticisi ağırlıkları değiştirecekse ADR ve snapshot test ister.




---

<!-- KAYNAK BELGE: docs/requirements/ERROR_STATES.md -->
## BELGE: `docs/requirements/ERROR_STATES.md`

# Hata sınıfları ve kullanıcıya gösterimi

| Kod | Kaynak | Üründe aksiyon |
|---|---|---|
| INVALID_URL | Giriş doğrulama | Kullanıcıya URL düzeltme önerisi; job oluşturulmaz |
| URL_BLOCKED | SSRF/safety | Güvenlik nedeniyle reddedildi; ayrıntılı iç IP açığa çıkmaz |
| ROBOTS_DISALLOWED | Robots parser | Tarama yapma; açıklama/erişim kısıtı |
| DNS_FAILED | SafeHttpClient | Ulaşılamadı; skor yok |
| HTTP_TIMEOUT | Network | Partial/unknown, uygun retry |
| HTTP_RATE_LIMITED | Hedef 429 | Retry-After/nezaket; otomatik flood yok |
| HTTP_SERVER_ERROR | 5xx | Gerçek teknik gözlem, uzun tarama durdurulabilir |
| BODY_TOO_LARGE | Parser guard | Uygulanamayanlar unknown, log içerik yok |
| XML_UNSAFE | Sitemap parser | Sitemap güvenli şekilde reddedildi |
| EXTERNAL_API_UNAVAILABLE | PSI/CrUX/GSC | Entegrasyon unavailable, temel rapor devam |
| EXTERNAL_API_QUOTA | Provider | Maliyet limit; ölçüm unknown |
| NO_FIELD_DATA | CrUX | Uygun saha verisi yok, 0 puan değil |
| AUTH_REQUIRED | GSC | Yetki açıklaması, teknik skor etkilenmez |
| VERIFICATION_REQUIRED | Deep crawl | Doğrulama adımları |
| ANALYSIS_PARTIAL | Worker | Tamamlananlar ve ölçülemeyenler ayrı |
| INTERNAL_ERROR | Uygulama | Correlation request id; gizli trace gösterme |

HTTP status ve SEO issue ayrı katmanlar: `HTTP 404` teknik bulgudur; `DNS_FAILED` tarama engelidir; bunlardan 'Google indeksi dışı' gibi kesin iddia doğmaz.




---

<!-- KAYNAK BELGE: docs/architecture/SYSTEM.md -->
## BELGE: `docs/architecture/SYSTEM.md`

# Sistem mimarisi / klasör sınırları

## Karar (ADR-001)
Framework bağımlılığı düşük, PHP 8.4+ modüler monolit; PDO MySQL 8+, PHP-FPM web, CLI + cron job queue, server-rendered HTML + vanilla JS + responsive CSS. Composer sadece küçük, denetlenmiş kütüphane/autoload için. Tek VPS başlangıç. Laravel/Next.js/Redis/Kubernetes zorunlu değil. JS renderer başka süreç/izole konteyner ve ancak V1.2'de.

## Lojik katmanlar
`Browser -> FrontController -> Router -> Authentication/RateLimit -> AnalysisController -> AnalysisService -> DB Queue -> CLI Worker -> SafeHttpClient/Robots -> Extractor -> Rules -> Score -> Report Repository -> Read-only API/UI`.

## Nihai önerilen kod dizinleri
```text
/public/                # Yalnızca web root, index.php ve derlenmiş statik kaynaklar
/app/Core/              # Router, config, DB, Auth, Logging, Errors, I18n
/app/Modules/SeoAnalyzer/
  Domain/               # Analysis, Site, Page, Issue, Rule, Value Objects
  Application/          # CreateAnalysis, ScanPage, ReportQueries
  Infrastructure/       # Http, Robots, Sitemap, Queue, Cache, Repositories
  Rules/                # RuleRegistry, Technical, OnPage, Links, ...
  Integrations/         # PSI, CrUX, GSC; interface-based adapters
  Controllers/          # Web/API erişim noktaları
  Views/                # SEO Analyzer Twig gerektirmeyen escaped PHP templates
/app/Shared/            # Ortak platform servisleri
/config/                # Framework-free config; secrets as env
/database/migrations/   # Reversible ve sıralı migration'lar
/resources/lang/tr-TR/  # JSON/PHP translations
/resources/lang/en-US/
/resources/views/        # SitePortal genel şablonları
/storage/{cache,logs,reports,temp}/ # webroot dışında
/bin/{worker,scheduler,migrate}.php
/tests/{Unit,Integration,E2E,Security,Fixtures}/
/docs/{adr,reports,operations,roadmap}/
```

## Sorumluluk sınırları
- Controller **sadece** validate, auth ve use-case çağrısı; HTTP fetch/controller içinde yapılmaz.
- `SafeHttpClient` dış isteklerin tek çıkış kapısı; hizmet adaptörleri provider endpoint allowlist ve secret store kullanır.
- Crawler HTTP/HTML alır; rules sadece normalize snapshot okur, ek ağ isteğini açık adapter üzerinden ister.
- Site/sayfa kuralları birbirinden ayrı; site kapsamı bilinmedikçe site-geneli kesin bulgu üretilmez.
- ReportBuilder normalleştirilmiş bulgu + kaynak + puan çeker; kullanıcı HTML'ini doğrudan render etmez.
- Queue idempotent; worker yeniden çalıştırılabilir. DB uzun transaction içinde ağ çağrısı yapılmaz.
- Dağıtım: `APP_ENV=production` debug kapalı, health/readiness izinli, admin IP/MFA seçenekleri.

## Geliştirme / geri dönüş
Dış bağımlılık için önce interface ve fixture, sonra implementasyon. DB migration'ları ileri/geri test et; üretimde yıkıcı migration öncesi backup/kullanıcı onayı. Feature flag ile riskli modülleri kapat.

## Teknik borç ilkesi
Yeni servis sadece gözlemlenmiş ihtiyaç için. Redis, Kafka, Kubernetes, mikroservis ve sürekli Chrome yalnızca kabul edilmiş performans yük testiyle gerekçelendirilirse. Açık kaynak bağımlılık sürümü ve lisansını kaydet.




---

<!-- KAYNAK BELGE: docs/architecture/CRAWLER.md -->
## BELGE: `docs/architecture/CRAWLER.md`

# Crawler / kuyruk teknik sözleşmesi

## Hedef
Herkesin URL gönderebildiği sistem, siteye zarar vermeden, özel ağlara ulaşmadan, belirlenmiş bütçe içinde analiz üretmeli. Robots politikası bütün analiz istekleri için açıkça belgelenir. Tarama gerçek Googlebot gibi tanıtılmaz.

## Akış
1. Normalize URL, şema/port/userinfo/fragment ayrıştır, izinli adresi göster.
2. DNS çöz, public IPv4/IPv6 listesi denetle, connection IP pin; TLS sertifika/host doğrula.
3. Hedefin robots.txt dosyasını aynı güvenli istemciyle al; belirtilen user-agent grubu için Allow/Disallow değerlendir. Robots erişim hatalarını açık `UNKNOWN` veya korumacı durdurma olarak ele al; gizlice robots atlama yok.
4. Protokol ve origin scope belirle; sayfa/URL/dosya boyutu/derinlik/zaman/redirect sınırları oluştur.
5. HTML `GET` ile al; HEAD her zaman güvenilir değil. Sıkıştırılmış/sıkıştırılmamış byte sınırlarını, content type ve timeout kontrol et.
6. HTTP 3xx için otomatik serbest redirect kapalı; **her atlama URL+DNS+IP yeniden kontrol**, maksimum zincir.
7. `PageSnapshot` oluştur: status, redirects, headers, content_hash, charset, title, meta, directives, canonical, href, image, JSON-LD, links, timestamp, provenance.
8. Sitemap ve link keşfiyle queue'ya sadece izinli host/registrable domain kapsamı içindeki URL ekle. Public suffix list ihtiyacı varsa denetlenmiş bağımlılık kullan.
9. Site bazlı aggregation yalnızca yeterli kapsam olduğunda. Örnek taramada kesin orphan/duplicate site oranı iddiası yok.
10. Kural motoru ve skor, rapora veri kaynağı/son ölçüm timestamp ve kısmi sonuç durumuyla yazılır.

## İlk varsayılan limitler (değiştirilebilir, onaysız artırma yok)
- Anonim: tek sayfa, 1 aktif job/IP, günlük kullanıcı kotası.
- Ücretsiz üye: 10 URL/site, günlük kota. Onaylanmış domain sahibi üst paket: 100/500.
- Eş zamanlı 1 fetch/host; global worker sayısı deployment konfigürasyonu.
- Max 5 redirect; HTTP request örnek timeout 12sn; HTML 2MB; XML site haritası 50MB Google protokol üst sınırı olsa da sistem daha düşük indirme bütçesi uygulayabilir.
- Toplam crawl süre, bytes, external requests, tekrar deneme sayısı; host 429/503 `Retry-After` politikası.

## URL eşitliği
Sadece fragmentı kaldırmak güvenli genel varsayım. Path case, slash ve anlamlı query parametreleri rastgele normalize edilmez. `utm_*` gibi izleme parametrelerini ayrı heuristik olarak değerlendir; her silme kuralı yanlış eşleme testinden geçmeli. Canonical URL hint, otomatik crawl dedup ile aynı kavram değil.

## Queue modeli
`jobs(id,type,analysis_id,payload_json,status,priority,attempts,max_attempts,available_at,leased_until,worker_id,idempotency_key,last_error,created_at,updated_at)`; transaction ile atomik claim, `leased_until` ile crash recover, exponential backoff+jitter, dead letter, job cancellation, heartbeat ve bounded retries. MySQL sürümünün `SKIP LOCKED` davranışı test edilmeden kullanılmaz.

## Dış istekler
Google PageSpeed ve CrUX gibi provider entegrasyonları URL parametrelerini alabilir; bu servislerin döndürdüğü bilgiler kullanıcı taraması olarak ayrı kaynak. Provider hostları allowlist, API anahtarları yalnızca backend. Browser renderer egress sınırı, sandbox, CPU/memory, browser timeout ve istemsiz dosya erişimini engelleme zorunlu.

## Başarısızlık matrisi
- DNS çözülemiyor → `FAILED_FETCH/DNS_ERROR`, skor yok.
- Robots engeli → `ROBOTS_DISALLOWED`, tarama yok.
- 429/503 → host budget düşür, bekleme/retry, süre aşarsa partial.
- Malformed HTML → parser toleranslı; kanıt yoksa UNKNOWN.
- API quota → entegrasyon bulgusu UNKNOWN, temel HTML sürer.
- Sıra job crash → lease dolunca idempotent retry.
- Çok büyük content → `BODY_LIMIT`, kontrol edilemeyen kurallar UNKNOWN.




---

<!-- KAYNAK BELGE: docs/architecture/SECURITY.md -->
## BELGE: `docs/architecture/SECURITY.md`

# Güvenlik zorunlulukları / tehdit modeli

## P0: SSRF önlemleri
- Host/port/scheme doğrulaması: sadece `http/https`; userinfo, IPv6 trick, URL encoded host, backslash/at-sign ve parser disagreement reddedilir; public ağ dışında adres yok.
- Public IPv4/IPv6 denetimi hem DNS dönüşünde hem gerçek bağlantıda; DNS pinning, CNAME, redirect, DNS rebinding, IPv4-mapped IPv6, loopback, RFC1918, link-local, ULA, multicast, metadata IP blokları.
- Yönlendirme her hop yeniden validate edilir, otomatik follow kapalı, DNS yeniden kontrol. TLS sertifika doğrulaması kapatılmaz.
- HTTP GET ve dış görsel/sitemap/schema fetch dâhil **tüm** erişimler SafeHttpClient ile. SSRF güvenlik testleri release blocker.
- Ağ firewall/egress filtresi uygulama dışı ikinci koruma; browser worker ayrı kullanıcı/sandbox ve private network deny.
- Dahili servislerin (127.0.0.1, 169.254.x, fd00:: vb.) erişimini testte canlandır; gerçek istismar gerektirmez.

## P0: İstek ve işlem limitleri
Zaman aşımı, max redirect, max bytes, max decompressed bytes, max depth, per-host parallel, request quota, global queue depth, job deadline, circuit breaker. Robots 429/503 açıklık kontrolü, tarayıcının meşru user-agent ve opt-out yöntemi.

## Uygulama
- Output escaping HTML/attribute/URL/JSON bağlama göre; DOM XSS, CSV formula injection, log injection, SQL injection için fixtures.
- PDO prepared statements, CSRF for state-changing browser actions, HttpOnly/Secure/SameSite sessions, auth throttling, password hashing Argon2id support.
- Private report access: 128-bit+ random identifiers tek başına sır değildir; owner/expiring token access control. `noindex` ve unauthorized access farklı.
- RBAC + tenant isolation V2+, API key hashes, restricted OAuth scopes, refresh tokens at-rest encryption, state+PKCE, OAuth revocation.
- XML parser external entity/network false; zip bombs, decompression bombs, malicious charset handling, HTML size & token count limits.
- Admin secrets env, no secrets in repo; masked logs; redact Authorization/Cookie/referrer URL query secrets.
- Tarayıcı robots/kullanım koşullarına saygı; siteden özel kişisel data cache'ini minimize et, retention belirle.

## Release blocking kontrolleri
1. SSRF matrix (IPv4, IPv6, redirect, DNS rebinding, alternative encodings, nonstandard ports).
2. Broken access control (guest, owner, organization, shared report, admin, API key scope).
3. Parser fuzz malformed HTML/XML/JSON-LD; content length and decompression caps.
4. Queue race/retry flood/duplicate jobs; rate limiter bypass.
5. Secret scan and dependency vulnerability/license review.
6. Production debug off, HTTPS, security headers and backups.

## Kaynak
OWASP SSRF Prevention Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html

## Güvenlik politikası
Bu araç aktif penetrasyon, port scanning, auth bypass, saldırı simülasyonu veya güvenlik açığı sömürüsü yapmaz. SEO dışı güvenlik başlıkları bilgilendirici ayrı raporlanır. P0 güvenlik açığı varsa kamuya açma ve fazı DONE sayma.




---

<!-- KAYNAK BELGE: docs/architecture/DATABASE.md -->
## BELGE: `docs/architecture/DATABASE.md`

# Veri modeli — ayrıntılı taslak

## İlkeler
MySQL 8+ InnoDB, UTC veritabanı saatleri, arayüz `Europe/Istanbul` ya da seçilen locale, DB foreign key/unique indices. Migration numaralı ve geri döndürülebilir, üretim yedek alma kontrolünden sonra. Veriler: `analysis_id` ile izlenebilir, tenant yetkili, saklama politikalı, rapor sürümlü.

## Tablolar
| Tablo | Asgari önemli alanlar | Özel indeks / güvenlik |
|---|---|---|
| users | id, email, password_hash, locale, status, created_at | unique email, PII koruma |
| organizations | id, name, owner_user_id | tenant separation V3 |
| organization_members | organization_id, user_id, role | composite unique |
| sites | id, normalized_origin, owner_user_id?, organization_id?, verified_at | hostname hash, permissions |
| site_verifications | site_id, method, challenge_hash, expires_at, verified_at | token düz metin tutma |
| analyses | id, public_id, site_id, user_id?, mode, status, ruleset_version, scoring_version, scope_count, started_at, completed_at | public_id unique, site/time |
| analysis_jobs | id, analysis_id, idempotency_key, type, status, attempts, leased_until, available_at, worker_id | unique idempotency_key, status/available_at |
| pages | id, analysis_id, normalized_url_hash, url, crawl_status, http_status, depth | unique per analysis/url hash |
| page_snapshots | page_id, observed_at, content_hash, headers_json, meta_json, extraction_version | bounded JSON retention |
| links | analysis_id, source_page_id, target_url_hash, href, anchor, rel | graph source/target |
| rules | rule_id, rule_version, category, severity, definition_hash | composite unique |
| findings | id, analysis_id, rule_id, rule_version, status, severity, confidence, evidence_json, first_seen | query status/category |
| finding_pages | finding_id, page_id | unique pairing |
| scores | analysis_id, scoring_version, category, score, coverage, explanation_json | compare ruleset version |
| performance | analysis_id, page_id, source, device, metric, value, measured_at, sample_window | field/lab disjoint |
| integration_credentials | user_id, provider, encrypted_token, scopes, updated_at | application-level encryption |
| report_shares | analysis_id, token_hash, expires_at, revoked_at | expiry + owner authorization |
| rate_limits | key_hash, window_start, used_count, expires_at | anonymous privacy |
| audit_logs | actor_id, action, target, outcome, request_id, occurred_at | no tokens/PII leak |
| plans/subscriptions/usage | plan, period, owner, quota, use, provider reference | V3; no raw card data |

## İlişkiler
Site 1→N Analyses; Analysis 1→N Pages/Jobs/Findings/Scores; Finding N↔N Pages; Page 1→N Performance; User 1→N Sites; User/Organization scope all private records. `ON DELETE` behavior policy per table; never silent cascade across tenant.

## Saklama politikası (öneri, hukuki gereklilik ayrıca incelenir)
- Anonim rapor: kısa süreli (örn. 7-30 gün), private by default.
- Ham HTML: normal şartlarda saklama; debug için gerekli ise minimum şifreli/süreli.
- Sayfa bulguları: rapor yaşam süresi, verinin temizlenmesi.
- Hesap raporları: abonelik/izin ve silme isteği ile uyumlu.
- Ölçüm cache: provider penceresine uygun, kullanıcıya timestamp görünür.
- Audit log: belirlenmiş saklama süresi, secret redaction.

## Migrations
`0001_core.sql` ... `NNNN_*`; her migration reversible strateji ve test ortamı örnekleri. Migration checksum, schema_version, dry-run/backup, idempotency korunur.




---

<!-- KAYNAK BELGE: docs/architecture/API.md -->
## BELGE: `docs/architecture/API.md`

# HTTP API sözleşmesi

## İlkeler
`/api/v1` JSON; ISO-8601 UTC timestamp; `application/problem+json` hata, HTTP kodu doğru; listelerde cursor pagination; API değişmez sürümlü. Domain/URL kullanıcı girdisi doğrudan SQL/komut/shell'e gitmez. Authentication cookie+CSRF veya scoped API key. Analiz async `202 Accepted`.

## Endpoint sözlüğü
| Method | Endpoint | Kimlik | Amaç | Sürüm |
|---|---|---|---|---|
| POST | `/api/v1/analyses` | guest+rate limit | idempotent create, 202 | V1.0 |
| GET | `/api/v1/analyses/{id}` | authorized/read token | status/scope summary | V1.0 |
| GET | `/api/v1/analyses/{id}/progress` | authorized | stages counts | V1.0 |
| GET | `/api/v1/analyses/{id}/issues` | authorized | filter+cursor | V1.0 |
| GET | `/api/v1/analyses/{id}/scores` | authorized | category/coverage | V1.0 |
| GET | `/api/v1/analyses/{id}/pages` | authorized | discovered/crawled pages | V1.1 |
| GET | `/api/v1/analyses/{id}/performance` | authorized | field/lab metrics | V1.2 |
| POST | `/api/v1/sites/verify` | site owner | DNS/file verify | V2.0 |
| POST | `/api/v1/integrations/gsc/connect` | site owner | OAuth initiation | V2.0 |
| GET | `/api/v1/sites/{id}/search-performance` | owner | authorized GSC | V2.0 |
| GET | `/api/v1/analyses/{id}/report.pdf` | owner/share | PDF | V2.0 |
| POST | `/api/v1/api-keys` | org admin | scoped key | V3.0 |

## Analiz POST örneği
```json
{"url":"https://example.com/","mode":"quick","language":"tr-TR"}
```
Başarılı async cevap:
```json
{"analysis_id":"01EXAMPLEPUBLICANALYSISID","status":"queued","status_url":"/api/v1/analyses/01EXAMPLEPUBLICANALYSISID","limits":{"max_pages":1}}
```
`Idempotency-Key` eşdeğer isteklerde aynı job'ı döndürür. Kontroller: URL, plan cap, host cap, rate, robots, worker availability. HTTP 422 invalid URL; 429 quota; 503 worker unavailable; 403 unauthorized.

## Normalleştirilmiş Finding sözleşmesi
`issue_id`, `rule_id`, `rule_version`, `category`, `status`, `severity`, `confidence`, `data_source`, `observed_at`, `title_key`, `description_key`, `evidence[]`, `affected_urls[]`/count, `fix_key`, `references[]`. Kanıtın kullanıcıya doğrudan render edilmesi XSS riski taşır; escaping zorunlu.

## API hata örneği
```json
{"type":"https://siteportal.net/problems/url-blocked","title":"URL güvenlik politikası tarafından reddedildi","status":422,"code":"URL_BLOCKED","request_id":"req_example"}
```
Gerçek alan adı kesinleşmediyse `type` canonical link değiştirilebilir. Hata nedenleri iç IP gibi sırları istemciye ayrıntılı sızdırmamalı.

## OpenAPI
`sсhemas` altında taslak sözleşme bulunur; gerçek endpoint çıktıları implementasyonla güncellenip contract tests ile doğrulanacak. v1 yayınlandıktan sonra breaking changes yeni sürüm gerektirir.




---

<!-- KAYNAK BELGE: docs/architecture/I18N.md -->
## BELGE: `docs/architecture/I18N.md`

# Türkçe öncelikli çoklu dil tasarımı

## Değişmez kural
**Mimari ilk fazdan çok dilli, ürün başlangıç dilinde Türkçe.** Kodun içinde görünen metin sabit yazılmaz. Locale keys `ui.scan.submit`, `seo.canonical.broken.title`, `errors.rate_limited` biçiminde. `tr-TR` tam, `en-US` minimum çalışan fallback. Diğer diller yeni locale paketiyle eklenebilir.

## Katmanlar
- `LocaleResolver`: user setting > explicit language route/param > Accept-Language > tr-TR (ürün politikası); admin locale zorlaması yok.
- `Translator`: key, named params, plurals; HTML içerikleri escape edilir. Çeviriler şablon güvenlik sınırını aşamaz.
- `RuleDefinition`: title/description/fix metinleri id+params saklanır, DB'ye çevrilmiş cümle kalıcı yazılmaz; raporda locale seçilince yeniden çevrilir.
- `Number/Date`: `Intl` veya eşdeğer, sayı/para/birim/zaman ölçüsü locale'ye göre; DB UTC.
- `SEO`: locale başına özgün canonical ve `hreflang` kuralları; tüm diller hazır olmadan yanlış hreflang oluşturma.
- `PDF/Email`: bütün sistem metinleri translator API üzerinden; Unicode font desteği kalite testinde.
- `RTL`: ilk sürüm hedefi değil, CSS logical properties kullan ve yön değiştirme desteğini engelleme.

## Kalite kapıları
- Her tr key'in en-US karşılığı veya explicit fallback değeri var; missing key warning CI'da hata.
- Yer tutucu anahtarlar `{count}`/`{url}` iki dilde aynı; türlü/çoğullu kontrol.
- URL/domen kullanıcı verisi çeviri içine yerleştirilirken escape.
- Türkçe i/İ ve sorting/slug testleri. `lang` attribute ve accessibility labels tutarlı.
- Dil değiştirme analiz sonucunu/skoru değiştirmez, yalnızca sunumu değiştirir.




---

<!-- KAYNAK BELGE: docs/architecture/INTEGRATIONS.md -->
## BELGE: `docs/architecture/INTEGRATIONS.md`

# Haricî veri kaynakları ve entegrasyon kapıları

| Servis | Sürüm | Veri | Kesin sınır |
|---|---|---|---|
| PageSpeed Insights v5 | V1.2 | Lighthouse lab metric/diagnostic | Lab testi, gerçek kullanıcı değil |
| Chrome UX Report API | V1.2 | Field CWV ve örneklem | Kaynak yoksa UNKNOWN; 28 günlük kayan pencere/75. yüzdelik |
| Google Search Console Search Analytics | V2.0 | Yetkili tıklama/gösterim/CTR/konum | Tüm sorgu/satırların dönmesi garanti değil |
| Search Console URL Inspection | V2.0 | İndeksteki URL durumu | Canlı URL testini sağlamaz |
| Google OAuth | V2.0 | Sahipliği yetkilendirme | Minimum scope, token encryption, revocation |
| Bing Webmaster / IndexNow | Opsiyonel V2+ | Desteklenen URL discovery | Genel Google anında indeksleme iddiası yok |
| LLM provider | Opsiyonel V2.1 | Açıklama/öneri taslağı | Kanıt üretici değil; kullanıcı verisini minimum gönder |
| Payment gateway | V3.0 | Abonelik/plan | Hosted checkout, PCI alanını genişletme |

## Provider contract
Adapter: `isConfigured()`, `fetch(site,url,options)`, `normalize(source payload)`, `timestamp()`, `source()`, `quota()`, `errors()`. Timeouts, circuit breakers, retries/backoff, masked logs, provider-specific cache. API key/secret hiçbir zaman client koduna veya rapora girmez.

## Kullanıcı beklentisi
- GSC yokken tahmini trafik/anahtar kelime ölçümü gerçek gibi yazılmaz.
- CrUX verisi yokken sıfır puan verilmez.
- PSI kesildiğinde temel crawler sonucu `PARTIAL` ama tamamlanabilir.
- Google Search Central rehber güncellenirse yeni rule version/ADR ve test eklenir.




---

<!-- KAYNAK BELGE: docs/architecture/OPERATIONS.md -->
## BELGE: `docs/architecture/OPERATIONS.md`

# Deployment, operasyon, yedek ve maliyet

## Geliştirme
PHP 8.4+ `curl`, `dom`, `xml`, `mbstring`, `intl`, `openssl`, `pdo_mysql`; MySQL 8+; Composer (gerekli küçük kütüphaneler); cron/CLI; modern browser. Env/DB yoksa uygulama yanlışlıkla production mode'a geçmez. `public/` tek webroot.

## Ortamlar
`local` -> `staging` -> `production`. Staging gerçek kullanıcı PII ve gerçek OAuth secret kopyası barındırmaz. DB migration ve rollback staging'de onay. Release tags `v1.0.0` biçiminde sadece kabulden sonra.

## VPS önerisi
Başlangıç tek VPS PHP-FPM + nginx/Apache + MySQL + CLI cron, minimum haklarla dosya sahibi, firewall ve TLS. Worker CLI cron kısa süreli batch çalıştırılır; sürekli daemon gerekmez. GitHub Actions CI ve manuel/izinli deploy script; özel bilgileri env/secrets store'da.

## Paylaşımlı hosting alternatifi
Temel HTML analiz modülü CLI+cron olan cPanel'de çalışabilir; yoğun crawler ve Browser renderer orada önerilmez. Tarama kotasını düşür ve uzman özellikleri haricî izole worker'a taşı. Her site taraması tek PHP web isteğinde yürütülmez.

## SLO ve alarm başlangıcı
Queue length, job age, job failures, PSI/CrUX error, per-host 429, DB slow query, disk usage, API spend, suspicious URL blocks, admin login failures izlenir. RPO/RTO değerleri üretim öncesi kullanıcı onayıyla somutlaşır. Bütçe barajı aşılırsa JS/AI/PSI modüllerini durduracak feature flag olmalı.

## Yedek ve geri dönüş
DB şifreli günlük backup + restore drill; reports object store varsa ayrı version/retention; secrets backup değil korumalı altyapı. Deploy rollback: önce uygulama önceki tag, sonra gerekirse reversible migration. Çalıştırılabilir komutlar yalnızca kod üretildiğinde ve gerçekten test edildiğinde yazılır.

## KVKK / gizlilik
IP/email ve OAuth erişimleri amaçla sınırlı; aydınlatma, uygun hukuki temel, çerez politikası, kullanıcı talebiyle silme, veri minimizasyonu, retention, DPA/üçüncü taraf API veri akışı hukuk danışmanıyla doğrulanır. Üçüncü taraf sitelerin kişisel içeriği gereksiz kalıcı saklanmaz.




---

<!-- KAYNAK BELGE: docs/operations/GITHUB.md -->
## BELGE: `docs/operations/GITHUB.md`

# Boydaksu/siteportal GitHub çalışma stratejisi

## Repo
`https://github.com/Boydaksu/siteportal` — 2026-11-03 incelemesinde özel repo, `main`, çok kısa `README.md`. Bu tespit **o anın durumudur**; her faz öncesi yeniden `fetch` gerekir. Başka dosyalar ortaya çıktıysa dosyaları ezme ve merge otomatik yapma.

## Branch / commit / PR
- Her alt faz: `phase/F00.01-repo-inventory`, `phase/F01.03-ssrf`, `phase/F02.05-score` gibi tek dal.
- Düzeltme: aynı PR dalına ek commit; kabul edilen fazın regresyonu `fix/F02.05-...`.
- Commit örn. `feat(seo): F02.03 teknik denetim kuralları`.
- PR başlığı `[F02.03] Teknik denetim kuralları`, ekinde görev, test, kaynak, screenshots, güvenlik, rollback, review checklist.
- Her alt faz PR'ı `README/CURRENT_STATUS/project-status/CHANGELOG/docs/reports` değişikliklerini içerir.
- Önerilen main koruma: PR ve test gereksinimi, 1 manuel onay, force-push kapalı. Bu repo ayarlarını değiştirmek ayrıca kullanıcı onayı gerektirir.
- **Push etme yetkisi yoksa** gerçek commit SHA/PR uydurma. `BLOCKED` + patch veya dosya paketi bırak.

## README durum tutarlılığı
`main` en son **kabul edilmiş ve merge edilmiş** aşamayı göstermeli; açık PR dalında `READY_FOR_REVIEW` bulunabilir. Kullanıcı onayı aldıktan sonra merge sırasında `main` durumu `DONE` olacak biçimde düzelt. 'Push edildi' ile 'kabul edildi' ayrımı JSON'da saklanmalı. README en üstte durum paneli olmalı.

## Handoff kanıtları
Her raporda repo SHA, branch, PR URL, CI status URL, gerçek test sonuçları, değişen dosyalar ve sıradaki tek subphase. PR birleştirilmeden üretime deploy yasak.

## İlk plan paketini aktarma
Bu ZIP'i yerelde klasöre aç, projedeki dosyaları repo dizinine kopyala, **mevcut README'yi karşılaştırarak** birleştir. `git status` ve `git diff` kontrol et. İlk faz F00.01 kapsamında özel bir PR aç, onaydan önce merge etme. Gerekirse doküman dosyalarını `docs/` altında tutup SitePortal uygulamasıyla daha sonra entegre et.

## Git işlemi örneği (kullanıcı onayı ve gerçek repo durumu teyidinden sonra)
```bash
git clone git@github.com:Boydaksu/siteportal.git
cd siteportal
git switch main && git pull --ff-only
git switch -c phase/F00.01-repo-inventory
# ZIP'deki belgeleri karşılaştırıp kopyala; var olan içeriği ezme.
git status --short
git diff --check
git add README.md AGENTS.md START_HERE.md CURRENT_STATUS.md CHANGELOG.md project-status.json docs/ schemas/ locales/ .github/ .gitignore .env.example
# Yalnız gerçekten mevcut dosyaları stage et.
git commit -m "docs: F00.01 plan ve repo envanteri"
git push -u origin phase/F00.01-repo-inventory
# GitHub'da PR aç, inceleme bekle. Sonraki subphase'e GEÇME.
```




---

<!-- KAYNAK BELGE: docs/operations/PRIVACY.md -->
## BELGE: `docs/operations/PRIVACY.md`

# KVKK, veri yaşam döngüsü ve gizlilik hazırlığı

## Veri envanteri
Guest IP/rate limit hash, URL ve rapor, user email/locale, site ownership, OAuth tokens, Search Console verisi, payment subscription reference, audit logs. Verinin kimden, hangi amaçla, ne kadar süreyle ve hangi servise gönderildiği sürüm çıktısında belgelenmeli.

## Ürün kuralları
- Anonim analizler public search index'e açılmaz. Paylaşım varsayılan kapalı; süreli ve iptal edilebilir token.
- Ham HTML ve üçüncü taraf sayfadaki PII gereksiz saklanmaz; snapshot tanımlayıcı/meta ve maskeli kanıtlardan oluşur.
- Google OAuth minimum scope, token encryption, revoke ve hesabı silme senaryoları.
- Çerez izinleri ve aydınlatma metni entegrasyonlardan bağımsız planlanır; zorunlu olmayan analytics/ads için izin akışı gerekir.
- Veri paylaşımı kullanıcı yetkisi ve servis şartlarına bağlı; AI provider'a PII otomatik tam HTML yollama.
- Veri silme süreci, backup retention, audit ve irreversible account deletion tasarım ve test gerektirir.

## Önemli not
Bu dosya teknik hazırlık planıdır, hukukî görüş veya otomatik KVKK uygunluk sertifikası değildir. Üretimden önce gerçek işletmeci ve veri sorumlusu bilgileriyle hukuk uzmanı tarafından kontrol edilmelidir.




---

<!-- KAYNAK BELGE: docs/operations/RELEASE.md -->
## BELGE: `docs/operations/RELEASE.md`

# Sürüm / yayın yönetimi

## Sürüm kapıları
V0.x: yalnız geliştirme ve güvenirlik; V1.0: genel beta public guest analyzer; V1.1/1.2: tarama ve veri modülleri; V2.x: doğrulanmış site sahibi ve içerik; V3: ticari/ajans; V4: ölçek/i18n olgunluk.

## Yayın öncesi
CI pass → pentest-scope security check → rollback/backups → migration dry-run → env/config review → staging gerçek test fixture → user phase approval → merge → tag (SemVer) → manuel/izinli deploy → smoke/monitor → post-release report. Üretim kurulum için kullanıcıya makineye özel doğru komutlar ve env değişkenleri anlatılır; test edilmemiş dağıtım sözü verilmez.

## Incident response
P0 güvenlik açığı veya istismarda public scan feature flag kapatılır, kalan işler durdurulur, loglar korunur, etki alanı tespit edilir, owner bilgilendirilir. Ajan yetkisi olmadan dış kullanıcıya bildirim/production secret değiştirmez.

## 2026+ bakım ritmi
API/provider değişikliklerini, Search Central dokümanlarını ve dependency CVE'lerini düzenli kontrol. Her değişiklik yeni issue/FIX PR, test, docs ve changelog ile. 'SEO 2026' ismi dondurulmuş kurallar demek değildir.




---

<!-- KAYNAK BELGE: docs/process/WORKFLOW.md -->
## BELGE: `docs/process/WORKFLOW.md`

# Geliştirme / GitHub / AI devir protokolü — ZORUNLU

## Tek alt faz döngüsü
1. **Kontrol:** `README`, `CURRENT_STATUS`, JSON, GitHub default branch ve varsa açık PR. Son onaylı subphase'i doğrula.
2. **Plan:** Sıradaki `Fxx.yy` hedef, kabul, bağımlılıklar, kapsam dışı; dokunulacak dosyaları kısa bildir.
3. **Dal:** `phase/Fxx.yy-kisa-ad` dalını `main`'in en güncel commit'inden aç; önceki onaylanmamış dal üzerinden devam etme.
4. **Kod/test:** Yalnızca bu subphase; uygun unit/integration/E2E/security test, fixture, i18n, lint, static analysis.
5. **Belgele:** `docs/reports/Fxx.yy.md` raporu ve README/CURRENT_STATUS/project-status/CHANGELOG'ı güncelle; `READY_FOR_REVIEW` yaz. Çıktıda test komutları ve gerçek sonuçlar.
6. **GitHub:** Commit örn. `feat(seo): F02.03 teknik kurallar` veya `docs: F00.02 başvuru dokümanları`; push, PR aç; CI durumunu kontrol et. Dönüşte gerçek SHA/PR URL göster.
7. **Bildir:** Türkçe alt faz raporu: amaç, yapılanlar, değişen dosyalar, testler, kanıt, riskler, diff, URL, maliyet, sonraki adım; **onay iste ve DUR**.
8. **Kullanıcı onayı:** Kabul varsa PR merge (ayrı açık onay) ve `main` durum kayıtlarını doğrula. Ret durumunda aynı subphase dalında düzeltme yap ve yeniden raporla. Onay yoksa ilerleme yok.
9. **Faz bitimi:** Bütün alt fazlar kabul edildiğinde `docs/reports/Fxx-PHASE-SUMMARY.md` ile kapsamlı kapanış raporu oluştur; bir kez daha **dur ve ana faz onayı bekle**.

## Durum makinesi
`NOT_STARTED -> IN_PROGRESS -> READY_FOR_REVIEW -> DONE` (DONE sadece açık kullanıcı kabulünden sonra). Hata durumunda `BLOCKED`, `FAILED_QA` veya `CHANGES_REQUESTED`. `DONE` geçişinde `approved_at` ve `approval_reference` kayıt altında. GitHub push başarısı = kullanıcı kabulü değildir.

## Çelişki çözüm önceliği
1. Kullanıcının son açık kararı.
2. GitHub `main` gerçektenki içerik ve mevcut PR/commit.
3. AGENTS.md/README/CURRENT_STATUS/JSON.
4. Roadmap ve alt faz dokümanı.
5. Tahminler (otomatik uygulama yasak).

Şartnamede değişiklik gerekiyorsa önce risk ve ADR öner, kullanıcı onayı al, sonra değiştir. Mevcut `main` code'unu habersiz silme, force-push, credential commit veya production deploy yasak.

## Bağlamı olmayan sonraki AI için gerçek devir testi
Yeni AI'a **yalnız repo ve ZIP** verildiğinde okuyup şu beş soruyu yanıtlayabilmeli: Son onaylı faz? Aktif PR? Test sonucu? Bir sonraki tek subphase? Açık engel? Biri belirsizse devir kalitesi yetersiz; o subphase DONE olamaz.

## README güncelleme normu
İlk 15 satırda tarih, son kabul edilen Fxx.yy, aktif Fxx.yy, durum, PR, SHA, son test özeti ve takip eden görev anlaşılır olmalı. Uzun raporlar `docs/reports` içinde, README bağlantılarla kısa kalır. `project-status.json` makine tarafından parse edilebilir olmalı.

## Belge bakımı ve açık kayıtlar
[START_HERE](START_HERE.md#sabit-okuma-sırası) tek okuma sırasını, [MAINTENANCE](docs/process/MAINTENANCE.md) durum alanları/komut/manifest standardını tanımlar. [OPEN_QUESTIONS](docs/process/OPEN_QUESTIONS.md) mimari karar bekleyen farkları korur. Başlangıç yetkisi, teslim kabulü, PR merge ve dağıtım yetkisi ayrı kaydedilir. Devir testi sohbet geçmişi aktarılmayan ayrı ajanla yapılır; yalnız repo yolu verilir, beş soruya cevap için beklenen sonuçlar paylaşılmaz. ZIP yoksa korunmuş envanter kullanılır ve eksik arşiv raporlanır.




---

<!-- KAYNAK BELGE: docs/process/QA.md -->
## BELGE: `docs/process/QA.md`

# Test stratejisi ve Definition of Done

## Test piramidi
- **Unit:** URL normalize, IP CIDR public vs private, redirect validation, robots groups, canonical extraction, malformed DOM, score denominator, locale placeholder.
- **Contract:** API request/response schema, 202/422/429/503 codes, idempotent POST, pagination, issue evidence schema, OAuth provider mocks.
- **Integration:** MySQL migrations, job lease race/retry, crawler request budgets, XML streaming, cache, report TTL.
- **E2E:** Guest URL→job→complete, noindex, blocked host, payment plan, GSC revoke, mobile/i18n navigation (ilgili sürümden itibaren).
- **Security:** SSRF adversarial fixtures, XSS, SQL injection, XXE, zip bomb, CSRF, ACL IDOR, API key and token leak, rate limit bypass.
- **Quality:** 30+ kuralın fixture'ları (positive, negative, not_applicable, unknown), false positive review; snapshot tests, source validity.
- **Performance:** 1, 10, 100, 500, 10k sentetik URL benchmark (sürüme bağlı), no runaway worker; memory/CPU, queue latency, DB indexes.
- **Resilience:** API outage, DNS timeout, 429/503, robots failure, queue crash, worker restart, partial report, rate limit.
- **A11y/i18n:** Klavye, screen reader semantics, focus, contrast, Türkçe/İngilizce çeviri anahtarları, Turkish locale collation.

## Release engelleyici kriterler
- SSRF critical bypass, internal IP erişimi, OAuth/tenant leak, özel rapor yetki zafiyeti, XSS, worker sonsuz döngü, metrics uydurma, kaynağı belirsiz kesin SEO iddiası.
- Fail olan veya çalıştırılmamış test için 'testleri geçti' denmez. Zorunlu test çalıştırılamıyorsa `BLOCKED`, mock/synthetic test açıkça etiketlenir.

## Güvenlik fixture örnekleri
`http://127.0.0.1`, `http://[::1]`, `http://169.254.169.254`, private IPv6/IPv4, encoded loopback, redirect public->private, DNS rebinding, escaped `@` ve ters eğik çizgi, malformed HTTP/HTML, sitemap external entities ve zip bomb. Saldırı kodu değil, kontrollü test ortamı.

## DoD checklist
- [ ] Kod veya sözleşme alt faz sınırında.
- [ ] Kaynak/algoritma/format doğrulanmış ve referanslı.
- [ ] En az mutlu yol, olumsuz yol, yetki/güvenlik ve veri yok senaryosu testli.
- [ ] Lint, statik analiz, migration testleri yeşil.
- [ ] Yeni metinlerin tr-TR/en-US anahtarı ve UI a11y kontrolü var.
- [ ] Kapsam, kaynak, zaman damgası ve unknown semantics korundu.
- [ ] README, CURRENT_STATUS, JSON, CHANGELOG, alt faz raporu ve GitHub PR/SHA güncel.
- [ ] Kullanıcı raporu sunuldu ve **onay bekleniyor**.

## Faz kapanış / regresyon
Sürümün bütün kabul kriterleri; önceki sürümlerin kritik regression suite'i; güvenlik risk değerlendirmesi; gerçek demo veya açık blocker; migration rollback; depolama ve API quota; source freshness; KVKK/retention; geri alma planı. Ana faz onayı olmadan sonraki sürüme geçilmez.




---

<!-- KAYNAK BELGE: docs/process/REPORT_TEMPLATE.md -->
## BELGE: `docs/process/REPORT_TEMPLATE.md`

# Fxx.yy — Alt Faz Uygulama Raporu (şablon)

> Tarih/saat (Europe/Istanbul): YYYY-MM-DD HH:MM
> Durum: NOT_STARTED / IN_PROGRESS / READY_FOR_REVIEW / BLOCKED / CHANGES_REQUESTED / DONE
> Branch: `phase/Fxx.yy-...`
> PR URL: ...
> Commit SHA: ...
> Kullanıcı onayı: ALINMADI (varsa kanıt linki/tarih)

## 1. Bu alt fazın amacı
Kapsam, kabul kriterleri, neden şimdi.

## 2. Gerçekleştirilen işler
Numaralı maddeler, etkilenen modüller, düzey (gerçek/kısmi/demo).

## 3. Değişen dosyalar
Dosya yolu, neden, test.

## 4. Doğrulamalar
| Komut / test | Beklenen | Gerçek sonuç | Kanıt |
|---|---|---|---|
| ... | ... | PASS/FAIL/BLOCKED | Log/CI bağlantısı |

## 5. Güvenlik, SEO doğruluğu ve i18n
SSRF etki, yeni alan/erişim kontrolleri, kaynak referansı, API gerçek/heuristic ayrımı, Türkçe/İngilizce, veri gizliliği.

## 6. Kalan eksik, risk, teknik borç
Severity, sahip, öneri; bilinmeyenleri gizleme.

## 7. GitHub ve geri alma
PR, SHA, diff özeti, rollback ve migration riskleri.

## 8. Kullanıcıya gösterilebilir demo
URL/video/screenshot/synthetic vs real açıklaması.

## 9. Sonraki tek alt faz ve bağımlılık
`Fxx.yy` alt fazını öner; otomatik başlama YOK.

## 10. Karar
**İNCELEME VE AÇIK ONAY BEKLENİYOR — AJAN DURMALIDIR.**

---

# Fxx — Ana Faz Kapanış Raporu (şablon)

| Alt faz | Kullanıcı onayı | PR/commit | Test | Kalan iş |
|---|---|---|---|---|
| Fxx.01 | ... | ... | ... | ... |

Ek: sürüm hedefleri gerçekleşti mi, önceki sürüm regresyonu, güvenlik, deployment, kullanım bütçesi, değişen mimari, riskler, demo, sonraki ana faza geçiş önerisi ve **AYRI ANA FAZ ONAYI BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/process/DECISIONS.md -->
## BELGE: `docs/process/DECISIONS.md`

# Architectural Decision Records (ADR) ve açık kararlar

## Kabul edilen başlangıç kararları
- ADR-001: SitePortal modüler PHP monolit, framework az, MySQL + CLI cron, server-side HTML; veri tabanlı kuyruk, opsiyonel renderer.
- ADR-002: SEO Analyzer genel SitePortal modülü; hosting işi değildir.
- ADR-003: Türkçe öncelik, locale key zorunluluğu, en-US fallback.
- ADR-004: Güvenlik, doğruluk, bilgi kaynağı ve fazdan sonra durma davranışı özelliklerin önünde.
- ADR-005: Özel rapor varsayılan; domain doğrulanmadıkça yoğun/site çaplı tarama yok.

## İlk uygulamadan önce karara bağlanacaklar
- Prod ortamı alan adı ve gerçek sunucu/cPanel/VPS ayrıntıları.
- E-posta servisi, KVKK legal metin sahibi, veri saklama süreleri.
- Hangi ödeme sağlayıcısı/hangi plan fiyatları V3'te seçilecek.
- Admin güvenlik/MFA policy, müşteri dashboard ihtiyacı.
- Gerçek GitHub ana dal koruması PR review ve CI. Repo yönetim değişikliği ayrı onay gerektirir.

## Yeni ADR formatı
`ADR-NNN: problem → seçenekler → karar → neden → güvenlik/maliyet etkisi → geri dönüş → kullanıcı onayı → değiştirilecek faz`. Riskli/geri dönüşsüz kararlarda önce kullanıcı onayı; sessiz değiştirme yapılmaz.

## Açık sözleşme farkları
F00.01 bulguları [OPEN_QUESTIONS](docs/process/OPEN_QUESTIONS.md) içinde izlenir. F00.02 bunları görünür kılar; yeni ADR veya çözüm üretmez.




---

<!-- KAYNAK BELGE: docs/roadmap/ROADMAP.md -->
## BELGE: `docs/roadmap/ROADMAP.md`

# ROADMAP — Fazlar, alt fazlar, duraklama kuralları

Bu belge sürüm hedeflerini tanımlar. **Her Fxx.yy sonrasında ayrı commit+PR+test+rapor; ardından kullanıcı onayı gelene dek DUR.** Fxx bitiminde tekrar kapsamlı kapanış raporu ve onay gerekir. Faz numarası tamamlanma anlamına gelmez.

| Faz | Sürüm | Amaç | Alt faz sayısı | Durum |
|---|---|---|---:|---|
| [F00](docs/roadmap/F00.md) | V0.0 | Sözleşme, repo ve standartlar | 6 | IN_PROGRESS |
| [F01](docs/roadmap/F01.md) | V0.1 | Güvenli çekirdek ve worker | 7 | NOT_STARTED |
| [F02](docs/roadmap/F02.md) | V1.0 | Üyeliksiz tek sayfa SEO analizi | 8 | NOT_STARTED |
| [F03](docs/roadmap/F03.md) | V1.1 | Site çapında kontrollü tarama | 7 | NOT_STARTED |
| [F04](docs/roadmap/F04.md) | V1.2 | Performans, mobil, yapılandırılmış veri | 7 | NOT_STARTED |
| [F05](docs/roadmap/F05.md) | V2.0 | Hesap, domain doğrulama ve Search Console | 8 | NOT_STARTED |
| [F06](docs/roadmap/F06.md) | V2.1 | İçerik, AI hazırlık, alan modülleri | 7 | NOT_STARTED |
| [F07](docs/roadmap/F07.md) | V3.0 | Abonelik, ajans, açık API | 8 | NOT_STARTED |
| [F08](docs/roadmap/F08.md) | V4.0 | Ölçeklenebilir, çok dilli, olgun platform | 7 | NOT_STARTED |

## Sıralama ve kapılar

1. **F00 — V0.0:** Sözleşme, repo ve standartlar. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
2. **F01 — V0.1:** Güvenli çekirdek ve worker. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
3. **F02 — V1.0:** Üyeliksiz tek sayfa SEO analizi. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
4. **F03 — V1.1:** Site çapında kontrollü tarama. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
5. **F04 — V1.2:** Performans, mobil, yapılandırılmış veri. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
6. **F05 — V2.0:** Hesap, domain doğrulama ve Search Console. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
7. **F06 — V2.1:** İçerik, AI hazırlık, alan modülleri. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
8. **F07 — V3.0:** Abonelik, ajans, açık API. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
9. **F08 — V4.0:** Ölçeklenebilir, çok dilli, olgun platform. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.

## DoD (Definition of Done)
- Kod + fixture + test + güvenlik + i18n + doküman + rapor + commit SHA/PR kanıtı.
- Kritik test başarısızsa faz DONE olamaz.
- PR dalına push `READY_FOR_REVIEW`, kullanıcının kabulü `DONE`; birleştirme ayrıca onaya bağlıdır.
- Kapanışta önceki faz geriye dönük değiştiriliyorsa ayrı fix PR aç.


## Önceliklendirme
**P0:** SSRF, robots, güvenli istek, queue, yetki, doğruluk, temel UI. **P1:** Site mimarisi, CWV, schema, GSC doğrulanmış kullanıcı. **P2:** AI/ajans/white-label/ücretli hesap. **Şimdilik gereksiz:** Kubernetes, mikroservis ağı, herkes için headless Chrome, backlink scraping, Google sırası tahmin modeli, otomatik SEO düzeltme, zorunlu llms.txt.

## Aktif F00 teslimi
F00.01 kullanıcı kabulüyle DONE ve PR #1 birleştirildi. Aktif alt faz F00.02; durum [JSON](project-status.json), kanıt [F00.02 raporu](docs/reports/F00.02.md) içindedir. F00.03 ve sonrası NOT_STARTED.




---

<!-- KAYNAK BELGE: docs/roadmap/F00.md -->
## BELGE: `docs/roadmap/F00.md`

# F00 — V0.0: Sözleşme, repo ve standartlar

**Amaç:** Kullanılabilir ürün değil; test edilebilir ve devredilebilir çalışma zemini.

**Bağımlılık:** Başlangıç; mevcut repo envanteri.

**Bitiş kapısı:** Her alt faz `READY_FOR_REVIEW` raporuyla durur. Kullanıcı kabul edip gerekirse PR birleştirilmeden diğer alt faza geçilmez. Son alt fazın ardından **ayrı ana faz kapanış raporu** ve onay beklenir.

**Kapsam dışı:** Sonraki sürümlerin özellikleri ve toplu/tek seferde geliştirme.

## Alt fazlar

### F00.01 — Mevcut repo envanteri
**Uygulama durumu:** DONE; [envanter raporu](docs/reports/F00.01.md). Kullanıcı kabulü ve PR #1 birleştirmesi kayıtlı.
**Hedef:** Mevcut repo envanteri.
**Yapılacak işler:**
1. GitHub main ve izinleri oku.
2. mevcut README ve dal durumunu kayıt altına al.
3. çakışma ve veri silme risklerini tespit et.

**Kabul/kanıt:**
- [x] Depo gerçek içerikleri raporda.
- [x] hiçbir mevcut dosya habersiz silinmedi.
- [x] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [x] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [x] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [x] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F00.01.md` + durum kayıtları.

### F00.02 — Belgeleri ve faz sözleşmesini yerleştir
**Uygulama durumu:** READY_FOR_REVIEW; [F00.02 raporu](docs/reports/F00.02.md). F00.03 ve sonrası başlamadı.
**Hedef:** Belgeleri ve faz sözleşmesini yerleştir.
**Yapılacak işler:**
1. README AGENTS START_HERE CURRENT_STATUS roadmap ve kaynakları ekle.
2. bağlantıları düzelt.
3. Türkçe bakım talimatlarını standartlaştır.

**Kabul/kanıt:**
- [x] Doküman bağlantıları geçerli.
- [x] devir testi sıfır bağlamlı ajanla yapılmış.
- [x] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [x] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [x] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F00.02.md` + durum kayıtları.

### F00.03 — İlk ADR ve bağımlılık politikası
**Hedef:** İlk ADR ve bağımlılık politikası.
**Yapılacak işler:**
1. PHP/DB/JS teknik sınırlarını ADR ile kaydet.
2. modül sınırlarını açıkla.
3. dependency lisans/sürüm ve tehdit modelini oluştur.

**Kabul/kanıt:**
- [ ] Seçimlerin gerekçesi ve alternatifleri ADR içinde.
- [ ] onaysız framework eklenmedi.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F00.03.md` + durum kayıtları.

### F00.04 — Test ve CI iskeleti
**Hedef:** Test ve CI iskeleti.
**Yapılacak işler:**
1. PHP lint format static-analysis test harness.
2. GitHub Actions en az PHP lint/doküman bağlantı kontrolü.
3. örnek fixture.

**Kabul/kanıt:**
- [ ] Temiz checkout üzerinde CI yeşil.
- [ ] çalıştırılabilir test komutları belgelenmiş.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F00.04.md` + durum kayıtları.

### F00.05 — Repo ve PR politikası
**Hedef:** Repo ve PR politikası.
**Yapılacak işler:**
1. Dallar PR şablonları issue template inceleme adımları.
2. ana dal koruması önerisini dokümante et.
3. secret scanning kontrol listesi.

**Kabul/kanıt:**
- [ ] PR formatı ve rollback yönergesi geçerli.
- [ ] main koruması yetki varsa ve kullanıcı onayıyla.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F00.05.md` + durum kayıtları.

### F00.06 — F00 faz kapanış / kabul
**Hedef:** F00 faz kapanış / kabul.
**Yapılacak işler:**
1. Önceki alt faz raporlarını ve kanıtları özetle.
2. açık risk ve kabul kararı hazırla.
3. kullanıcıya kapsamlı kapanış raporu ver.

**Kabul/kanıt:**
- [ ] F00 ana faz raporu ve kullanıcı kabulü olmadan F01 başlamaz.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F00.06.md` + durum kayıtları.

## Ana faz kapanış kontrolü
- [ ] Bütün alt fazlar açık kullanıcı onayı ile DONE.
- [ ] Bütün kabul maddeleri için test kanıtı (komut/çıktı ve commit).
- [ ] Güvenlik, veri gizliliği ve performans riskleri değerlendirildi.
- [ ] README ve sistem mimarisi değişen gerçek durumu yansıtıyor.
- [ ] `docs/reports/F00-PHASE-SUMMARY.md` oluşturuldu; kullanıcıya kapsamlı Türkçe rapor sunuldu.
- [ ] Kullanıcının açık ana faz onayı bekleniyor; otomatik sonraki faz yok.

## Bitiş raporu şablonu
Amaç → yapılan alt fazlar → dosyalar/PR/SHA → testler → bulgular → riskler/borç → ürün demo bağlantısı → geri alma planı → değişen maliyet → önerilen sonraki faz → **ONAY BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/roadmap/F01.md -->
## BELGE: `docs/roadmap/F01.md`

# F01 — V0.1: Güvenli çekirdek ve worker

**Amaç:** Haricî URL istekleri ve iş sırası güvenli değilse ilerlemek yasak.

**Bağımlılık:** Bir önceki ana fazın onaylanmış kapanışı ve `main` ile senkronizasyon.

**Bitiş kapısı:** Her alt faz `READY_FOR_REVIEW` raporuyla durur. Kullanıcı kabul edip gerekirse PR birleştirilmeden diğer alt faza geçilmez. Son alt fazın ardından **ayrı ana faz kapanış raporu** ve onay beklenir.

**Kapsam dışı:** Sonraki sürümlerin özellikleri ve toplu/tek seferde geliştirme.

## Alt fazlar

### F01.01 — Proje çalışma iskeleti
**Hedef:** Proje çalışma iskeleti.
**Yapılacak işler:**
1. public bootstrap router request-id hata yakalayıcı config loader PSR-4 autoload ve tr/en dizileri.
2. robots/sitemap ve 404/500 sayfaları.

**Kabul/kanıt:**
- [ ] PHP server ile health endpoint ve UI açılıyor.
- [ ] debug kapalı güvenli hata sayfası.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F01.01.md` + durum kayıtları.

### F01.02 — Kurulum ve migration motoru
**Hedef:** Kurulum ve migration motoru.
**Yapılacak işler:**
1. MySQL bağlantısı PDO prepared statements.
2. reversible migration runner.
3. seed development fixtures.
4. .env.example ve kurulum README.

**Kabul/kanıt:**
- [ ] Boş DB ye kurulur ve yeniden çalıştırmak idempotent.
- [ ] rollback test edildi.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F01.02.md` + durum kayıtları.

### F01.03 — URL doğrulama / SSRF koruması
**Hedef:** URL doğrulama / SSRF koruması.
**Yapılacak işler:**
1. URL parser allowlist şema/port.
2. IPv4/IPv6 public IP denetimi.
3. DNS pinning.
4. redirect yeniden doğrulama.
5. outbound network kısıt politikası.

**Kabul/kanıt:**
- [ ] Internal/metadata/loopback/DNS rebinding fixture istekleri reddedildi.
- [ ] public örnek istek geçer.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F01.03.md` + durum kayıtları.

### F01.04 — HTTP istemcisi ve robots uyumu
**Hedef:** HTTP istemcisi ve robots uyumu.
**Yapılacak işler:**
1. SafeHttpClient timeout byte/decompress limits redirect chain ve HTTP headers.
2. robots parser user-agent grupları ve cache.
3. polite crawler kimliği.

**Kabul/kanıt:**
- [ ] Robots engelinde sayfa alınmıyor.
- [ ] 429/503 yavaşlatma.
- [ ] gzip bomb sınırı.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F01.04.md` + durum kayıtları.

### F01.05 — DB queue / worker scheduler
**Hedef:** DB queue / worker scheduler.
**Yapılacak işler:**
1. Atomic job claim transaction lock lease retry backoff idempotent dispatch watchdog.
2. CLI worker ve cron fallback.

**Kabul/kanıt:**
- [ ] İki worker aynı işi iki kere işlemez.
- [ ] yarım iş güvenli toparlanır.
- [ ] maksimum deneme durdurur.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F01.05.md` + durum kayıtları.

### F01.06 — Gözlemlenebilirlik, kotalar, oturum
**Hedef:** Gözlemlenebilirlik, kotalar, oturum.
**Yapılacak işler:**
1. Log masking correlation ID metrics request budget API throttle CSRF güvenli session ve temel admin auth.

**Kabul/kanıt:**
- [ ] Secret loga yazılmıyor.
- [ ] rate limit ve yeniden deneme testleri geçiyor.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F01.06.md` + durum kayıtları.

### F01.07 — F01 güvenlik testi ve kapanış
**Hedef:** F01 güvenlik testi ve kapanış.
**Yapılacak işler:**
1. SSRF matrisi, queue yarış testleri, izolasyon ve sağlık testlerini çalıştır.
2. ADR/kullanım komutlarını netleştir.

**Kabul/kanıt:**
- [ ] Kritik güvenlik açığı yok.
- [ ] F01 ana faz raporu/kullanıcı onayı olmadan F02 yok.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F01.07.md` + durum kayıtları.

## Ana faz kapanış kontrolü
- [ ] Bütün alt fazlar açık kullanıcı onayı ile DONE.
- [ ] Bütün kabul maddeleri için test kanıtı (komut/çıktı ve commit).
- [ ] Güvenlik, veri gizliliği ve performans riskleri değerlendirildi.
- [ ] README ve sistem mimarisi değişen gerçek durumu yansıtıyor.
- [ ] `docs/reports/F01-PHASE-SUMMARY.md` oluşturuldu; kullanıcıya kapsamlı Türkçe rapor sunuldu.
- [ ] Kullanıcının açık ana faz onayı bekleniyor; otomatik sonraki faz yok.

## Bitiş raporu şablonu
Amaç → yapılan alt fazlar → dosyalar/PR/SHA → testler → bulgular → riskler/borç → ürün demo bağlantısı → geri alma planı → değişen maliyet → önerilen sonraki faz → **ONAY BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/roadmap/F02.md -->
## BELGE: `docs/roadmap/F02.md`

# F02 — V1.0: Üyeliksiz tek sayfa SEO analizi

**Amaç:** İlk gerçek kullanılabilir sürüm. URL başına güvenli analizin kanıtlı raporu.

**Bağımlılık:** Bir önceki ana fazın onaylanmış kapanışı ve `main` ile senkronizasyon.

**Bitiş kapısı:** Her alt faz `READY_FOR_REVIEW` raporuyla durur. Kullanıcı kabul edip gerekirse PR birleştirilmeden diğer alt faza geçilmez. Son alt fazın ardından **ayrı ana faz kapanış raporu** ve onay beklenir.

**Kapsam dışı:** Sonraki sürümlerin özellikleri ve toplu/tek seferde geliştirme.

## Alt fazlar

### F02.01 — URL formu ve analiz yaşam döngüsü
**Hedef:** URL formu ve analiz yaşam döngüsü.
**Yapılacak işler:**
1. Ana sayfa responsive URL formu.
2. form doğrulama analiz oluşturma durum endpointi.
3. polling ve gerçek progress.

**Kabul/kanıt:**
- [ ] Mobil/masaüstü form çalışıyor.
- [ ] sahte yüzdelik yok.
- [ ] anonim limitler aktif.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F02.01.md` + durum kayıtları.

### F02.02 — HTML parser ve metadata
**Hedef:** HTML parser ve metadata.
**Yapılacak işler:**
1. DOMDocument/DOMXPath UTF-8 meta title description headings lang canonical directives og image ve link extraction.

**Kabul/kanıt:**
- [ ] Bozuk/eksik HTML fixture da parser çökmüyor.
- [ ] normalize edilmiş alanlar doğru.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F02.02.md` + durum kayıtları.

### F02.03 — Teknik SEO kuralları
**Hedef:** Teknik SEO kuralları.
**Yapılacak işler:**
1. HTTP redirects HTTPS robots meta/X-Robots canonical 404 soft404 sezgisi sitemap discover.
2. ilk kritik kurallar.

**Kabul/kanıt:**
- [ ] Her bulgunun observed/evidence source ve rule_version bilgisi var.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F02.03.md` + durum kayıtları.

### F02.04 — On-page kuralları
**Hedef:** On-page kuralları.
**Yapılacak işler:**
1. Title meta description h1-h6 semantic headings anchor alt ve index hints.
2. heuristic eşikleri kanıtla tanımla.

**Kabul/kanıt:**
- [ ] Mutlak title uzunluğu/h1 sayısı cezası yok.
- [ ] applicability ve UNKNOWN doğru.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F02.04.md` + durum kayıtları.

### F02.05 — Kural kayıt sistemi ve skorlama
**Hedef:** Kural kayıt sistemi ve skorlama.
**Yapılacak işler:**
1. RuleInterface registry per-rule versions.
2. pass warn fail unknown not-applicable.
3. kapsama göre ağırlıklar.

**Kabul/kanıt:**
- [ ] Tekrarlanabilir deterministik skor.
- [ ] UNKNOWN zero sayılmaz.
- [ ] snapshot testleri.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F02.05.md` + durum kayıtları.

### F02.06 — Rapor UI ve çözüm önerisi
**Hedef:** Rapor UI ve çözüm önerisi.
**Yapılacak işler:**
1. Genel skor kategori sekmeleri sorun listesi severity kanıt çözüm kod parçaları erişilebilir filtreler.

**Kabul/kanıt:**
- [ ] Her hata kaynak/URL/çözüm içeriyor.
- [ ] HTML escaping XSS fixture geçiyor.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F02.06.md` + durum kayıtları.

### F02.07 — Anonim paylaşım ve yaşam süresi
**Hedef:** Anonim paylaşım ve yaşam süresi.
**Yapılacak işler:**
1. Tahmin edilemez rapor kimliği public/private erişim kuralları noindex ve TTL.
2. kontrollü cache.

**Kabul/kanıt:**
- [ ] Özel rapor kimliksiz görülemiyor.
- [ ] robots noindex uygulanmış.
- [ ] TTL temizlik işliyor.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F02.07.md` + durum kayıtları.

### F02.08 — V1.0 uçtan uca ve kapanış
**Hedef:** V1.0 uçtan uca ve kapanış.
**Yapılacak işler:**
1. Örnek sitelerle gerçek test.
2. erişilemeyen hedef.
3. external outage.
4. mobil smoke.
5. kullanıcı demo kabul listesi.

**Kabul/kanıt:**
- [ ] Üyeliksiz tek URL analizi V1.0 çalışır.
- [ ] faz raporu/kullanıcı onayı zorunlu.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F02.08.md` + durum kayıtları.

## Ana faz kapanış kontrolü
- [ ] Bütün alt fazlar açık kullanıcı onayı ile DONE.
- [ ] Bütün kabul maddeleri için test kanıtı (komut/çıktı ve commit).
- [ ] Güvenlik, veri gizliliği ve performans riskleri değerlendirildi.
- [ ] README ve sistem mimarisi değişen gerçek durumu yansıtıyor.
- [ ] `docs/reports/F02-PHASE-SUMMARY.md` oluşturuldu; kullanıcıya kapsamlı Türkçe rapor sunuldu.
- [ ] Kullanıcının açık ana faz onayı bekleniyor; otomatik sonraki faz yok.

## Bitiş raporu şablonu
Amaç → yapılan alt fazlar → dosyalar/PR/SHA → testler → bulgular → riskler/borç → ürün demo bağlantısı → geri alma planı → değişen maliyet → önerilen sonraki faz → **ONAY BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/roadmap/F03.md -->
## BELGE: `docs/roadmap/F03.md`

# F03 — V1.1: Site çapında kontrollü tarama

**Amaç:** Sitemap+link graph ile maksimum kapsam, agresif crawler değil.

**Bağımlılık:** Bir önceki ana fazın onaylanmış kapanışı ve `main` ile senkronizasyon.

**Bitiş kapısı:** Her alt faz `READY_FOR_REVIEW` raporuyla durur. Kullanıcı kabul edip gerekirse PR birleştirilmeden diğer alt faza geçilmez. Son alt fazın ardından **ayrı ana faz kapanış raporu** ve onay beklenir.

**Kapsam dışı:** Sonraki sürümlerin özellikleri ve toplu/tek seferde geliştirme.

## Alt fazlar

### F03.01 — URL frontier ve normalize keşif
**Hedef:** URL frontier ve normalize keşif.
**Yapılacak işler:**
1. Origin scope registrable domain/host policy.
2. dedup trailing slash path query canonicalization.
3. frontier states.

**Kabul/kanıt:**
- [ ] Aynı URL tekrar işlenmez.
- [ ] farklı anlamlı query parametreleri korunur.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F03.01.md` + durum kayıtları.

### F03.02 — Sitemap index ve XML güvenliği
**Hedef:** Sitemap index ve XML güvenliği.
**Yapılacak işler:**
1. Sitemap keşfi robots içinden ve bilinen yollardan.
2. nested index limits.
3. streaming XMLReader no external entities.

**Kabul/kanıt:**
- [ ] Çok büyük/bozuk/bomb XML reddedilir.
- [ ] 50k URL/50MB kontrolleri.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F03.02.md` + durum kayıtları.

### F03.03 — İç link grafiği ve derinlik
**Hedef:** İç link grafiği ve derinlik.
**Yapılacak işler:**
1. Href resolution graph source/target status depth anchor text.
2. BFS crawl budget prioritization.

**Kabul/kanıt:**
- [ ] Bozuk link ve derinlik sayımları fixture ile doğru.
- [ ] dış domain izinsiz taranmıyor.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F03.03.md` + durum kayıtları.

### F03.04 — Site çapı metadata ve canonical analizleri
**Hedef:** Site çapı metadata ve canonical analizleri.
**Yapılacak işler:**
1. Duplicate title/meta/h1 near duplicates.
2. sitemap canonical noindex conflict.
3. sampled coverage.

**Kabul/kanıt:**
- [ ] Eksik tarama durumunda yetim sayfa kesin diye işaretlenmiyor.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F03.04.md` + durum kayıtları.

### F03.05 — Tarama bütçesi ve host nezaketi
**Hedef:** Tarama bütçesi ve host nezaketi.
**Yapılacak işler:**
1. Per-host concurrency=1 başlangıç, adaptive delay, retry-after, crawl-stop, global quota ve scheduler fairness.

**Kabul/kanıt:**
- [ ] 429 ve 503 durumunda davranış doğrulandı.
- [ ] API bütçesi aşılmadı.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F03.05.md` + durum kayıtları.

### F03.06 — Çok sayfalı rapor/CSV
**Hedef:** Çok sayfalı rapor/CSV.
**Yapılacak işler:**
1. Sayfa listesi arama filtre sayfalama etkilenen URL ve sayfa kapsamı CSV güvenli escaping.

**Kabul/kanıt:**
- [ ] 10/100 sayfalık sentetik veriyle rapor ve CSV doğrulandı.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F03.06.md` + durum kayıtları.

### F03.07 — V1.1 uçtan uca kapanış
**Hedef:** V1.1 uçtan uca kapanış.
**Yapılacak işler:**
1. Sitemap yok, redirect döngü, robots engel, binlerce URL, 10 sayfa quota ve devam eden iş testi.

**Kabul/kanıt:**
- [ ] Tarama durdurma/güncelleme sağlam.
- [ ] faz kapanış raporu ve onay.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F03.07.md` + durum kayıtları.

## Ana faz kapanış kontrolü
- [ ] Bütün alt fazlar açık kullanıcı onayı ile DONE.
- [ ] Bütün kabul maddeleri için test kanıtı (komut/çıktı ve commit).
- [ ] Güvenlik, veri gizliliği ve performans riskleri değerlendirildi.
- [ ] README ve sistem mimarisi değişen gerçek durumu yansıtıyor.
- [ ] `docs/reports/F03-PHASE-SUMMARY.md` oluşturuldu; kullanıcıya kapsamlı Türkçe rapor sunuldu.
- [ ] Kullanıcının açık ana faz onayı bekleniyor; otomatik sonraki faz yok.

## Bitiş raporu şablonu
Amaç → yapılan alt fazlar → dosyalar/PR/SHA → testler → bulgular → riskler/borç → ürün demo bağlantısı → geri alma planı → değişen maliyet → önerilen sonraki faz → **ONAY BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/roadmap/F04.md -->
## BELGE: `docs/roadmap/F04.md`

# F04 — V1.2: Performans, mobil, yapılandırılmış veri

**Amaç:** Dış API kaynaklarını doğru gösteren, hata toleranslı modüller.

**Bağımlılık:** Bir önceki ana fazın onaylanmış kapanışı ve `main` ile senkronizasyon.

**Bitiş kapısı:** Her alt faz `READY_FOR_REVIEW` raporuyla durur. Kullanıcı kabul edip gerekirse PR birleştirilmeden diğer alt faza geçilmez. Son alt fazın ardından **ayrı ana faz kapanış raporu** ve onay beklenir.

**Kapsam dışı:** Sonraki sürümlerin özellikleri ve toplu/tek seferde geliştirme.

## Alt fazlar

### F04.01 — PageSpeed Insights bağlayıcısı
**Hedef:** PageSpeed Insights bağlayıcısı.
**Yapılacak işler:**
1. Mobile/desktop lab ölçümleri async fetch budget API-key config safe cache.
2. scored vs raw metrics ayrımı.

**Kabul/kanıt:**
- [ ] API kesilince SEO raporu bozulmaz.
- [ ] lab veri kaynağı etiketli.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F04.01.md` + durum kayıtları.

### F04.02 — CrUX gerçek kullanıcı verisi
**Hedef:** CrUX gerçek kullanıcı verisi.
**Yapılacak işler:**
1. CrUX API field data 75th percentile 28-day window ve data unavailable durumu.

**Kabul/kanıt:**
- [ ] Lab ile field karışmaz.
- [ ] no-data UNKNOWN olarak görünür.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F04.02.md` + durum kayıtları.

### F04.03 — CWV rapor deneyimi
**Hedef:** CWV rapor deneyimi.
**Yapılacak işler:**
1. LCP INP CLS eşikleri grafik, fırsatlar, mobil/desktop kıyasları ve zaman damgası.

**Kabul/kanıt:**
- [ ] Eşik sınır testleri geçer.
- [ ] performans 0 puan veri yokluğunda verilmez.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F04.03.md` + durum kayıtları.

### F04.04 — JSON-LD Microdata RDFa
**Hedef:** JSON-LD Microdata RDFa.
**Yapılacak işler:**
1. Parser, schema.org genel doğrulama, Google rich-result destek ve sayfa türü applicability ayrımı.

**Kabul/kanıt:**
- [ ] Syntax hatası ile eligibility önerisi ayrı.
- [ ] geçersiz nested veri fixtures.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F04.04.md` + durum kayıtları.

### F04.05 — Mobil/a11y temel kontroller
**Hedef:** Mobil/a11y temel kontroller.
**Yapılacak işler:**
1. Viewport semantic labels headings image alt dekoratif ayrım ve otomatik accessibility.
2. varsa Lighthouse data.

**Kabul/kanıt:**
- [ ] Erişilebilirlik tam sertifika diye iddia edilmez.
- [ ] uyumsuz sinyaller kanıtla.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F04.05.md` + durum kayıtları.

### F04.06 — Selektif JS rendering tasarımı
**Hedef:** Selektif JS rendering tasarımı.
**Yapılacak işler:**
1. Opt-in isolated browser worker contract, render request/timeout/network rules, DOM diff.
2. yoksa NOT_MEASURED.

**Kabul/kanıt:**
- [ ] JS servisi kapalıyken sistem çalışır.
- [ ] SSRF/browser sandbox testleri.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F04.06.md` + durum kayıtları.

### F04.07 — V1.2 regresyon ve kapanış
**Hedef:** V1.2 regresyon ve kapanış.
**Yapılacak işler:**
1. External API throttle CI mocks schema fixtures cross-browser UI ve maliyet budget testi.

**Kabul/kanıt:**
- [ ] Performans entegrasyonları fail gracefully.
- [ ] ana faz raporu ve onay.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F04.07.md` + durum kayıtları.

## Ana faz kapanış kontrolü
- [ ] Bütün alt fazlar açık kullanıcı onayı ile DONE.
- [ ] Bütün kabul maddeleri için test kanıtı (komut/çıktı ve commit).
- [ ] Güvenlik, veri gizliliği ve performans riskleri değerlendirildi.
- [ ] README ve sistem mimarisi değişen gerçek durumu yansıtıyor.
- [ ] `docs/reports/F04-PHASE-SUMMARY.md` oluşturuldu; kullanıcıya kapsamlı Türkçe rapor sunuldu.
- [ ] Kullanıcının açık ana faz onayı bekleniyor; otomatik sonraki faz yok.

## Bitiş raporu şablonu
Amaç → yapılan alt fazlar → dosyalar/PR/SHA → testler → bulgular → riskler/borç → ürün demo bağlantısı → geri alma planı → değişen maliyet → önerilen sonraki faz → **ONAY BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/roadmap/F05.md -->
## BELGE: `docs/roadmap/F05.md`

# F05 — V2.0: Hesap, domain doğrulama ve Search Console

**Amaç:** Sahipliğe bağlı gelişmiş analiz, izinli gerçek Google verisi.

**Bağımlılık:** Bir önceki ana fazın onaylanmış kapanışı ve `main` ile senkronizasyon.

**Bitiş kapısı:** Her alt faz `READY_FOR_REVIEW` raporuyla durur. Kullanıcı kabul edip gerekirse PR birleştirilmeden diğer alt faza geçilmez. Son alt fazın ardından **ayrı ana faz kapanış raporu** ve onay beklenir.

**Kapsam dışı:** Sonraki sürümlerin özellikleri ve toplu/tek seferde geliştirme.

## Alt fazlar

### F05.01 — Hesap ve RBAC
**Hedef:** Hesap ve RBAC.
**Yapılacak işler:**
1. Email auth e-posta doğrulama password reset MFA opsiyon.
2. roller owner member admin.
3. guest migration.

**Kabul/kanıt:**
- [ ] Yetkisiz site/rapor erişimi reddedilir.
- [ ] güvenli session ve brute force limit.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F05.01.md` + durum kayıtları.

### F05.02 — Domain doğrulama
**Hedef:** Domain doğrulama.
**Yapılacak işler:**
1. DNS TXT token ve HTTP dosya.
2. token expiry replay guard ownership periodic revalidation.

**Kabul/kanıt:**
- [ ] Rastgele domain başkası adına doğrulanamıyor.
- [ ] yanlış host token kabul edilmiyor.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F05.02.md` + durum kayıtları.

### F05.03 — OAuth güvenliği
**Hedef:** OAuth güvenliği.
**Yapılacak işler:**
1. Google OAuth PKCE state scopes encrypted refresh tokens revocation ve consent copy.

**Kabul/kanıt:**
- [ ] Token sadece sunucu tarafında encrypted.
- [ ] izin kaldırmada erişim sonlanır.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F05.03.md` + durum kayıtları.

### F05.04 — Search Analytics entegrasyonu
**Hedef:** Search Analytics entegrasyonu.
**Yapılacak işler:**
1. Clicks impressions CTR avg position breakdown tarih/ülke/cihaz ve API kota/caching.

**Kabul/kanıt:**
- [ ] Eksik veri durumu açık.
- [ ] GSC web traffic ile crawler skoru karışmıyor.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F05.04.md` + durum kayıtları.

### F05.05 — URL Inspection entegrasyonu
**Hedef:** URL Inspection entegrasyonu.
**Yapılacak işler:**
1. Doğrulanmış mülk ve yetki ile indexed status.
2. rate limits GSC metadata source.

**Kabul/kanıt:**
- [ ] Canlı URL testi iddiası yok.
- [ ] özel rapor yetkisiz görülmez.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F05.05.md` + durum kayıtları.

### F05.06 — Geçmiş taramalar ve diff
**Hedef:** Geçmiş taramalar ve diff.
**Yapılacak işler:**
1. Versioned run snapshots score comparison issue first_seen resolved trends ve retention.

**Kabul/kanıt:**
- [ ] Eski kural sürümü değişikliğinin karşılaştırmaya etkisi etiketli.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F05.06.md` + durum kayıtları.

### F05.07 — PDF ve rapor paylaşımı
**Hedef:** PDF ve rapor paylaşımı.
**Yapılacak işler:**
1. PDF render güvenli erişim temporary signed links CSV download.
2. domain ownership opt-in publicity.

**Kabul/kanıt:**
- [ ] PDF içinde özel token yok.
- [ ] özel link süreli.
- [ ] izin kontrolü.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F05.07.md` + durum kayıtları.

### F05.08 — V2.0 üyelik güvenliği kapanışı
**Hedef:** V2.0 üyelik güvenliği kapanışı.
**Yapılacak işler:**
1. OAuth/ACL regression permissions integration quota data export ve deletion tests.

**Kabul/kanıt:**
- [ ] Kritik erişim açığı yok.
- [ ] kapanış raporu + açık kullanıcı kabulü.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F05.08.md` + durum kayıtları.

## Ana faz kapanış kontrolü
- [ ] Bütün alt fazlar açık kullanıcı onayı ile DONE.
- [ ] Bütün kabul maddeleri için test kanıtı (komut/çıktı ve commit).
- [ ] Güvenlik, veri gizliliği ve performans riskleri değerlendirildi.
- [ ] README ve sistem mimarisi değişen gerçek durumu yansıtıyor.
- [ ] `docs/reports/F05-PHASE-SUMMARY.md` oluşturuldu; kullanıcıya kapsamlı Türkçe rapor sunuldu.
- [ ] Kullanıcının açık ana faz onayı bekleniyor; otomatik sonraki faz yok.

## Bitiş raporu şablonu
Amaç → yapılan alt fazlar → dosyalar/PR/SHA → testler → bulgular → riskler/borç → ürün demo bağlantısı → geri alma planı → değişen maliyet → önerilen sonraki faz → **ONAY BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/roadmap/F06.md -->
## BELGE: `docs/roadmap/F06.md`

# F06 — V2.1: İçerik, AI hazırlık, alan modülleri

**Amaç:** Heuristics kanıtlı ve öneri olarak; AI sonuçları yanlış kesinlik taşımayacak.

**Bağımlılık:** Bir önceki ana fazın onaylanmış kapanışı ve `main` ile senkronizasyon.

**Bitiş kapısı:** Her alt faz `READY_FOR_REVIEW` raporuyla durur. Kullanıcı kabul edip gerekirse PR birleştirilmeden diğer alt faza geçilmez. Son alt fazın ardından **ayrı ana faz kapanış raporu** ve onay beklenir.

**Kapsam dışı:** Sonraki sürümlerin özellikleri ve toplu/tek seferde geliştirme.

## Alt fazlar

### F06.01 — İçerik sinyali çıkarımı
**Hedef:** İçerik sinyali çıkarımı.
**Yapılacak işler:**
1. Main content extraction language detection readability near-duplicate and thin-content heuristic.

**Kabul/kanıt:**
- [ ] İçerik ölçümü kaynaklı.
- [ ] sözcük sayısı kendi başına ağır ceza değil.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F06.01.md` + durum kayıtları.

### F06.02 — AI destekli öneri motoru
**Hedef:** AI destekli öneri motoru.
**Yapılacak işler:**
1. İsteğe bağlı LLM provider adapter input minimize prompt injection defense budget citation sources.
2. disable default.

**Kabul/kanıt:**
- [ ] AI yeni teknik hata uyduramaz.
- [ ] evidence-only prompt ve privacy guard test.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F06.02.md` + durum kayıtları.

### F06.03 — AI Search hazırlık kategorisi
**Hedef:** AI Search hazırlık kategorisi.
**Yapılacak işler:**
1. Indexable content text access source transparency entity citation crawler restrictions.
2. Google AI fundamentals.

**Kabul/kanıt:**
- [ ] llms.txt absence kritik hata değil.
- [ ] AI görünürlük garantisi asla yok.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F06.03.md` + durum kayıtları.

### F06.04 — Hreflang ve çok dilli site SEO
**Hedef:** Hreflang ve çok dilli site SEO.
**Yapılacak işler:**
1. Cross-reference reciprocal valid language region x-default canonical same-language checks.

**Kabul/kanıt:**
- [ ] Mixed locale fixture ve conflict suggestions doğrulandı.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F06.04.md` + durum kayıtları.

### F06.05 — E-ticaret / yerel SEO modülleri
**Hedef:** E-ticaret / yerel SEO modülleri.
**Yapılacak işler:**
1. Detected site type rules Product Offer variants faceted pages Business entity ve kontakt consistency.

**Kabul/kanıt:**
- [ ] Blog için stok/schema zorunlu değil.
- [ ] local data izinli kaynakla.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F06.05.md` + durum kayıtları.

### F06.06 — Görsel/video SEO
**Hedef:** Görsel/video SEO.
**Yapılacak işler:**
1. LCP images responsive srcset dimensions alt video poster structured data ve broken assets.

**Kabul/kanıt:**
- [ ] Decorative alt ile missing meaningful alt ayrımı test.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F06.06.md` + durum kayıtları.

### F06.07 — V2.1 içerik kalite ve kapanış
**Hedef:** V2.1 içerik kalite ve kapanış.
**Yapılacak işler:**
1. LLM feedback/eval false-positive audit hallucination check cost reports and industry regression.

**Kabul/kanıt:**
- [ ] Yapay zekâ kapalıyken ürün tamam.
- [ ] kapanış raporu/onay.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F06.07.md` + durum kayıtları.

## Ana faz kapanış kontrolü
- [ ] Bütün alt fazlar açık kullanıcı onayı ile DONE.
- [ ] Bütün kabul maddeleri için test kanıtı (komut/çıktı ve commit).
- [ ] Güvenlik, veri gizliliği ve performans riskleri değerlendirildi.
- [ ] README ve sistem mimarisi değişen gerçek durumu yansıtıyor.
- [ ] `docs/reports/F06-PHASE-SUMMARY.md` oluşturuldu; kullanıcıya kapsamlı Türkçe rapor sunuldu.
- [ ] Kullanıcının açık ana faz onayı bekleniyor; otomatik sonraki faz yok.

## Bitiş raporu şablonu
Amaç → yapılan alt fazlar → dosyalar/PR/SHA → testler → bulgular → riskler/borç → ürün demo bağlantısı → geri alma planı → değişen maliyet → önerilen sonraki faz → **ONAY BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/roadmap/F07.md -->
## BELGE: `docs/roadmap/F07.md`

# F07 — V3.0: Abonelik, ajans, açık API

**Amaç:** Ücretli altyapı ancak ücretsiz ürünün güvenilirliğini bozmadan.

**Bağımlılık:** Bir önceki ana fazın onaylanmış kapanışı ve `main` ile senkronizasyon.

**Bitiş kapısı:** Her alt faz `READY_FOR_REVIEW` raporuyla durur. Kullanıcı kabul edip gerekirse PR birleştirilmeden diğer alt faza geçilmez. Son alt fazın ardından **ayrı ana faz kapanış raporu** ve onay beklenir.

**Kapsam dışı:** Sonraki sürümlerin özellikleri ve toplu/tek seferde geliştirme.

## Alt fazlar

### F07.01 — Plan ve kota motoru
**Hedef:** Plan ve kota motoru.
**Yapılacak işler:**
1. Guest free pro business agency entitlements billing separation quota metering and grace periods.

**Kabul/kanıt:**
- [ ] Plan sınırları API/UI/worker aynı.
- [ ] downgrade veri kaybı yapmıyor.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F07.01.md` + durum kayıtları.

### F07.02 — Ödeme entegrasyonu
**Hedef:** Ödeme entegrasyonu.
**Yapılacak işler:**
1. Yetkili gateway seçimi ADR PCI scope minimal hosted checkout webhook verification refunds trials.

**Kabul/kanıt:**
- [ ] Webhook replay/idempotency test.
- [ ] asla kart verisi saklanmaz.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F07.02.md` + durum kayıtları.

### F07.03 — Ajans ekipleri
**Hedef:** Ajans ekipleri.
**Yapılacak işler:**
1. Multi-tenant organizations roles site permission invited users audit trail tenant isolation.

**Kabul/kanıt:**
- [ ] Tenant IDOR testleri.
- [ ] müşteri A verisi B tarafından görünmez.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F07.03.md` + durum kayıtları.

### F07.04 — White-label raporlar
**Hedef:** White-label raporlar.
**Yapılacak işler:**
1. Doğrulanmış markalama logo tema template safe rendering export.
2. attribution policy.

**Kabul/kanıt:**
- [ ] XSS ve gizli veri sızması yok.
- [ ] watermark doğru.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F07.04.md` + durum kayıtları.

### F07.05 — Public API ve API anahtarları
**Hedef:** Public API ve API anahtarları.
**Yapılacak işler:**
1. Scope-limited keys rotating secrets hashed storage rate limits billing quota OpenAPI documentation.

**Kabul/kanıt:**
- [ ] Cross-tenant API erişimi red.
- [ ] pagination/errors/429 standardı.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F07.05.md` + durum kayıtları.

### F07.06 — Otomatik izleme ve bildirim
**Hedef:** Otomatik izleme ve bildirim.
**Yapılacak işler:**
1. Scheduled scans change detection dedup email opt-in notification templates tenant permissions.

**Kabul/kanıt:**
- [ ] Kota altındayken queue çalışıyor.
- [ ] gürültülü tekrar bildirimi yok.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F07.06.md` + durum kayıtları.

### F07.07 — Sponsor ve reklam sınırları
**Hedef:** Sponsor ve reklam sınırları.
**Yapılacak işler:**
1. Reklam alanı optional no score conflict consent and UX performance budget labeling.

**Kabul/kanıt:**
- [ ] Reklam skoru etkilemez.
- [ ] sponsorlu alanlar açık etiketli.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F07.07.md` + durum kayıtları.

### F07.08 — V3.0 ticari ve güvenlik kapanışı
**Hedef:** V3.0 ticari ve güvenlik kapanışı.
**Yapılacak işler:**
1. Payment reconciliation feature entitlement audit abuse cost SLA summary.

**Kabul/kanıt:**
- [ ] Finansal/tenant kritik sorun yok.
- [ ] faz raporu/onay.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F07.08.md` + durum kayıtları.

## Ana faz kapanış kontrolü
- [ ] Bütün alt fazlar açık kullanıcı onayı ile DONE.
- [ ] Bütün kabul maddeleri için test kanıtı (komut/çıktı ve commit).
- [ ] Güvenlik, veri gizliliği ve performans riskleri değerlendirildi.
- [ ] README ve sistem mimarisi değişen gerçek durumu yansıtıyor.
- [ ] `docs/reports/F07-PHASE-SUMMARY.md` oluşturuldu; kullanıcıya kapsamlı Türkçe rapor sunuldu.
- [ ] Kullanıcının açık ana faz onayı bekleniyor; otomatik sonraki faz yok.

## Bitiş raporu şablonu
Amaç → yapılan alt fazlar → dosyalar/PR/SHA → testler → bulgular → riskler/borç → ürün demo bağlantısı → geri alma planı → değişen maliyet → önerilen sonraki faz → **ONAY BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/roadmap/F08.md -->
## BELGE: `docs/roadmap/F08.md`

# F08 — V4.0: Ölçeklenebilir, çok dilli, olgun platform

**Amaç:** Talep doğrulanınca yatay büyüme; özellik enflasyonu değil.

**Bağımlılık:** Bir önceki ana fazın onaylanmış kapanışı ve `main` ile senkronizasyon.

**Bitiş kapısı:** Her alt faz `READY_FOR_REVIEW` raporuyla durur. Kullanıcı kabul edip gerekirse PR birleştirilmeden diğer alt faza geçilmez. Son alt fazın ardından **ayrı ana faz kapanış raporu** ve onay beklenir.

**Kapsam dışı:** Sonraki sürümlerin özellikleri ve toplu/tek seferde geliştirme.

## Alt fazlar

### F08.01 — İş kuyruğu yatay ölçek
**Hedef:** İş kuyruğu yatay ölçek.
**Yapılacak işler:**
1. Queue abstraction Redis optional distributed locks event idempotency throughput load tests.

**Kabul/kanıt:**
- [ ] Tek node fallback çalışır.
- [ ] concurrency veri tutarsızlığı yaratmaz.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F08.01.md` + durum kayıtları.

### F08.02 — Büyük taramalar ve kaynak yönetimi
**Hedef:** Büyük taramalar ve kaynak yönetimi.
**Yapılacak işler:**
1. Incremental recrawl selective caching URL priority streaming reports object storage optional.

**Kabul/kanıt:**
- [ ] 10k synthetic URL test backpressure.
- [ ] gerçek sitelerde agresif tarama yok.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F08.02.md` + durum kayıtları.

### F08.03 — i18n tam kapsama ve a11y
**Hedef:** i18n tam kapsama ve a11y.
**Yapılacak işler:**
1. tr-TR en-US tam UI/rule/report/email PDF.
2. ICU-like plural locale-aware time/numbers.
3. RTL hazırlığı.

**Kabul/kanıt:**
- [ ] Eksik key CI hatası.
- [ ] iki dilde e2e ve WCAG otomatik test.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F08.03.md` + durum kayıtları.

### F08.04 — Güvenlik sertleştirme ve DR
**Hedef:** Güvenlik sertleştirme ve DR.
**Yapılacak işler:**
1. Secret rotation backups recovery drill dependencies CVE audit intrusion monitoring privacy review.

**Kabul/kanıt:**
- [ ] RPO/RTO tanımlı ve recovery kanıtlı.
- [ ] yetki gözden geçirildi.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F08.04.md` + durum kayıtları.

### F08.05 — Observability ve SLO
**Hedef:** Observability ve SLO.
**Yapılacak işler:**
1. Metrics latency queue depth completion costs retry dashboards alert escalation.

**Kabul/kanıt:**
- [ ] SLO/alert deneyleri ve operasyon runbook test edildi.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F08.05.md` + durum kayıtları.

### F08.06 — Performans, SEO ve ürün kalite
**Hedef:** Performans, SEO ve ürün kalite.
**Yapılacak işler:**
1. SitePortal kendi SEO indeks stratejisi CWV privacy structured data website testing.

**Kabul/kanıt:**
- [ ] Kendi site deneyimi kontrollü.
- [ ] rapor URL default noindex.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F08.06.md` + durum kayıtları.

### F08.07 — V4.0 genel kabul
**Hedef:** V4.0 genel kabul.
**Yapılacak işler:**
1. Tüm ürün gereksinimleri traceability matrix regression security docs and maintenance plan.

**Kabul/kanıt:**
- [ ] Son rapor, kullanıcının açık onayı ve release tag olmadan done değil.
- [ ] İlgili birim/entegrasyon/güvenlik/regresyon test çıktıları rapora eklendi.
- [ ] Kod/doküman Türkçe öncelikli ve i18n disiplininde.
- [ ] `README`, `CURRENT_STATUS`, `project-status.json`, `CHANGELOG`, rapor ve PR/commit güncellendi.
- [ ] Kullanıcıya alt faz raporu gönderildi ve **DUR** kuralı uygulandı.

**Teslim dosyaları:** Uygulama kodu/fixture/test + `docs/reports/F08.07.md` + durum kayıtları.

## Ana faz kapanış kontrolü
- [ ] Bütün alt fazlar açık kullanıcı onayı ile DONE.
- [ ] Bütün kabul maddeleri için test kanıtı (komut/çıktı ve commit).
- [ ] Güvenlik, veri gizliliği ve performans riskleri değerlendirildi.
- [ ] README ve sistem mimarisi değişen gerçek durumu yansıtıyor.
- [ ] `docs/reports/F08-PHASE-SUMMARY.md` oluşturuldu; kullanıcıya kapsamlı Türkçe rapor sunuldu.
- [ ] Kullanıcının açık ana faz onayı bekleniyor; otomatik sonraki faz yok.

## Bitiş raporu şablonu
Amaç → yapılan alt fazlar → dosyalar/PR/SHA → testler → bulgular → riskler/borç → ürün demo bağlantısı → geri alma planı → değişen maliyet → önerilen sonraki faz → **ONAY BEKLENİYOR**.




---

<!-- KAYNAK BELGE: docs/reference/SOURCES.md -->
## BELGE: `docs/reference/SOURCES.md`

# Resmî referans envanteri (2026-11-03 itibarıyla)

Bu envanter başlangıç plan paketinden alınan **birincil/otoritatif kaynak** adaylarını ve paketteki uygulama notlarını korur. Paket hazırlanırken yapıldığı belirtilen kontrol bu teslimin yeniden doğrulaması değildir. F00.02 yalnız belge bağlantılarının biçimini ve yerel referans düzenini denetler; dış sayfaların erişimi/içeriği bu aşamada yeniden kontrol edilmedi. Dış dokümanların içeriği değişebilir; AI ajanı bir SEO kuralını kodlamadan önce ilgili güncel sayfayı yeniden okumalı, kanıt tarihi ve gerekirse kural sürümünü güncellemelidir. S1-S15 kural kataloğundaki `reference` alanıdır.

| ID | Kaynak | Resmî URL | Uygulama kararı |
|---|---|---|---|
| S1 | Google Search crawling/indexing + HTTP | https://developers.google.com/search/docs/crawling-indexing ; https://developers.google.com/crawling/docs/troubleshooting/http-status-codes | 200 indeks garantisi değildir; soft 404/redirect ayrı ölçülür |
| S2 | Canonical resmi rehberi | https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls | Canonical güçlü sinyal ama zorunlu kesin komut değildir |
| S3 | Robots ve noindex | https://developers.google.com/search/docs/crawling-indexing/robots/intro ; https://developers.google.com/search/docs/crawling-indexing/special-tags | robots taramayı yönetir, indeks dışına çıkarma yöntemi değildir |
| S4 | Sitemap | https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap | Sitemap başına 50.000 URL veya 50MB sıkıştırılmamış sınır |
| S5 | JavaScript SEO | https://developers.google.com/search/docs/crawling-indexing/javascript/javascript-seo-basics | Crawl/render/index aşamaları ayrı, SSR ideal |
| S6 | Title, snippets, meta tags | https://developers.google.com/search/docs/appearance/title-link ; https://developers.google.com/search/docs/appearance/snippet ; https://developers.google.com/search/docs/crawling-indexing/special-tags | Sabit title/meta karakter sınırı yok, meta keywords puansız |
| S7 | CWV thresholds | https://web.dev/articles/defining-core-web-vitals-thresholds | P75 LCP<=2500ms INP<=200ms CLS<=0.1 iyi |
| S8 | PageSpeed + CrUX API | https://developers.google.com/speed/docs/insights/v5/get-started ; https://developer.chrome.com/docs/crux/api | PSI lab ayrıdır; CrUX son 28 günün saha verisi |
| S9 | Google structured data | https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data | Schema.org syntax ile Google rich results uygunluğu ayrı |
| S10 | W3C WCAG 2.2 | https://www.w3.org/TR/WCAG22/ | Otomatik tarama tam uygunluk sertifikası değildir |
| S11 | People-first content ve Google spam | https://developers.google.com/search/docs/fundamentals/creating-helpful-content ; https://developers.google.com/search/docs/essentials/spam-policies | E-E-A-T tekil sayısal sıralama puanı değildir; scaled content abuse yasak |
| S12 | Google AI features / AEO rehberi | https://developers.google.com/search/docs/appearance/ai-features ; https://developers.google.com/search/docs/fundamentals/ai-optimization-guide | Özel AI markup veya llms.txt Google için zorunlu değil; temel SEO geçerli |
| S13 | OWASP SSRF cheat sheet | https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html | Outbound URL/DNS/redirect/egress korumaları zorunlu |
| S14 | GSC Search Analytics API | https://developers.google.com/webmaster-tools/v1/searchanalytics | Yetkiyle gerçek CTR/clicks/impressions; tüm satırlar garanti değil |
| S15 | URL Inspection API | https://developers.google.com/webmaster-tools/v1/urlInspection.index/inspect | Yalnız indekste bilinen durum; canlı URL test değil |

## Tamamlayıcı resmî kaynaklar
- Google güncel generative AI rehberi yayını (15 Mayıs 2026): https://developers.google.com/search/blog/2026/05/a-new-resource-for-optimizing
- Google Search Essentials: https://developers.google.com/search/docs/essentials
- Google robots.txt teknik belirtim: https://developers.google.com/search/docs/crawling-indexing/robots/robots_txt
- Google indexing API sınırı: https://developers.google.com/search/apis/indexing-api/v3/using-api
- GitHub branch protection: https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches
- OpenAPI Specification: https://spec.openapis.org/oas/latest.html
- OAuth 2.0 PKCE RFC 7636: https://www.rfc-editor.org/rfc/rfc7636
- RFC 9309 Robots Exclusion Protocol: https://www.rfc-editor.org/rfc/rfc9309
- RFC 3986 URI: https://www.rfc-editor.org/rfc/rfc3986
- Sitemaps protocol: https://www.sitemaps.org/protocol.html
- PHP supported versions: https://www.php.net/supported-versions.php (uygulama başlangıcında yeniden kontrol)
- KVKK resmî kurum: https://www.kvkk.gov.tr/ (çerez, aydınlatma ve veri güvenliği metinleri hukuk incelemesi ister)

## Referans kullanım disiplini
1. Her rule'un `reference` alanı URL'ye ve `checked_at` tarihine bağlanmalı; ilgili pasaj ve değerlendirme notu saklanmalı (copyright uzun alıntı kopyalanmaz).
2. **Google kuralı**, **ürün heuristiği**, **erişilebilirlik önerisi**, **genel site sağlığı**, **yetkili dış API gözlemi** ayrı türlerdir.
3. Ürün önerisi değişirse kural sürümünü artır, fixture ve scores'ı güncelle, ADR ekle.
4. Arama motorunun özel sıralama sistemi, indeksleme garantisi veya AI yanıtı kesin olarak bildirilmez.
5. İleri sürümlerde 6 ayda bir kaynak tazelik taraması ve değişiklik raporu yapılır.

## Bakım bağlantıları
Kural uygulamadan önceki [referans disiplini](docs/reference/SOURCES.md#referans-kullanım-disiplini) geçerlidir. [Belge haritası](docs/README.md), [bakım rehberi](docs/process/MAINTENANCE.md) ve [kural CSV](docs/requirements/rules-catalog.csv) kaynak kimliklerini birlikte izler.




---

<!-- KAYNAK BELGE: docs/README.md -->
## BELGE: `docs/README.md`

# Belge haritası

Güncel durum: [CURRENT_STATUS](CURRENT_STATUS.md) ve [project-status.json](project-status.json). Başlangıç: [START_HERE](START_HERE.md). Bağlayıcı sınırlar: [AGENTS](AGENTS.md).

| Alan | Belgeler | Amaç |
|---|---|---|
| Fazlar | [Yol haritası](docs/roadmap/ROADMAP.md), [F00](docs/roadmap/F00.md), [F01](docs/roadmap/F01.md), [F02](docs/roadmap/F02.md), [F03](docs/roadmap/F03.md), [F04](docs/roadmap/F04.md), [F05](docs/roadmap/F05.md), [F06](docs/roadmap/F06.md), [F07](docs/roadmap/F07.md), [F08](docs/roadmap/F08.md) | Sıra, bağımlılık ve kabul kriterleri |
| Ürün | [Kapsam](docs/requirements/PRODUCT.md), [özellik matrisi](docs/requirements/FEATURE_MATRIX.md), [öncelikler](docs/requirements/PRIORITIES.md), [UX](docs/requirements/UX.md), [hatalar](docs/requirements/ERROR_STATES.md) | Planlanan davranış; tamamlanmış ürün iddiası değil |
| SEO | [Kurallar](docs/requirements/RULES.md), [CSV katalog](docs/requirements/rules-catalog.csv), [skorlama](docs/requirements/SCORING.md), [referanslar](docs/reference/SOURCES.md) | Kaynak, kanıt, uygulanabilirlik ve UNKNOWN |
| Mimari | [Sistem](docs/architecture/SYSTEM.md), [crawler](docs/architecture/CRAWLER.md), [güvenlik](docs/architecture/SECURITY.md), [DB](docs/architecture/DATABASE.md), [API](docs/architecture/API.md), [i18n](docs/architecture/I18N.md), [entegrasyon](docs/architecture/INTEGRATIONS.md), [operasyon](docs/architecture/OPERATIONS.md) | Başlangıç taslakları ve sınırlar |
| Süreç | [İş akışı](docs/process/WORKFLOW.md), [bakım](docs/process/MAINTENANCE.md), [QA](docs/process/QA.md), [rapor şablonu](docs/process/REPORT_TEMPLATE.md), [kararlar](docs/process/DECISIONS.md), [açık sorular](docs/process/OPEN_QUESTIONS.md) | Tek alt faz, kalite, devir ve onay |
| GitHub/yayın | [GitHub](docs/operations/GITHUB.md), [gizlilik](docs/operations/PRIVACY.md), [sürüm](docs/operations/RELEASE.md) | Repo, veri yaşam döngüsü ve yayın kapıları |
| Kanıt | [Rapor dizini](docs/reports/README.md), [CHANGELOG](CHANGELOG.md) | Gerçek iş, komut çıktısı, commit, PR ve kabul |
| Örnekler | [OpenAPI](schemas/openapi-draft.yaml), [kural seed](schemas/rule-registry.seed.json), [bulgu şeması](examples/finding.schema.json), [DEMO yanıt](examples/analysis-response.example.json), [DEMO rapor](examples/phase-report.example.md) | Taslak/örnek; gerçek analiz kanıtı değil |
| Çeviri | [tr-TR](locales/tr-TR.example.json), [en-US](locales/en-US.example.json) | Örnek anahtarlar; çeviri motoru henüz yok |

[MASTER_PLAN_TR](MASTER_PLAN_TR.md) kaynak belgelerden üretilen okunabilir kopyadır. Kaynağı düzenle, sonra yeniden üret; master dosyasını tek başına değiştirme.




---

<!-- KAYNAK BELGE: docs/process/MAINTENANCE.md -->
## BELGE: `docs/process/MAINTENANCE.md`

# Türkçe belge bakım ve teslim rehberi

## Durumun tek kaynağı
[project-status.json](project-status.json) makine kaydıdır. [CURRENT_STATUS](CURRENT_STATUS.md) ve [README](README.md) aynı gerçek durumu kısa Türkçe metinle gösterir. [START_HERE](START_HERE.md) kalıcı okuma sırasıdır; içine eskimeye açık aktif alt faz numarası gömme.

| Alan | Anlam |
|---|---|
| `current_phase`, `current_subphase`, `status` | Üzerinde çalışılan veya kabul bekleyen tek alt faz |
| `last_approved_phase`, `last_approved_subphase` | Açık kullanıcı kabulü; ana faz kabulü ayrı |
| `approvals` | Her DONE için `approved_at` ve `approval_reference`; varsa ayrı merge izni |
| `start_authorization` | Yalnız belirtilen alt faza başlama izni; teslim kabulü değildir |
| `branch`, `pr_url`, `commit_sha` | Gerçek GitHub teslimi; `commit_sha_scope` kanıt commit'ini açıklar |
| `previous_delivery` | Önceki kabul edilmiş PR ve gerçek merge SHA |
| `last_report`, `test_status`, `ci_status` | Son rapor, ilgili test ve CI'nin gerçek durumu |
| `blockers`, `open_questions` | Aktif iş engelleri ile sonraki geliştirmeye ait açık kararlar |
| `next_subphase`, `next_action` | Sonraki aday iş ve izin kapısı; otomatik başlama yetkisi değil |

Yeni alt fazda eski `commit_sha`/`pr_url` kopyalama; oluşturulana kadar null yaz. Kabul edilmemiş teslim `READY_FOR_REVIEW` kalır. `DONE`, push/merge ile otomatik oluşmaz. Geçmiş raporlar tarihsel kanıttır; sonraki kabul/merge gelişmelerini tarihli ek kayıtla belirt.

## Belge değişikliği sırası
1. [İş akışı](docs/process/WORKFLOW.md), aktif plan ve [açık kararları](docs/process/OPEN_QUESTIONS.md) oku. Kapsam dışında mimari karar alma.
2. Kaynakları UTF-8, LF ve göreli Markdown bağlantılarıyla düzenle. Sözleşme anahtarları gerektiğinde İngilizce kalır; bakım açıklamaları Türkçedir.
3. README ilk 15 satırı, CURRENT_STATUS, JSON, CHANGELOG, faz durumu ve `docs/reports/Fxx.yy.md` kaydını birlikte güncelle.
4. Ana şartnameyi üret:

```text
python scripts/build_master_plan.py
```

5. Yalnız ilgili dosyaları Git'e hazırla (`git add <ilgili yollar>`). Sonra manifesti takip edilen dosyaların mevcut byte'larından üret:

```text
python scripts/update_manifest.py
```

6. Belge ve envanter kontrollerini çalıştır; gerçek çıktıları rapora bağla:

```text
python scripts/verify_blueprint.py
python scripts/test_document_links.py
python scripts/verify_inventory.py
git diff --cached --check
```

`verify_blueprint` yerel dosya ve başlık bağlantılarını (MASTER dahil), faz kayıtlarını ve JSON örneklerini denetler. Dış URL biçimini kontrol eder; internetten sayfa erişimi veya içerik onayı vermez. `verify_inventory` manifest, CSV/registry, onay sırası, TR/EN ve sınırlı secret kalıplarını denetler. İkisi de uygulama/SSRF testi değildir. CI F00.04 kapsamındadır.

7. Log/rapor değişirse manifesti yeniden üret, kontrolleri tekrar çalıştır ve dosyaları tekrar hazırla. Logu kendi kontrolü sırasında üzerine yazma: çıktıyı geçici dosyada yakala, son logu kaydet, manifesti yenile, son kontrolü tekrar çalıştır.
8. Commit ve push yap; gerçek PR URL'sini ve ilk kanıt commit'ini kaydet. Dosya kendi son commit SHA'sını içeremez: `commit_sha` doğrulanmış içerik commit'idir; daha yeni metadata commit'i PR head'de olabilir. Son SHA kullanıcı raporunda ve PR açıklamasında verilir.
9. [Şablonla](docs/process/REPORT_TEMPLATE.md) Türkçe rapor sun ve **DUR**. Kullanıcı kabulü, merge ve sonraki alt faz iznini ayrı kaydet.

## Devir testi
F00.02 için sohbet geçmişi paylaşılmayan ayrı ajana yalnız etkin repo yolu verilir. Ajan [iş akışındaki](docs/process/WORKFLOW.md) beş soruyu dosya/alan kanıtıyla yanıtlar. Beklenen cevaplar görev metnine eklenmez. Gerçek yanıt, incelenen commit/dosya durumu ve sonuç rapora kaydedilir. Sayım gibi değişken sonuçları ayrı raporlara kopyalamak yerine gerçek komut günlüğüne bağla. Devir ajanı başlatılmadan önce commit/push yap; inceleme boyunca dosyaları değiştirme. Belirsizlik varsa düzelt ve yeni sabit kayıt üzerinde yeniden denetle. ZIP yoksa bunu açıkça belirt; korunmuş manifest/envanter kullanılır.

## Korunacaklar
`BUNDLE_INFO.json` ile F00.01 özgün manifest/envanter üretim anına aittir. Etkin `FILE_MANIFEST.sha256` her teslimde yenilenir. `siteportal_seo_masterplan/` eski yerel pakettir; güncel kaynak değildir. Gerçek `.env`, token ve özel veri ekleme. SEO kuralı kodlanmadan dış referansı yeniden oku ve kontrol tarihini kaydet. [Açık sorular](docs/process/OPEN_QUESTIONS.md) kullanıcı kararı olmadan kapatılmaz.




---

<!-- KAYNAK BELGE: docs/process/OPEN_QUESTIONS.md -->
## BELGE: `docs/process/OPEN_QUESTIONS.md`

# Açık sözleşme soruları

Bu kayıt F00.01 envanterindeki farkları görünür tutar; mimari karar vermez. `blockers` aktif işi durduran sorunlardır. Aşağıdakiler F00.02 belge düzenlemesini engellemez; belirtilen uygulama başlamadan kullanıcıyla karara bağlanmalıdır.

| ID | Durum | Fark / kanıt | Karar kapısı |
|---|---|---|---|
| Q-001 | OPEN | [API](docs/architecture/API.md) `data_source` ve `evidence[]`; [bulgu şeması](examples/finding.schema.json) `source` ve nesne `evidence` kullanıyor | API/kural implementasyonundan önce tek Finding sözleşmesi; F00.03'te ele alınacak |
| Q-002 | OPEN | [Crawler](docs/architecture/CRAWLER.md) `jobs`; [veritabanı](docs/architecture/DATABASE.md) `analysis_jobs` diyor | Migration/queue kodundan önce tablo adı; F00.03'te ele alınacak |
| Q-003 | OPEN | [AGENTS](AGENTS.md) tr/en tamamlanmasını, [I18N](docs/architecture/I18N.md) minimum fallback'i, [F08](docs/roadmap/F08.md) tam kapsamı tarif ediyor | İlk UI yazılmadan sürüm bazında en-US kapsamı; F00.03'te ele alınacak |

İlk paket aktarımı/yerleştirme örtüşmesi durum ayrımıyla açıklanmıştır: F00.01 paketi envantere aldı; F00.02 rehber, bakım, bağlantı ve devir doğrulamasını yürütür. Sonraki fazların kapsamı değişmez.

Yeni karar gerektiğinde [DECISIONS](docs/process/DECISIONS.md) formatını kullan; kullanıcı onayı, gerekçe, güvenlik/maliyet etkisi, ilgili faz ve geri dönüşü kaydet. F00.03 başlamadan çözüm varsayma.
