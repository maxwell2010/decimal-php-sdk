<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
$audit=new LiveRpcAudit('Default local CA configuration diagnostic',2);
$client=$audit->client(Decimal\Network::mainnet());
try{$client->verifyChain();$audit->note('default_trust_store','passed','Configured local PHP trust store validates the RPC certificate');}
catch(Decimal\RpcTransportException $error){
    if(($error->rpcData['curl_errno']??null)===60)$audit->note('default_trust_store','expected-environment-tls-failure',['message'=>$error->getMessage(),'diagnostics'=>$error->rpcData,'resolution'=>'Configure a trusted CA bundle using curl.cainfo; certificate verification remains enabled']);
    else $audit->note('default_trust_store','failed',['message'=>$error->getMessage(),'diagnostics'=>$error->rpcData]);
}
$audit->save(dirname(__DIR__).'/docs/live/transport.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
exit($audit->exitCode());
