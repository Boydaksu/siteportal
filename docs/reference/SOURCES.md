# Resmî referans envanteri (2026-11-03 itibarıyla)

Bu bağlantılar yol haritası ve kural kataloğu için **birincil/otoritatif kaynak** olarak kontrol edilmiştir. Dış dokümanların içeriği değişebilir; AI ajanı bir SEO kuralını kodlamadan önce ilgili güncel sayfayı yeniden okumalı, kanıt tarihi ve gerekirse kural sürümünü güncellemelidir. S1-S15 kural kataloğundaki `reference` alanıdır.

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
