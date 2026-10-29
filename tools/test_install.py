"""Exercise the Composer single-command source installation against loopback metadata."""
import http.server
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import threading
import release
ROOT=release.ROOT

def main():
    release.build()
    archive=ROOT/'dist'/f'decimal-php-sdk-{release.VERSION}.zip'
    metadata=json.loads((ROOT/'dist/packages.json').read_text())
    class Handler(http.server.BaseHTTPRequestHandler):
        def log_message(self,*args):pass
        def do_GET(self):
            if self.path=='/packages.json':body=json.dumps(metadata).encode();content='application/json'
            elif self.path=='/source.zip':body=archive.read_bytes();content='application/zip'
            else:self.send_error(404);return
            self.send_response(200);self.send_header('Content-Type',content);self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
    server=http.server.ThreadingHTTPServer(('127.0.0.1',0),Handler)
    base=f'http://127.0.0.1:{server.server_port}'
    metadata['packages']['mintcandy/decimal-web3-sdk'][release.VERSION]['dist']['url']=base+'/source.zip'
    threading.Thread(target=server.serve_forever,daemon=True).start()
    php=[os.environ.get('PHP_BINARY','php')]
    if 'gmp' not in subprocess.check_output(php+['-m'],text=True).splitlines():php+=['-d','extension=gmp']
    composer=os.environ.get('COMPOSER_BINARY') or shutil.which('composer')
    phar=Path('C:/composer/composer.phar')
    if phar.is_file():command=php+[str(phar)]
    elif composer:
        candidate=Path(composer)
        if candidate.suffix.lower() in {'.bat','.cmd'}:
            candidate=candidate.with_name('composer.phar')
            if not candidate.is_file():raise RuntimeError('Set COMPOSER_BINARY to the Composer phar file')
            command=php+[str(candidate)]
        elif candidate.suffix.lower()=='.phar':command=php+[str(candidate)]
        else:command=[composer]
    else:raise RuntimeError('Composer required')
    env=dict(os.environ,COMPOSER_MAX_PARALLEL_HTTP='1',COMPOSER_NO_INTERACTION='1')
    try:
        with tempfile.TemporaryDirectory(prefix='decimal-install-') as tmp:
            destination=Path(tmp)/'sdk'
            run=subprocess.run(command+['create-project','--repository='+base+'/packages.json','--no-secure-http','--prefer-dist','--no-interaction','mintcandy/decimal-web3-sdk',str(destination)],env=env,text=True,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
            if run.returncode:raise RuntimeError(run.stdout[-10000:])
            assert (destination/'vendor/autoload.php').is_file()
            assert (destination/'VERSION').read_text().strip()==release.VERSION
            subprocess.run(php+[str(destination/'examples/offline.php')],check=True,stdout=subprocess.DEVNULL)
            subprocess.run(php+[str(destination/'tests/run.php')],check=True)
    finally:server.shutdown();server.server_close()
    print('Single-command Composer installation and installed package tests passed')

if __name__=='__main__':main()
