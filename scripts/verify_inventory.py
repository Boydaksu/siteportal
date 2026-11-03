#!/usr/bin/env python3
"""F00.01 belge/envanter kontrolleri; çalışan ürün güvenlik testi değildir."""
from pathlib import Path
import csv
import hashlib
import json
import re
import subprocess

root = Path(__file__).resolve().parents[1]


def check(condition, label):
    if not condition:
        raise SystemExit(f"FAIL: {label}")
    print(f"PASS: {label}")


inventory = json.loads((root / "docs/reports/F00.01-inventory.json").read_text(encoding="utf-8"))
check(len(inventory["original_package_files"]) == 57, "Başlangıç paketi: 57 dosya kayıtlı")
manifest = (root / "FILE_MANIFEST.sha256").read_text(encoding="utf-8").splitlines()
tracked = subprocess.check_output(["git", "ls-files"], cwd=root, text=True).splitlines()
listed = set()
for entry in manifest:
    expected, name = entry.split("  ", 1)
    listed.add(name)
    check(hashlib.sha256((root / name).read_bytes()).hexdigest() == expected, f"SHA256 {name}")
check(listed == set(tracked) - {"FILE_MANIFEST.sha256"}, "Manifest bütün takip edilen dosyaları kapsıyor")
for name in tracked:
    if name.endswith(".json"):
        json.loads((root / name).read_text(encoding="utf-8"))
check(True, "Takip edilen bütün JSON dosyaları ayrıştırılıyor")
rules = json.loads((root / "schemas/rule-registry.seed.json").read_text(encoding="utf-8"))
with (root / "docs/requirements/rules-catalog.csv").open(encoding="utf-8-sig", newline="") as stream:
    catalog = list(csv.DictReader(stream))
check(rules == catalog and len(rules) == 84, "BOM uyumlu CSV ile JSON: aynı 84 kayıt")
status = json.loads((root / "project-status.json").read_text(encoding="utf-8"))
for phase, data in status["phases"].items():
    headings = re.findall(r"^### (F\d{2}\.\d{2}) —", (root / f"docs/roadmap/{phase}.md").read_text(encoding="utf-8"), re.M)
    check(headings == list(data["subphases"]), f"{phase} plan/JSON sırası ve tam kapsamı")
check(sum(len(v["subphases"]) for v in status["phases"].values()) == 65, "Toplam 65 alt faz")
check(status["current_subphase"] == "F00.01" and status["last_approved_subphase"] is None, "Aktif alt faz F00.01; onay uydurulmadı")
check(all(value == "NOT_STARTED" for data in status["phases"].values() for key, value in data["subphases"].items() if key != "F00.01"), "Sonraki 64 alt faz başlamadı")
tr = json.loads((root / "locales/tr-TR.example.json").read_text(encoding="utf-8"))
en = json.loads((root / "locales/en-US.example.json").read_text(encoding="utf-8"))
check(tr.keys() == en.keys() and all(re.findall(r"\{\w+\}", tr[k]) == re.findall(r"\{\w+\}", en[k]) for k in tr), "TR/EN anahtar ve parametre eşliği")
check(not subprocess.check_output(["git", "diff", "--name-only", "--diff-filter=D", "origin/main"], cwd=root, text=True).strip(), "Başlangıç main dosyalarından silinen yok")
patterns = [r"gh[pousr]_[A-Za-z0-9]{30,}", r"github_pat_[A-Za-z0-9_]{40,}", r"AKIA[A-Z0-9]{16}", r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"]
suspects = []
for name in tracked:
    if name == "scripts/verify_inventory.py":
        continue
    content = (root / name).read_text(encoding="utf-8-sig")
    if any(re.search(pattern, content) for pattern in patterns):
        suspects.append(name)
check(not suspects, "Sınırlı secret kalıp taraması: bulgu yok (tam güvenlik denetimi değildir)")
check(not any(name == ".env" or (Path(name).name.startswith(".env.") and Path(name).name != ".env.example") for name in tracked), "Gerçek .env dosyası takip edilmiyor")
check(not any(name.endswith(".php") for name in tracked), "F00.01 kapsamında PHP uygulama kodu eklenmedi")
print("NOT: PHP/DB/crawler birim, entegrasyon ve güvenlik testleri uygulanamaz; uygulama yok.")
