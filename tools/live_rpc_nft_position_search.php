<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
use Decimal\{Network,Units,Contract,Events,NftStakingReader};

// A fixed 4000-block horizon per chain, split into eight inclusive 500-block windows.
// This is an opt-in discovery tool, never a historical full-chain scan.
$audit=new LiveRpcAudit('Bounded unified NFT position search',110);
$abi=json_decode(file_get_contents(dirname(__DIR__).'/abi/Delegation20261030.json'),true,512,JSON_THROW_ON_ERROR);
$event=array_values(array_filter($abi,fn($e)=>($e['type']??'')==='event'&&$e['name']==='NFTDelegated'))[0];
$topic='0x'.kornrunner\Keccak::hash(Contract::signature($event),256);
foreach(['mainnet'=>Network::mainnet(),'testnet'=>Network::testnet()]as $name=>$network){
 $client=$audit->client($network);$client->verifyChain();$block=$client->rpc->call('eth_blockNumber');
 $deployment=$client->resolveSystemContract('delegation',$block);$reader=new NftStakingReader($client,$deployment);$logs=[];
 $audit->note($name.'.snapshot','observed',compact('block','deployment'));
 for($window=0;$window<8;$window++){
  $to=bcsub(Units::decimal($block),(string)($window*500),0);if(bccomp($to,'0',0)<0)break;
  $from=bccomp($to,'499',0)<0?'0':bcsub($to,'499',0);
  $batch=$audit->check($name.'.window.'.$window,fn()=>$client->rpc->call('eth_getLogs',[['address'=>$deployment,'fromBlock'=>Units::hex($from),'toBlock'=>Units::hex($to),'topics'=>[$topic]]]));
  foreach($batch??[]as $log)$logs[]=$log;
 }
 $seen=[];$active=0;
 foreach($logs as $log){
  if(count($seen)>=6)break;
  $p=Events::decode($abi,$log,$deployment)['args'];$key=$p['nftStakeId'];if(isset($seen[$key]))continue;$seen[$key]=true;
  // NFTDelegated has no hold timestamp. Probe unheld keys only and explicitly record this limit.
  $position=$audit->check($name.'.position.'.count($seen),fn()=>$reader->stake($p['nftContract'],$p['tokenId'],$p['delegator'],$p['validator'],'0',$block));
  if(($position['isActive']??false)&&($position['amount']??'0')!=='0')$active++;
  $historical=$audit->check($name.'.event_block_position.'.count($seen),fn()=>$reader->stake($p['nftContract'],$p['tokenId'],$p['delegator'],$p['validator'],'0',$log['blockNumber']));
  if(($historical['isActive']??false)&&($historical['amount']??'0')!=='0'){
   $nftType=$historical['nftType']==='2'?721:1155;
   $ops=new Decimal\NftOperations($client,$p['nftContract'],$nftType);
   $draft=$ops->withdraw($p['delegator'],$deployment,$p['validator'],$p['tokenId'],$historical['amount'],null,'Delegation20261030');
   $audit->check($name.'.historical_withdraw_simulation.'.count($seen),fn()=>$client->call($draft->rpc(),$log['blockNumber']));
  }
 }
 $audit->note($name.'.coverage','observed',['windows'=>8,'maximum_blocks'=>4000,'events'=>count($logs),'unique_positions_read'=>count($seen),'active_unheld_positions'=>$active,'limit'=>'At most six unique unheld positions; event lacks holdTimestamp. Empty or inactive results do not establish absence of funded or held positions.']);
}
$audit->save(dirname(__DIR__).'/docs/live/nft-position-search.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
exit($audit->exitCode());
