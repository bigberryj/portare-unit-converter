#!/usr/bin/env python3
"""Build a runtime-only WordPress plugin archive without private/dev files."""
from pathlib import Path
import zipfile,hashlib,re
ROOT=Path(__file__).resolve().parents[1]
SLUG='portare-unit-converter'
files=[ROOT/'portare-unit-converter.php',ROOT/'uninstall.php',ROOT/'readme.txt',ROOT/'README.md',ROOT/'LICENSE']
for folder in ['includes','assets','docs']:
 files.extend(p for p in (ROOT/folder).rglob('*') if p.is_file())
missing=[str(p) for p in files if not p.is_file()]
if missing: raise SystemExit('Missing runtime files: '+', '.join(missing))
version=re.search(r'\* Version:\s*(\S+)',(ROOT/'portare-unit-converter.php').read_text()).group(1)
path=ROOT/'dist'/f'{SLUG}-{version}.zip';path.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(path,'w',zipfile.ZIP_DEFLATED) as archive:
 for source in sorted(files): archive.write(source,SLUG+'/'+str(source.relative_to(ROOT)))
with zipfile.ZipFile(path) as archive:
 assert archive.testzip() is None
 assert all(n.startswith(SLUG+'/') and not any(x in n for x in ['node_modules','.git/','cookies','session-token']) for n in archive.namelist())
print(str(path));print('SHA256',hashlib.sha256(path.read_bytes()).hexdigest());print('Runtime files:',len(files))
