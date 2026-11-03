#!/usr/bin/env python3
"""Blueprint paketini haricî bağımlılık olmadan denetle. Çalışan ürünü test ETMEZ."""
from pathlib import Path
import json,csv,re,sys
root=Path(__file__).resolve().parents[1]
errs=[]
required=['README.md','AGENTS.md','START_HERE.md','CURRENT_STATUS.md','CHANGELOG.md','project-status.json','docs/roadmap/ROADMAP.md','docs/requirements/RULES.md','docs/reference/SOURCES.md']
for p in required:
 if not (root/p).is_file(): errs.append(f'Missing {p}')
try:
 st=json.loads((root/'project-status.json').read_text(encoding='utf-8'))
 assert len(st['phases'])==9
 assert sum(len(v['subphases']) for v in st['phases'].values())==65
 assert st['current_subphase']=='F00.01'
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
 assert all(r['reference'].startswith('S') for r in rules)
except Exception as ex:errs.append(f'Rules invalid: {ex}')
# Yerel Markdown bağlantılarını test et; fragment ayrıca manüel test.
markdown_files=[p for p in root.rglob('*.md') if not any(part in {'.git','siteportal_seo_masterplan'} for part in p.relative_to(root).parts)]
for md in markdown_files:
 if md.name == 'MASTER_PLAN_TR.md':
  continue  # Çoklu kaynaktan birleştiği için göreli linkler kaynak konumunda doğrulanır.
 text=md.read_text(encoding='utf-8')
 for val in re.findall(r'(?<!!)\[[^\]]+\]\(([^)]+)\)',text):
  path=val.split('#')[0].split('?')[0]
  if not path or re.match(r'^[a-z][a-z0-9+.-]*://',path) or path.startswith('mailto:'):continue
  if not (md.parent/path).exists(): errs.append(f'Broken Markdown link {md.relative_to(root)} -> {val}')
for e in errs: print('ERROR:',e)
if errs: sys.exit(1)
print(f'PASS: {len(st["phases"])} ana faz, {sum(len(v["subphases"]) for v in st["phases"].values())} alt faz, {len(rules)} kural, {len(markdown_files)} Markdown dosyası; JSON ve iç bağlantılar geçerli.')
print('NOT: Bu sonuç yazılımın implementasyon veya güvenlik testlerini geçtiğini göstermez.')
