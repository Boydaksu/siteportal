# Geri alma yönergesi

## Karar ve kapsam
Hatalı PR/merge SHA, sorun, hedef davranış, sonraki değişikliklere etki ve kullanıcı yetkisi kaydedilir. Acil durum otomatik merge veya üretim yetkisi değildir. F00.05 belge/şablon değiştirir; DB, migration ve üretim verisi yoktur.

## Hazırlama
1. Güncel main ve çalışma ağacını kontrol et; mevcut işi koru. `fix/Fxx.yy-revert-kisa-konu` dalını main'den aç.
2. Hedef merge'in parent'larını ve ilk parent'ın merge öncesi main olduğunu `git show --no-patch --format=raw <MERGE_SHA>` ile doğrula. Yer tutucuyu gerçek SHA ile değiştir.
3. Yalnız bu koşulda `git revert -m 1 <MERGE_SHA>` ile yeni revert commit'i hazırla. Tek parent için `git revert <COMMIT_SHA>` kullanılır. Körlemesine aralık geri alma. Çakışmada anlamı incele; emin değilsen dur. `git revert --abort` yalnız aktif revert'i iptal eder.
4. Durum kayıtlarını gerçekliğe uyarla; tarihsel raporları koru. Master/manifest'i yeniden üret. Sonraki düzeltmeleri geri almanın etkisini değerlendir.
5. Diff, ilgili regresyon/kalite/belge testleri ve yeni head CI'sını kontrol et. PR'a hedef, gerekçe, veri etkisi ve tekrar uygulama planı yaz.
6. Açık kullanıcı merge onayından sonra beklenen head SHA ile birleştir; gerçek main SHA/CI'yı doğrula. Hatalı PR'a bağlantı ve sonuç ekle.

Reset/force push veya geçmişten commit silme kullanılmaz. GitHub Revert de yeni PR oluşturur; çakışmada yerel hazırlık gerekebilir. [Resmî kaynaklar](../reference/REPOSITORY_SOURCES.md).

## Doğrulama sınırı
Belge geri dönüşü DB geri dönüşünün kanıtı değildir. Migration fazında yedek, restore denemesi, veri kaybı sınırı ve ayrı onay eklenir. Secret için revert anahtarı geçersiz kılmaz; [SECRETS](../process/SECRETS.md) izlenir.

F00.05 kontrolü: baseline→çalışma ağacı patch'inin tersi `git apply --reverse --check` ile dosya değiştirmeden sınanır. Yeni main değişikliklerindeki çatışmayı veya canlı geri dönüşü garanti etmez. Bu fazda gerçek revert/merge yapılmaz.
