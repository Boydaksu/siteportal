# F00 — Temel ve Hazırlık Ana Faz Kapanış Özeti

> **Tarih:** 2026-11-03, Europe/Istanbul
> **Repo:** [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal) (özel), varsayılan dal `main`
> **Faz:** F00 — Sözleşme, repo ve standartlar (Hedef sürüm: V0.0)
> **Durum:** READY_FOR_REVIEW (Tüm alt fazlar tamamlandı; F00 ana faz kabulü ve PR #6 merge bekleniyor)

---

## 1. Ana Fazın Amacı ve Kapsamı
F00 ana fazının hedefi; modüler Türkçe öncelikli SEO denetim platformu (SitePortal) için sağlam bir mimari temel, bağlayıcı geliştirme sözleşmesi, belge envanteri, bağımlılık ve tehdit politikası, PHP kalite kapıları, CI/CD altyapısı ve GitHub iş akışı kurallarını kurmaktır.

Bu fazda çalışan ürün kodu (crawler, MySQL/veritabanı, analiz motoru, frontend) geliştirilmemiş; tüm zemin sonraki fazların (F01 ve sonrası) güvenli, kontrollü ve geri alınabilir şekilde ilerlemesi için hazırlanmıştır.

---

## 2. Alt Fazların Durumu ve Onay Özeti

| Alt Faz | Konu | Durum | PR / Merge SHA | Kullanıcı Onayı | Test / CI Kanıtı |
|---|---|---|---|---|---|
| **F00.01** | Depo envanteri ve temizleme | DONE | [PR #1](https://github.com/Boydaksu/siteportal/pull/1) (`63c1145`) | 2026-11-03 16:30 | 114 dosya SHA256 manifest, Python kontrol scriptleri PASS |
| **F00.02** | Belge sözleşmesi ve kurallar | DONE | [PR #2](https://github.com/Boydaksu/siteportal/pull/2) (`48a425f`) | 2026-11-03 16:50 | 84 kural kataloğu, 691 Markdown hedef bağlantı testi PASS |
| **F00.03** | ADR, bağımlılık politikası ve tehdit modeli | DONE | [PR #3](https://github.com/Boydaksu/siteportal/pull/3) (`74c56bc`) | 2026-11-03 18:22 | ADR-001/003/006, Q-001/002/003 kararları, 15 tehdit analizi PASS |
| **F00.04** | PHP test harness ve GitHub Actions CI | DONE | [PR #4](https://github.com/Boydaksu/siteportal/pull/4) (`36a1f4b`) | 2026-11-03 20:16 | PHPUnit 12 (3 test, 74 assertion), PHPStan seviye 8, PSR-12, CI [Run 41666506494](https://github.com/Boydaksu/siteportal/actions/runs/41666506494) & [41682655805](https://github.com/Boydaksu/siteportal/actions/runs/41682655805) SUCCESS |
| **F00.05** | Repo, PR, rollback ve secret politikası | DONE | [PR #5](https://github.com/Boydaksu/siteportal/pull/5) (`4116434`) | 2026-11-03 22:26 | PR/issue şablonları, rollback, main koruma önerisi, CI [Run 41684279984](https://github.com/Boydaksu/siteportal/actions/runs/41684279984) & [41707262344](https://github.com/Boydaksu/siteportal/actions/runs/41707262344) SUCCESS |
| **F00.06** | F00 faz kapanış ve ana faz kabulü | READY_FOR_REVIEW | [PR #6](https://github.com/Boydaksu/siteportal/pull/6) | Bekleniyor (DUR) | Tüm yerel kontroller ve GitHub Actions CI |

---

## 3. Ana Faz Kabul Kriterleri Doğrulaması

- [x] **Tüm alt fazlar açık kullanıcı onayı ile tamamlandı:** F00.01'den F00.05'e kadar her alt faz ayrı ayrı kullanıcı onayıyla birleştirilmiştir.
- [x] **Çalıştırılabilir gerçek test kapıları ve yeşil CI:** Yerel ortamda PHP 8.4 lint, PHP_CodeSniffer PSR-12, PHPStan (seviye 8), PHPUnit (74 assertion), 4 negatif kalite kapısı ve belge bağlantı/envanter testleri eksiksiz geçmektedir. Temiz checkout üzerinde GitHub Actions CI yeşildir.
- [x] **Güvenlik ve Tehdit Modeli (T01–T15):** SSRF koruma bariyerleri fail-closed olarak belgelenmiş, secret tarama ve sızıntı kontrol listesi tanımlanmış, depoya hiçbir hassas veri sızdırılmamıştır.
- [x] **Türkçe Öncelik ve Çoklu Dil (i18n):** Belgeler Türkçe öncelikli tutulmuş, İngilizce ve Türkçe eşlik kontrolleri (`locales/` ve `examples/`) test edilmiştir.
- [x] **Geri Alma (Rollback) Güvencesi:** Force push yerine ayrı onaylı revert PR prosedürü belgelenmiştir.
- [x] **Bağlayıcı Sözleşme Uyumu:** AGENTS.md, START_HERE.md ve MAINTENANCE.md devir protokolleri harfiyen uygulanmıştır.

---

## 4. Açık Kararlar, Riskler ve Kapsam Sınırları

1. **Q-004 Dağıtım Lisansı:** Projenin nihai dağıtım lisansı henüz seçilmemiştir. Depo özel (private) olduğu ve halka açık dağıtım yapılmadığı için bu karar V1.0 öncesine ertelenmiştir; Composer'ın "No license specified" uyarısı bilinmektedir.
2. **Main Dal Koruması (Branch Protection):** GitHub API entegrasyon yetkisi (403) ve tek geliştirici durumunda kilitlenme riski nedeniyle koruma kuralları otomatik uygulanmamış, [ayrıntılı öneri dosyası](../operations/main-protection.proposal.json) hazırlanmıştır.
3. **Uygulama Kodu Durumu:** F00 fazı yalnızca altyapı ve sözleşme fazıdır. PHP crawler motoru, MySQL tabloları, analiz kuralları motoru ve web arayüzü F01 ve F02 fazlarında geliştirilecektir.

---

## 5. Sıradaki Ana Faz: F01 — Güvenli Çekirdek ve Tarama Kuyruğu (V0.1)

F00 ana fazı kapatıldıktan sonra başlanacak olan **F01** ana fazının ilk alt fazı:
* **F01.01 — Güvenli çekirdek ve bootstrap:** PHP 8.4 PSR-4 çekirdek yapısı, `src/` mimarisi, strict types disiplini ve konfigürasyon yükleyicisi.

> **ÖNEMLİ KURAL:** F00 ana fazı için açık kullanıcı onayı alınmadan ve PR #6 birleştirilmeden F01 ana fazına GEÇİLEMEZ.
