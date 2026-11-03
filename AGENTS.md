# AGENTS.md — SitePortal geliştirme ajanı bağlayıcı sözleşmesi

## Başlamadan önce
Bu dosya tüm AI kod ajanları için bağlayıcı proje sözleşmesidir. Sabit okuma sırası [START_HERE](START_HERE.md#sabit-okuma-sırası) içindedir: README → AGENTS → CURRENT_STATUS ve JSON → yol haritası/aktif faz/iş akışı → son rapor ve açık kararlar → ilgili mimari/kaynaklar. Aktif fazı dosyaya sabitleme; [project-status.json](project-status.json) kaydından bul. Kullanıcının en yeni açık talimatı önceliklidir. Mimari/sözleşme çelişkisini kaydet; önemli kararı sessizce değiştirme. [Belge haritası](docs/README.md), [bakım standardı](docs/process/MAINTENANCE.md) ve [açık sorular](docs/process/OPEN_QUESTIONS.md) devrin parçasıdır.

## Misyon
`Boydaksu/siteportal` deposunda modüler SitePortal web-utilities çatısı altında Türkçe öncelikli, çoklu dil destekli, kaynak ve kanıt gösteren SEO denetimi geliştir. İlk hedef güvenli ve kaliteli V1.0; sonra sürüm sıralamasını izle. Hosting işini bu projeye karıştırma.

## Çalışma protokolü — HER ALT FAZDA
1. İlgili Fxx.yy şartnamesini ve bağımlılıklarını oku; tamamlanan son fazı doğrula.
2. GitHub `main` güncel durumunu ve kendi çalışma dalını kontrol et. Diğer kişinin değişikliklerini ezme.
3. Alt faz için kısa uygulanabilir plan ve dokunulacak dosyaları belirt.
4. **Yalnızca mevcut alt fazın kapsamını** geliştir. Gizli özellik ekleme, bağımlılıkları rastgele artırma.
5. Statik analiz, birim/entegrasyon/güvenlik testleri ve gereken regresyonları çalıştır. Çalışmayan testleri dürüstçe göster.
6. Doküman ve durum dosyalarını aynı PR/commit içinde güncelle; `docs/reports/Fxx.yy.md` raporunu oluştur.
7. `phase/Fxx.yy-kisa-konu` dalına commit ve push yap, PR aç veya linkini sağla. GitHub yetkisi yoksa `BLOCKED`, yerel patch/diff bırak; push yaptım deme.
8. Kullanıcıya kapsam, değişen dosyalar, test kanıtları, riskler, PR/commit, sıradaki alt faz ve onay sorusuyla rapor ver.
9. **DUR. Kullanıcının açık onayını bekle.** Onay gelmeden sonraki alt faza veya ana faza geçme. Onay olmadan PR merge/production deployment yapma.
10. Fazın son alt aşamasından sonra ayrıca **ana faz kapanış raporu** üret, kabul kriterleri ve açık sorunlar üzerinden yeniden onay iste. Sıradaki ana faz kendiliğinden başlamaz.

## Sınırlar
- Kod, rapor ve testlerde sahte veri kullanılıyorsa `DEMO/TEST` diye işaretle; gerçek tarama iddiasında bulunma.
- GitHub'a hiçbir `.env`, token, müşteri verisi, OAuth refresh token'ı, ham özel rapor veya gizli konfigürasyon yükleme.
- Hiçbir koşulda canlı hedefi yoğun tarama, açık port tarama, kimlik bilgisi kullanma ya da robots kurallarını atlatma.
- SSRF bariyerlerini hiçbir alt faz esnetemez; güvenli varsayılanlar `fail closed`.
- Ölçüm dışı alanlara puan verme; `UNKNOWN` ve `NOT_APPLICABLE` durumları korunur.
- Google'ın sıralama faktörlerini, indeks durumunu veya AI görünürlük ihtimalini kesin biliyormuş gibi raporlama.
- Veritabanı migration'ları geri dönüşü planlanmadan uygulanmaz; silme/bozma riski taşıyan işlemler açık onaya tabidir.
- Paket bağımlılıkları sabitlenmiş sürümlerle ve lisans/güvenlik kontrolünden geçerek eklenir.
- UI metinlerini doğrudan kodun içine gömme; `tr-TR` ve `en-US` çevirileri tamamlanmalı (başka diller ileride).
- Yapılandırılmamış kaynak, yetkisiz API, yanlış kaynak atfı, gereksiz bağımlılık veya anlamsız SEO skor kuralı kullanma.

## Nihai teslim standardı
İlgili alt fazın tüm `Acceptance Criteria` maddeleri kanıtla geçtiyse `READY_FOR_REVIEW`; kullanıcı kabulünden sonra `DONE`. Bir veya daha fazla kritik kriter başarısızsa `BLOCKED` veya `IN_REVIEW`; asla `DONE` deme.

## Devir için sabit okuma sırası
[START_HERE](START_HERE.md#sabit-okuma-sırası) tek okuma sırasıdır. GitHub main son kabul/birleştirme durumunu, aktif PR kendi teslimini gösterir. `next_subphase` otomatik başlama izni değildir; faz başlangıcı, teslim kabulü ve PR merge izinlerini ayrı kaydet.
