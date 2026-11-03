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
