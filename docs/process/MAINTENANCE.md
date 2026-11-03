# Türkçe belge bakım ve teslim rehberi

## Durumun tek kaynağı
[project-status.json](../../project-status.json) makine kaydıdır. [CURRENT_STATUS](../../CURRENT_STATUS.md) ve [README](../../README.md) aynı gerçek durumu kısa Türkçe metinle gösterir. [START_HERE](../../START_HERE.md) kalıcı okuma sırasıdır; içine eskimeye açık aktif alt faz numarası gömme.

| Alan | Anlam |
|---|---|
| `current_phase`, `current_subphase`, `status` | Üzerinde çalışılan veya kabul bekleyen tek alt faz |
| `last_approved_phase`, `last_approved_subphase` | Açık kullanıcı kabulü; ana faz kabulü ayrı |
| `approvals` | Her DONE için `approved_at` ve `approval_reference`; varsa ayrı merge izni |
| `start_authorization` | Yalnız belirtilen alt faza başlama izni; teslim kabulü değildir |
| `branch`, `pr_url`, `commit_sha` | Gerçek GitHub teslimi; `commit_sha_scope` kanıt commit'ini açıklar |
| `previous_delivery` | Önceki kabul edilmiş PR ve gerçek merge SHA |
| `last_report`, `test_status`, `ci_status` | Son rapor, ilgili test ve CI'nin gerçek durumu |
| `blockers`, `open_questions` | Aktif iş engelleri ile sonraki geliştirmeye ait açık kararlar |
| `next_subphase`, `next_action` | Sonraki aday iş ve izin kapısı; otomatik başlama yetkisi değil |

Yeni alt fazda eski `commit_sha`/`pr_url` kopyalama; oluşturulana kadar null yaz. Kabul edilmemiş teslim `READY_FOR_REVIEW` kalır. `DONE`, push/merge ile otomatik oluşmaz. Geçmiş raporlar tarihsel kanıttır; sonraki kabul/merge gelişmelerini tarihli ek kayıtla belirt.

## Belge değişikliği sırası
1. [İş akışı](WORKFLOW.md), aktif plan ve [açık kararları](OPEN_QUESTIONS.md) oku. Kapsam dışında mimari karar alma.
2. Kaynakları UTF-8, LF ve göreli Markdown bağlantılarıyla düzenle. Sözleşme anahtarları gerektiğinde İngilizce kalır; bakım açıklamaları Türkçedir.
3. README ilk 15 satırı, CURRENT_STATUS, JSON, CHANGELOG, faz durumu ve `docs/reports/Fxx.yy.md` kaydını birlikte güncelle.
4. Ana şartnameyi üret:

```text
python scripts/build_master_plan.py
```

5. Yalnız ilgili dosyaları Git'e hazırla (`git add <ilgili yollar>`). Sonra manifesti takip edilen dosyaların mevcut byte'larından üret:

```text
python scripts/update_manifest.py
```

6. Belge ve envanter kontrollerini çalıştır; gerçek çıktıları rapora bağla:

```text
python scripts/verify_blueprint.py
python scripts/test_document_links.py
python scripts/verify_inventory.py
git diff --cached --check
```

`verify_blueprint` yerel dosya ve başlık bağlantılarını (MASTER dahil), faz kayıtlarını ve JSON örneklerini denetler. Dış URL biçimini kontrol eder; internetten sayfa erişimi veya içerik onayı vermez. `verify_inventory` manifest, CSV/registry, onay sırası, TR/EN ve sınırlı secret kalıplarını denetler. İkisi de uygulama/SSRF testi değildir. CI F00.04 kapsamındadır.

7. Log/rapor değişirse manifesti yeniden üret, kontrolleri tekrar çalıştır ve dosyaları tekrar hazırla. Logu kendi kontrolü sırasında üzerine yazma: çıktıyı geçici dosyada yakala, son logu kaydet, manifesti yenile, son kontrolü tekrar çalıştır.
8. Commit ve push yap; gerçek PR URL'sini ve ilk kanıt commit'ini kaydet. Dosya kendi son commit SHA'sını içeremez: `commit_sha` doğrulanmış içerik commit'idir; daha yeni metadata commit'i PR head'de olabilir. Son SHA kullanıcı raporunda ve PR açıklamasında verilir.
9. [Şablonla](REPORT_TEMPLATE.md) Türkçe rapor sun ve **DUR**. Kullanıcı kabulü, merge ve sonraki alt faz iznini ayrı kaydet.

## Devir testi
F00.02 için sohbet geçmişi paylaşılmayan ayrı ajana yalnız etkin repo yolu verilir. Ajan [iş akışındaki](WORKFLOW.md) beş soruyu dosya/alan kanıtıyla yanıtlar. Beklenen cevaplar görev metnine eklenmez. Gerçek yanıt, incelenen commit/dosya durumu ve sonuç rapora kaydedilir. Sayım gibi değişken sonuçları ayrı raporlara kopyalamak yerine gerçek komut günlüğüne bağla. Devir ajanı başlatılmadan önce commit/push yap; inceleme boyunca dosyaları değiştirme. Belirsizlik varsa düzelt ve yeni sabit kayıt üzerinde yeniden denetle. ZIP yoksa bunu açıkça belirt; korunmuş manifest/envanter kullanılır.

## Korunacaklar
`BUNDLE_INFO.json` ile F00.01 özgün manifest/envanter üretim anına aittir. Etkin `FILE_MANIFEST.sha256` her teslimde yenilenir. `siteportal_seo_masterplan/` eski yerel pakettir; güncel kaynak değildir. Gerçek `.env`, token ve özel veri ekleme. SEO kuralı kodlanmadan dış referansı yeniden oku ve kontrol tarihini kaydet. [Açık sorular](OPEN_QUESTIONS.md) kullanıcı kararı olmadan kapatılmaz.
