<?php
declare(strict_types=1);
namespace Decimal;

/** Unary gRPC over HTTP/2 with pinned read/simulation method restrictions. */
final class CosmosGrpc
{
    public function __construct(private readonly string $endpoint,private readonly ?\Closure $transport=null)
    {
        $url=parse_url($endpoint);
        if(!filter_var($endpoint,FILTER_VALIDATE_URL)||($url['scheme']??'')!=='https'||isset($url['user'])||isset($url['query'])||isset($url['fragment']))throw new \InvalidArgumentException('HTTPS gRPC endpoint required');
        if($transport===null&&(!defined('CURL_VERSION_HTTP2')||((curl_version()['features']??0)&CURL_VERSION_HTTP2)===0))throw new \RuntimeException('Native gRPC requires curl HTTP/2 support');
    }
    public static function parseStatusHeader(string $line): ?int
    {
        if(strncasecmp($line,'grpc-status:',12)!==0)return null;
        if(!preg_match('/^grpc-status:[ \t]*(0|[1-9][0-9]*)[ \t]*(?:\r\n|\n)?$/iD',$line,$matches)||strlen($matches[1])>2||(int)$matches[1]>16)throw new \InvalidArgumentException('Malformed gRPC status header');
        return (int)$matches[1];
    }
    public static function requireHttp2(int $negotiatedVersion): void
    { if($negotiatedVersion!==CURL_HTTP_VERSION_2_0)throw new \RuntimeException('Native gRPC did not negotiate HTTP/2'); }
    public function query(string $service,string $method,array $request): array
    {
        $routes=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/grpc-routes.json'),true,512,JSON_THROW_ON_ERROR);
        $route=$routes[$service.'/'.$method]??throw new \InvalidArgumentException('Unsupported or mutating gRPC method');
        $codec=new Protobuf();$payload=$codec->encode($route['input'],$request);$framed="\0".pack('N',strlen($payload)).$payload;
        if(strlen($framed)>4*1024*1024)throw new \InvalidArgumentException('gRPC request too large');
        if($this->transport)$response=($this->transport)('/'.$service.'/'.$method,$framed);
        else{
            $curl=curl_init(rtrim($this->endpoint,'/').'/'.$service.'/'.$method);$body='';$status=null;
            curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$framed,CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_2_0,CURLOPT_HTTPHEADER=>['Content-Type: application/grpc','TE: trailers'],CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_HEADERFUNCTION=>static function($c,$line)use(&$status){try{$parsed=self::parseStatusHeader($line);}catch(\InvalidArgumentException){return 0;}if($parsed!==null){if($status!==null)return 0;$status=$parsed;}return strlen($line);},
                CURLOPT_WRITEFUNCTION=>static function($c,$part)use(&$body){if(strlen($body)+strlen($part)>4*1024*1024)return 0;$body.=$part;return strlen($part);}]);
            $ok=curl_exec($curl);$http=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$version=curl_getinfo($curl,CURLINFO_HTTP_VERSION);curl_close($curl);
            self::requireHttp2($version);
            if($ok===false||$http!==200)throw new \RuntimeException('gRPC transport failed');$response=['status'=>$status,'body'=>$body];
        }
        if(!is_array($response)||($response['status']??null)!==0||!is_string($response['body']??null))throw new \RuntimeException('gRPC returned non-success status');
        $body=$response['body'];if(strlen($body)<5||strlen($body)>4*1024*1024||$body[0]!=="\0"||unpack('N',substr($body,1,4))[1]!==strlen($body)-5)throw new \RuntimeException('Invalid unary gRPC frame');
        return $codec->decode($route['output'],substr($body,5));
    }
    public function simulate(string $rawTransaction): array
    { return $this->query('cosmos.tx.v1beta1.Service','Simulate',['tx_bytes'=>'0x'.bin2hex($rawTransaction)]); }
}
