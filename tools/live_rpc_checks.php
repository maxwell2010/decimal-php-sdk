<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
$audit=new LiveRpcAudit('Checks explicit public configuration read audit',60);
// Public addresses from recovery-014 src/decimal_web3_sdk/config.py, not inferred from zero registry results.
$targets=['mainnet'=>[Decimal\Network::mainnet(),'0x9c3326594b49AFa24db9b988Cda1258d0dcb4e40'],'testnet'=>[Decimal\Network::testnet(),'0xb6161CAA8Bd7167C2fa67b93b041FF9a94d6a735']];
foreach($targets as $name=>[$network,$address]){
    $client=$audit->client($network);
    $block=$audit->check($name.'.head',function()use($client){$client->verifyChain();return $client->rpc->call('eth_blockNumber');});
    if($block===null)continue;
    $code=$audit->check($name.'.checks.code',fn()=>$client->rpc->call('eth_getCode',[$address,$block]));
    if(!$code||$code==='0x'){$audit->note($name.'.checks.coverage','limitation','Public configured address has no runtime at the observed block');continue;}
    $service=new Decimal\ChecksService($client,$address);
    $audit->note($name.'.deployment','observed',['address'=>$address,'block'=>$block,'source'=>'recovery-014 src/decimal_web3_sdk/config.py']);
    $audit->check($name.'.checks.owner',fn()=>$service->owner($block));
    $audit->check($name.'.checks.implementation',fn()=>$service->implementation($block));
    foreach(['0x000000000000000000000000000000000000dead','0x0000000000000000000000000000000000000001'] as $sender)$audit->check($name.'.checks.nonce.'.$sender,fn()=>$service->nonce($sender,$block));
}
$audit->save(dirname(__DIR__).'/docs/live/checks.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;

exit($audit->exitCode());
