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
