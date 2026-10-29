"""Reject malformed/unsolicited WebSocket replies using a loopback-only peer."""
import base64,hashlib,json,os,socketserver,struct,subprocess,threading,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
errors=[]
class Handler(socketserver.BaseRequestHandler):
 def handle(self):
  try:
   self.request.settimeout(10)
   def read(n):
    out=b''
    while len(out)<n:
     b=self.request.recv(n-len(out))
     if not b:raise EOFError()
     out+=b
    return out
   header=b''
   while not header.endswith(b'\r\n\r\n'):header+=read(1)
   case=header.split(b' ')[1].decode().lstrip('/')
   key=[l.split(b':',1)[1].strip() for l in header.split(b'\r\n') if l.lower().startswith(b'sec-websocket-key:')][0]
   accept=base64.b64encode(hashlib.sha1(key+b'258EAFA5-E914-47DA-95CA-C5AB0DC85B11').digest())
   self.request.sendall(b'HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: '+accept+b'\r\n\r\n')
   if case=='options':
    assert self.request.recv(1)==b'';return
   a,b=read(2);n=b&127
   if n==126:n=struct.unpack('!H',read(2))[0]
   if n==127:n=struct.unpack('!Q',read(8))[0]
   mask=read(4);payload=read(n);request=json.loads(bytes(c^mask[i%4] for i,c in enumerate(payload)))
   def send(obj):
    p=json.dumps(obj).encode();n=len(p);h=b'\x81'+(bytes([n]) if n<126 else b'\x7e'+struct.pack('!H',n) if n<=65535 else b'\x7f'+struct.pack('!Q',n));self.request.sendall(h+p)
   if case=='pings':
    until=time.monotonic()+2
    while time.monotonic()<until:
     self.request.sendall(b'\x89\x01x');a,b=read(2);n=b&127;mask=read(4);read(n);time.sleep(.1)
   elif case=='future':send(dict(jsonrpc='2.0',id=request['id']+1,result='poison'))
   elif case=='both':send(dict(jsonrpc='2.0',id=request['id'],result='bad',error=dict(code=-1,message='bad')))
   elif case=='nullerror':send(dict(jsonrpc='2.0',id=request['id'],error=None))
   elif case=='badnotification':send(dict(jsonrpc='2.0',method='eth_subscription',params={'subscription':'id'}))
   elif case=='oversizequeue':
    for _ in range(6):send(dict(jsonrpc='2.0',method='eth_subscription',params={'subscription':'id','result':'x'*2000000}))
  except (EOFError,BrokenPipeError,ConnectionResetError,ConnectionAbortedError):pass
  except Exception as e:errors.append(repr(e))
server=socketserver.ThreadingTCPServer(('127.0.0.1',0),Handler)
threading.Thread(target=server.serve_forever,daemon=True).start()
try:
 cmd=[os.environ.get('PHP_BINARY','php')]
 if 'gmp' not in subprocess.check_output(cmd+['-m'],text=True).splitlines():cmd+=['-d','extension=gmp']
 env=dict(os.environ,PHP_TEST_WS=f'ws://127.0.0.1:{server.server_address[1]}')
 result=subprocess.run(cmd+[str(ROOT/'tests/websocket-audit.php')],env=env,timeout=60,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
 if result.returncode or errors:raise SystemExit(f'Failure {result.returncode}: {errors}')
finally:server.shutdown();server.server_close()
print('WebSocket adversarial loopback checks passed; no remote connections')
