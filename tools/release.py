"""Build an allowlisted source archive without Git history, vendor or local credentials."""
import hashlib
import pathlib
import zipfile

ROOT = pathlib.Path(__file__).resolve().parents[1]
VERSION = '0.1.0-alpha.1'
TOP = {'src','abi','docs','examples','resources','tests','tools','.github'}
FILES = {'README.md','README.ru.md','CHANGELOG.md','SECURITY.md','LICENSE','THIRD_PARTY_NOTICES.md','THIRD_PARTY_JS_LICENSE','THIRD_PARTY_NODE_LICENSE','composer.json','composer.lock','.gitignore','.gitattributes'}

def sources():
    for path in sorted(ROOT.rglob('*')):
        relative=path.relative_to(ROOT)
        if not path.is_file() or any(part in {'vendor','.git','__pycache__','dist'} for part in relative.parts):continue
        if relative.parts[0] not in TOP and relative.as_posix() not in FILES:continue
        if path.suffix in {'.pyc','.log','.pem','.key','.env'} or path.name.startswith('.env'):raise RuntimeError('Unexpected sensitive/local file')
        data=path.read_bytes()
        if b'-----BEGIN '+b'PRIVATE KEY-----' in data or b'-----BEGIN '+b'EC PRIVATE KEY-----' in data:raise RuntimeError('Private key material found')
        yield path,relative

def build():
    dest=ROOT/'dist';dest.mkdir(exist_ok=True);archive=dest/f'decimal-php-sdk-{VERSION}.zip'
    with zipfile.ZipFile(archive,'w',compression=zipfile.ZIP_DEFLATED) as z:
        for path,relative in sources():
            item=zipfile.ZipInfo(f'decimal-php-sdk-{VERSION}/{relative.as_posix()}',date_time=(2026,10,29,0,0,0));item.compress_type=zipfile.ZIP_DEFLATED;item.external_attr=0o644<<16;z.writestr(item,path.read_bytes())
    checksum=hashlib.sha256(archive.read_bytes()).hexdigest()
    (dest/'SHA256SUMS').write_text(f'{checksum}  {archive.name}\n',encoding='ascii')
    print(archive)
    print(f'SHA256 {checksum}')

if __name__=='__main__':build()
