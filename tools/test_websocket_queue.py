"""Loopback-only RPC/API/WebSocket tests. Does not contact Decimal or submit transactions."""
import base64
import hashlib
import http.server
import json
import os
from pathlib import Path
import socketserver
import struct
import subprocess
import threading

ROOT = Path(__file__).resolve().parents[1]
errors = []

class Http(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args): pass
    def do_POST(self):
        request = json.loads(self.rfile.read(int(self.headers['Content-Length'])))
        method = request['method']
        if method == 'eth_sendRawTransaction':
            errors.append('Unexpected broadcast')
            self.send_error(500)
            return
        result = {'eth_chainId': '0x31524', 'eth_getBalance': '0x7b'}.get(method)
        body = json.dumps({'jsonrpc': '2.0', 'id': request['id'], 'result': result}).encode()
        self.send_response(200); self.send_header('Content-Length', str(len(body))); self.end_headers(); self.wfile.write(body)
    def do_GET(self):
        body = json.dumps({'path': self.path}).encode()
        self.send_response(200); self.send_header('Content-Length', str(len(body))); self.end_headers(); self.wfile.write(body)

class Ws(socketserver.BaseRequestHandler):
    def handle(self):
        try:
            self.request.settimeout(10)
            def read(n):
                out = b''
                while len(out) < n:
                    part = self.request.recv(n-len(out))
                    if not part: raise RuntimeError('Unexpected client close')
                    out += part
                return out
            header = b''
            while not header.endswith(b'\r\n\r\n'): header += read(1)
            key = [line.split(b':',1)[1].strip() for line in header.split(b'\r\n') if line.lower().startswith(b'sec-websocket-key:')][0]
            accept = base64.b64encode(hashlib.sha1(key+b'258EAFA5-E914-47DA-95CA-C5AB0DC85B11').digest())
            self.request.sendall(b'HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: '+accept+b'\r\n\r\n')
            def receive():
                first, second = read(2)
                assert second & 128
                n = second & 127
                if n == 126: n = struct.unpack('!H', read(2))[0]
                if n == 127: n = struct.unpack('!Q', read(8))[0]
                mask = read(4); payload = read(n)
                return first & 15, bytes(b ^ mask[i%4] for i,b in enumerate(payload))
            def send(payload, opcode=1, final=True):
                head = bytes([(128 if final else 0)|opcode])
                n = len(payload)
                head += bytes([n]) if n < 126 else bytes([126])+struct.pack('!H',n)
                self.request.sendall(head+payload)
            op, raw = receive(); request = json.loads(raw); assert request['method']=='eth_subscribe'
            send(json.dumps({'jsonrpc':'2.0','method':'eth_subscription','params':{'subscription':'0xsub','result':{'number':'0x0'}}}).encode())
            send(json.dumps({'jsonrpc':'2.0','id':request['id'],'result':'0xsub'}).encode())
            send(b'ping',9)
            assert receive() == (10,b'ping')
            data=json.dumps({'jsonrpc':'2.0','method':'eth_subscription','params':{'subscription':'0xsub','result':{'number':'0x1'}}}).encode()
            send(data[:30],1,False);send(data[30:],0,True)
            op,raw=receive();request=json.loads(raw);assert request['method']=='eth_unsubscribe'
            send(json.dumps({'jsonrpc':'2.0','id':request['id'],'result':True}).encode())
        except Exception as exc:
            if str(exc) != 'Unexpected client close': errors.append(repr(exc))

http = http.server.ThreadingHTTPServer(('127.0.0.1',0),Http)
ws = socketserver.ThreadingTCPServer(('127.0.0.1',0),Ws)
for server in (http,ws): threading.Thread(target=server.serve_forever,daemon=True).start()
try:
    cmd = [os.environ.get('PHP_BINARY','php')]
    modules = subprocess.check_output(cmd+['-m'],text=True)
    if 'gmp' not in modules.splitlines(): cmd += ['-d','extension=gmp']
    env = dict(os.environ, PHP_TEST_HTTP=f'http://127.0.0.1:{http.server_port}', PHP_TEST_WS=f'ws://127.0.0.1:{ws.server_address[1]}')
    result = subprocess.run(cmd+[str(ROOT/'tests'/'websocket-queue.php')],env=env,cwd=ROOT,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
    if result.returncode or errors: raise SystemExit(f'Loopback transport failure: exit={result.returncode}, errors={errors}')
finally:
    for server in (http,ws): server.shutdown();server.server_close()
print('WebSocket notification queue and reconnect checks passed; loopback only')
