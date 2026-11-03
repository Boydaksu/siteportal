# KVKK, veri yaşam döngüsü ve gizlilik hazırlığı

## Veri envanteri
Guest IP/rate limit hash, URL ve rapor, user email/locale, site ownership, OAuth tokens, Search Console verisi, payment subscription reference, audit logs. Verinin kimden, hangi amaçla, ne kadar süreyle ve hangi servise gönderildiği sürüm çıktısında belgelenmeli.

## Ürün kuralları
- Anonim analizler public search index'e açılmaz. Paylaşım varsayılan kapalı; süreli ve iptal edilebilir token.
- Ham HTML ve üçüncü taraf sayfadaki PII gereksiz saklanmaz; snapshot tanımlayıcı/meta ve maskeli kanıtlardan oluşur.
- Google OAuth minimum scope, token encryption, revoke ve hesabı silme senaryoları.
- Çerez izinleri ve aydınlatma metni entegrasyonlardan bağımsız planlanır; zorunlu olmayan analytics/ads için izin akışı gerekir.
- Veri paylaşımı kullanıcı yetkisi ve servis şartlarına bağlı; AI provider'a PII otomatik tam HTML yollama.
- Veri silme süreci, backup retention, audit ve irreversible account deletion tasarım ve test gerektirir.

## Önemli not
Bu dosya teknik hazırlık planıdır, hukukî görüş veya otomatik KVKK uygunluk sertifikası değildir. Üretimden önce gerçek işletmeci ve veri sorumlusu bilgileriyle hukuk uzmanı tarafından kontrol edilmelidir.
