# Açık sözleşme soruları

Bu kayıt F00.01 envanterindeki farkları görünür tutar; mimari karar vermez. `blockers` aktif işi durduran sorunlardır. Aşağıdakiler F00.02 belge düzenlemesini engellemez; belirtilen uygulama başlamadan kullanıcıyla karara bağlanmalıdır.

| ID | Durum | Fark / kanıt | Karar kapısı |
|---|---|---|---|
| Q-001 | OPEN | [API](../architecture/API.md) `data_source` ve `evidence[]`; [bulgu şeması](../../examples/finding.schema.json) `source` ve nesne `evidence` kullanıyor | API/kural implementasyonundan önce tek Finding sözleşmesi; F00.03'te ele alınacak |
| Q-002 | OPEN | [Crawler](../architecture/CRAWLER.md) `jobs`; [veritabanı](../architecture/DATABASE.md) `analysis_jobs` diyor | Migration/queue kodundan önce tablo adı; F00.03'te ele alınacak |
| Q-003 | OPEN | [AGENTS](../../AGENTS.md) tr/en tamamlanmasını, [I18N](../architecture/I18N.md) minimum fallback'i, [F08](../roadmap/F08.md) tam kapsamı tarif ediyor | İlk UI yazılmadan sürüm bazında en-US kapsamı; F00.03'te ele alınacak |

İlk paket aktarımı/yerleştirme örtüşmesi durum ayrımıyla açıklanmıştır: F00.01 paketi envantere aldı; F00.02 rehber, bakım, bağlantı ve devir doğrulamasını yürütür. Sonraki fazların kapsamı değişmez.

Yeni karar gerektiğinde [DECISIONS](DECISIONS.md) formatını kullan; kullanıcı onayı, gerekçe, güvenlik/maliyet etkisi, ilgili faz ve geri dönüşü kaydet. F00.03 başlamadan çözüm varsayma.
