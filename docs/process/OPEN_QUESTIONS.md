# Açık sözleşme soruları

Bu kayıt F00.01 envanterindeki özgün farkları ve son kararlarını izler. Q-001–003 F00.03 içinde ayrı kullanıcı onayıyla RESOLVED oldu; aşağıdaki fark açıklamaları tarihsel bulgudur, güncel sözleşme onaylı ADR’dedir. `blockers` yalnız aktif işi durduran sorunlardır; Q-004 yayın öncesi açık karar olarak kalır.

| ID | Durum | Fark / kanıt | Karar kapısı |
|---|---|---|---|
| Q-001 | RESOLVED | [API](../architecture/API.md) `data_source` ve `evidence[]`; [bulgu şeması](../../examples/finding.schema.json) `source` ve nesne `evidence` kullanıyor | API/kural implementasyonundan önce tek Finding sözleşmesi; F00.03 ayrı kullanıcı onayıyla uzlaştırıldı; [ADR-006](../adr/ADR-006-contract-alignment.md) |
| Q-002 | RESOLVED | [Crawler](../architecture/CRAWLER.md) `jobs`; [veritabanı](../architecture/DATABASE.md) `analysis_jobs` diyor | Migration/queue kodundan önce tablo adı; F00.03 ayrı kullanıcı onayıyla uzlaştırıldı; [ADR-006](../adr/ADR-006-contract-alignment.md) |
| Q-003 | RESOLVED | [AGENTS](../../AGENTS.md) tr/en tamamlanmasını, [I18N](../architecture/I18N.md) minimum fallback'i, [F08](../roadmap/F08.md) tam kapsamı tarif ediyor | İlk UI yazılmadan sürüm bazında en-US kapsamı; F00.03 ayrı kullanıcı onayıyla uzlaştırıldı; [ADR-003](../adr/ADR-003-i18n.md) |

İlk paket aktarımı/yerleştirme örtüşmesi durum ayrımıyla açıklanmıştır: F00.01 paketi envantere aldı; F00.02 rehber, bakım, bağlantı ve devir doğrulamasını yürütür. Sonraki fazların kapsamı değişmez.

Yeni karar gerektiğinde [DECISIONS](DECISIONS.md) formatını kullan; kullanıcı onayı, gerekçe, güvenlik/maliyet etkisi, ilgili faz ve geri dönüşü kaydet. Q-001–003 için 2026-11-03: Kullanıcı somut Q-001/Q-002/Q-003 önerilerine “Üç öneriyi de onaylıyorum” yanıtını verdi. Onaylı kararlar ADR-003/006 içinde; özgün farklar bu tabloda tarihsel açıklama olarak korunur.

## Dağıtım öncesi lisans kararı
Q-004 — OPEN: Projenin kendi dağıtım lisansı belirlenmedi. [Bağımlılık politikası](DEPENDENCIES.md) gereği LICENSE/NOTICE veya SPDX lisansı uydurulmaz; işletmeci kamuya dağıtımdan önce seçer. Bu, F00.03 belge hazırlığını engellemez.
