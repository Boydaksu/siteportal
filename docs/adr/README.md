# ADR dizini ve karar durumları

F00.03, mevcut planın gerekçelerini kaydeder. Başlangıçtaki ADR kimlikleri korunur; var olan planı açıklamak yeni framework/servis ekleme izni değildir. `BASELINE_DOCUMENTED` mevcut planın kaydı, `PROPOSED` henüz onaylanmamış değişiklik, `APPROVED` ilgili somut öneri için açık kullanıcı onayı anlamına gelir. F00.03 tesliminin kabulü ayrıca beklenir.

| ID | Konu | Kanıt / durum |
|---|---|---|
| ADR-001 | PHP/MySQL/JS teknik sınırları | [Gerekçe ve alternatifler](ADR-001-stack.md), BASELINE_DOCUMENTED |
| ADR-002 | SEO Analyzer genel SitePortal modülü; hosting yazılımı değil | [Ürün sınırı](../requirements/PRODUCT.md), [modül matrisi](../architecture/MODULES.md); mevcut plan korunur |
| ADR-003 | Türkçe öncelik ve locale keys; en-US kapsam takvimi | [Dil önerisi](ADR-003-i18n.md), Q-003 için APPROVED (ayrı kullanıcı onayı) |
| ADR-004 | Güvenlik/doğruluk/kanıt ve onay kapıları | [SECURITY](../architecture/SECURITY.md), [THREAT_MODEL](../architecture/THREAT_MODEL.md), mevcut güvenlik sınırları korunur |
| ADR-005 | Private rapor ve domain doğrulamasına bağlı derin tarama | [PRODUCT](../requirements/PRODUCT.md), [PRIVACY](../operations/PRIVACY.md), mevcut plan korunur |
| ADR-006 | Finding alanları/kanıt biçimi ve queue adı | [Sözleşme önerisi](ADR-006-contract-alignment.md), Q-001/Q-002 için APPROVED (ayrı kullanıcı onayı) |

Makine kaydı [architecture-decisions.json](architecture-decisions.json), açık işler [OPEN_QUESTIONS](../process/OPEN_QUESTIONS.md), lisans/sürüm kapısı [DEPENDENCIES](../process/DEPENDENCIES.md), kontrol edilmiş teknik referanslar [ARCHITECTURE_SOURCES](../reference/ARCHITECTURE_SOURCES.md). Yeni önemli kararda problem/seçenek/karar/gerekçe/güvenlik-maliyet/geri dönüş ve kullanıcı onayı birlikte kaydedilir. Onaysız öneri asıl sözleşmeye uygulanmaz.
