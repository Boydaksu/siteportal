# Changelog

## 2026-11-03 | F00.02 kabulü
- Kullanıcının “devam edelim” yanıtıyla F00.02 teslimi, PR #2 birleştirmesi ve F00.03 başlangıcı onaylandı.

## 2026-11-03 | F00.02 | READY_FOR_REVIEW | PR #2
- F00.01 kullanıcı kabulü ve ayrı merge izniyle PR #1 birleştirildi; merge SHA: `09716d298587dc29ea5d9092127a65d0f24a4c07`.
- Güncel main üzerinden `phase/F00.02-docs-contract` açıldı.
- Başlangıç rehberleri ve belge haritası düzenlendi; Türkçe bakım standardı ve açık karar kayıtları eklendi.
- Belge denetimi aktif alt fazdan bağımsızlaştırıldı; yerel dosya/başlık ve master bağlantı kontrolleri eklendi.
- Uygulama/CI/ADR geliştirilmedi; F00.03 başlamadı. Belge/envanter kontrolleri ve 7/7 TEST PASS; ilk devir FAIL düzeltildi, ikinci sıfır bağlamlı ajan temiz sabit HEAD üzerinde PASS verdi. Kanıtlar F00.02 raporu, kontrol günlüğü ve devir kaydında. Kullanıcı kabulü bekleniyor.

## 2026-11-03 | F00.01 kabulü
- Kullanıcı “devam edelim” ile teslimi kabul etti ve F00.02 başlangıcını onayladı.
- Ayrı “Evet, PR #1’i birleştir” yanıtıyla PR #1 merge onayı alındı; onay kaydı commit’e eklendi.

## 2026-11-03 | F00.01 | READY_FOR_REVIEW | PR #1 | kanıt 611ad26740e4d2d984f56810f924726cc8496827
- GitHub main, gerçek dosya içeriği, başlangıç commit'i, tek dal, açık PR ve yazma izinleri doğrulandı.
- 57 dosyalık plan paketi envanteri ve orijinal manifest korundu; başlangıç paketi geliştirme dalına alındı.
- Durum dosyaları, envanter raporu ve tekrar çalıştırılabilir belge/envanter kontrolü eklendi.
- Uygulama geliştirilmedi; F00.02 başlamadı; kullanıcı kabulü bekleniyor.
- Push başarılı; PR: https://github.com/Boydaksu/siteportal/pull/1. Belge/envanter kontrolleri PASS; CI yok. PR ayrıca bu teslim metadata kaydını içerir. Kullanıcı kabulü alınmadı.

## Blueprint 0.1.0 — 2026-11-03
- Proje şartnamesi, faz/alt faz, kabul koşulları, iş akışı, güvenlik, SEO kuralları, API ve veritabanı taslakları hazırlandı.
- **Uygulama kodu ve faz tamamlaması yok.** Bu yalnızca plan paketinin başlangıç sürümüdür.

## Sonraki değişiklik kayıt formatı
`YYYY-MM-DD | Fxx.yy | commit SHA | PR # | Added/Changed/Fixed/Tested | onay durumu`
