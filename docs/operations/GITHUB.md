# GitHub çalışma ve inceleme politikası

## Güncel temel
2026-11-03: özel [Boydaksu/siteportal](https://github.com/Boydaksu/siteportal), varsayılan dal main. F00.04 [PR #4](https://github.com/Boydaksu/siteportal/pull/4) kullanıcı onayıyla merge edildi; F00.05 başlangıç SHA `36a1f4b6c5b1d2ed9b2cf50b5e348950324d443d`. İlk envanterdeki kısa README tarihsel bilgidir. Her iş öncesi fetch, main/PR/dal ve çalışma ağacını yeniden kontrol et; mevcut işi ezme.

## Dal ve PR
- Tek alt faz: `phase/Fxx.yy-kisa-konu`; güncel kabul edilmiş main üzerinden açılır.
- Aynı teslim düzeltmesi aynı PR'a ek commit; kabul edilmiş işte regresyon `fix/Fxx.yy-kisa-konu` ve ayrı PR.
- Commit örneği `docs: F00.05 repo ve PR politikası`; PR başlığı `[F00.05] Repo ve PR politikası`.
- [PR şablonu](../../.github/PULL_REQUEST_TEMPLATE.md), [alt faz](../../.github/ISSUE_TEMPLATE/alt-faz.md) ve [hata şablonu](../../.github/ISSUE_TEMPLATE/hata.md) kullanılır. Issue açmak zorunlu değil; yetkisiz bildirim/etiket/atama yapılmaz.
- Draft PR'da eksik kanıt PENDING yazılır. İncelemeye açılırken gerçek sonuç, SHA ve CI linki eklenir.
- İnceleme sırası: yetki/kapsam → diff/veri kaybı → güvenlik/bağımlılık → davranış/test/çeviri → geri alma → durum/devir.
- Komut, beklenen ve gerçek sonuç ayrılır; N/A gerekçelendirilir. [Gizli bilgi kontrolü](../process/SECRETS.md) staging ve push öncesi yapılır.

## Birleştirme kapısı
Son PR head SHA'sının `PHP 8.4 and document checks` işi başarılı olmalı; önceki commit'in yeşil sonucu yeterli değildir. Main ilerlerse uyumluluğu yeniden değerlendir, çakışmaları incele ve yeni head CI'sını bekle. Force push kullanma. Kullanıcı kabul kaydı eklenen commit de yeniden CI'dan geçer.

Kullanıcı teslim kabulü, PR merge ve sonraki alt faz başlangıcını ayrı kaydet. GitHub review ile sohbet kabulü farklıdır. Merge sırasında beklenen head SHA kontrolü kullan; beklenmeyen değişiklikte dur. Mevcut merge commit stratejisi korunur; squash/rebase otomatik seçilmez. README/JSON main'de kabul edilmiş durumu, açık dalda teslim durumunu gösterir. Merge sonrasında gerçek SHA'yı doğrula. Üretim yetkisi bundan doğmaz.

## Main koruması — uygulanmadı
Main sorgusunda `protected:false` görüldü. Yönetim okuması bağlı entegrasyonda `403 Resource not accessible by integration` verdi. Hesabın admin/push bilgisi entegrasyona yönetim erişimi sağlamıyor. UI doğrulaması tarayıcı URL'si güvenle belirlenemediği için durdu; ayar değiştirilmedi.

[Kesin ayar önerisi](main-protection.proposal.json): zorunlu PR, bir farklı yetkili inceleyenin onayı, yeni commit'te eski onayların düşmesi, strict/up-to-date CI, yorumların çözülmesi, yöneticilere de uygulama; force push ve silme kapalı. Merge commit korunacağı için linear history zorunlu değil.

**Ayrı ayar onayı alınmadı; bu dosya canlı koruma değildir.** Bir inceleyen şartı tek kişilik çalışmayı kilitleyebilir; farklı inceleyen bulunmadan uygulanmaz. Sıfır inceleyen alternatifi ayrıca açık kararla seçilir. Uygulamadan önce özel repo plan desteği, yönetim kanalı, mevcut ruleset/korumalar ve check kaynağı doğrulanmalı; bilinmeyen kuralların üstüne yazılmamalı. [Resmî kaynaklar](../reference/REPOSITORY_SOURCES.md).

## Hata ve geri dönüş
Push/PR erişimi yoksa BLOCKED, yerel diff/patch ve gerçek hata bırak; SHA/PR uydurma. Yanlış merge için [geri alma PR'ı](ROLLBACK.md) hazırla. Reset/force push, geçmiş silme ve onaysız deployment yapma. Sızıntıda [olay adımları](../process/SECRETS.md) izlenir.
