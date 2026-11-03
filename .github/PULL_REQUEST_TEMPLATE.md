## Amaç ve izin
- Alt faz / şartname / son kabul:
- Somut sorun ve sonuç:
- Bu işe başlama yetkisi:
- Kapsam dışı:

## Değişiklik ve kanıt
- Dosyalar ve gerekçe:
- Kaynak / güncellik:
- DEMO/TEST işaretleri:

| Kontrol | Komut / kapsam | Gerçek sonuç | Commit / CI kanıtı |
|---|---|---|---|
| Yerel kalite | | | |
| CI | | | |
| Güvenlik / gizli bilgi | | | |
| Regresyon | | | |

Çalıştırılmayan test için gerekçe ve risk yaz; N/A kapsamını açıkla. Loglarda gizli değer gösterme.

## Etki ve geri alma
- Bağımlılık / lisans / maliyet / performans:
- DB migration / veri kaybı riski (yoksa belirt):
- Geri alma hedefi, yöntem, doğrulama ve gerekli onay:
- Yönerge: docs/operations/ROLLBACK.md

## İnceleme kapısı
- [ ] Yalnız izin verilen alt faz; diff ve dosya listesi incelendi.
- [ ] docs/process/SECRETS.md kontrol listesi uygulandı; yalnız kalıp taramasına dayanılmadı.
- [ ] Uygulanan kullanıcı metinlerinin tr-TR/en-US anahtar ve parametreleri eş.
- [ ] README, CURRENT_STATUS, project-status, CHANGELOG, rapor ve roadmap güncel.
- [ ] Manifest/master güncel; mevcut dosya kaybı yok.
- [ ] Son PR head commit'inin CI sonucu başarılı; yeni commit eski sonucu geçersiz kılar.
- [ ] İnceleme yorumları ve riskler çözüldü veya açıkça raporlandı.

**READY_FOR_REVIEW:** Kullanıcı teslim kabulü, PR birleştirme ve sonraki alt faz başlangıcı ayrı izinlerdir. CI/review bunları sağlamaz. Repo ayarı ve üretim işlemi ayrıca açık onay gerektirir.
