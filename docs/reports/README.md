# Faz raporları ve kanıt dizini

| Alt faz | Rapor | Kanıt / durum |
|---|---|---|
| F00.01 | [Depo envanteri](F00.01.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #1](https://github.com/Boydaksu/siteportal/pull/1) |
| F00.02 | [Belge sözleşmesi](F00.02.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #2](https://github.com/Boydaksu/siteportal/pull/2) |
| F00.03 | [ADR ve bağımlılık politikası](F00.03.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #3](https://github.com/Boydaksu/siteportal/pull/3) |
| F00.04 | [Test ve CI iskeleti](F00.04.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #4](https://github.com/Boydaksu/siteportal/pull/4) |
| F00.05 | [Repo ve PR politikası](F00.05.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #5](https://github.com/Boydaksu/siteportal/pull/5) |
| F00.06 | [F00 faz kapanış ve kabul](F00.06.md) | DONE; kullanıcı kabulü ve PR #6 |
| **F00 Özeti** | [F00 Ana Faz Kapanış Özeti](F00-PHASE-SUMMARY.md) | DONE; F00 ana fazı onaylandı |
| F01.01 | [Proje çalışma iskeleti](F01.01.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #7](https://github.com/Boydaksu/siteportal/pull/7) |
| F01.02 | [Kurulum ve migration motoru](F01.02.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #8](https://github.com/Boydaksu/siteportal/pull/8) |
| F01.03 | [URL doğrulama ve SSRF koruması](F01.03.md) | DONE; kullanıcı kabulü ve birleştirilmiş [PR #9](https://github.com/Boydaksu/siteportal/pull/9) |
| F01.04 | [HTTP istemcisi ve robots uyumu](F01.04.md) | READY_FOR_REVIEW; kullanıcı kabulü ve açık onayı bekleniyor |

Her alt fazda `Fxx.yy.md`, ana faz kapanışında `Fxx-PHASE-SUMMARY.md` oluştur. [Rapor şablonu](../process/REPORT_TEMPLATE.md) ve [bakım rehberi](../process/MAINTENANCE.md) kullanılır. [Özgün paket envanteri](F00.01-inventory.json), [özgün manifest](F00.01-original-manifest.sha256) ve [F00.01 kontrol çıktıları](F00.01-checks.txt) tarihsel kanıttır; sonraki test başarısını temsil etmez. Aktif teslimin gerçek sonuçlarını kendi raporunda tut. Kullanıcıya Türkçe rapor sun ve DUR.
