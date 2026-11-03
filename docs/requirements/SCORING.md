# Açıklanabilir SEO puanı / önceliklendirme modeli

## Kesin ayrım
**Skor Google metrikleri değildir.** SitePortal ürününün tekniğe dayalı denetim puanıdır. `rule_set_version`, `scoring_version`, `coverage` tüm raporlarda görünür.

## Önerilen kategori ağırlıkları (ilk hipotez)
| Kategori | Ağırlık |
|---|---:|
| Teknik erişilebilirlik / indekslenebilirlik | 25 |
| On-page / açıklayıcı içerik yapısı | 20 |
| Performans / CWV | 20 |
| Site mimarisi ve linkler | 15 |
| Uygulanabilir structured data | 10 |
| Mobil kullanılabilirlik | 10 |

**Tek sayfalık V1.0'da** performans ve site mimarisi gibi henüz ölçülemeyen veya uygulanmayan kategoriler denominator'a katılmaz. Global ağırlıklar yalnızca ölçülebilen uygun kategoriler arasında yeniden normalize edilir. Örneğin performans ölçümü yapılmadığında o 20 puan kayıp değil; `NOT_MEASURED` ve ayrı kapsam yüzdesidir.

## Kural durumu
- `PASS`: Uygulanabilir, veri mevcut, kontrol başarılı.
- `WARN`: İncelemeye değer, kesin teknik arıza olmayabilir.
- `FAIL`: Gözlenen ve kanıtlanmış başarısızlık.
- `UNKNOWN`: İstek, yetki, eksik içerik, API kesintisi veya güven düzeyi yetersiz.
- `NOT_APPLICABLE`: Sayfanın türü/bağlamı için kontrol gerekli değil.

## Önerilen formül
Her uygun, ölçülebilir kural için `q_i` ∈ [0,1], `w_i` > 0. PASS=1, WARN varsayılan 0.6, FAIL=0; **UNKNOWN/N/A denominator'a dahil edilmez**. Kategori skoru = `100 * Σ(w_i*q_i) / Σ(w_i)`; toplam = uygulanabilir kategori ağırlıklarından normalize edilmiş ağırlıklı ortalama. Sonuç ancak ölçülen uygulama kapsamı yeterliyse sayısal gösterilsin; diğer hâlde `insufficient_evidence`.

## Kapsam ayrı gösterimi
`coverage = measured_applicable_weight / all_expected_applicable_weight` (teknik ölçülemeyeni temsil ettiği bilinen kural evreninde). Ölçülecek evren bilinmiyorsa hesaplanamaz; kapsam açıklaması ve ham sayaçlar kullanılır. Örnekleme 10 sayfaysa 10 sayfa olduğu açıkça yazılır. Ağırlık eşikleri ve confidence dereceleri sürümlenir.

## Etki önceliği skorla aynı değildir
`priority = severity_factor * breadth_factor * confidence_factor / effort_factor` yalnızca **issue sort** için. Site skoruna doğrudan iş gücü veya kullanıcı paketi girmez. Tekrarlayan bulgu kategori bazında capped ağırlıkla etkiler; 100 tekrar '100 kat ceza' olmaz.

## Kural sürümü
Kural imzası `rule_id`, `rule_version`, `category`, `severity`, `evidence`, `applicability`, `confidence`, `fix_template` içerir. Sürüm değişince eski analiz tekrar hesaplanmadıkça eski puan değişmez. Cross-version comparison 'doğrudan kıyaslanamaz' etiketiyle sunulur.

## Sıfır puan güvenlik bariyeri
Erişim kapalı, robots blocked, HTTP timeout, CrUX no-data olduğunda rastgele 0/100 yazılmaz. Arıza gerçek teknik eksi ise FAIL, ölçüm imkânsızsa UNKNOWN. Sistem yöneticisi ağırlıkları değiştirecekse ADR ve snapshot test ister.
