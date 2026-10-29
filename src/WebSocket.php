<?php
declare(strict_types=1);
namespace Decimal;
/** Blocking JSON-RPC WebSocket transport; subscription setup never submits transactions. */
final class WebSocket
{
    private $stream;
    private int $id=0;
    private array $notifications=[];
    private int $notificationBytes=0;
    private array $subscriptions=[];
    public function __construct(private readonly string $url,private readonly int $timeout=15)
    { $this->connect(); }
    private function connect(): void
    {
        $url=$this->url;$timeout=$this->timeout;
        $u=parse_url($url);if(!is_array($u)||!in_array($u['scheme']??'',['ws','wss'],true)||!isset($u['host'])||isset($u['user'])||isset($u['fragment'])||$timeout<1)throw new \InvalidArgumentException('Invalid WebSocket URL');
        $tls=$u['scheme']==='wss';$host=$u['host'];$port=$u['port']??($tls?443:80);
        $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host]]);
        $stream=@stream_socket_client(($tls?'tls':'tcp').'://'.$host.':'.$port,$errno,$error,$timeout,STREAM_CLIENT_CONNECT,$context);if($stream===false)throw new \RuntimeException('WebSocket connection failed');
        $this->stream=$stream;stream_set_timeout($stream,$timeout);$key=base64_encode(random_bytes(16));$path=$u['path']??'/';if(isset($u['query']))$path.='?'.$u['query'];
        $this->write("GET $path HTTP/1.1\r\nHost: $host:$port\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\n\r\n");
        $deadline=microtime(true)+$timeout;$header='';while(!str_ends_with($header,"\r\n\r\n")){if(strlen($header)>16384)throw new \RuntimeException('WebSocket headers too large');$header.=$this->read(1,$deadline);}
        $accept=base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11',true));
        if(!preg_match('~^HTTP/1\.[01] 101 ~',$header)||!preg_match('/^Upgrade:\s*websocket\s*$/mi',$header)||!preg_match('/^Connection:[^\r\n]*\bUpgrade\b/mi',$header)||!preg_match('/^Sec-WebSocket-Accept:\s*'.preg_quote($accept,'/').'\s*$/mi',$header)){$this->close();throw new \RuntimeException('Invalid WebSocket handshake');}
    }
    public static function frame(string $payload,int $opcode=1): string
    {
        $n=strlen($payload);if($n>4*1024*1024||!in_array($opcode,[1,8,9,10],true)||($opcode>=8&&$n>125))throw new \InvalidArgumentException('Invalid WebSocket frame');
        $head=chr(128|$opcode).($n<126?chr(128|$n):($n<=65535?chr(254).pack('n',$n):chr(255).pack('NN',0,$n)));$mask=random_bytes(4);$data='';for($i=0;$i<$n;++$i)$data.=$payload[$i]^$mask[$i%4];return $head.$mask.$data;
    }
    private function write(string $bytes): void
    {
        $deadline=microtime(true)+$this->timeout;
        while($bytes!==''){
            $left=$deadline-microtime(true);if($left<=0){$this->close();throw new \RuntimeException('WebSocket write timed out');}
            $seconds=(int)$left;stream_set_timeout($this->stream,$seconds,max(1,(int)(($left-$seconds)*1000000)));
            $n=@fwrite($this->stream,$bytes);if(!$n)throw new \RuntimeException('WebSocket write failed');$bytes=substr($bytes,$n);
        }
    }
    private function read(int $n,?float $deadline=null): string
    {
        $deadline??=microtime(true)+$this->timeout;$out='';
        while(strlen($out)<$n){
            $left=$deadline-microtime(true);if($left<=0){$this->close();throw new \RuntimeException('WebSocket operation timed out');}
            $seconds=(int)$left;$micros=max(1,(int)(($left-$seconds)*1000000));stream_set_timeout($this->stream,$seconds,$micros);
            $part=@fread($this->stream,$n-strlen($out));if($part===false||$part==='')throw new \RuntimeException('WebSocket closed or timed out');$out.=$part;
        }
        return $out;
    }
    private function readMessage(?float $deadline=null): array
    {
        $deadline??=microtime(true)+$this->timeout;$data='';$started=false;
        do{
            $h=unpack('C2',$this->read(2,$deadline));$fin=($h[1]&128)!==0;$op=$h[1]&15;$n=$h[2]&127;
            if(($h[1]&112)!==0||($h[2]&128)!==0)throw new \RuntimeException('Invalid server frame');
            if($n===126){$n=unpack('n',$this->read(2,$deadline))[1];if($n<126)throw new \RuntimeException('Noncanonical frame length');}elseif($n===127){$len=unpack('N2',$this->read(8,$deadline));if($len[1]!==0)throw new \RuntimeException('Frame too large');$n=$len[2];if($n<=65535)throw new \RuntimeException('Noncanonical frame length');}
            if($n>4*1024*1024||strlen($data)+$n>4*1024*1024||($op>=8&&(!$fin||$n>125)))throw new \RuntimeException('WebSocket frame limit');$part=$this->read($n,$deadline);
            if($op===8){$this->close();throw new \RuntimeException('WebSocket closed by server');}if($op===9){$this->write(self::frame($part,10));continue;}if($op===10)continue;
            if(($op===1&&$started)||($op===0&&!$started)||!in_array($op,[0,1],true))throw new \RuntimeException('Invalid frame sequence');$started=true;$data.=$part;
            if($fin){$json=json_decode($data,true,512,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);if(!is_array($json)||($json['jsonrpc']??null)!=='2.0')throw new \RuntimeException('Invalid JSON-RPC WebSocket message');return $json;}
        }while(true);
    }
    public function subscribe(string $kind,array $options=[]): string
    {
        if(!in_array($kind,['newHeads','logs','newPendingTransactions','syncing'],true))throw new \InvalidArgumentException('Unsupported subscription');
        if(!array_is_list($options))throw new \InvalidArgumentException('Subscription options must be positional');
        $id=++$this->id;$this->write(self::frame(json_encode(['jsonrpc'=>'2.0','id'=>$id,'method'=>'eth_subscribe','params'=>[$kind,...$options]],JSON_THROW_ON_ERROR)));
        $r=$this->waitResponse($id);if(!is_string($r['result']??null))throw new RpcException('Subscription failed');$this->subscriptions[$r['result']]=[$kind,$options];return $r['result'];
    }
    public function unsubscribe(string $subscription): bool
    {
        $id=++$this->id;$this->write(self::frame(json_encode(['jsonrpc'=>'2.0','id'=>$id,'method'=>'eth_unsubscribe','params'=>[$subscription]],JSON_THROW_ON_ERROR)));$r=$this->waitResponse($id);if(($r['result']??null)===true){unset($this->subscriptions[$subscription]);return true;}return false;
    }
    private function notification(array $message): array
    {
        if(array_key_exists('id',$message)||($message['method']??null)!=='eth_subscription'||!is_array($message['params']??null)||!is_string($message['params']['subscription']??null)||$message['params']['subscription']===''||!array_key_exists('result',$message['params'])) {
            $this->close();throw new RpcException('Invalid WebSocket notification');
        }
        return $message;
    }
    private function waitResponse(int $id): array
    {
        $deadline=microtime(true)+$this->timeout;
        while(true){
            $r=$this->readMessage($deadline);
            if(array_key_exists('id',$r)){
                // This blocking API has exactly one outstanding request. Never cache
                // future IDs: an unsolicited response could poison a later request.
                if($r['id']!==$id||array_key_exists('result',$r)===array_key_exists('error',$r)){
                    $this->close();throw new RpcException('Invalid WebSocket response envelope or ID');
                }
                if(array_key_exists('error',$r)){
                    $e=$r['error'];
                    if(!is_array($e)||!is_int($e['code']??null)||!is_string($e['message']??null)){$this->close();throw new RpcException('Invalid WebSocket error envelope');}
                    throw new RpcException($e['message'],$e['code'],$e['data']??null);
                }
                return $r;
            }
            $r=$this->notification($r);$bytes=strlen(json_encode($r,JSON_THROW_ON_ERROR));
            if(count($this->notifications)>=1000||$this->notificationBytes+$bytes>8388608){$this->close();throw new RpcException('WebSocket pending queue full');}
            $this->notifications[]=$r;$this->notificationBytes+=$bytes;
        }
    }
    public function receive(): array
    {
        if($this->notifications!==[]){$r=array_shift($this->notifications);$this->notificationBytes-=strlen(json_encode($r,JSON_THROW_ON_ERROR));return $r;}
        return $this->notification($this->readMessage());
    }
    /** Explicit reconnect returns old-to-new subscription IDs. No hidden retries. */
    public function reconnect(): array
    {
        $old=$this->subscriptions;$this->close();$this->notificationBytes=0;$this->notifications=[];$this->subscriptions=[];
        try {
            $this->connect();$mapping=[];
            foreach($old as $id=>[$kind,$options])$mapping[$id]=$this->subscribe($kind,$options);
            return $mapping;
        } catch(\Throwable $e) {
            $this->close();$this->subscriptions=$old;$this->notificationBytes=0;$this->notifications=[];throw $e;
        }
    }
    public function close(): void { if(is_resource($this->stream))fclose($this->stream); }
    public function __destruct(){ $this->close(); }
}
