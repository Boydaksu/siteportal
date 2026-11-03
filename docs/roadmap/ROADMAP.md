# ROADMAP — Fazlar, alt fazlar, duraklama kuralları

Bu belge sürüm hedeflerini tanımlar. **Her Fxx.yy sonrasında ayrı commit+PR+test+rapor; ardından kullanıcı onayı gelene dek DUR.** Fxx bitiminde tekrar kapsamlı kapanış raporu ve onay gerekir. Faz numarası tamamlanma anlamına gelmez.

| Faz | Sürüm | Amaç | Alt faz sayısı | Durum |
|---|---|---|---:|---|
| [F00](./F00.md) | V0.0 | Sözleşme, repo ve standartlar | 6 | DONE |
| [F01](./F01.md) | V0.1 | Güvenli çekirdek ve worker | 7 | NOT_STARTED |
| [F02](./F02.md) | V1.0 | Üyeliksiz tek sayfa SEO analizi | 8 | NOT_STARTED |
| [F03](./F03.md) | V1.1 | Site çapında kontrollü tarama | 7 | NOT_STARTED |
| [F04](./F04.md) | V1.2 | Performans, mobil, yapılandırılmış veri | 7 | NOT_STARTED |
| [F05](./F05.md) | V2.0 | Hesap, domain doğrulama ve Search Console | 8 | NOT_STARTED |
| [F06](./F06.md) | V2.1 | İçerik, AI hazırlık, alan modülleri | 7 | NOT_STARTED |
| [F07](./F07.md) | V3.0 | Abonelik, ajans, açık API | 8 | NOT_STARTED |
| [F08](./F08.md) | V4.0 | Ölçeklenebilir, çok dilli, olgun platform | 7 | NOT_STARTED |

## Sıralama ve kapılar

1. **F00 — V0.0:** Sözleşme, repo ve standartlar. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
2. **F01 — V0.1:** Güvenli çekirdek ve worker. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
3. **F02 — V1.0:** Üyeliksiz tek sayfa SEO analizi. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
4. **F03 — V1.1:** Site çapında kontrollü tarama. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
5. **F04 — V1.2:** Performans, mobil, yapılandırılmış veri. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
6. **F05 — V2.0:** Hesap, domain doğrulama ve Search Console. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
7. **F06 — V2.1:** İçerik, AI hazırlık, alan modülleri. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
8. **F07 — V3.0:** Abonelik, ajans, açık API. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.
9. **F08 — V4.0:** Ölçeklenebilir, çok dilli, olgun platform. Çıkış koşulu: belgede yazan tüm kabul maddeleri ve faz kapanış raporu.

## DoD (Definition of Done)
- Kod + fixture + test + güvenlik + i18n + doküman + rapor + commit SHA/PR kanıtı.
- Kritik test başarısızsa faz DONE olamaz.
- PR dalına push `READY_FOR_REVIEW`, kullanıcının kabulü `DONE`; birleştirme ayrıca onaya bağlıdır.
- Kapanışta önceki faz geriye dönük değiştiriliyorsa ayrı fix PR aç.


## Önceliklendirme
**P0:** SSRF, robots, güvenli istek, queue, yetki, doğruluk, temel UI. **P1:** Site mimarisi, CWV, schema, GSC doğrulanmış kullanıcı. **P2:** AI/ajans/white-label/ücretli hesap. **Şimdilik gereksiz:** Kubernetes, mikroservis ağı, herkes için headless Chrome, backlink scraping, Google sırası tahmin modeli, otomatik SEO düzeltme, zorunlu llms.txt.

## Aktif teslim
F00 ana fazı (F00.01–F00.06) kullanıcı kabulüyle DONE; F00 kapatıldı. Sıradaki ana faz F01 (V0.1: Güvenli çekirdek ve worker); durum [JSON](../../project-status.json) ve [F00 ana faz özeti](../reports/F00-PHASE-SUMMARY.md) içindedir.
