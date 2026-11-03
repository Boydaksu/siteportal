# ADR-006 — Finding biçimi ve queue tablo adını uzlaştırma

- Tarih: 2026-11-03. Durum: APPROVED; Q-001 ve Q-002 için açık kullanıcı onayı alındı.
- Kaynaklar: [API](../architecture/API.md), [bulgu şeması](../../examples/finding.schema.json), [DEMO yanıt](../../examples/analysis-response.example.json), [CRAWLER](../architecture/CRAWLER.md), [DATABASE](../architecture/DATABASE.md).

## Problem
API `data_source`/`evidence[]`, örnekler `source`/nesne `evidence` kullanır. Crawler `jobs`, DB planı `analysis_jobs` der. Uygulama başlamadan tek sözleşme gerekir.

## Seçenekler ve öneriler
| Soru | Alternatifler | Öneri ve gerekçe |
|---|---|---|
| Q-001 | API'yi source/tek nesneye çevirmek; çift alias taşımak; örnekleri API biçimine getirmek | **data_source + nesnelerden oluşan evidence dizisi.** Ürün veri kaynağı adını ve bir bulguda birden çok kanıtı tutarlı kılar; henüz canlı API olmadığı için alias/geçiş kodu gerektirmez. |
| Q-002 | DB'yi jobs diye adlandırmak; ayrı jobs/analysis_jobs tablosu eklemek; crawler adını düzeltmek | **analysis_jobs** tek analiz kuyruk tablosu adı. DB planı korunur, SEO modülünün iş kaydı genel platform işlerinden ayrılır. Yeni tablo veya migration eklenmez. |

## Onayla uygulanan somut sözleşme
- `examples/finding.schema.json`: zorunlu `source` alanı `data_source` olarak güncellendi; `evidence` type=array, items type=object. UNKNOWN veya ölçüm yok durumunda boş dizi mümkün; sahte kanıt üretilmez.
- `examples/analysis-response.example.json`: `_demo:true` korunarak `source` anahtarı `data_source` olarak güncellendi; mevcut kanıt nesnesi tek elemanlı diziye alındı. Başka gerçek tarama iddiası yok.
- API belgesinde alanların anlamı ve örneğe referans açıklandı. İleride haricî provider adaptörünün `source()` metodu veya DB kaynağı otomatik yeniden adlandırılmayacak; bu karar API Finding alanıyla sınırlı.
- CRAWLER belgesindeki queue örneği `analysis_jobs(...)` diye adlandırıldı; lifecycle alanları korundu. DATABASE aynı adı kullanmaya devam edecek. SQL/PHP/DB işlemi yok.

## Güvenlik, maliyet ve geri dönüş
Kanıt dizisindeki her nesne güvenilmeyen içeriktir; ham HTML render edilmez, kapsam/saklama/maskeleme sınırları korunur. Şema uzlaşması XSS veya SSRF korumasının yerine geçmez. Yeni paket/servis maliyeti yok. Taslaklar Git revert ile döndürülebilir; canlı veri/istemci henüz yoktur. İleride yayınlanmış API değişikliği sürüm ve migration kararı ister.

## Kullanıcı onayı
ALINDI. 2026-11-03: Kullanıcı somut Q-001/Q-002/Q-003 önerilerine “Üç öneriyi de onaylıyorum” yanıtını verdi. API/örnek ve queue belgeleri bu onaydan sonra güncellendi. Karar onayı F00.03 teslim kabulü veya uygulama kodlama izni değildir.
