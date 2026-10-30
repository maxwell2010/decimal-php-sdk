<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
$audit=new LiveRpcAudit('Nonpersistent eth_call validation of ERC20 action drafts',50);
$sender='0x000000000000000000000000000000000000dead';$recipient='0x0000000000000000000000000000000000000001';
foreach(['mainnet'=>Decimal\Network::mainnet(),'testnet'=>Decimal\Network::testnet()] as $name=>$network){
    $client=$audit->client($network);$client->verifyChain();$block=$client->rpc->call('eth_blockNumber');
    $token=$client->resolveSystemContract('wdel',$block);$erc=new Decimal\Erc20Service($client,$token);$abi=Decimal\Contract::profile($token,'Token');
    $audit->note($name.'.snapshot','observed',compact('block','token','sender'));
    $approve=$erc->approve($sender,$recipient,'0',0,$block);$transfer=$erc->transferRaw($sender,$recipient,'0');
    foreach(['approve(address,uint256)'=>$approve,'transfer(address,uint256)'=>$transfer] as $signature=>$draft)$audit->check($name.'.eth_call.'.$signature,function()use($client,$abi,$signature,$draft,$block){$decoded=$abi->decode($signature,$client->call($draft->rpc(),$block));if($decoded!==[true])throw new RuntimeException('ERC20 zero-value simulation did not return true');return $decoded;});
    $balance=$erc->balance($sender,$block);$tooMuch=bcadd($balance,'1',0);
    if(bccomp($tooMuch,bcpow('2','256',0),0)>=0){$audit->note($name.'.insufficient_balance','limitation','Balance already at uint256 maximum');continue;}
    try{$client->call($erc->transferRaw($sender,$recipient,$tooMuch)->rpc(),$block);$audit->note($name.'.insufficient_balance','failed','Unexpected successful over-balance simulation');}
    catch(Decimal\RpcException $e){$audit->note($name.'.insufficient_balance',$e->getCode()===3?'expected-revert':'failed',['code'=>$e->getCode(),'message'=>$e->getMessage(),'data'=>$e->rpcData]);}
}
$audit->save(dirname(__DIR__).'/docs/live/eth-call.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
exit($audit->exitCode());
