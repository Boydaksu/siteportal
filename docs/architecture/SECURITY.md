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

## F00.03 tehdit ve tedarik zinciri kaydı
[Varlıklar, güven sınırları ve T01–T15 matrisi](THREAT_MODEL.md) her riskin sorumlu modülünü, test fazını ve kalan riskini gösterir. [Bağımlılık politikası](../process/DEPENDENCIES.md) kesin sürüm/lock, lisans, advisory ve plugin/script kabul kapısını tanımlar. Bunlar plan kayıtlarıdır; runtime veya audit testleri henüz geçilmiş değildir.
