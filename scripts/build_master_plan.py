#!/usr/bin/env python3
"""Tek dosyalık, okunabilir MASTER_PLAN_TR.md exportu üret."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
paths=['README.md','AGENTS.md','START_HERE.md','CURRENT_STATUS.md',
'docs/requirements/PRODUCT.md','docs/requirements/FEATURE_MATRIX.md','docs/requirements/PRIORITIES.md',
'docs/requirements/UX.md','docs/requirements/RULES.md','docs/requirements/SCORING.md','docs/requirements/ERROR_STATES.md',
'docs/architecture/SYSTEM.md','docs/architecture/CRAWLER.md','docs/architecture/SECURITY.md','docs/architecture/DATABASE.md','docs/architecture/API.md','docs/architecture/I18N.md','docs/architecture/INTEGRATIONS.md','docs/architecture/OPERATIONS.md',
'docs/operations/GITHUB.md','docs/operations/PRIVACY.md','docs/operations/RELEASE.md',
'docs/process/WORKFLOW.md','docs/process/QA.md','docs/process/REPORT_TEMPLATE.md','docs/process/DECISIONS.md',
'docs/roadmap/ROADMAP.md']+[f'docs/roadmap/F{i:02d}.md' for i in range(9)]+['docs/reference/SOURCES.md']
sections=['# SITEPORTAL SEO ANALYZER — TEK DOSYALIK TÜRKÇE ANA ŞARTNAME','',
'> Bu dosya ZIP içindeki modüler belgelerden üretilmiş **okunabilir kopyadır**. Değişikliklerin tek gerçek kaynağı ilgili ayrı belgelerdir. Son durum için `CURRENT_STATUS.md` ve GitHub main kontrol edilir.','',
'> Oluşturma: `python3 scripts/build_master_plan.py`. İlk hazırlanma tarihi: 2026-11-03.','']
for p in paths:
 f=root/p
 content=f.read_text(encoding='utf-8')
 # Kaynaktaki yerel Markdown linklerini köke göre düzenle (master dosya kök dizindedir).
 def fix_link(m):
  label,link=m.group(1),m.group(2)
  if link.startswith(('https://','http://','mailto:','#')): return m.group(0)
  target=(f.parent/link).resolve()
  try: root_rel=target.relative_to(root.resolve())
  except ValueError: return m.group(0)
  return f'[{label}]({root_rel.as_posix()})'
 content=re.sub(r'(?<!!)\[([^\]]+)\]\(([^)]+)\)',fix_link,content)
 sections.extend(['\n---\n',f'<!-- KAYNAK BELGE: {p} -->',f'## BELGE: `{p}`','',content, '\n'])
(root/'MASTER_PLAN_TR.md').write_text('\n'.join(sections).rstrip()+'\n',encoding='utf-8',newline='\n')
print('MASTER_PLAN_TR.md generated from',len(paths),'documents')
