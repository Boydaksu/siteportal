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
