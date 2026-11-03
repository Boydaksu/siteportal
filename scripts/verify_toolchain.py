#!/usr/bin/env python3
"""Lock, dev-only sınırı, lisans kanıtları ve CI pin'lerini kontrol et."""
from pathlib import Path
import hashlib
import json
import re

root = Path(__file__).resolve().parents[1]


def check(value, label):
    if not value:
        raise SystemExit(f"FAIL: {label}")
    print(f"PASS: {label}")


def read_json(name):
    return json.loads((root / name).read_text(encoding="utf-8"))


manifest = read_json("composer.json")
lock = read_json("composer.lock")
inventory = read_json("docs/process/dependencies.json")
review = read_json("docs/reports/F00.04-dependency-review.json")
check(manifest["require"] == {"php": ">=8.4 <9.0"} and not lock["packages"], "Uygulama paketi yok; yalnız PHP platform şartı")
check(manifest["config"]["allow-plugins"] is False and manifest["config"]["secure-http"] is True, "Composer plugin kapalı ve HTTPS zorunlu")
check(not manifest.get("scripts"), "Otomatik/root Composer script'i eklenmedi")
check("license" not in manifest and inventory["project_distribution_license"] == "UNDECIDED", "Q-004 için yetkisiz kök lisans kararı alınmadı")
packages = {p["name"]: p for p in lock["packages-dev"]}
reviewed = {p["name"]: p for p in review["packages"]}
registered = {p["name"]: p for p in inventory["development_packages"]}
check(packages.keys() == reviewed.keys() == registered.keys(), "Bütün doğrudan/geçişli dev paketleri incelenmiş ve kayıtlı")
for name, version in manifest["require-dev"].items():
    check(bool(re.fullmatch(r"\d+\.\d+\.\d+", version)) and packages[name]["version"].lstrip("v") == version, f"Kesin doğrudan sürüm: {name}")
for name, package in packages.items():
    check(package["version"] == reviewed[name]["version"] == registered[name]["resolved_version"], f"Lock/inceleme/envanter sürüm eşliği: {name}")
    check(package["dist"]["reference"] == reviewed[name]["dist"]["reference"] == registered[name]["reference_or_integrity"], f"Kilitli artifact reference: {name}")
    check(package["license"] == reviewed[name]["license_spdx"] == registered[name]["license_spdx"], f"Lisans metadata eşliği: {name}")
    for license_file in reviewed[name]["license_files"]:
        installed = root / license_file["installed_path"]
        check(installed.is_file() and hashlib.sha256(installed.read_bytes()).hexdigest() == license_file["sha256"], f"Kurulu LICENSE/NOTICE inceleme ile aynı: {name}")
workflow = (root / ".github/workflows/quality.yml").read_text(encoding="utf-8")
actions = re.findall(r"uses:\s+([^\s@]+)@([^\s]+)", workflow)
check({name: sha for name, sha in actions} == {a["name"]: a["reference"] for a in inventory["ci_actions"]}, "CI action sürüm kaydı workflow ile aynı")
check(all(re.fullmatch(r"[0-9a-f]{40}", sha) for _, sha in actions), "Action'lar tam commit SHA ile sabit")
check("contents: read" in workflow and "persist-credentials: false" in workflow and "pull_request_target" not in workflow, "CI minimum token yetkisi; kalıcı credential ve privileged PR trigger yok")
print("NOT: Bu denetim güncel CVE veritabanını sorgulamaz; composer audit ayrı çalıştırılır. Paketlenmiş PHAR iç bileşenleri ve hukuki uygunluk sertifikası kapsam dışı.")
