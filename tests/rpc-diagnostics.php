<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Decimal\{Rpc,RpcTransportException};
$base=getenv('PHP_RPC_DIAGNOSTIC_HTTP');$closed=getenv('PHP_RPC_DIAGNOSTIC_CLOSED');
if(!$base||!$closed)throw new RuntimeException('Loopback test endpoints required');
function transportFailure(Rpc $rpc): RpcTransportException
{ try{$rpc->call('eth_chainId');}catch(RpcTransportException $e){return $e;}throw new RuntimeException('Expected transport exception'); }
$http=transportFailure(new Rpc($base.'/http503'));
if($http->getCode()!==503||$http->rpcData['http_status']!==503||$http->rpcData['curl_errno']!==0||!str_contains($http->getMessage(),'HTTP 503'))throw new RuntimeException('HTTP diagnostics lost');
if(str_contains(json_encode($http->rpcData).$http->getMessage(),'MUST_NOT_LEAK_RESPONSE'))throw new RuntimeException('Response body leaked into transport diagnostics');
$size=transportFailure(new Rpc($base.'/oversize',maxResponseBytes:32));
if($size->rpcData['http_status']!==200||$size->rpcData['curl_errno']===0||!$size->rpcData['response_limit_exceeded']||!str_contains($size->getMessage(),'response size limit'))throw new RuntimeException('Response cap diagnostics lost');
$connection=transportFailure(new Rpc($closed,timeoutSeconds:2));
if($connection->getCode()!==0||$connection->rpcData['curl_errno']===0||$connection->rpcData['curl_error']===''||!str_contains($connection->getMessage(),'cURL '))throw new RuntimeException('Connection failure diagnostics lost');
if((new Rpc([$base.'/http503',$base.'/ok'],expectedChainId:75))->call('eth_chainId')!=='0x4b')throw new RuntimeException('Read failover changed');
echo "HTTP, cURL, response-limit diagnostics and read failover passed; loopback only\n";
