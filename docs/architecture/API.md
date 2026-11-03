# HTTP API sözleşmesi

## İlkeler
`/api/v1` JSON; ISO-8601 UTC timestamp; `application/problem+json` hata, HTTP kodu doğru; listelerde cursor pagination; API değişmez sürümlü. Domain/URL kullanıcı girdisi doğrudan SQL/komut/shell'e gitmez. Authentication cookie+CSRF veya scoped API key. Analiz async `202 Accepted`.

## Endpoint sözlüğü
| Method | Endpoint | Kimlik | Amaç | Sürüm |
|---|---|---|---|---|
| POST | `/api/v1/analyses` | guest+rate limit | idempotent create, 202 | V1.0 |
| GET | `/api/v1/analyses/{id}` | authorized/read token | status/scope summary | V1.0 |
| GET | `/api/v1/analyses/{id}/progress` | authorized | stages counts | V1.0 |
| GET | `/api/v1/analyses/{id}/issues` | authorized | filter+cursor | V1.0 |
| GET | `/api/v1/analyses/{id}/scores` | authorized | category/coverage | V1.0 |
| GET | `/api/v1/analyses/{id}/pages` | authorized | discovered/crawled pages | V1.1 |
| GET | `/api/v1/analyses/{id}/performance` | authorized | field/lab metrics | V1.2 |
| POST | `/api/v1/sites/verify` | site owner | DNS/file verify | V2.0 |
| POST | `/api/v1/integrations/gsc/connect` | site owner | OAuth initiation | V2.0 |
| GET | `/api/v1/sites/{id}/search-performance` | owner | authorized GSC | V2.0 |
| GET | `/api/v1/analyses/{id}/report.pdf` | owner/share | PDF | V2.0 |
| POST | `/api/v1/api-keys` | org admin | scoped key | V3.0 |

## Analiz POST örneği
```json
{"url":"https://example.com/","mode":"quick","language":"tr-TR"}
```
Başarılı async cevap:
```json
{"analysis_id":"01EXAMPLEPUBLICANALYSISID","status":"queued","status_url":"/api/v1/analyses/01EXAMPLEPUBLICANALYSISID","limits":{"max_pages":1}}
```
`Idempotency-Key` eşdeğer isteklerde aynı job'ı döndürür. Kontroller: URL, plan cap, host cap, rate, robots, worker availability. HTTP 422 invalid URL; 429 quota; 503 worker unavailable; 403 unauthorized.

## Normalleştirilmiş Finding sözleşmesi
`issue_id`, `rule_id`, `rule_version`, `category`, `status`, `severity`, `confidence`, `data_source`, `observed_at`, `title_key`, `description_key`, `evidence[]`, `affected_urls[]`/count, `fix_key`, `references[]`. Kanıtın kullanıcıya doğrudan render edilmesi XSS riski taşır; escaping zorunlu.

## API hata örneği
```json
{"type":"https://siteportal.net/problems/url-blocked","title":"URL güvenlik politikası tarafından reddedildi","status":422,"code":"URL_BLOCKED","request_id":"req_example"}
```
Gerçek alan adı kesinleşmediyse `type` canonical link değiştirilebilir. Hata nedenleri iç IP gibi sırları istemciye ayrıntılı sızdırmamalı.

## OpenAPI
`sсhemas` altında taslak sözleşme bulunur; gerçek endpoint çıktıları implementasyonla güncellenip contract tests ile doğrulanacak. v1 yayınlandıktan sonra breaking changes yeni sürüm gerektirir.
