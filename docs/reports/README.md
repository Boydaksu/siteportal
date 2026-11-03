# Faz raporları ve kanıt dizini

| Alt faz | Rapor | Kanıt / durum |
|---|---|---|
| F00.01 | [Depo envanteri](F00.01.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #1](https://github.com/Boydaksu/siteportal/pull/1) |
| F00.02 | [Belge sözleşmesi](F00.02.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #2](https://github.com/Boydaksu/siteportal/pull/2) |
| F00.03 | [ADR ve bağımlılık politikası](F00.03.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #3](https://github.com/Boydaksu/siteportal/pull/3) |
| F00.04 | [Test ve CI iskeleti](F00.04.md) | Aktif teslim; gerçek durum JSON/CURRENT_STATUS içinde |

Her alt fazda `Fxx.yy.md`, ana faz kapanışında `Fxx-PHASE-SUMMARY.md` oluştur. [Rapor şablonu](../process/REPORT_TEMPLATE.md) ve [bakım rehberi](../process/MAINTENANCE.md) kullanılır. [Özgün paket envanteri](F00.01-inventory.json), [özgün manifest](F00.01-original-manifest.sha256) ve [F00.01 kontrol çıktıları](F00.01-checks.txt) tarihsel kanıttır; sonraki test başarısını temsil etmez. Aktif teslimin gerçek sonuçlarını kendi raporunda tut. Kullanıcıya Türkçe rapor sun ve DUR.
