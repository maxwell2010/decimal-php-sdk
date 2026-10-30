<?php
declare(strict_types=1);
namespace Decimal;
/** Bounded HTTP JSON-RPC with explicit read-only failover. Writes are attempted once. */
final class Rpc
{
    private int $id = 0;
    private array $urls;
    private const READS=['eth_chainId','eth_blockNumber','eth_getBalance','eth_getTransactionCount','eth_call','eth_estimateGas','eth_gasPrice','eth_maxPriorityFeePerGas','eth_feeHistory','eth_getCode','eth_getStorageAt','eth_getLogs','eth_getBlockByNumber','eth_getBlockByHash','eth_getTransactionByHash','eth_getTransactionReceipt','net_version','web3_clientVersion'];
    public function __construct(string|array $url,private readonly ?\Closure $transport=null,private readonly ?int $expectedChainId=null,private readonly int $maxResponseBytes=8388608,private readonly int $timeoutSeconds=30)
    {
        $this->urls=is_array($url)?array_values($url):[$url];
        if(!$this->urls||count($this->urls)>10||$maxResponseBytes<1||$timeoutSeconds<1||($expectedChainId!==null&&$expectedChainId<1))throw new \InvalidArgumentException('Invalid RPC configuration');
        if(count($this->urls)>1&&$expectedChainId===null)throw new \InvalidArgumentException('Failover requires expected chain ID');
        foreach($this->urls as $endpoint){$u=is_string($endpoint)?parse_url($endpoint):false;if(!is_array($u)||!filter_var($endpoint,FILTER_VALIDATE_URL)||!in_array($u['scheme']??'',['http','https'],true)||isset($u['user'])||isset($u['fragment']))throw new \InvalidArgumentException('Invalid RPC URL');}
    }
    private function request(string $url,array $request): mixed
    {
        if($this->transport)return ($this->transport)($request,$url);
        $curl=curl_init($url);$raw='';$overflow=false;
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($request,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_CONNECTTIMEOUT=>min(10,$this->timeoutSeconds),CURLOPT_TIMEOUT=>$this->timeoutSeconds,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_WRITEFUNCTION=>function($c,$part)use(&$raw,&$overflow){if(strlen($raw)+strlen($part)>$this->maxResponseBytes){$overflow=true;return 0;}$raw.=$part;return strlen($part);}]);
        $ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$errno=curl_errno($curl);$error=curl_error($curl);curl_close($curl);
        if($ok===false||$status!==200){
            $reason=$overflow?'response size limit exceeded':($errno!==0?'cURL '.$errno.': '.$error:'HTTP '.$status);
            throw new RpcTransportException('RPC transport failed ('.$reason.')',$status,['http_status'=>$status,'curl_errno'=>$errno,'curl_error'=>$error,'response_limit_exceeded'=>$overflow]);
        }
        try{return json_decode($raw,true,512,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);}catch(\JsonException){throw new RpcException('Invalid RPC JSON');}
    }
    private function result(mixed $response,int $id): mixed
    {
        if(!is_array($response)||($response['jsonrpc']??null)!=='2.0'||($response['id']??null)!==$id)throw new RpcException('Invalid RPC envelope');
        if(array_key_exists('error',$response)){
            $error=$response['error'];if(!is_array($error)||!is_int($error['code']??null)||!is_string($error['message']??null)||array_key_exists('result',$response))throw new RpcException('Invalid RPC error envelope');
            throw new RpcException($error['message'],$error['code'],$error['data']??null);
        }
        if(!array_key_exists('result',$response))throw new RpcException('Missing RPC result');return $response['result'];
    }
    public function call(string $method,array $params=[]): mixed
    {
        if(!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D',$method)||!array_is_list($params))throw new \InvalidArgumentException('Invalid RPC method or positional parameters');
        $request=['jsonrpc'=>'2.0','id'=>++$this->id,'method'=>$method,'params'=>$params];
        $urls=in_array($method,self::READS,true)?$this->urls:[$this->urls[0]];$last=null;
        foreach($urls as $url){
            try{
                if($this->expectedChainId!==null){$probe=['jsonrpc'=>'2.0','id'=>++$this->id,'method'=>'eth_chainId','params'=>[]];$chain=$this->result($this->request($url,$probe),$probe['id']);if(!is_string($chain)||Units::decimal($chain)!==(string)$this->expectedChainId)throw new RpcException('RPC endpoint chain mismatch');}
                return $this->result($this->request($url,$request),$request['id']);
            }catch(RpcTransportException $e){$last=$e;}
        }
        throw $last??new RpcException('RPC failed');
    }
}
