# CURRENT_STATUS — Tek Bakışta Devir Panosu

- **Tarih / repo:** 2026-11-04, Europe/Istanbul; özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), `main`.
- **Plan / ürün / ana faz:** blueprint-0.1.0; ürün 0.1.0-dev; F02 / V1.0 IN_PROGRESS (F00 ve F01 ana fazları DONE).
- **Son onay:** F02.01 DONE; kullanıcı “onaylıyorum” ile teslim kabulü, PR #14 merge ve F02.02 başlangıcını onayladı.
- **Aktif alt faz / durum:** F02.02 — HTML parser ve metadata / READY_FOR_REVIEW.
- **Dal:** `phase/F02.02-html-parser-metadata`.
- **Aktif PR / kanıt commit:** PR açılacak; [CI doğrulaması](docs/reports/F02.02-checks.txt) SUCCESS.
- **Rapor:** [F02.02](docs/reports/F02.02.md).
- **Yerel sonuç:** PASS; PHP lint (138 dosya), PSR-12 (0 hata, 0 uyarı), PHPStan seviye 8 ([OK] No errors), PHPUnit 252 test / 1804 assertion PASS, 4 olumsuz kapı, 7 link TEST, blueprint/mimari/envanter kontrolleri PASS.
- **Uygulama testleri:** `HtmlParser` motoru (DOMDocument/DOMXPath, LIBXML_NONET XXE kalkanı, 5MB DoS koruması, UTF-8/ISO-8859-9 Türkçe karakter desteği, tek geçişli meta/OG/Twitter çıkarımı, WCAG boş alt `alt=""` dekoratif vs `alt === null` eksik alt metni ayrımı, RFC 3986 göreceli URL çözümü, iç/dış bağlantı tespiti, nofollow tespiti, script/style filtreli kelime ve karakter sayımı, HTML5 etiket hata filtresi ve biçimsiz HTML'de sıfır çökme garantisi).
- **Açık karar:** [Q-004](docs/process/OPEN_QUESTIONS.md) dağıtım lisansı; mevcut özel repo tesliminde yayın yapılmıyor.
- **Sonraki alt faz:** F02.03 — Teknik SEO kuralları; BEKLİYOR.
- **Bir sonraki işlem:** F02.02 kullanıcı teslim kabulü ve PR onayı bekleniyor; açık onay olmadan F02.03'e geçilmez (DUR).

Eski raporlar tarihsel snapshot'tır. Bu pano/JSON/aktif PR güncel durumu gösterir.
