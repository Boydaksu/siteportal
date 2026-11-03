# Architectural Decision Records (ADR) ve açık kararlar

## Başlangıç belgelerinde kayıtlı kararlar
- ADR-001: SitePortal modüler PHP monolit, framework az, MySQL + CLI cron, server-side HTML; veri tabanlı kuyruk, opsiyonel renderer.
- ADR-002: SEO Analyzer genel SitePortal modülü; hosting işi değildir.
- ADR-003: Türkçe öncelik, locale key zorunluluğu, en-US fallback.
- ADR-004: Güvenlik, doğruluk, bilgi kaynağı ve fazdan sonra durma davranışı özelliklerin önünde.
- ADR-005: Özel rapor varsayılan; domain doğrulanmadıkça yoğun/site çaplı tarama yok.

## İlk uygulamadan önce karara bağlanacaklar
- Prod ortamı alan adı ve gerçek sunucu/cPanel/VPS ayrıntıları.
- E-posta servisi, KVKK legal metin sahibi, veri saklama süreleri.
- Hangi ödeme sağlayıcısı/hangi plan fiyatları V3'te seçilecek.
- Admin güvenlik/MFA policy, müşteri dashboard ihtiyacı.
- Gerçek GitHub ana dal koruması PR review ve CI. Repo yönetim değişikliği ayrı onay gerektirir.

## Yeni ADR formatı
`ADR-NNN: problem → seçenekler → karar → neden → güvenlik/maliyet etkisi → geri dönüş → kullanıcı onayı → değiştirilecek faz`. Riskli/geri dönüşsüz kararlarda önce kullanıcı onayı; sessiz değiştirme yapılmaz.

## F00.03 somut karar kayıtları
[ADR dizini](../adr/README.md), [ADR-001 teknik sınırlar](../adr/ADR-001-stack.md), [MODULES](../architecture/MODULES.md), [DEPENDENCIES](DEPENDENCIES.md) ve [THREAT_MODEL](../architecture/THREAT_MODEL.md) gerekçe, alternatif, sorumluluk, güvenlik ve geri dönüşü kaydeder. Yeni framework/servis veya uygulama geliştirilmedi.

Q-001/Q-002 için [ADR-006](../adr/ADR-006-contract-alignment.md), Q-003 için [ADR-003](../adr/ADR-003-i18n.md) somut önerileri kullanıcı tarafından “Üç öneriyi de onaylıyorum” yanıtıyla onaylandı; ilgili API/örnek/queue/i18n belgeleri bundan sonra uzlaştırıldı. Karar kabulü F00.03 teslim kabulü değildir. [Açık kayıtlar](OPEN_QUESTIONS.md) ve [makine karar kaydı](../adr/architecture-decisions.json) birlikte tutulur.
