# F00.03 — Teknik karar kaynaklarının kontrol kaydı

Kontrol tarihi: 2026-11-03 (Europe/Istanbul). Aşağıdaki birincil sayfalar bu alt fazda internet üzerinden açılıp ilgili bölümleri okundu. Bu kontrol uygulama, lisans uygunluğu, runtime kurulumu veya security audit geçişi değildir. SEO kural kaynağı S1–S15 [ayrı envanterde](SOURCES.md) kalır.

| ID | Okunan birincil kaynak | Kontrol edilen konu / sınır |
|---|---|---|
| A1 | [PHP supported versions](https://www.php.net/supported-versions.php) | 8.4 için aktif/güvenlik destek tarihleri; gerçek ortam kurulurken yeniden kontrol gerekir |
| A2 | [Composer basic usage](https://getcomposer.org/doc/01-basic-usage.md) | Uygulamanın lock dosyasını commit etmesi ve install'ın kesin kilitli sürümleri kullanması |
| A3 | [Composer config](https://getcomposer.org/doc/06-config.md) | allow-plugins=false ve plugin yürütme sınırı; yeni config alanları yerel sürüm uyumluluğu varsayılarak kullanılmaz |
| A4 | [Composer CLI](https://getcomposer.org/doc/03-cli.md) | validate, audit/--locked, licenses/--locked/--format ve check-platform-reqs; komutlar manifest/lock olmadığından bu aşamada çalıştırılmadı |
| A5 | [MySQL LTS ve Innovation](https://dev.mysql.com/doc/refman/8.4/en/mysql-releases.html) | Desteklenen LTS seçimini değerlendirme; kesin runtime/yama sürümü veya veri tabanı uyumluluğu burada onaylanmadı |
| A6 | [OWASP threat modeling](https://cheatsheetseries.owasp.org/cheatsheets/Threat_Modeling_Cheat_Sheet.html) | Varlık/veri akışı/güven sınırı/tehdit/önlem ve yeniden değerlendirme yaklaşımı |
| A7 | [OWASP SSRF prevention](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html) | URL/host/IP/DNS/yönlendirme ve uygulama+ağ katmanı önlemleri; gerçek SSRF TEST'i çalıştırılmadı |

Mevcut planın kaynakları: [SYSTEM](../architecture/SYSTEM.md), [SECURITY](../architecture/SECURITY.md), [CRAWLER](../architecture/CRAWLER.md), [DATABASE](../architecture/DATABASE.md), [AGENTS](../../AGENTS.md), [F00 şartnamesi](../roadmap/F00.md). Haricî sayfaların tamamı kopyalanmadı; kararları destekleyen kısa notlar saklandı. Sürüm, lisans, paket veya yayında değişiklik olduğunda ilgili kaynak yeniden kontrol edilir.
