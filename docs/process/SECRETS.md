# Gizli bilgi kontrol listesi

## Staging öncesi
- [ ] Yalnız gerekli proje dosyalarını ekle; .env, özel rapor, token, refresh token, müşteri verisi ve özel anahtar ekleme.
- [ ] `git status --short` ve dosya listesini incele. Ignore dosyalarını veya credential store'u sır aramak için açma.
- [ ] Örnekler boş yer tutucu veya açık DEMO/TEST verisidir; gerçek anahtardan türetilmez.

## Commit ve push öncesi
- [ ] `git diff --cached --name-only` ile eklenen dosyaları kontrol et; .env.example yalnız boş/örnek değer içerir.
- [ ] Staged diff'i yerelde incele; şüpheli değeri çıktı, sohbet, issue veya rapora kopyalama. Maskeli dosya/konum/tür yeterlidir.
- [ ] `python scripts/verify_inventory.py` çalıştır. GitHub/AWS/private-key kalıpları yalnız takip edilen çalışma dosyalarındadır; indeks/geçmiş/binary/entropi/tüm sağlayıcı taraması değildir. Araç kendi kalıpları nedeniyle kendi dosyasını hariç tutar.
- [ ] İnceleme ile commit arasında değişiklik varsa yinele. Geçmiş sızıntı şüphesinde commit/PR/CI/artifact kapsamını yetkili kişiyle ayrıca değerlendir; çalışma ağacı PASS sonucunu geçmiş temizliği sayma.
- [ ] CI logları, fixture ve ekleri de incele; erişim bilgisi/özel veri barındıran log yükleme.

## GitHub hizmeti
Native secret scanning/push protection bu fazda etkinleştirilmedi ve çalıştığı doğrulanmadı. Özel repo plan/ürün erişimi ayrıca kontrol edilir. Yerel sınırlı kontrolün geçmesi bu hizmetin aktif olduğu anlamına gelmez. Yönetim ayarı ayrıca açık onay gerektirir. [Resmî kapsam](../reference/REPOSITORY_SOURCES.md).

## Şüpheli bulgu
Push'u durdur; değeri yeniden yayımlamadan dosya/commit/kapsamı kaydet. Anahtar sahibine yetkili süreçle iptal/rotasyon yaptır; dış sağlayıcıya anahtarı deneyerek doğrulama isteği gönderme. İletişim yetkisi olmadan üçüncü kişilere mesaj gönderme. Revert veya dosyadan kaldırma anahtarı geçersiz kılmaz. Geçmiş temizliği ayrı etki analizi ve açık onay ister; force push otomatik yapılmaz. Yeni anahtar Git'e eklenmez. Sonuç maskeli raporlanır.
