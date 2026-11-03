# Başlangıç tehdit modeli ve güven sınırları

Tarih: 2026-11-03. Durum: DESIGN_REVIEW; kontroller planlanmıştır, implementasyon veya pentest geçişi değildir. Kaynaklar: [SECURITY](SECURITY.md), [CRAWLER](CRAWLER.md), [API](API.md), [MODULES](MODULES.md), [OWASP threat modeling](https://cheatsheetseries.owasp.org/cheatsheets/Threat_Modeling_Cheat_Sheet.html) ve [OWASP SSRF](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html). Yaklaşım OWASP'ın sistem/veri akışı/güven sınırı/tehdit/önlem/yeniden inceleme yapısını kullanır; STRIDE başlıkları düşünme yardımıdır, güvenlik sertifikası değildir.

## Varlıklar, aktörler ve varsayımlar
Korunacaklar: özel rapor ve sahiplik bilgisi; kullanıcı/tenant verisi; kimlik/session/OAuth/API anahtarları; worker/DB/egress kaynakları; kanıt, kaynak ve skor bütünlüğü; maliyet bütçesi ve audit kayıtları. Aktörler: anonim URL gönderen, yetkili site sahibi/ajans üyesi, kötü niyetli hedef site veya DNS/provider cevabı, hesabı ele geçirilmiş kullanıcı/admin, paket sağlayıcısı. Hedef sayfa/HTTP/HTML/XML/JSON-LD, URL/redirect/DNS, provider payload'ı ve kullanıcı kanıt parametreleri güvenilmeyen veridir.

Henüz production/domain/provider hesabı yok. Tek VPS, private DB ve minimum yetkili ayrı web/worker süreçleri tasarım varsayımıdır; ortam doğrulaması değildir. Gerçek deployment yeni veri akışı/sınır eklerse model ve kontroller güncellenir.

## Güven sınırları ve akış
```text
TB1 Internet/kullanıcı -> HTTPS giriş/API -> auth/kota/idempotency -> Application
TB2 Application -> yetkili repository/DB queue -> sınırlı CLI worker
TB3 Worker/Integration -> SafeHttpClient + robots + egress -> hedef Internet/DNS/provider
TB4 Güvenilmeyen hedef içerik -> byte/parser sınırı -> normalize snapshot -> Rules/Score
TB5 Rapor deposu -> owner/tenant/süreli paylaşım kontrolü -> escaped UI/API/export
TB6 CI/paket kaynağı -> lisans/audit/lock incelemesi -> testli build -> onaylı yayın
TB7 Gelecek izole browser / OAuth / LLM -> ayrı egress, veri ve secret sınırı (şimdilik kapalı)
```
DB'nin Internet'e açık olmaması hedeflenir; TB2'nin varlığı yetki denetimini kaldırmaz. Provider allowlist veya DNS kontrolü tek başına güvenlik garantisi değildir; bağlanılan IP, redirect ve TLS doğrulaması da gerekir. Renderer alt kaynakları dahil bütün egress sınırı korunur.

## Tehdit ve kabul matrisi
STRIDE: S=kimlik taklidi, T=veri değiştirme, R=inkâr/izlenebilirlik, I=bilgi ifşası, D=hizmet engeli, E=yetki yükseltme. P0 kamuya açılmayı engelleyen, P1 ilgili sonraki özelliğin yayını öncesi giderilmesi gereken önceliktir; sayısal risk puanı veya giderilmiş açık iddiası değildir. Öncelik ilgili işlevin yayın kapısına aittir; henüz olmayan gelecek özellikler kapalı kalır ve V1 kapsamına taşınmaz.

| ID / tür / öncelik | Giriş, varlık ve kötüye kullanım | Planlanan önlem / sorumlu sınır | Doğrulama ve faz kapısı | Kalan risk / şimdi durum |
|---|---|---|---|---|
| T01 / I,E / P0 | TB3 kullanıcı URL/redirect/DNS ile private, loopback veya metadata erişimi | SafeHttpClient: şema/port/userinfo/IP/CNAME kontrolü, DNS pin, her hop yeniden kontrol, TLS, egress deny | F01.03–04 kontrollü IPv4/IPv6/rebinding/public→private fixture matrisi; gerçek iç ağa saldırı yok | Parser farkı ve değişen ağlar; NOT_IMPLEMENTED |
| T02 / D / P0 | TB1/3 tarama flood, crawl trap, provider maliyeti | Application/worker: guest/site/global kota, per-host=1, süre/bytes/depth/redirect cap, backoff/Retry-After, iptal | F01.04–06 ve F03.05; 429/503 ve bütçe/iptal TEST senaryoları | Dağıtık abuse ve hedef tepkisi; NOT_IMPLEMENTED |
| T03 / T,D,I / P0 | TB4 XXE, sıkıştırma/XML bombası, bozuk HTML/JSON-LD | Parser dış entity/network kapalı, sıkıştırılmış/açılmış boyut ve süre sınırı, toleranslı parse, ham içerik saklama yok | F01.04, F02.02, F03.02; malformed/oversize/XXE TEST girdileri | Yeni format/parser açığı; NOT_IMPLEMENTED |
| T04 / I,E / P0 | TB5 IDOR, tahmin edilen ID veya tenant karışmasıyla özel rapor erişimi | Application/repository owner/tenant ACL; süreli paylaşım hash/expiry/revoke; noindex yalnız ek görünürlük önlemi | F02.07, F05.01/07, F07.03/05 guest/owner/tenant/expired-token izin matrisi | Ele geçirilmiş yetkili hesap; NOT_IMPLEMENTED |
| T05 / T,I / P0 | TB4/5 kötü niyetli HTML/URL/metin ile XSS, CSV veya log injection | View/export/logger bağlama uygun escaping, CSV formül koruması, maskeleme/uzunluk sınırı | F01.06, F02.06, F03.06 ve PDF fazı; saldırgan TEST kanıt/metinleri | Yanlış escape bağlamı; NOT_IMPLEMENTED |
| T06 / S,E / P0 | TB1 state-changing istek/oturum ele geçirme | Core CSRF, güvenli session cookie, auth throttling, password hash, admin minimum yetki | F01.06/F05.01; token/session/CSRF ve auth rate TEST | Phishing/cihaz ele geçirme; NOT_IMPLEMENTED |
| T07 / T,D / P0 | TB2 aynı job iki worker tarafından işlenir; lease/retry fırtınası | Queue atomik claim/transaction, lease, idempotency, bounded retry/dead-letter; ağ transaction dışında | F01.05 iki worker/race/crash/lease expiry TEST, MySQL gerçek sürümünde lock davranışı | Clock/DB kesintisi; NOT_IMPLEMENTED |
| T08 / T,E / P0 | TB1/2 URL/parametre SQL veya shell girdisine dönüşür | PDO prepared statement, allowlist query alanları, kullanıcı verisiyle shell komutu kurmama | F01.02/06 ve API fazı injection TEST'leri | Yeni sorgu/komut yolu; NOT_IMPLEMENTED |
| T09 / I,R / P0 | TB2/5 log/backup/export veya repo ile secret/PII sızıntısı | Secret env/store, log redaction, webroot dışı storage, minimum saklama/yetki, private rapor | F01.06, F02.07, F05 OAuth/export; log/retention/delete/backup erişim TEST | Operatör/backup yetkisi ve gerçek hukuki süreler açık; NOT_IMPLEMENTED |
| T10 / S,I,E / P1 | TB7 OAuth state/scope/token veya domain doğrulama tekrar kullanımı | State+PKCE, scope minimumu, şifreli refresh token, challenge hash/expiry/host kontrolü, revoke | F05.02–05; replay/state/scope/revoke/ACL TEST | Provider hesabı ve consent hatası; ilgili özellik kapalı |
| T11 / T,R / P0 | TB4/5 kanıt yokken başarı/0 puan, lab-field karışması veya sahte indeks garantisi | Domain rule_version/source/time/scope, UNKNOWN/N/A, deterministik skor, provider lab-field ayrımı | F02.03–05/F04.01–03; no-data/outage/score fixture ve source review | Kaynak değişimi/false positive; NOT_IMPLEMENTED |
| T12 / T,E,I / P0 | TB6 kötü niyetli veya terk edilmiş paket/plugin/build script | Kesin sürüm+lock, lisans/advisory incelemesi, plugin/script izni, least privilege, gizli anahtar içermeyen test | Her yeni bağımlılık PR'ı ve F00.04 CI tasarımı; envanter/lock/source diff | Sıfır gün/upstream hesabı ele geçirme; paket henüz yok |
| T13 / I,E,D / P1 | TB7 browser alt kaynağı SSRF veya renderer kaçışı | Ayrı kullanıcı/izole worker, private egress deny, dosya/CPU/RAM/zaman sınırı, default kapalı | F04.06 renderer açık/kapalı ve alt kaynak/private ağ TEST | Browser sıfır gün; renderer kapalı |
| T14 / T,I,D / P1 | TB7 hedef içerikle LLM prompt injection, PII/maliyet sızıntısı, uydurma bulgu | Opt-in/default kapalı, minimum veri ve evidence-only açıklama, bütçe, skor üretimine yetki yok | F06.02/07 adversarial TEST/eval, AI kapalı tam temel işlev | Model değişkenliği; LLM kapalı |
| T15 / R,T / P0 | TB1/2/6 kritik işlemin izi yok veya audit log silinebilir | Request-id, maskeli actor/action/outcome/time; minimum ayrı yetki, retention ve operasyon gözden geçirme | F01.06/F05.08; izlenebilirlik, redaction, yetki ve hata TEST | Gerçek retention/admin politikası açık; NOT_IMPLEMENTED |

## Gözden geçirme ve risk kabulü
Her yeni dış istek yolu, export, auth/tenant, paket, browser veya provider için etkilenen TB/T-ID ve test fazı PR'a yazılır. Güvenlik testleri yalnız yetkili yerel/sentetik fixture ortamındadır. P0 giderilmeden ilgili ürün kamuya açılmaz; ölçülmemiş kontrol başarılı sayılmaz. Bir tehdit satırının belgede bulunması riskin giderildiği anlamına gelmez. İstisna gerekiyorsa kullanıcı kararı, sorumlu, telafi, süre ve yeniden kontrol kaydı gerekir; private ağ/ACL bariyerleri zayıflatılmaz.

Bu alt faz belge kapsamındadır. T01–T15 runtime kontrolleri çalıştırılmadı; gerçek testler matristeki fazlarda uygulanacak. Mevcut sınırlı secret ve belge kontrolleri yalnız repo teslimini değerlendirir. Prod alan adı, saklama süreleri, e-posta, admin/MFA, provider ve deployment ayarları ilgili faz/yayın öncesi açık kararlardır.
