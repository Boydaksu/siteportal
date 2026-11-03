# Frontend / UX tam planı

## Görsel sistem
Lacivert `#101827`, mavi `#2563EB`, yeşil `#10B981`, amber `#F59E0B`, kırmızı `#EF4444`, açık arka plan `#F5F7FB`. CSS custom properties; dark mode bağımsız. Modern system font stack, Lucide SVG ikonlar. Değerler erişilebilir kontrast testine tabidir; yalnızca renkle uyarı verilmez.

## Anasayfa
Navbar → SEO URL girişli hero → güvenilir kaynak açıklaması → demo rapor → analiz özellikleri → nasıl çalışır → fiyat/limitler → SSS → gizlilik/iletişim footer. İlk ekranda URL girişi; mobil klavye uyumu. SEO içerik sayfaları gerçekten özgün bilgi sunar.

## URL formu
Domain veya `https://...` girişi, koşulsuz otomatik analiz yok; `https://` eksikse ve tek anlamlıysa kullanıcıya normalize adres önizlemesi; IDN unicode/punycode normalize; port ve kullanıcı bilgi bölümü kurallara tabi. Kullanıcı hata gördüğünde örnek anlaşılır mesaj.

## İşlem ekranı
Durum: `queued`, `validating`, `fetching`, `parsing`, `rules`, `finalizing`, `completed`, `partial`, `failed`, `cancelled`. Progress gerçekleştirilen işlerden hesaplanır; geri kalanı tahmin gibi sunulmaz. Uzun işlerde `analysis_id` ile sonra geri dönme mümkün; rastgele sürede done iddiası yok.

## Rapor ekranı
Üst kart: URL, kapsam, tarih, skor ve veri güveni. Tablar: Genel/Technical/On-page/Links/Performance/Schema/Mobile/AI/Pages/Recommendations. Olmayan modül tabı gizlenebilir, ancak ölçülmeyen kategorilerin yokluğu açık. Filtreler: severity, rule_id, affected URL, status. Sorun kartı evidence, fix, docs link, recheck ile genişler. PDF/CSV yalnızca yetkili planda.

## Durumlarda özel tasarımlar
- Site cevap vermiyorsa `UNKNOWN` veya `FAILED` gerekçesi ve yeniden dene; sahte SEO puanı yok.
- Robots engelleniyorsa dur, `robots_disallowed` raporu ve çözüm açıklaması göster.
- Bot koruması 403/429 döndürürse bunu SEO başarısızlığı değil erişim engeli/uyarı diye sınıflandır.
- GSC bağlı değilse kapalı alanlarda `Bağlantı gerekiyor` yaz.
- CrUX yoksa `Yeterli gerçek kullanıcı verisi bulunamadı` yaz.
- JavaScript worker yoksa render testleri `Not measured`.

## İçerik prensipleri
İlk dil Türkçe; tüm hata/öneriler sade bir cümle ve detay bağlantısı. Teknik etiketler kopyalanabilir. 'Kritik' yalnızca kanıtlanmış kırıklarda. Formun ve tabların klavye erişilebilirliği, aria-describedby, canlı durum duyuruları, boş durum mesajları ve görünür focus haleleri test edilir.

## Analytics
Analiz başlatma, hata görme, sorun açma, PDF/CSV, yeniden analiz olayları toplanabilir; minimum veri ilkesi, izin yönetimi ve KVKK aydınlatması şarttır. Özel rapor URL'si analytics'e açık metin gönderilmez.
