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
Tek URL anonim tarama; URL doğrulama; robots/sitemap ilk kontrol; HTTP/canonical/meta/headings/images/links; rule engine; teknik puan + ölçüm kapsamı; açıklamalı kanıt, sınırlı cache, progress, kısa ömürlü özel rapor, HTTP API, temel admin, Türkçe UI, uygulanan özelliklerde tam İngilizce çeviri anahtarları, fail-closed SSRF, queue/worker/cron, test/CI ve dokümantasyon.

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
