# Architectural Decision Records (ADR) ve açık kararlar

## Kabul edilen başlangıç kararları
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

## Açık sözleşme farkları
F00.01 bulguları [OPEN_QUESTIONS](OPEN_QUESTIONS.md) içinde izlenir. F00.02 bunları görünür kılar; yeni ADR veya çözüm üretmez.
