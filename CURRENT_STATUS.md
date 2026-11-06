# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-06, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `phase/F05.05-url-inspection`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 1.2.0; F05 / V2.0 IN_PROGRESS (F00, F01, F02, F03 ve F04 ana fazları DONE; F05.01, F05.02, F05.03 ve F05.04 DONE; F05.05 READY_FOR_REVIEW).
- **Son onay:** F05.04 DONE; kullanıcı “onaylıyorum” ile teslim kabulünü, PR #39 merge ve F05.05 başlangıç hazırlığını onayladı; PR #39 main dalına squash-merge edildi (`9308f1147522e5e7756cfa810c24d5a643f47d28`).
- **Önceki teslim:** [PR #39](https://github.com/Boydaksu/siteportal/pull/39) birleştirildi (`9308f1147522e5e7756cfa810c24d5a643f47d28`).
- **Aktif alt faz / durum:** F05.05 — URL Inspection entegrasyonu / READY_FOR_REVIEW (PR ve kullanıcı onayı bekleniyor).
- **Dal:** `phase/F05.05-url-inspection`.
- **Aktif PR / kanıt commit:** PR açılacak (çalışma dalı commit edilecek).
- **Rapor:** `docs/reports/F05.05.md` ve kontrol özeti `docs/reports/F05.05-checks.txt`.
- **Yerel sonuç:** PASS; PHP lint (430 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 740 test / 5278 assertion PASS (%100, 0 notice, 0 warning), 13 güvenlik testi, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** Google URL Inspection API v1 entegrasyonu, indeks durumu (`indexed status`), kapsam (`coverage state`), robots.txt, canonical ve mobil uyumluluk kontrolleri, 24 saatlik veritabanı önbelleği (`url_inspection_caches`), token yenileme, yetkisiz erişim engeli, canlı URL testi yapılmadığı güvencesi (`isLiveTest = false`), GSC verisinin teknik crawler skorunu etkilememesi ve 294 anahtarlı TR/EN dil eşliği (%100 PASS).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F05.06 — Multi-page + GSC harmanlanmış rapor sunumu.
- **Bir sonraki işlem:** PR açılması, CI kontrolü ve kullanıcının açık onayı bekleniyor.

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
