# Changelog

## 2026-11-03 | F01.01 kabulü
- Kullanıcı “devam edelim” ile F01.01 teslimi, PR #7 birleştirmesi ve F01.02 başlangıcını onayladı; kabul kaydı CI’dan sonra birleştirilecek.

## 2026-11-03 | F01.01 | READY_FOR_REVIEW
- PHP 8.4 tabanlı modüler monolit çalışma iskeleti (`app/Core`, `app/Controllers`, `public`, `config`, `resources`) kuruldu; haricî framework yükü olmadan PSR-4 otopaketi bağlandı.
- Front Controller (`public/index.php`), Request-ID takibi (`X-Request-Id` ve Correlation ID), dot-notasyonlu Config loader (`app/Core/Config.php`), ErrorHandler (`app/Core/ErrorHandler.php`), View motoru (`app/Core/View.php`), Router (`app/Core/Router.php`) ve iki dilli I18n çeviri altyapısı (`app/Core/I18n.php`) geliştirildi.
- Health JSON API (`/health`), kullanıcı arayüzü ana sayfası (`/`), `robots.txt`, `sitemap.xml` ile güvenli 404 ve 500 hata sayfaları (`debug=false` durumunda sızıntısız genel hata; `debug=true` durumunda geliştirici detayları) hazırlandı.
- Türkçe (`tr-TR`) ve İngilizce (`en-US`) metin anahtarları birebir ve yer tutucu parametreleri `{param}` kontrolüyle eksiksiz eşitlendi.
- Birim ve entegrasyon testleri (`tests/Unit/Core`, `tests/Integration`) yazılarak toplam 36 test, 242 assertion ve gerçek PHP sunucu HTTP çağrıları ile doğrulandı.
- Kullanıcı teslim kabulü ve açık onayı bekleniyor; F01.02'ye geçilmez (DUR).

## 2026-11-03 | F00.06 kabulü ve F00 ana faz kapanışı
- Kullanıcı “onaylıyorum” ile F00.06 teslimi, PR #6 birleştirmesi ve F00 ana faz kapanışını onayladı; F01 ana fazına geçiş izni alındı.

## 2026-11-03 | F00.06 | READY_FOR_REVIEW
- Onaylı PR #5 birleştirildi; merge SHA `411643464e08b16d6bf660978ecec79d6f0472e5`; güncel main üzerinden F00.06 faz kapanış dalı açıldı.
- F00.01'den F00.05'e tüm alt faz kanıtları, test sonuçları ve onayları derlendi; F00 ana faz kapanış özeti (`F00-PHASE-SUMMARY.md`) hazırlandı.
- Açık kararlar (Q-004), main koruma önerisi ve uygulama sınırları belgelendi; F00 ana faz kabulü ve PR #6 incelemeye sunuldu.

## 2026-11-03 | F00.05 kabulü
- Kullanıcı “onaylıyorum” ile F00.05 teslimi, PR #5 birleştirmesi ve F00.06 başlangıcını onayladı; kabul kaydı CI’dan sonra birleştirilecek.

## 2026-11-03 | F00.05 | READY_FOR_REVIEW
- Onaylı PR #4 CI sonrasında birleştirildi; güncel main üzerinden repo politikası dalı açıldı.
- PR/issue/inceleme ve geri alma yönergesi, secret kontrol listesi ve main koruma önerisi hazırlandı.
- Main koruması uygulanmadı: entegrasyon yönetim erişimi 403, UI URL doğrulaması durdu. Ayrı ayar onayı yok.

## 2026-11-03 | F00.04 kabulü
- Kullanıcı “evet onaylıyorum” ile F00.04 teslimi, PR #4 birleştirmesi ve F00.05 başlangıcını onayladı; kabul kaydı CI’dan sonra birleştirilecek.

## 2026-11-03 | F00.04 | READY_FOR_REVIEW | PR #4
- Onaylı PR #3 birleştirildi; merge SHA `74c56bc9ddfa9471b168cf7908cbb49e07aca857`; güncel main'den F00.04 dalı açıldı.
- PHPUnit 12.5.43, PHPStan 2.3.5, PHP_CodeSniffer 4.0.4 ve 27 toplam dev paket exact/lock, lisans ve advisory kaydıyla eklendi. Uygulama paketi yok.
- Yerel lint/PSR-12/statik analiz/3 fixture TEST (74 assertion) ve dört beklenen hata kapısı geçti. İlk Windows wrapper/dosya erişimi sorunu giderildi.
- Tam SHA pinli, contents:read CI workflow ve temiz checkout komutları yazıldı; [Gerçek temiz checkout CI](https://github.com/Boydaksu/siteportal/actions/runs/41666506494) success; içerik SHA `06b3e12fd122eeef24dbc748c956d79ff4ed85bb`, runtime PHP 8.4.27/Composer 2.10.2.
- TESTING, fixture/sınırlı kontrol kapsamı, lisans/audit kanıtı, envanter ve durum dosyaları güncellendi. F00.05 başlamadı; F00.04 kullanıcı teslim kabulü bekleniyor.

## 2026-11-03 | F00.03 kabulü
- Kullanıcı “devam edelim” ile F00.03 teslimi, PR #3 birleştirmesi ve F00.04 başlangıcını onayladı; bağımlılık politikası kabul kaydı güncellendi.

## 2026-11-03 | F00.03 | READY_FOR_REVIEW | PR #3
- Onaylı F00.02 PR #2 birleştirildi; merge SHA `8ed972e5e74806ea62f5dd50e7f7953570472e4c`.
- Güncel main'den F00.03 dalı açıldı; teknik ADR gerekçeleri, modül sınırları, bağımlılık/lisans/sürüm politikası ve başlangıç tehdit modeli hazırlandı.
- Q-001–003 somut önerilerine ayrı “Üç öneriyi de onaylıyorum” onayı alındı. data_source/evidence dizisi, analysis_jobs ve her uygulanan özellikte tam tr/en kapsamı belge/örneklerde uzlaştırıldı.
- Yeni paket, framework, PHP/DB uygulaması, migration veya CI kurulmadı. Proje dağıtım lisansı Q-004 olarak yayın öncesi açık kaldı.
- İlgili belge/envanter/sözleşme kontrolleri ve 7/7 bağlantı TEST PASS; rapor/log ve [PR #3](https://github.com/Boydaksu/siteportal/pull/3) güncel. Kanıt commit `0e4206400f34bda67d0358ce1fc05b57dd7ae77d`; teslim metadata kaydı sonraki commit’te. F00.04 başlamadı; kullanıcı teslim kabulü bekleniyor.

## 2026-11-03 | F00.02 kabulü
- Kullanıcının “devam edelim” yanıtıyla F00.02 teslimi, PR #2 birleştirmesi ve F00.03 başlangıcı onaylandı.

## 2026-11-03 | F00.02 | READY_FOR_REVIEW | PR #2
- F00.01 kullanıcı kabulü ve ayrı merge izniyle PR #1 birleştirildi; merge SHA: `09716d298587dc29ea5d9092127a65d0f24a4c07`.
- Güncel main üzerinden `phase/F00.02-docs-contract` açıldı.
- Başlangıç rehberleri ve belge haritası düzenlendi; Türkçe bakım standardı ve açık karar kayıtları eklendi.
- Belge denetimi aktif alt fazdan bağımsızlaştırıldı; yerel dosya/başlık ve master bağlantı kontrolleri eklendi.
- Uygulama/CI/ADR geliştirilmedi; F00.03 başlamadı. Belge/envanter kontrolleri ve 7/7 TEST PASS; ilk devir FAIL düzeltildi, ikinci sıfır bağlamlı ajan temiz sabit HEAD üzerinde PASS verdi. Kanıtlar F00.02 raporu, kontrol günlüğü ve devir kaydında. Kullanıcı kabulü bekleniyor.

## 2026-11-03 | F00.01 kabulü
- Kullanıcı “devam edelim” ile teslimi kabul etti ve F00.02 başlangıcını onayladı.
- Ayrı “Evet, PR #1’i birleştir” yanıtıyla PR #1 merge onayı alındı; onay kaydı commit’e eklendi.

## 2026-11-03 | F00.01 | READY_FOR_REVIEW | PR #1 | kanıt 611ad26740e4d2d984f56810f924726cc8496827
- GitHub main, gerçek dosya içeriği, başlangıç commit'i, tek dal, açık PR ve yazma izinleri doğrulandı.
- 57 dosyalık plan paketi envanteri ve orijinal manifest korundu; başlangıç paketi geliştirme dalına alındı.
- Durum dosyaları, envanter raporu ve tekrar çalıştırılabilir belge/envanter kontrolü eklendi.
- Uygulama geliştirilmedi; F00.02 başlamadı; kullanıcı kabulü bekleniyor.
- Push başarılı; PR: https://github.com/Boydaksu/siteportal/pull/1. Belge/envanter kontrolleri PASS; CI yok. PR ayrıca bu teslim metadata kaydını içerir. Kullanıcı kabulü alınmadı.

## Blueprint 0.1.0 — 2026-11-03
- Proje şartnamesi, faz/alt faz, kabul koşulları, iş akışı, güvenlik, SEO kuralları, API ve veritabanı taslakları hazırlandı.
- **Uygulama kodu ve faz tamamlaması yok.** Bu yalnızca plan paketinin başlangıç sürümüdür.

## Sonraki değişiklik kayıt formatı
`YYYY-MM-DD | Fxx.yy | commit SHA | PR # | Added/Changed/Fixed/Tested | onay durumu`
