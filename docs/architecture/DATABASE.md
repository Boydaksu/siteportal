# Veri modeli — ayrıntılı taslak

## İlkeler
MySQL 8+ InnoDB, UTC veritabanı saatleri, arayüz `Europe/Istanbul` ya da seçilen locale, DB foreign key/unique indices. Migration numaralı ve geri döndürülebilir, üretim yedek alma kontrolünden sonra. Veriler: `analysis_id` ile izlenebilir, tenant yetkili, saklama politikalı, rapor sürümlü.

## Tablolar
| Tablo | Asgari önemli alanlar | Özel indeks / güvenlik |
|---|---|---|
| users | id, email, password_hash, locale, status, created_at | unique email, PII koruma |
| organizations | id, name, owner_user_id | tenant separation V3 |
| organization_members | organization_id, user_id, role | composite unique |
| sites | id, normalized_origin, owner_user_id?, organization_id?, verified_at | hostname hash, permissions |
| site_verifications | site_id, method, challenge_hash, expires_at, verified_at | token düz metin tutma |
| analyses | id, public_id, site_id, user_id?, mode, status, ruleset_version, scoring_version, scope_count, started_at, completed_at | public_id unique, site/time |
| analysis_jobs | id, analysis_id, idempotency_key, type, status, attempts, leased_until, available_at, worker_id | unique idempotency_key, status/available_at |
| pages | id, analysis_id, normalized_url_hash, url, crawl_status, http_status, depth | unique per analysis/url hash |
| page_snapshots | page_id, observed_at, content_hash, headers_json, meta_json, extraction_version | bounded JSON retention |
| links | analysis_id, source_page_id, target_url_hash, href, anchor, rel | graph source/target |
| rules | rule_id, rule_version, category, severity, definition_hash | composite unique |
| findings | id, analysis_id, rule_id, rule_version, status, severity, confidence, evidence_json, first_seen | query status/category |
| finding_pages | finding_id, page_id | unique pairing |
| scores | analysis_id, scoring_version, category, score, coverage, explanation_json | compare ruleset version |
| performance | analysis_id, page_id, source, device, metric, value, measured_at, sample_window | field/lab disjoint |
| integration_credentials | user_id, provider, encrypted_token, scopes, updated_at | application-level encryption |
| report_shares | analysis_id, token_hash, expires_at, revoked_at | expiry + owner authorization |
| rate_limits | key_hash, window_start, used_count, expires_at | anonymous privacy |
| audit_logs | actor_id, action, target, outcome, request_id, occurred_at | no tokens/PII leak |
| plans/subscriptions/usage | plan, period, owner, quota, use, provider reference | V3; no raw card data |

## İlişkiler
Site 1→N Analyses; Analysis 1→N Pages/Jobs/Findings/Scores; Finding N↔N Pages; Page 1→N Performance; User 1→N Sites; User/Organization scope all private records. `ON DELETE` behavior policy per table; never silent cascade across tenant.

## Saklama politikası (öneri, hukuki gereklilik ayrıca incelenir)
- Anonim rapor: kısa süreli (örn. 7-30 gün), private by default.
- Ham HTML: normal şartlarda saklama; debug için gerekli ise minimum şifreli/süreli.
- Sayfa bulguları: rapor yaşam süresi, verinin temizlenmesi.
- Hesap raporları: abonelik/izin ve silme isteği ile uyumlu.
- Ölçüm cache: provider penceresine uygun, kullanıcıya timestamp görünür.
- Audit log: belirlenmiş saklama süresi, secret redaction.

## Migrations
`0001_core.sql` ... `NNNN_*`; her migration reversible strateji ve test ortamı örnekleri. Migration checksum, schema_version, dry-run/backup, idempotency korunur.

## Queue adı
F00.03 Q-002 onayıyla crawler ve bu model `analysis_jobs` adını kullanır; [ADR-006](../adr/ADR-006-contract-alignment.md). Somut migration alanları ve lock davranışı F01.02/F01.05 testleriyle kesinleşir.
