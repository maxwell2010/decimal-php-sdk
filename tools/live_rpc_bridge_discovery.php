<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
$audit=new LiveRpcAudit('Bridge source/discovery candidate characterization',90);
foreach(['mainnet'=>Decimal\Network::mainnet(),'testnet'=>Decimal\Network::testnet()] as $name=>$network){
 $client=$audit->client($network);$client->verifyChain();$block=$client->rpc->call('eth_blockNumber');$audit->note($name.'.snapshot','observed',$block);
 foreach(['bridge','bridge-v2'] as $slug){try{$target=$client->resolveSystemContract($slug,$block);$audit->note($name.'.registry.'.$slug,'observed',$target);}catch(Throwable $e){$audit->note($name.'.registry.'.$slug,'unregistered-or-unavailable',['class'=>get_class($e),'message'=>$e->getMessage()]);}}
 if($name!=='testnet')continue;
 $address='0x827ea5381adb912ed0befda26afe8cdc5a5e050a';
 $audit->check('testnet.candidate.runtime',function()use($client,$address,$block){$code=$client->rpc->call('eth_getCode',[$address,$block]);return ['code'=>$code,'keccak'=>'0x'.kornrunner\Keccak::hash(hex2bin(substr($code,2)),256)];});
 $audit->check('testnet.candidate.implementation',fn()=>Decimal\Deployment::implementation($client,$address,$block));
 $audit->check('testnet.candidate.token_metadata',fn()=>(new Decimal\Erc20Service($client,$address))->info($block));
 $views=[['minimalServiceFees',['uint16'],['2'],'uint256'],['minimalServiceFees',['uint256'],['2'],'uint256'],['chainId',[],[],'uint256'],['wormhole',[],[],'address']];
 foreach($views as [$method,$types,$args,$output]){
  $c=new Decimal\Contract($address,[['type'=>'function','name'=>$method,'stateMutability'=>'view','inputs'=>array_map(fn($t)=>['name'=>'','type'=>$t],$types),'outputs'=>[['name'=>'','type'=>$output]]]]);
  try{$v=$c->call($client,$method.'('.implode(',',$types).')',$args,$block);$audit->note('testnet.candidate.'.$method.'('.implode(',',$types).')','observed',$v);}catch(Throwable $e){$audit->note('testnet.candidate.'.$method.'('.implode(',',$types).')','unsupported-view',['code'=>$e->getCode(),'message'=>$e->getMessage(),'data'=>$e instanceof Decimal\RpcException?$e->rpcData:null]);}
 }
}
foreach($audit->checks as &$entry)if($entry['name']==='testnet.candidate.token_metadata'&&$entry['status']==='failed'&&($entry['error']['code']??null)===3)$entry['status']='rejected-unrelated-token-abi';unset($entry);
$audit->save(dirname(__DIR__).'/docs/live/bridge-discovery.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
exit($audit->exitCode());
