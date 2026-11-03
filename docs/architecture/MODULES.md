# Modül sınırları ve veri akışı

Bu belge [ADR-001](../adr/ADR-001-stack.md) ve mevcut [SYSTEM](SYSTEM.md) katmanlarını açıklar; dizin veya PHP sınıfı oluşturmaz. Somut interface imzaları ilgili F01/F02 alt fazlarında testlerle kesinleşir.

## İzinli bağımlılık yönü
`HTTP/CLI girişleri → Application kullanım senaryoları → Domain sözleşmeleri`. Infrastructure, Domain/Application sözleşmelerini uygular; yalnız bootstrap/composition noktası somut adaptörleri bağlar. Domain, HTTP framework'ü/PDO/provider/browser detaylarına bağımlı olmaz. Eşdeğer bağımlılık yönü korunmadan yeni katman atlanmaz.

| Sınır / konum | Sorumluluk | İzinli bağımlılık | Yapmaması gereken |
|---|---|---|---|
| public / Controllers | HTTP girdisi, auth/CSRF/rate kararı, kullanım senaryosu çağrısı ve doğru HTTP cevabı | Core Request/Response, Application | Doğrudan dış HTTP, SQL, puan veya parser işi |
| app/Core | Config, router, hata/request-id, DB bağlantı altyapısı, logging, auth/i18n temelleri | PHP platformu, denetlenmiş küçük yardımcılar | SEO kuralı, crawler domain mantığı veya müşteri rapor verisi bilmek |
| SeoAnalyzer/Domain | Analysis, PageSnapshot, Finding, Rule ve değer nesneleri; durum/geçerlilik kuralları | Dilin temel tipleri ve kendi sözleşmeleri | Ağ, PDO, dosya sistemine serbest erişim, locale'e göre skor değiştirme |
| SeoAnalyzer/Application | CreateAnalysis, ScanPage, ReadReport gibi iş akışı ve yetkili kullanım senaryoları | Domain ve açık repository/queue/fetch sözleşmeleri | Provider payload'ını UI'ya sızdırmak, kontrol sınırını atlamak |
| SeoAnalyzer/Infrastructure | SafeHttpClient, robots/sitemap/parser, PDO repository, DB queue/cache adaptörleri | Domain/Application sözleşmeleri; en az ayrıcalıklı I/O | Redirect/DNS/byte/robots kontrollerini devre dışı bırakmak |
| SeoAnalyzer/Rules | Uygulanabilirlik, normalize kanıt, deterministik PASS/WARN/FAIL/UNKNOWN/N/A | Snapshot ve kural bağlamı | HTTP/DB çağrısı; ölçülmeyene skor cezası; kapsam dışı site-geneli kesinlik |
| SeoAnalyzer/Integrations | PSI/CrUX/GSC payload normalizasyonu, provider kota/cache/hata eşlemesi | Yetkili secret erişimi, ortak güvenli HTTP, provider allowlist | Token'ı controller/UI/loga vermek, serbest provider URL'si kullanmak |
| Views / resources/lang | Escaped sunum, çeviri anahtarları, a11y ve locale biçimleme | Yetkili salt okunur rapor modeli, translator | Ham hedef HTML, SQL/fetch, bulgu üretimi veya gizli anahtar |
| app/Shared | Birden fazla gerçek modülün ihtiyaç duyduğu kanıtlanmış ortak sözleşmeler | Core ve genel tipler | SEO iç kurallarını erken “genel framework” haline getirmek |
| bin worker/scheduler | Sınırlı süreli lease/claim, iptal/retry/dead-letter ve kapanış | Application iş akışı, minimum DB/ağ yetkisi | Web session taklidi, sınırsız döngü veya sınırsız retry |

## Başlıca akışlar ve sahiplik
1. Kullanıcı URL'si güvenilmeyen veri olarak kalır; analiz kaydı ve queue işlemi yetki/kota/idempotency kontrolünden geçer. Ağ çağrısı queue transaction'ı dışında yapılır.
2. Worker SafeHttpClient/robots üzerinden aldığı sınırlı veriden PageSnapshot üretir. Kural motoru yalnız snapshot/ölçüm bağlamını değerlendirir. Ek ölçüm gerekiyorsa yeni ağ yolu açılmaz; Application bütçeli güvenli adaptörü çağırır.
3. Finding kaynak/tarih/kapsam/kanıt taşır. ReportBuilder erişim kontrolünü geçmiş salt okunur modeli UI/API'ye verir. Skor, sponsor/plan/locale veya LLM tarafından değiştirilmez.
4. Veritabanı yazma sahibi repository/Application akışıdır. Tenant/owner yetkisi yalnız controller'da değil kullanım senaryosu ve veri erişim sınırında da kontrol edilir. Public ID tek başına yetki değildir.
5. Yeni SitePortal aracı ileride ayrı Modules altına gelir; SeoAnalyzer iç tablolarına veya kural sınıflarına doğrudan bağlanmaz. API/queue olay sözleşmesi gerekiyorsa ayrıca belgelenir; şu an event bus veya mikroservis eklenmez.

## Değişiklik ve doğrulama kapıları
Bu matris kod sınırının kabul hedefidir, uygulanmış mimari testi değildir. F01.01 bootstrap/i18n; F01.03–04 tek HTTP çıkışı/robots; F01.05 queue; F01.06 auth/kota/log; F02.03–05 kural/score sözleşmesi testleri sınırları doğrular. Yeni I/O adaptörü güvenlik incelemesi, bağımlılık kaydı ve [tehdit modeli](THREAT_MODEL.md) güncellemesi ister. Q-001–003 için [onaylı ADR kayıtları](../adr/README.md) bağlayıcıdır; kalan [açık kayıtlar](../process/OPEN_QUESTIONS.md) varsayımla kapatılmaz.
