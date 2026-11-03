# Belge haritası

Güncel durum: [CURRENT_STATUS](../CURRENT_STATUS.md) ve [project-status.json](../project-status.json). Başlangıç: [START_HERE](../START_HERE.md). Bağlayıcı sınırlar: [AGENTS](../AGENTS.md).

| Alan | Belgeler | Amaç |
|---|---|---|
| Fazlar | [Yol haritası](roadmap/ROADMAP.md), [F00](roadmap/F00.md), [F01](roadmap/F01.md), [F02](roadmap/F02.md), [F03](roadmap/F03.md), [F04](roadmap/F04.md), [F05](roadmap/F05.md), [F06](roadmap/F06.md), [F07](roadmap/F07.md), [F08](roadmap/F08.md) | Sıra, bağımlılık ve kabul kriterleri |
| Ürün | [Kapsam](requirements/PRODUCT.md), [özellik matrisi](requirements/FEATURE_MATRIX.md), [öncelikler](requirements/PRIORITIES.md), [UX](requirements/UX.md), [hatalar](requirements/ERROR_STATES.md) | Planlanan davranış; tamamlanmış ürün iddiası değil |
| SEO | [Kurallar](requirements/RULES.md), [CSV katalog](requirements/rules-catalog.csv), [skorlama](requirements/SCORING.md), [referanslar](reference/SOURCES.md) | Kaynak, kanıt, uygulanabilirlik ve UNKNOWN |
| Mimari | [Sistem](architecture/SYSTEM.md), [crawler](architecture/CRAWLER.md), [güvenlik](architecture/SECURITY.md), [DB](architecture/DATABASE.md), [API](architecture/API.md), [i18n](architecture/I18N.md), [entegrasyon](architecture/INTEGRATIONS.md), [operasyon](architecture/OPERATIONS.md) | Başlangıç taslakları ve sınırlar |
| Süreç | [İş akışı](process/WORKFLOW.md), [bakım](process/MAINTENANCE.md), [QA](process/QA.md), [rapor şablonu](process/REPORT_TEMPLATE.md), [kararlar](process/DECISIONS.md), [açık sorular](process/OPEN_QUESTIONS.md) | Tek alt faz, kalite, devir ve onay |
| GitHub/yayın | [GitHub](operations/GITHUB.md), [gizlilik](operations/PRIVACY.md), [sürüm](operations/RELEASE.md) | Repo, veri yaşam döngüsü ve yayın kapıları |
| Kanıt | [Rapor dizini](reports/README.md), [CHANGELOG](../CHANGELOG.md) | Gerçek iş, komut çıktısı, commit, PR ve kabul |
| Örnekler | [OpenAPI](../schemas/openapi-draft.yaml), [kural seed](../schemas/rule-registry.seed.json), [bulgu şeması](../examples/finding.schema.json), [DEMO yanıt](../examples/analysis-response.example.json), [DEMO rapor](../examples/phase-report.example.md) | Taslak/örnek; gerçek analiz kanıtı değil |
| Çeviri | [tr-TR](../locales/tr-TR.example.json), [en-US](../locales/en-US.example.json) | Örnek anahtarlar; çeviri motoru henüz yok |

[MASTER_PLAN_TR](../MASTER_PLAN_TR.md) kaynak belgelerden üretilen okunabilir kopyadır. Kaynağı düzenle, sonra yeniden üret; master dosyasını tek başına değiştirme.

## F00.03 teknik kayıtlar
[ADR dizini](adr/README.md), [modül sınırları](architecture/MODULES.md), [bağımlılık politikası](process/DEPENDENCIES.md), [bağımlılık envanteri](process/dependencies.json), [tehdit modeli](architecture/THREAT_MODEL.md) ve [kontrol edilen teknik kaynaklar](reference/ARCHITECTURE_SOURCES.md). Karar önerisi/onayı, implementasyon ve faz teslim kabulü ayrı durumlardır.

## F00.04 test ve CI
[Çalıştırma rehberi](process/TESTING.md), [TEST kapsamı/fixture](../tests/README.md), [CI workflow](../.github/workflows/quality.yml), [birincil kaynaklar](reference/TESTING_SOURCES.md), [bağımlılık incelemesi](reports/F00.04-dependency-review.json) ve [F00.04 raporu](reports/F00.04.md).
