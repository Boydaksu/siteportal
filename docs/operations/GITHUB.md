# Boydaksu/siteportal GitHub çalışma stratejisi

## Repo
`https://github.com/Boydaksu/siteportal` — 2026-11-03 incelemesinde özel repo, `main`, çok kısa `README.md`. Bu tespit **o anın durumudur**; her faz öncesi yeniden `fetch` gerekir. Başka dosyalar ortaya çıktıysa dosyaları ezme ve merge otomatik yapma.

## Branch / commit / PR
- Her alt faz: `phase/F00.01-repo-inventory`, `phase/F01.03-ssrf`, `phase/F02.05-score` gibi tek dal.
- Düzeltme: aynı PR dalına ek commit; kabul edilen fazın regresyonu `fix/F02.05-...`.
- Commit örn. `feat(seo): F02.03 teknik denetim kuralları`.
- PR başlığı `[F02.03] Teknik denetim kuralları`, ekinde görev, test, kaynak, screenshots, güvenlik, rollback, review checklist.
- Her alt faz PR'ı `README/CURRENT_STATUS/project-status/CHANGELOG/docs/reports` değişikliklerini içerir.
- Önerilen main koruma: PR ve test gereksinimi, 1 manuel onay, force-push kapalı. Bu repo ayarlarını değiştirmek ayrıca kullanıcı onayı gerektirir.
- **Push etme yetkisi yoksa** gerçek commit SHA/PR uydurma. `BLOCKED` + patch veya dosya paketi bırak.

## README durum tutarlılığı
`main` en son **kabul edilmiş ve merge edilmiş** aşamayı göstermeli; açık PR dalında `READY_FOR_REVIEW` bulunabilir. Kullanıcı onayı aldıktan sonra merge sırasında `main` durumu `DONE` olacak biçimde düzelt. 'Push edildi' ile 'kabul edildi' ayrımı JSON'da saklanmalı. README en üstte durum paneli olmalı.

## Handoff kanıtları
Her raporda repo SHA, branch, PR URL, CI status URL, gerçek test sonuçları, değişen dosyalar ve sıradaki tek subphase. PR birleştirilmeden üretime deploy yasak.

## İlk plan paketini aktarma
Bu ZIP'i yerelde klasöre aç, projedeki dosyaları repo dizinine kopyala, **mevcut README'yi karşılaştırarak** birleştir. `git status` ve `git diff` kontrol et. İlk faz F00.01 kapsamında özel bir PR aç, onaydan önce merge etme. Gerekirse doküman dosyalarını `docs/` altında tutup SitePortal uygulamasıyla daha sonra entegre et.

## Git işlemi örneği (kullanıcı onayı ve gerçek repo durumu teyidinden sonra)
```bash
git clone git@github.com:Boydaksu/siteportal.git
cd siteportal
git switch main && git pull --ff-only
git switch -c phase/F00.01-repo-inventory
# ZIP'deki belgeleri karşılaştırıp kopyala; var olan içeriği ezme.
git status --short
git diff --check
git add README.md AGENTS.md START_HERE.md CURRENT_STATUS.md CHANGELOG.md project-status.json docs/ schemas/ locales/ .github/ .gitignore .env.example
# Yalnız gerçekten mevcut dosyaları stage et.
git commit -m "docs: F00.01 plan ve repo envanteri"
git push -u origin phase/F00.01-repo-inventory
# GitHub'da PR aç, inceleme bekle. Sonraki subphase'e GEÇME.
```
