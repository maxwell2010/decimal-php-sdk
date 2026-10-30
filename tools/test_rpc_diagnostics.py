"""Exercise actual SDK cURL failures against loopback; never contacts a node."""
import http.server
import json
import os
from pathlib import Path
import socket
import subprocess
import threading

ROOT = Path(__file__).resolve().parents[1]
paths = []


class Handler(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_POST(self):
        paths.append(self.path)
        request = json.loads(self.rfile.read(int(self.headers['Content-Length'])))
        if request['method'] != 'eth_chainId':
            self.send_error(400)
            return
        status, body = 200, json.dumps({'jsonrpc': '2.0', 'id': request['id'], 'result': '0x4b'}).encode()
        if self.path == '/http503':
            status, body = 503, b'MUST_NOT_LEAK_RESPONSE'
        elif self.path == '/oversize':
            body = b'x' * 4096
        self.send_response(status)
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)


server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler)
threading.Thread(target=server.serve_forever, daemon=True).start()
reserved = socket.socket()
reserved.bind(('127.0.0.1', 0))
closed_port = reserved.getsockname()[1]
php = [os.environ.get('PHP_BINARY', 'php')]
if 'gmp' not in subprocess.check_output(php + ['-m'], text=True).splitlines():
    php += ['-d', 'extension=gmp']
try:
    env = dict(os.environ, PHP_RPC_DIAGNOSTIC_HTTP=f'http://127.0.0.1:{server.server_port}', PHP_RPC_DIAGNOSTIC_CLOSED=f'http://127.0.0.1:{closed_port}')
    subprocess.run(php + [str(ROOT / 'tests/rpc-diagnostics.php')], env=env, cwd=ROOT, check=True, timeout=30, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    assert {'/http503', '/oversize', '/ok'} <= set(paths), paths
finally:
    reserved.close()
    server.shutdown()
    server.server_close()
print('Actual SDK HTTP/cURL diagnostics, response limit and read failover passed; loopback only')
