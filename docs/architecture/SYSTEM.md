# Sistem mimarisi / klasör sınırları

## Karar (ADR-001)
Framework bağımlılığı düşük, PHP 8.4+ modüler monolit; PDO MySQL 8+, PHP-FPM web, CLI + cron job queue, server-rendered HTML + vanilla JS + responsive CSS. Composer sadece küçük, denetlenmiş kütüphane/autoload için. Tek VPS başlangıç. Laravel/Next.js/Redis/Kubernetes zorunlu değil. JS renderer başka süreç/izole konteyner ve ancak V1.2'de.

## Lojik katmanlar
`Browser -> FrontController -> Router -> Authentication/RateLimit -> AnalysisController -> AnalysisService -> DB Queue -> CLI Worker -> SafeHttpClient/Robots -> Extractor -> Rules -> Score -> Report Repository -> Read-only API/UI`.

## Nihai önerilen kod dizinleri
```text
/public/                # Yalnızca web root, index.php ve derlenmiş statik kaynaklar
/app/Core/              # Router, config, DB, Auth, Logging, Errors, I18n
/app/Modules/SeoAnalyzer/
  Domain/               # Analysis, Site, Page, Issue, Rule, Value Objects
  Application/          # CreateAnalysis, ScanPage, ReportQueries
  Infrastructure/       # Http, Robots, Sitemap, Queue, Cache, Repositories
  Rules/                # RuleRegistry, Technical, OnPage, Links, ...
  Integrations/         # PSI, CrUX, GSC; interface-based adapters
  Controllers/          # Web/API erişim noktaları
  Views/                # SEO Analyzer Twig gerektirmeyen escaped PHP templates
/app/Shared/            # Ortak platform servisleri
/config/                # Framework-free config; secrets as env
/database/migrations/   # Reversible ve sıralı migration'lar
/resources/lang/tr-TR/  # JSON/PHP translations
/resources/lang/en-US/
/resources/views/        # SitePortal genel şablonları
/storage/{cache,logs,reports,temp}/ # webroot dışında
/bin/{worker,scheduler,migrate}.php
/tests/{Unit,Integration,E2E,Security,Fixtures}/
/docs/{adr,reports,operations,roadmap}/
```

## Sorumluluk sınırları
- Controller **sadece** validate, auth ve use-case çağrısı; HTTP fetch/controller içinde yapılmaz.
- `SafeHttpClient` dış isteklerin tek çıkış kapısı; hizmet adaptörleri provider endpoint allowlist ve secret store kullanır.
- Crawler HTTP/HTML alır; rules sadece normalize snapshot okur, ek ağ isteğini açık adapter üzerinden ister.
- Site/sayfa kuralları birbirinden ayrı; site kapsamı bilinmedikçe site-geneli kesin bulgu üretilmez.
- ReportBuilder normalleştirilmiş bulgu + kaynak + puan çeker; kullanıcı HTML'ini doğrudan render etmez.
- Queue idempotent; worker yeniden çalıştırılabilir. DB uzun transaction içinde ağ çağrısı yapılmaz.
- Dağıtım: `APP_ENV=production` debug kapalı, health/readiness izinli, admin IP/MFA seçenekleri.

## Geliştirme / geri dönüş
Dış bağımlılık için önce interface ve fixture, sonra implementasyon. DB migration'ları ileri/geri test et; üretimde yıkıcı migration öncesi backup/kullanıcı onayı. Feature flag ile riskli modülleri kapat.

## Teknik borç ilkesi
Yeni servis sadece gözlemlenmiş ihtiyaç için. Redis, Kafka, Kubernetes, mikroservis ve sürekli Chrome yalnızca kabul edilmiş performans yük testiyle gerekçelendirilirse. Açık kaynak bağımlılık sürümü ve lisansını kaydet.

## F00.03 gerekçe ve sınır kayıtları
Mevcut teknik plan [ADR-001](../adr/ADR-001-stack.md) içinde seçenek/gerekçe/etki/geri dönüşle açılmıştır. [Modül matrisi](MODULES.md), [bağımlılık politikası](../process/DEPENDENCIES.md) ve [tehdit modeli](THREAT_MODEL.md) uygulanacak kabul sınırlarıdır; çalışan kod iddiası değildir.
