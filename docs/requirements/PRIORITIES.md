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
