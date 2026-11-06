# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.1.0; F04 / V1.2 IN_PROGRESS (F00, F01, F02 ve F03 ana fazları DONE; F04.01 DONE, F04.02 DONE, F04.03 DONE, F04.04 DONE, F04.05 DONE, F04.06 BAŞLAYACAK).
- **Son onay:** F04.05 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #33 merge ve F04.06 başlangıcını onayladı; PR #33 main dalına squash-merge edildi (`160d040a3a9f0fadd61360a726ce00efa62312b4`).
- **Önceki teslim:** [PR #33](https://github.com/Boydaksu/siteportal/pull/33) birleştirildi (`160d040a3a9f0fadd61360a726ce00efa62312b4`).
- **Aktif alt faz / durum:** F04.05 — Mobil/a11y temel kontroller / DONE.
- **Dal:** `main`.
- **Aktif PR / kanıt commit:** PR #33 squash-merged (`160d040a3a9f0fadd61360a726ce00efa62312b4`).
- **Rapor:** `docs/reports/F04.05.md`.
- **Yerel sonuç:** PASS; PHP lint (321 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 572 test / 3856 assertion PASS (%100), 4 olumsuz kapı, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** F04.05 Mobil viewport ayrıştırıcı, kısıtlayıcı yakınlaştırma (WCAG 1.4.4) ve taşma denetimi; form girdileri (<input>, <select>, <textarea>) erişilebilir etiketleme (label for, wrapped label, aria-label, aria-labelledby, title) ve dekoratif görsel (role="presentation", role="none", aria-hidden="true", alt="") ayrımı; 4 yeni/güncel kural (`MOBILE_VIEWPORT`, `MOBILE_OVERFLOW`, `A11Y_FORM_LABEL`, `A11Y_CONTRAST`), V1.2 registry (40 kural), TR/EN çeviriler ve 25 yeni test (%100 PASS) tamamlandı ve main dalına merge edildi.
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F04.06 — Selektif JS rendering tasarımı.
- **Bir sonraki işlem:** F04.06 dalının açılması ve selektif JS rendering tasarımının geliştirilmesi.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
