<?php
declare(strict_types=1);
require_once __DIR__.'/live_rpc_common.php';
use Decimal\{Network,Units,Contract,StakingReader,NftStakingReader,ValidatorService,Events};
$audit=new LiveRpcAudit('staking',240);
$knownIds=['0x2f85914a2bdf5731de9700a00e3fa64036fd7f0ea14389f3d17738f346ad9273','0x4fef77053ad8e99ac63f0010f19431b6711a68088b4c39a984eeb77b48e23864','0x48a24baf7400f7887627d992a73d9560cb19836b7a738ee736b483bd8e6095e2','0x8738df9516bee66b399267dc35874ee6fd2ca00123c406bd94534cc6c84b84c1'];
$zero='0x'.str_repeat('0',40);
foreach(['mainnet'=>Network::mainnet(),'testnet'=>Network::testnet()] as $name=>$network){
 $client=$audit->client($network);
 $head=$audit->check($name.'.head',function()use($client){$client->verifyChain();$head=$client->rpc->call('eth_blockNumber');Units::decimal($head);return $head;});
 if($head===null){$audit->note($name.'.coverage','limitation','Primary endpoint unavailable; no contract acceptance inferred');continue;}
 $audit->check($name.'.header',fn()=>$client->rpc->call('eth_getBlockByNumber',[$head,false]));
 $deployment=$audit->check($name.'.resolve.delegation',fn()=>$client->resolveSystemContract('delegation',$head));
 if($deployment!==null){
  $audit->check($name.'.delegation.code',fn()=>$client->rpc->call('eth_getCode',[$deployment,$head]));
  $contract=Contract::profile($deployment,'Delegation20261030');
  $rows=$audit->check($name.'.stakes',function()use($client,$contract,$head,$knownIds){$rows=(new StakingReader($client,$contract))->stakes($knownIds,$head);if(count($rows)!==count($knownIds))throw new RuntimeException('Stake count mismatch');foreach($rows as $row)if(!in_array(count($row),[7,8],true))throw new RuntimeException('Stake width mismatch');return $rows;});
  if($rows!==null){
   $active=array_values(array_filter($rows,fn($r)=>$r[3]!=='0'));
   $audit->note($name.'.active_stake_count','observed',count($active));
   if($active){$r=$active[0];$audit->check($name.'.regular_stake',fn()=>(new StakingReader($client,$contract))->stake($r[0],$r[1],$r[2],$head)->fields);}
  }
  $audit->check($name.'.empty_nft_stake',fn()=>(new NftStakingReader($client,$deployment))->stake($zero,'0',$zero,$zero,'0',$head));
  $audit->check($name.'.nft_withdraw_freeze',fn()=>(new NftStakingReader($client,$deployment))->freezeTime('1',$head));
  $audit->note($name.'.funded_nft_coverage','limitation','Empty NFT position verifies actual tuple10 decoding only; no known funded NFT lifecycle exercised');
 }
 $master=$audit->check($name.'.resolve.master-validator',fn()=>$client->resolveSystemContract('master-validator',$head));
 if($master!==null){$service=new ValidatorService($client,$master);foreach(['owner','implementation','reward','validator','status','active','member','penalties'] as $query)$audit->check($name.'.validator.'.$query,fn()=>$service->read($query,$zero,$head));}
 if($name==='mainnet'&&$deployment!==null){
  $logs=$audit->check('mainnet.historical_stake_logs',fn()=>$client->rpc->call('eth_getLogs',[['address'=>$deployment,'fromBlock'=>'0x20c1ee0','toBlock'=>'0x20c2060','topics'=>[['0x58d3d64d5ec2e281761bdebe1b59491d61dd8ff0d2fc459c5ee3b128d29f0959','0xd68af310e1fd0d07cdc1aa6e9b62fd96f93ca318e225c938f87d8bd93de907e5']]] ]));
  if($logs!==null)$audit->check('mainnet.decode_real_stake_logs',function()use($logs,$deployment){$abi=json_decode(file_get_contents(dirname(__DIR__).'/abi/Delegation20261030.json'),true);$counts=[];foreach($logs as $log){$event=Events::decode($abi,$log,$deployment);$counts[$event['event']]=($counts[$event['event']]??0)+1;}return $counts;});
 }
}
$audit->save(dirname(__DIR__).'/docs/live/staking.json');
foreach($audit->checks as $check)echo $check['name'],' ',$check['status'],isset($check['error'])?' '.$check['error']['message']:'',PHP_EOL;
echo count($audit->requests)," real read requests; no signing, estimates or sends\n";

exit($audit->exitCode());
