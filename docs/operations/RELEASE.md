# Sürüm / yayın yönetimi

## Sürüm kapıları
V0.x: yalnız geliştirme ve güvenirlik; V1.0: genel beta public guest analyzer; V1.1/1.2: tarama ve veri modülleri; V2.x: doğrulanmış site sahibi ve içerik; V3: ticari/ajans; V4: ölçek/i18n olgunluk.

## Yayın öncesi
CI pass → pentest-scope security check → rollback/backups → migration dry-run → env/config review → staging gerçek test fixture → user phase approval → merge → tag (SemVer) → manuel/izinli deploy → smoke/monitor → post-release report. Üretim kurulum için kullanıcıya makineye özel doğru komutlar ve env değişkenleri anlatılır; test edilmemiş dağıtım sözü verilmez.

## Incident response
P0 güvenlik açığı veya istismarda public scan feature flag kapatılır, kalan işler durdurulur, loglar korunur, etki alanı tespit edilir, owner bilgilendirilir. Ajan yetkisi olmadan dış kullanıcıya bildirim/production secret değiştirmez.

## 2026+ bakım ritmi
API/provider değişikliklerini, Search Central dokümanlarını ve dependency CVE'lerini düzenli kontrol. Her değişiklik yeni issue/FIX PR, test, docs ve changelog ile. 'SEO 2026' ismi dondurulmuş kurallar demek değildir.
