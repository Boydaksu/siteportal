# Geliştirme / GitHub / AI devir protokolü — ZORUNLU

## Tek alt faz döngüsü
1. **Kontrol:** `README`, `CURRENT_STATUS`, JSON, GitHub default branch ve varsa açık PR. Son onaylı subphase'i doğrula.
2. **Plan:** Sıradaki `Fxx.yy` hedef, kabul, bağımlılıklar, kapsam dışı; dokunulacak dosyaları kısa bildir.
3. **Dal:** `phase/Fxx.yy-kisa-ad` dalını `main`'in en güncel commit'inden aç; önceki onaylanmamış dal üzerinden devam etme.
4. **Kod/test:** Yalnızca bu subphase; uygun unit/integration/E2E/security test, fixture, i18n, lint, static analysis.
5. **Belgele:** `docs/reports/Fxx.yy.md` raporu ve README/CURRENT_STATUS/project-status/CHANGELOG'ı güncelle; `READY_FOR_REVIEW` yaz. Çıktıda test komutları ve gerçek sonuçlar.
6. **GitHub:** Commit örn. `feat(seo): F02.03 teknik kurallar` veya `docs: F00.02 başvuru dokümanları`; push, PR aç; CI durumunu kontrol et. Dönüşte gerçek SHA/PR URL göster.
7. **Bildir:** Türkçe alt faz raporu: amaç, yapılanlar, değişen dosyalar, testler, kanıt, riskler, diff, URL, maliyet, sonraki adım; **onay iste ve DUR**.
8. **Kullanıcı onayı:** Kabul varsa PR merge (ayrı açık onay) ve `main` durum kayıtlarını doğrula. Ret durumunda aynı subphase dalında düzeltme yap ve yeniden raporla. Onay yoksa ilerleme yok.
9. **Faz bitimi:** Bütün alt fazlar kabul edildiğinde `docs/reports/Fxx-PHASE-SUMMARY.md` ile kapsamlı kapanış raporu oluştur; bir kez daha **dur ve ana faz onayı bekle**.

## Durum makinesi
`NOT_STARTED -> IN_PROGRESS -> READY_FOR_REVIEW -> DONE` (DONE sadece açık kullanıcı kabulünden sonra). Hata durumunda `BLOCKED`, `FAILED_QA` veya `CHANGES_REQUESTED`. `DONE` geçişinde `approved_at` ve `approval_reference` kayıt altında. GitHub push başarısı = kullanıcı kabulü değildir.

## Çelişki çözüm önceliği
1. Kullanıcının son açık kararı.
2. GitHub `main` gerçektenki içerik ve mevcut PR/commit.
3. AGENTS.md/README/CURRENT_STATUS/JSON.
4. Roadmap ve alt faz dokümanı.
5. Tahminler (otomatik uygulama yasak).

Şartnamede değişiklik gerekiyorsa önce risk ve ADR öner, kullanıcı onayı al, sonra değiştir. Mevcut `main` code'unu habersiz silme, force-push, credential commit veya production deploy yasak.

## Bağlamı olmayan sonraki AI için gerçek devir testi
Yeni AI'a **yalnız repo ve ZIP** verildiğinde okuyup şu beş soruyu yanıtlayabilmeli: Son onaylı faz? Aktif PR? Test sonucu? Bir sonraki tek subphase? Açık engel? Biri belirsizse devir kalitesi yetersiz; o subphase DONE olamaz.

## README güncelleme normu
İlk 15 satırda tarih, son kabul edilen Fxx.yy, aktif Fxx.yy, durum, PR, SHA, son test özeti ve takip eden görev anlaşılır olmalı. Uzun raporlar `docs/reports` içinde, README bağlantılarla kısa kalır. `project-status.json` makine tarafından parse edilebilir olmalı.

## Belge bakımı ve açık kayıtlar
[START_HERE](../../START_HERE.md#sabit-okuma-sırası) tek okuma sırasını, [MAINTENANCE](MAINTENANCE.md) durum alanları/komut/manifest standardını tanımlar. [OPEN_QUESTIONS](OPEN_QUESTIONS.md) mimari karar bekleyen farkları korur. Başlangıç yetkisi, teslim kabulü, PR merge ve dağıtım yetkisi ayrı kaydedilir. Devir testi sohbet geçmişi aktarılmayan ayrı ajanla yapılır; yalnız repo yolu verilir, beş soruya cevap için beklenen sonuçlar paylaşılmaz. ZIP yoksa korunmuş envanter kullanılır ve eksik arşiv raporlanır.
