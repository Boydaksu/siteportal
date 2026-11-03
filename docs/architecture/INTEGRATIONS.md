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
