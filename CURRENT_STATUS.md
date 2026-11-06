# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.2.0; F04 / V1.2 DONE (F00, F01, F02, F03 ve F04 ana fazları DONE; F04.01-F04.07 tamamlandı; 42 kural).
- **Son onay:** F04.07 ve F04 ana faz kapanışı DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #35 merge ve F04 ana faz kapanışını onayladı; PR #35 main dalına squash-merge edildi (`b390ae5f6bd660d1863c952e7f47e5bd219143b5`).
- **Önceki teslim:** [PR #35](https://github.com/Boydaksu/siteportal/pull/35) birleştirildi (`b390ae5f6bd660d1863c952e7f47e5bd219143b5`).
- **Aktif alt faz / durum:** F04 ana fazı tamamlandı / F05.01 hazırlığı (PENDING).
- **Dal:** `main`.
- **Aktif PR / kanıt commit:** [PR #35](https://github.com/Boydaksu/siteportal/pull/35) birleştirildi (`b390ae5f6bd660d1863c952e7f47e5bd219143b5`).
- **Rapor:** `docs/reports/F04.07.md` ve ana faz kapanış raporu `docs/reports/F04-PHASE-SUMMARY.md`.
- **Yerel sonuç:** PASS; PHP lint (329 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 610 test / 4592 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F04.07 V1.2 tam regresyon testi (`V12EndToEndRegressionTest`), harici API throttle / outage graceful fallback (CrUX 429, PageSpeed 500, bellek önbelleği ile bütçe koruması), yapılandırılmış veri matrisi (JSON-LD, Microdata, RDFa, sentaks hataları, eksik alanlar), mobil/erişilebilirlik matrisi (viewport, WCAG 1.4.4 zoom kısıtlaması, 5 katmanlı form etiketleri, dekoratif görseller, statik mod güvenliği), selektif JS rendering matrisi (SSRF koruması, ağır hydration uyarısı, kritik engellenen kaynaklar) ve 42 kuralın TR/EN yerelleştirme sunumu (%100 PASS) tamamlandı.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki ana faz:** F05 — V2.0: Üyelik, domain doğrulama, Search Console (F05.01 şartnamesi ve planı hazırlanacak).
- **Bir sonraki işlem:** F05.01 şartnamesi incelenerek uygulanabilir planın sunulması ve başlama onayı sorusu.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
