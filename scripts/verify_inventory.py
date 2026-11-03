#!/usr/bin/env python3
"""Belge/envanter ve faz kaydı kontrolleri; ürün güvenlik testi değildir."""
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
subphases = [(key, value) for data in status["phases"].values() for key, value in data["subphases"].items()]
names = [key for key, _ in subphases]
active_index = names.index(status["current_subphase"])
check(status["status"] == dict(subphases)[status["current_subphase"]], "Aktif alt faz ve üst durum eşleşiyor")
check(all(value == "DONE" for _, value in subphases[:active_index]), "Aktif alt fazın öncekileri DONE")
check(all(value == "NOT_STARTED" for _, value in subphases[active_index + 1:]), "Sonraki alt fazlar başlamadı")
approved = [key for key, value in subphases if value == "DONE"]
check(status["last_approved_subphase"] == (approved[-1] if approved else None), "Son onaylı alt faz DONE kayıtlarıyla tutarlı")
check(all(status.get("approvals", {}).get(key, {}).get("approved_at") and status["approvals"][key].get("approval_reference") for key in approved), "Her DONE için tarih ve kullanıcı onay kanıtı var")
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
if status["current_phase"] == "F00":
    check(not any(name.endswith(".php") for name in tracked), "F00 belge kapsamına PHP uygulama kodu eklenmedi")
print("NOT: Bu kontroller PHP/DB/crawler birim, entegrasyon veya güvenlik testleri değildir.")
