#!/usr/bin/env python3
"""Blueprint paketini haricî bağımlılık olmadan denetle. Çalışan ürünü test ETMEZ."""
from pathlib import Path
import json,csv,re,sys,subprocess
from document_links import validate_documents
root=Path(__file__).resolve().parents[1]
errs=[]
required=['README.md','AGENTS.md','START_HERE.md','CURRENT_STATUS.md','CHANGELOG.md','project-status.json','MASTER_PLAN_TR.md','docs/README.md','docs/process/MAINTENANCE.md','docs/process/OPEN_QUESTIONS.md','docs/roadmap/ROADMAP.md','docs/requirements/RULES.md','docs/reference/SOURCES.md']
for p in required:
 if not (root/p).is_file(): errs.append(f'Missing {p}')
try:
 st=json.loads((root/'project-status.json').read_text(encoding='utf-8'))
 assert len(st['phases'])==9
 assert sum(len(v['subphases']) for v in st['phases'].values())==65
 assert st['current_phase'] in st['phases']
 assert st['current_subphase'] in st['phases'][st['current_phase']]['subphases']
 assert st['status']==st['phases'][st['current_phase']]['subphases'][st['current_subphase']]
 for name,data in st['phases'].items():
  assert (root/f'docs/roadmap/{name}.md').is_file()
  for sf in data['subphases']:
   assert f'### {sf} ' in (root/f'docs/roadmap/{name}.md').read_text(encoding='utf-8')
except Exception as ex: errs.append(f'Phase status invalid: {ex}')
for p in (root/'locales').glob('*.json'):
 try:json.loads(p.read_text(encoding='utf-8'))
 except Exception as ex:errs.append(f'Locale invalid {p}: {ex}')
try:
 tr=json.loads((root/'locales/tr-TR.example.json').read_text(encoding='utf-8'))
 en=json.loads((root/'locales/en-US.example.json').read_text(encoding='utf-8'))
 assert set(tr)==set(en),'tr/en translation keys differ'
except Exception as ex:errs.append(f'Locale key mismatch: {ex}')
for p in (root/'examples').glob('*.json'):
 try:json.loads(p.read_text(encoding='utf-8'))
 except Exception as ex:errs.append(f'Example invalid {p}: {ex}')
try:
 rules=json.loads((root/'schemas/rule-registry.seed.json').read_text(encoding='utf-8'))
 assert len(rules)>=80
 assert len({r['id'] for r in rules})==len(rules)
 reference_ids=set(re.findall(r'^\| (S\d+) \|',(root/'docs/reference/SOURCES.md').read_text(encoding='utf-8'),re.M))
 assert all(r['reference'] in reference_ids for r in rules),'Rule source ID missing in SOURCES.md'
except Exception as ex:errs.append(f'Rules invalid: {ex}')
# Etkin belgelerde yerel dosya/başlık hedeflerini ve dış URL biçimini denetle.
project_paths=subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard','-z'],cwd=root).decode('utf-8').split('\0')
markdown_files=[root/p for p in sorted(set(project_paths)) if p.endswith('.md')]
link_errors,link_count=validate_documents(root,markdown_files)
errs.extend(link_errors)
for e in errs: print('ERROR:',e)
if errs: sys.exit(1)
print(f'PASS: {len(st["phases"])} ana faz, {sum(len(v["subphases"]) for v in st["phases"].values())} alt faz, {len(rules)} kural, {len(markdown_files)} Markdown dosyası; JSON ve iç bağlantılar geçerli.')
print('NOT: Bu sonuç yazılımın implementasyon veya güvenlik testlerini geçtiğini göstermez.')
print(f'Bağlantı kontrolü: {link_count} hedef; yerel başlıklar ve MASTER dahil. Dış URL içerik/erişimi kontrol edilmedi.')
