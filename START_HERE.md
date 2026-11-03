# SitePortal — Buradan başla

Bu depo, Türkçe öncelikli SEO Analyzer için geliştirme sözleşmesi ve planları içerir. Çalışan uygulama henüz yoktur. Güncel durumun kaynağı [project-status.json](project-status.json) ve [CURRENT_STATUS](CURRENT_STATUS.md) dosyalarıdır.

## Sabit okuma sırası
1. [README](README.md): ilk 15 satırdaki durum paneli.
2. [AGENTS](AGENTS.md): bağlayıcı sınırlar ve onay kuralları.
3. [CURRENT_STATUS](CURRENT_STATUS.md) ve [project-status.json](project-status.json): son kabul, aktif iş, dal, PR, test ve açık engeller.
4. [Yol haritası](docs/roadmap/ROADMAP.md), JSON'daki aktif fazın belgesi ve [iş akışı](docs/process/WORKFLOW.md).
5. JSON'daki `last_report`, [rapor dizini](docs/reports/README.md), [CHANGELOG](CHANGELOG.md) ve [açık kararlar](docs/process/OPEN_QUESTIONS.md).
6. İşin gerektirdiği [ürün/mimari belgeleri](docs/README.md) ve [resmî kaynak envanteri](docs/reference/SOURCES.md).

## Devralma kontrolü
- `git status --short`, `git branch --show-current`, `git log -1 --oneline` ile yerel durumu gör. Kullanıcının dosyalarını koru.
- Yetkili bağlantıyla GitHub `main` ve aktif PR'ı kontrol et; yalnız dosyalardaki eski SHA'ya güvenme.
- `status=READY_FOR_REVIEW` ise mevcut teslim için kullanıcı kabulünü bekle. `DONE` kullanıcı kabulüdür; `previous_delivery.merged` birleştirmeyi ayrıca izler.
- Başlangıç izni, alt faz kabulü, PR birleştirme ve üretim dağıtımı ayrı yetkilerdir. Kabul olsa bile PR merge için ayrı açık onay gerekir.
- `next_subphase`, başlama izni değildir. Aktif iş bitmeden veya yeni açık onay gelmeden sonraki alt faza geçme.

## Çalışma ve doğrulama
Etkin belgeler depo kökündedir. `siteportal_seo_masterplan/` varsa korunmuş yerel paket kopyasıdır; yeniden depoya kopyalama veya güncel durum kaynağı olarak kullanma. ZIP'in bulunmaması repo üzerinden devralmayı engellemez; özgün dosya envanteri raporlardadır.

[Bakım rehberi](docs/process/MAINTENANCE.md) komutların sırasını, manifesti ve ana şartnamenin üretilmesini açıklar. Belge kontrolleri çalışan uygulama testi değildir. Erişim veya gerekli test yoksa gerçek engeli raporla; başarı uydurma.

Tek alt faz: kapsamı oku → planı bildir → değiştir → doğrula → durum/rapor/PR kaydını güncelle → Türkçe rapor sun → **DUR**. Yeni ajan yalnız repo ile son kabulü, aktif PR'ı, test sonucunu, sonraki tek alt fazı ve açık engelleri yanıtlayabilmelidir.
