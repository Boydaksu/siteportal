# Test stratejisi ve Definition of Done

## Test piramidi
- **Unit:** URL normalize, IP CIDR public vs private, redirect validation, robots groups, canonical extraction, malformed DOM, score denominator, locale placeholder.
- **Contract:** API request/response schema, 202/422/429/503 codes, idempotent POST, pagination, issue evidence schema, OAuth provider mocks.
- **Integration:** MySQL migrations, job lease race/retry, crawler request budgets, XML streaming, cache, report TTL.
- **E2E:** Guest URL→job→complete, noindex, blocked host, payment plan, GSC revoke, mobile/i18n navigation (ilgili sürümden itibaren).
- **Security:** SSRF adversarial fixtures, XSS, SQL injection, XXE, zip bomb, CSRF, ACL IDOR, API key and token leak, rate limit bypass.
- **Quality:** 30+ kuralın fixture'ları (positive, negative, not_applicable, unknown), false positive review; snapshot tests, source validity.
- **Performance:** 1, 10, 100, 500, 10k sentetik URL benchmark (sürüme bağlı), no runaway worker; memory/CPU, queue latency, DB indexes.
- **Resilience:** API outage, DNS timeout, 429/503, robots failure, queue crash, worker restart, partial report, rate limit.
- **A11y/i18n:** Klavye, screen reader semantics, focus, contrast, Türkçe/İngilizce çeviri anahtarları, Turkish locale collation.

## Release engelleyici kriterler
- SSRF critical bypass, internal IP erişimi, OAuth/tenant leak, özel rapor yetki zafiyeti, XSS, worker sonsuz döngü, metrics uydurma, kaynağı belirsiz kesin SEO iddiası.
- Fail olan veya çalıştırılmamış test için 'testleri geçti' denmez. Zorunlu test çalıştırılamıyorsa `BLOCKED`, mock/synthetic test açıkça etiketlenir.

## Güvenlik fixture örnekleri
`http://127.0.0.1`, `http://[::1]`, `http://169.254.169.254`, private IPv6/IPv4, encoded loopback, redirect public->private, DNS rebinding, escaped `@` ve ters eğik çizgi, malformed HTTP/HTML, sitemap external entities ve zip bomb. Saldırı kodu değil, kontrollü test ortamı.

## DoD checklist
- [ ] Kod veya sözleşme alt faz sınırında.
- [ ] Kaynak/algoritma/format doğrulanmış ve referanslı.
- [ ] En az mutlu yol, olumsuz yol, yetki/güvenlik ve veri yok senaryosu testli.
- [ ] Lint, statik analiz, migration testleri yeşil.
- [ ] Yeni metinlerin tr-TR/en-US anahtarı ve UI a11y kontrolü var.
- [ ] Kapsam, kaynak, zaman damgası ve unknown semantics korundu.
- [ ] README, CURRENT_STATUS, JSON, CHANGELOG, alt faz raporu ve GitHub PR/SHA güncel.
- [ ] Kullanıcı raporu sunuldu ve **onay bekleniyor**.

## Faz kapanış / regresyon
Sürümün bütün kabul kriterleri; önceki sürümlerin kritik regression suite'i; güvenlik risk değerlendirmesi; gerçek demo veya açık blocker; migration rollback; depolama ve API quota; source freshness; KVKK/retention; geri alma planı. Ana faz onayı olmadan sonraki sürüme geçilmez.
