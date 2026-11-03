#!/usr/bin/env python3
"""F00.03 belge/sözleşme ve bağımlılık kayıtları; runtime güvenlik testi değil."""
from pathlib import Path
import json
import re

root = Path(__file__).resolve().parents[1]


def check(value, label):
    if not value:
        raise SystemExit(f"FAIL: {label}")
    print(f"PASS: {label}")


def read(name):
    return (root / name).read_text(encoding="utf-8")


records = json.loads(read("docs/adr/architecture-decisions.json"))
inventory = json.loads(read("docs/process/dependencies.json"))
for field in ["stack_record", "module_matrix", "dependency_policy", "dependency_inventory", "threat_model"]:
    check((root / records[field]).is_file(), f"ADR referansı mevcut: {field}")
check(records["baseline_adr_ids"] == [f"ADR-{n:03d}" for n in range(1, 6)], "Başlangıç ADR kimlikleri korundu")
questions = records["question_decisions"]
check(set(questions) == {"Q-001", "Q-002", "Q-003"}, "Üç sözleşme farkı karar kaydında")
for name, decision in questions.items():
    check(decision["status"] in {"PROPOSED", "APPROVED"}, f"{name} geçerli karar durumu")
    check((root / decision["adr"]).is_file(), f"{name} gerekçeli ADR mevcut")
    if decision["status"] == "APPROVED":
        check(bool(decision.get("approval_reference") and decision.get("approved_at")), f"{name} açık kullanıcı onayı kayıtlı")
    else:
        check(decision["approval_reference"] is None, f"{name} öneri kabul edilmiş gösterilmedi")

check(inventory["phase"] == "F00.03", "Bağımlılık envanteri kaynak alt fazını taşıyor")
check(not records["implementation_started"], "ADR kaydı uygulama geliştirilmiş iddiası taşımıyor")
manifests = [root / name for name in ["composer.json", "composer.lock", "package.json", "package-lock.json", "yarn.lock", "pnpm-lock.yaml"]]
check(inventory["application_manifests_present"] == any(p.exists() for p in manifests), "Paket manifesti durumu gerçek dosyalarla aynı")
required = set(inventory["package_record_required_fields"])
for package in inventory["application_packages"]:
    check(required <= package.keys() and all(package.get(k) for k in required), f"Paket sürüm/lisans/güvenlik/onay kaydı tam: {package.get('name')}")
if not inventory["application_packages"]:
    check(not any(p.exists() for p in manifests), "Yeni uygulama framework/paketi veya lock eklenmedi")
check(all(p["resolved_version"] is None and p["status"] == "PLANNED" for p in inventory["platform_requirements"]), "Planlanan platform için sahte resolved sürüm yok")
check(not inventory["toolchain_observation"]["runtime_versions_or_extensions_tested"], "Runtime uyumluluğu test edilmiş gösterilmedi")

model = read(records["threat_model"])
rows = re.findall(r"^\| (T\d{2}) /[^\n]+", model, re.M)
check(rows == [f"T{n:02d}" for n in range(1, 16)], "T01–T15 tehdit kimlikleri eksiksiz ve tekil")
check(all(f"TB{n}" in model for n in range(1, 8)), "Yedi güven sınırı kaydedildi")
known_phases = {key for phase in json.loads(read("project-status.json"))["phases"].values() for key in phase["subphases"]}
check(set(re.findall(r"F\d{2}\.\d{2}", model)) <= known_phases, "Tehdit doğrulama kapıları gerçek yol haritasında")
check(all(term in model for term in ["NOT_IMPLEMENTED", "SSRF", "tenant", "UNKNOWN", "lease", "OAuth", "LLM"]), "Kritik kontrol alanları ve uygulanmama durumu açık")

schema = json.loads(read("examples/finding.schema.json"))
demo = json.loads(read("examples/analysis-response.example.json"))
check(demo["_demo"] is True, "Örnek yanıt DEMO olarak işaretli")
for finding in demo["findings"]:
    check(set(schema["required"]) <= finding.keys(), "DEMO bulguda mevcut şemanın zorunlu alanları var")
    for field, definition in schema["properties"].items():
        if field not in finding:
            continue
        value = finding[field]
        if definition.get("type") == "array":
            check(isinstance(value, list), f"DEMO {field}: array")
            if definition.get("items", {}).get("type") == "object":
                check(all(isinstance(item, dict) for item in value), f"DEMO {field}: nesne listesi")
        elif definition.get("type") == "object":
            check(isinstance(value, dict), f"DEMO {field}: object")
        elif definition.get("type") == "string":
            check(isinstance(value, str), f"DEMO {field}: string")
        if "enum" in definition:
            check(value in definition["enum"], f"DEMO {field}: geçerli enum")
if questions["Q-001"]["status"] == "APPROVED":
    check("data_source" in schema["required"] and "source" not in schema["properties"], "Q-001: tek data_source alanı")
    check(schema["properties"]["evidence"].get("type") == "array" and schema["properties"]["evidence"].get("items", {}).get("type") == "object", "Q-001: evidence nesne dizisi")
if questions["Q-002"]["status"] == "APPROVED":
    crawler = read("docs/architecture/CRAWLER.md")
    check("`analysis_jobs(" in crawler and "`jobs(" not in crawler, "Q-002: crawler queue adı analysis_jobs")
    check("| analysis_jobs |" in read("docs/architecture/DATABASE.md"), "Q-002: DB/crawler adı eşliği")
if questions["Q-003"]["status"] == "APPROVED":
    check("her uygulanan özellik" in read("docs/architecture/I18N.md"), "Q-003: uygulanan özelliklerde dil kapsamı açık")
print("NOT: DEMO kontrolü zorunlu alan/type/enum eşliğidir; tam JSON Schema motoru, gerçek API, CVE/lisans audit veya SSRF testi değildir.")
