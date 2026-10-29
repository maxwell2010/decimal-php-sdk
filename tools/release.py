"""Reproducible source packages and a stable-URL Composer installation repository."""
import hashlib
import json
import pathlib
import re
import shutil
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
VERSION = (ROOT / 'VERSION').read_text(encoding='ascii').strip()
if not re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9.]+)?',VERSION):
    raise ValueError('Invalid release VERSION')
REPOSITORY='https://github.com/maxwell2010/decimal-php-sdk'
TOP={'src','abi','docs','examples','resources','tests','tools','.github'}
FILES={'VERSION','README.md','README.ru.md','CHANGELOG.md','SECURITY.md','THIRD_PARTY_NOTICES.md','LICENSE','THIRD_PARTY_JS_LICENSE','THIRD_PARTY_NODE_LICENSE','composer.json','composer.lock','.gitignore','.gitattributes'}

def sources():
    for path in sorted(ROOT.rglob('*'),key=lambda p:p.relative_to(ROOT).as_posix()):
        relative=path.relative_to(ROOT)
        if not path.is_file() or any(part in {'vendor','.git','__pycache__','dist'} for part in relative.parts):continue
        if relative.parts[0] not in TOP and relative.as_posix() not in FILES:continue
        if path.is_symlink() or not path.resolve().is_relative_to(ROOT.resolve()):raise RuntimeError('Source paths must remain inside the package root')
        if path.suffix in {'.pyc','.log','.pem','.key','.env'} or path.name.startswith('.env'):raise RuntimeError('Unexpected sensitive/local file')
        data=path.read_bytes()
        if b'-----BEGIN '+b'PRIVATE KEY-----' in data or b'-----BEGIN '+b'EC PRIVATE KEY-----' in data:raise RuntimeError('Private key material found')
        yield path,relative

def build():
    dest=ROOT/'dist';dest.mkdir(exist_ok=True)
    archive=dest/f'decimal-php-sdk-{VERSION}.zip'
    with zipfile.ZipFile(archive,'w',compression=zipfile.ZIP_DEFLATED) as z:
        for path,relative in sources():
            item=zipfile.ZipInfo(f'decimal-php-sdk-{VERSION}/{relative.as_posix()}',date_time=(2026,10,29,0,0,0));item.create_system=3;item.compress_type=zipfile.ZIP_DEFLATED;item.external_attr=0o644<<16;z.writestr(item,path.read_bytes())
    alias=dest/'decimal-php-sdk.zip';shutil.copyfile(archive,alias)
    package=json.loads((ROOT/'composer.json').read_text())
    package.pop('repositories',None)
    package.pop('scripts',None)
    package.pop('archive',None)
    package.update(version=VERSION,dist={'type':'zip','url':f'{REPOSITORY}/releases/download/v{VERSION}/{archive.name}','shasum':hashlib.sha1(archive.read_bytes()).hexdigest()},source={'type':'git','url':REPOSITORY+'.git','reference':'v'+VERSION})
    metadata=dest/'packages.json'
    metadata.write_text(json.dumps({'packages':{package['name']:{VERSION:package}}},indent=2)+'\n',encoding='utf-8',newline='\n')
    checksums=''.join(f'{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.name}\n' for p in [archive,alias,metadata])
    (dest/'SHA256SUMS').write_text(checksums,encoding='ascii',newline='\n')
    print(checksums,end='')
    return archive

if __name__=='__main__':build()
