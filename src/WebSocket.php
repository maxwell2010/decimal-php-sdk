<?php
declare(strict_types=1);
namespace Decimal;
/** Blocking JSON-RPC WebSocket transport; subscription setup never submits transactions. */
final class WebSocket
{
    private $stream;
    private int $id=0;
    public function __construct(string $url,private readonly int $timeout=15)
    {
        $u=parse_url($url);if(!is_array($u)||!in_array($u['scheme']??'',['ws','wss'],true)||!isset($u['host'])||isset($u['user'])||isset($u['fragment'])||$timeout<1)throw new \InvalidArgumentException('Invalid WebSocket URL');
        $tls=$u['scheme']==='wss';$host=$u['host'];$port=$u['port']??($tls?443:80);
        $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host]]);
        $stream=@stream_socket_client(($tls?'tls':'tcp').'://'.$host.':'.$port,$errno,$error,$timeout,STREAM_CLIENT_CONNECT,$context);if($stream===false)throw new \RuntimeException('WebSocket connection failed');
        $this->stream=$stream;stream_set_timeout($stream,$timeout);$key=base64_encode(random_bytes(16));$path=$u['path']??'/';if(isset($u['query']))$path.='?'.$u['query'];
        $this->write("GET $path HTTP/1.1\r\nHost: $host:$port\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n");
        $header='';while(!str_ends_with($header,"\r\n\r\n")){if(strlen($header)>16384)throw new \RuntimeException('WebSocket headers too large');$header.=$this->read(1);}
        $accept=base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true));
        if(!preg_match('~^HTTP/1\.[01] 101 ~',$header)||!preg_match('/^Sec-WebSocket-Accept:\s*'.preg_quote($accept,'/').'\s*$/mi',$header)){$this->close();throw new \RuntimeException('Invalid WebSocket handshake');}
    }
    public static function frame(string $payload,int $opcode=1): string
    {
        $n=strlen($payload);if($n>4*1024*1024||!in_array($opcode,[1,8,9,10],true)||($opcode>=8&&$n>125))throw new \InvalidArgumentException('Invalid WebSocket frame');
        $head=chr(128|$opcode).($n<126?chr(128|$n):($n<=65535?chr(254).pack('n',$n):chr(255).pack('NN',0,$n)));$mask=random_bytes(4);$data='';for($i=0;$i<$n;++$i)$data.=$payload[$i]^$mask[$i%4];return $head.$mask.$data;
    }
    private function write(string $bytes): void { while($bytes!==''){$n=@fwrite($this->stream,$bytes);if(!$n)throw new \RuntimeException('WebSocket write failed');$bytes=substr($bytes,$n);} }
    private function read(int $n): string { $out='';while(strlen($out)<$n){$part=@fread($this->stream,$n-strlen($out));if($part===false||$part==='')throw new \RuntimeException('WebSocket closed or timed out');$out.=$part;}return $out; }
    public function receive(): array
    {
        $data='';$started=false;
        do{
            $h=unpack('C2',$this->read(2));$fin=($h[1]&128)!==0;$op=$h[1]&15;$n=$h[2]&127;
            if(($h[1]&112)!==0||($h[2]&128)!==0)throw new \RuntimeException('Invalid server frame');
            if($n===126)$n=unpack('n',$this->read(2))[1];elseif($n===127){$len=unpack('N2',$this->read(8));if($len[1]!==0)throw new \RuntimeException('Frame too large');$n=$len[2];}
            if($n>4*1024*1024||strlen($data)+$n>4*1024*1024||($op>=8&&(!$fin||$n>125)))throw new \RuntimeException('WebSocket frame limit');$part=$this->read($n);
            if($op===8){$this->close();throw new \RuntimeException('WebSocket closed by server');}if($op===9){$this->write(self::frame($part,10));continue;}if($op===10)continue;
            if(($op===1&&$started)||($op===0&&!$started)||!in_array($op,[0,1],true))throw new \RuntimeException('Invalid frame sequence');$started=true;$data.=$part;
            if($fin){$json=json_decode($data,true,512,JSON_THROW_ON_ERROR);if(!is_array($json))throw new \RuntimeException('Invalid JSON-RPC WebSocket message');return $json;}
        }while(true);
    }
    public function subscribe(string $kind,array $options=[]): string
    {
        if(!in_array($kind,['newHeads','logs','newPendingTransactions','syncing'],true))throw new \InvalidArgumentException('Unsupported subscription');
        $id=++$this->id;$this->write(self::frame(json_encode(['jsonrpc'=>'2.0','id'=>$id,'method'=>'eth_subscribe','params'=>[$kind,...$options]],JSON_THROW_ON_ERROR)));
        $r=$this->receive();if(($r['id']??null)!==$id||!is_string($r['result']??null))throw new RpcException('Subscription failed');return $r['result'];
    }
    public function unsubscribe(string $subscription): bool
    {
        $id=++$this->id;$this->write(self::frame(json_encode(['jsonrpc'=>'2.0','id'=>$id,'method'=>'eth_unsubscribe','params'=>[$subscription]],JSON_THROW_ON_ERROR)));$r=$this->receive();if(($r['id']??null)!==$id)throw new RpcException('Unsubscribe ID mismatch');return ($r['result']??null)===true;
    }
    public function close(): void { if(is_resource($this->stream))fclose($this->stream); }
    public function __destruct(){ $this->close(); }
}
