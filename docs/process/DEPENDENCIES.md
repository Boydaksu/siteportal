# Bağımlılık, sürüm, lisans ve tedarik zinciri politikası

Tarih: 2026-11-03. Durum: F00.03 kullanıcı teslim kabulüyle ACCEPTED (2026-11-03). Kaynak sınırları [AGENTS](../../AGENTS.md) ve [ADR-001](../adr/ADR-001-stack.md). Makine envanteri: [dependencies.json](dependencies.json).

## Mevcut gerçek durum
F00.04, test/CI için 3 kesin doğrudan ve 27 toplam geçişli geliştirme paketini composer.json/lock ile ekledi; uygulama paketleri boştur. [TESTING](TESTING.md), [envanter](dependencies.json) ve [inceleme kanıtı](../reports/F00.04-dependency-review.json) kapsamı açıklar. Vendor ve araç önbelleği ignore edilir; PHP/MySQL deployment gereksinimleri hâlâ PLANNED'dır. Yerel PHP 8.4.24/Composer 2.10.2 ve test araçlarının extension gereksinimleri doğrulandı; uygulama/DB hazırlığı değildir. Python/Git belge araçları ayrıca kullanılır. İlk F00.03 paket/audit yokluğu eski raporda tarihsel olarak korunur.

## Paket ekleme kapısı
Her doğrudan veya geçişli bağımlılık için kayıtta ad, amaç, ilgili alt faz, upstream/source URL, kesin resolved sürüm/reference, dağıtım bütünlük kaydı veya Git ref, lisans/SPDX ve notice yolu, bakım durumu, güvenlik kontrol tarihi/çıktısı, sahibi/onayı ve kaldırma alternatifi bulunur. İlk tercih PHP'nin mevcut kabiliyetidir; kritik güvenlik algoritması sırf bağımlılıktan kaçınmak için denetimsiz yeniden icat edilmez.

Kayıt tamamlanmadan paket kurup çalıştırma. Yeni framework/servis/proprietary SDK mevcut planı değiştiriyorsa ayrıca kullanıcı onayı ve ADR gerekir. Transitive bağımlılıklar da incelenir; yalnız doğrudan paket sayısı güvenlik göstergesi değildir. Gerekli test araçları da ayrı dev-tool kapsamıyla aynı sürüm/lisans/güvenlik kapısından geçer; F00.04 test araçları kendi dev kapsamı, lisans/lock/audit kanıtıyla eklenmiştir.

## Sürüm ve tekrarlanabilirlik
- Uygulama doğrudan paketleri AGENTS gereği kesin sürümle seçilir; `*`, `dev-main`, yüzen branch veya `latest` ile teslim yapılmaz. Geçişli sürümler commit edilen Composer lock içinde çözülür. Kod incelemesi manifest ve lock diff'ini birlikte kapsar.
- [Composer resmî rehberi](https://getcomposer.org/doc/01-basic-usage.md#commit-your-composer-lock-file-to-version-control) lock dosyasının aynı kesin sürümleri kullandırdığını açıklar. Deployment mevcut lock'tan install yapar; production'da update/dependency çözümlemesi çalıştırılmaz.
- Platform alt sınırı kesin yama sabitlemesi değildir. PHP/MySQL/Composer/işletim sistemi ve extension sürümleri gerçek ortam kurulduğunda F01 ortam kaydında tutulur; resmî destek ve güvenlik yamaları yayın öncesi yeniden kontrol edilir. Sahte resolved sürüm yazılmaz.
- Gereksiz frontend paket yöneticisi veya CDN script zinciri eklenmez. İleride zorunlu olursa kendi lock/integrity/lisans kaydı ve aynı kontrol kapıları gerekir.

## Güvenlik ve paket çalıştırma
Composer plugin'leri varsayılan kapalı (`allow-plugins: false`); yalnız ayrı incelenmiş açık allowlist kararıyla değişir. [Resmî config](https://getcomposer.org/doc/06-config.md#allow-plugins) plugin'lerin Composer sırasında kod çalıştırabildiğini belirtir. Paket ve root script'leri güvenilmeyen kod kabul edilir; bilinmeyen paketi sırları olan oturumda çalıştırma. Bir plugin'i kapatmak bütün bağımlılık kodunu güvenli yapmaz.

Yeni veya değişen lock için advisory/abandoned/malware sinyalleri, upstream bakım durumu ve lisans envanteri incelenir. Doğrulanmış kritik/yüksek etkili açık veya zararlı paket release blocker'dır. Yetkisiz audit ignore/skip yapılmaz. Veri kaynağı erişilemezse “açık yok” denmez; kontrol UNKNOWN ve ekleme/release engelli kalır. Açıklanmış, erişilemez olduğu kanıtlanan istisna gerekiyorsa etki, telafi, sorumlu, son kullanma tarihi ve ayrı kullanıcı onayı kaydedilir; SSRF/ACL sınırı istisnayla gevşetilmez.

## Lisans kabul kapısı
SPDX etiketi, gerçek LICENSE/NOTICE metni, sürüm ve doğrudan/geçişli dağıtım yükümlülükleri birlikte incelenir. MIT/BSD/Apache etiketleri otomatik onay değildir; notice/patent/redistribution koşulları okunur. Copyleft, ticari, çift veya belirsiz lisans durumunda işletmeci değerlendirmesi gerekir; uygunluğu varsayarak ekleme yapılmaz. Bu teknik süreç hukukî uygunluk görüşü değildir. Kök projeye LICENSE dosyası uydurulmaz; ürünün dağıtım lisansı yayın öncesi işletmeci tarafından seçilir. PHP/MySQL gibi platformların kullanım/dağıtım koşulları uygulama paketlerinden ayrı kaydedilir.

## Gelecek fazda uygulanacak kontrol örnekleri
Aşağıdaki komutlar [Composer CLI belgesinden](https://getcomposer.org/doc/03-cli.md) kontrol edilen prosedür örnekleridir; F00.03 sırasında yalnız prosedür örneğiydi. F00.04 içinde manifest/lock eklendi, audit/lisans/platform komutları çalıştırıldı; güncel komut ve Q-004 kök lisans uyarısının sınırı [TESTING](TESTING.md) içindedir. Kullanılacak Composer sürümünde bayraklar yeniden doğrulanır.

```text
composer validate --strict
composer audit --locked --format=json
composer licenses --locked --format=json
composer check-platform-reqs
```

Audit başarılı çıkış kodu tek başına kapsamı ispatlamaz; çıktıdaki taranan paket/kaynak ve tarih rapora yazılır. Lisans komutu hukukî uygunluk kararı vermez. CI kapılarının kurulumu F00.04'tür; bu dosya yalnız politikadır.

## Güncelleme ve geri alma
Hedefli update ayrı alt faz/fix PR'ında yapılır: gerekçe → upstream farkı/lisans/advisory → manifest+lock+envanter → ilgili regresyon/güvenlik testleri → kullanıcı review. Geri alma eski manifest/lock ve testli artifact'tır; veri formatı etkilenirse ayrı migration/backup stratejisi gerekir. Paket kaldırılırken kalan transitive bağımlılıklar ve script/config/notice kayıtları da kontrol edilir. Üretimde otomatik downgrade veya yıkıcı işlem yapılmaz.
