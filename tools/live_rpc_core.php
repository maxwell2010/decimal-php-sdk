<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
use Decimal\{Network,Units,Contract,Deployment,ChecksService,BridgeService};
$audit=new LiveRpcAudit('Core RPC, registry, checks and bridge read audit',160);
$sender='0x000000000000000000000000000000000000dead';
foreach(['mainnet'=>Network::mainnet(),'testnet'=>Network::testnet()] as $name=>$network){
    $client=$audit->client($network);
    $block=$audit->check($name.'.head',function()use($client){$client->verifyChain();$b=$client->rpc->call('eth_blockNumber');Units::decimal($b);return $b;});
    if($block===null)continue;
    $audit->note($name.'.block','observed',$block);
    $audit->check($name.'.system_snapshot',fn()=>$client->resolveSystemContracts($block));
    $audit->check($name.'.gas_price',fn()=>Units::decimal($client->rpc->call('eth_gasPrice')));
    $audit->check($name.'.native_balance',fn()=>$client->balance($sender,$block));
    $audit->check($name.'.registry.code',fn()=>$client->rpc->call('eth_getCode',[$network->contractCenter,$block]));
    $audit->check($name.'.registry.standard_proxy_probe',fn()=>Deployment::implementation($client,$network->contractCenter,$block));
    foreach(['checks','bridge'] as $slug){
        $target=$audit->check($name.'.registry.'.$slug,fn()=>$client->resolveSystemContract($slug,$block));
        if($target===null){$audit->note($name.'.'.$slug.'.coverage','limitation','No registry target was resolved; no deployment behavior inferred');continue;}
        $audit->check($name.'.'.$slug.'.code',fn()=>$client->rpc->call('eth_getCode',[$target,$block]));
        if($slug==='checks'){
            $checks=new ChecksService($client,$target);
            $audit->check($name.'.checks.owner',fn()=>$checks->owner($block));
            $audit->check($name.'.checks.implementation',fn()=>$checks->implementation($block));
            $audit->check($name.'.checks.sender_nonce',fn()=>$checks->nonce($sender,$block));
        }else $audit->check($name.'.bridge.minimum_fee',fn()=>(new BridgeService($client,$target))->minimumServiceFee('2',$block));
    }
    $hash=$name==='mainnet'?'0xc64c37707f97b3d36a92c34ed80a03b22e2dae74cc49c18290f700e3fb30a243':'0x6196a0e9c610c4c0d2ddfbb35096ab8c15fb06e9f9f0bd3a7ed6ec10a73271ee';
    $audit->check($name.'.historical_result',fn()=>$client->transactionResult($hash)->toArray());
    try{$client->call(['from'=>$sender,'to'=>$network->contractCenter,'data'=>'0xffffffff'],$block);$audit->note($name.'.unknown_selector','observed','Deployment accepted/returned an empty fallback; no successful operation inferred');}
    catch(Decimal\RpcException $e){$audit->note($name.'.unknown_selector','expected-revert',['code'=>$e->getCode(),'message'=>$e->getMessage(),'data'=>$e->rpcData]);}
}
foreach($audit->checks as &$check){
    if($check['status']==='failed'&&str_contains($check['name'],'.registry.')&&($check['error']['message']??'')==='System contract is not registered'){$check['status']='unregistered-system-slug';$check['classification']='Registry returned zero and the SDK correctly rejected it. This does not establish the service deployment address.';}
    if($check['status']==='failed'&&str_ends_with($check['name'],'.bridge.minimum_fee')&&($check['error']['code']??null)===3){$check['status']='unsupported-deployment-view';$check['classification']='Resolved bridge does not accept the selected uint16 bridge-v2 fee getter; RPC revert is propagated without a default fee.';}
}unset($check);
$audit->save(dirname(__DIR__).'/docs/live/core.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;

exit($audit->exitCode());
