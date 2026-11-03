# F00.04 — Test ve CI çalıştırma rehberi

## Kapsam ve önkoşullar
Yalnız test/kalite iskeleti kuruludur; PHP uygulama çekirdeği, DB, crawler, UI veya migration yoktur. PHP 8.4+, Git, Python 3.10+ ve Composer 2.10.2 gerekir. Yerelde doğrulanan CLI: PHP 8.4.24 ve Composer 2.10.2; Python 3.13.15. PHP test eklentileri: dom, filter, json, libxml, mbstring, phar, simplexml, tokenizer, xmlwriter. Uygulamanın curl/intl/pdo_mysql gibi bütün platform gereksinimlerinin hazır olduğu iddia edilmez.

## Temiz checkout ve kurulum
Onaylı çalışma dalında/checkout'ta, kök dizinde:

```text
composer validate --no-check-publish --no-plugins --no-scripts
composer install --no-interaction --prefer-dist --no-plugins --no-scripts
composer check-platform-reqs --no-plugins --no-scripts
composer audit --locked --format=json --no-plugins --no-scripts
```

Doğrudan dev araçları kesin sürümlüdür: PHPUnit 12.5.43, PHPStan 2.3.5, PHP_CodeSniffer 4.0.4. Toplam 27 doğrudan/geçişli dev paket lock ve [envanterde](dependencies.json) bulunur; uygulama paketleri boştur. Kurulum, plugin/root script çalıştırmadan lock'tan yapılır. Production deploy veya update komutu değildir.

**Bilinen uyarı:** Kök proje lisansı Q-004 seçilmediğinden Composer validate “No license specified” uyarısı verir. Bu yüzden doğrulama publish denetimi olmadan çalıştırılır; gerçek schema/lock hatalarının başarısız çıkışı korunur. Lisans uydurulmaz, bu uyarı raporlanır; dağıtım öncesi lisans seçilip strict publish denetimi yapılmalıdır. Paketlerin kendi LICENSE/NOTICE metinleri ayrıca kontrol edildi ve vendor içinde korunur.

## Yerel ve CI kalite komutları

```text
python scripts/check_quality.py
python scripts/test_document_links.py
python scripts/verify_blueprint.py
python scripts/verify_architecture.py
python scripts/verify_toolchain.py
python scripts/verify_inventory.py
python scripts/build_master_plan.py --check
git diff --check
```

`check_quality` aynı sırayla gerçek php -l, PHP_CodeSniffer PSR-12, PHPStan seviye 8, PHPUnit ve dört olumsuz kalite kapısı testini çalıştırır. Kapsam yalnız [tests](../../tests/README.md); analiz edilen uygulama henüz yoktur. Lint boş kapsamda başarı saymaz; Git'in takip ettiği/yeni ignore edilmemiş PHP dosyalarını denetler. Vendor ve korunmuş eski plan kopyası kapsam dışıdır. Windows PHP .cmd başlatıcısı sabit bir sorguyla gerçek PHP executable/ini yoluna çözülür; dosya adları shell komutuna eklenmez. Gerekirse `SITEPORTAL_PHP_BINARY` ile native PHP yolu belirtilebilir.

`build_master_plan.py --check` dosyayı değiştirmeden kaynaklarla byte eşliğini denetler. Manifest üretimi test komutu değildir; belge değiştirirken [MAINTENANCE](MAINTENANCE.md) sırasıyla önce master'ı üret, yeni dosyaları hazırla, manifesti yenile. CI dosyaları yeniden üreterek hatayı gizlemez ve son çalışma ağacının temizliğini kontrol eder.

Biçim düzeltme gerekirse yalnız yerelde `php vendor/bin/phpcbf --standard=phpcs.xml` kullanılabilir; otomatik değişiklikleri incele ve kalite komutlarını tekrar çalıştır. CI yalnız kontrol eder.

## CI ve yetki sınırı
[quality.yml](../../.github/workflows/quality.yml) PR ve main push olaylarında temiz Ubuntu 24.04 checkout'ı kullanır. PHP 8.4 dalının kurulan gerçek yama sürümü job logunda kaydedilir; Composer 2.10.2 ve araç paketleri lock ile sabittir. Runner imajı/indirilen PHP yamaları zamanla değişebilir; bu uygulama deployment pin'i veya tüm ortamın bit düzeyinde tekrarlanabilirliği iddiası değildir.

Action'lar tam commit SHA ile sabittir: actions/checkout v7.0.1 ve shivammathur/setup-php v2'nin 2026-11-03 tarihinde çözülmüş commit'i. İki action'ın MIT lisansı ve kaynak referansı [envanterde](dependencies.json). Token yalnız contents:read; checkout credential'ı kalıcı tutulmaz. pull_request_target, deploy, secrets aktarımı, write token veya self-hosted runner kullanılmaz. Timeout 15 dakika; aynı ref'in eski işi iptal edilir. Repo koruma/PR politikası F00.05'tir, burada ayar değiştirilmez.

## Sonuçları yorumlama
Yeşil CI yalnız belirtilen kalite ve fixture/belge kontrollerinin geçtiğini gösterir. PHPStan seviye 8 veya fixture testleri SSRF, ACL, queue yarışı, DB rollback, UI/E2E, coverage yüzdesi ya da production hazırlığı kanıtı değildir. Coverage sürücüsü kapalıdır; sahte coverage yüzdesi verilmez.

[Bağımlılık incelemesi](../reports/F00.04-dependency-review.json) 27 indirilen dev paketinin lisans dosya hash/kaynaklarını ve ilk advisory sonucunu kaydeder; [Composer lisans çıktısı](../reports/F00.04-composer-licenses.json) ayrı kanıttır. Paketlenmiş PHAR iç bileşenleri ve tam kod/hukuk denetimi kapsam dışıdır. Canlı audit CI'da yeniden çalışır; ağ/veri kaynağı hatasında başarı varsayılmaz. Son gerçek yerel/CI kanıtı [F00.04 raporunda](../reports/F00.04.md) tutulur.
