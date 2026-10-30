<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
use Decimal\{Network,Units,Contract,Events,NftStakingReader,NftLegacyStaking};
$audit=new LiveRpcAudit('NFT positions, exits and bounded discovery',180);$zero='0x'.str_repeat('0',40);$dead='0x000000000000000000000000000000000000dead';
foreach(['mainnet'=>Network::mainnet(),'testnet'=>Network::testnet()]as $name=>$network){
 $client=$audit->client($network);$client->verifyChain();$block=$client->rpc->call('eth_blockNumber');$from=Units::hex(bcsub(Units::decimal($block),'499',0));$unified=$client->resolveSystemContract('delegation',$block);$legacy=$name==='mainnet'?'0xe45adfcc739a0d10ce9462b58866c9a1a06035e2':$unified;
 $audit->note($name.'.snapshot','observed',compact('block','from','unified','legacy'));
 $abi=json_decode(file_get_contents(dirname(__DIR__).'/abi/Delegation20261030.json'),true);$event=array_values(array_filter($abi,fn($e)=>($e['type']??'')==='event'&&$e['name']==='NFTDelegated'))[0];$topic='0x'.kornrunner\Keccak::hash(Contract::signature($event),256);
 $logs=$audit->check($name.'.recent_nft_delegated',fn()=>$client->rpc->call('eth_getLogs',[['address'=>$unified,'fromBlock'=>$from,'toBlock'=>$block,'topics'=>[$topic]]]));
 $positions=[];foreach($logs??[]as $log){$d=Events::decode($abi,$log,$unified);$positions[]=$d['args'];}
 $reader=new NftStakingReader($client,$unified);
 foreach($positions as $i=>$p){if($i>=3)break;$audit->check($name.'.actual_position.'.$i,fn()=>$reader->stake($p['nftContract'],$p['tokenId'],$p['delegator'],$p['validator'],'0',$block));}
 if(!$positions)$audit->note($name.'.funded_position_coverage','limitation','No NFTDelegated events in bounded latest500block window; no funded NFT position guessed or eligible exit claimed');
 $audit->check($name.'.empty_position',fn()=>$reader->stake($zero,'0',$zero,$zero,'0',$block));$id=$audit->check($name.'.empty_position_id',fn()=>$reader->stakeId($zero,'0',$zero,$zero,'0',$block));if($id!==null)$audit->check($name.'.empty_penalty_index',fn()=>$reader->penaltyIndex($id,$block));
 foreach(['1','2']as $type)$audit->check($name.'.unified_freeze.'.$type,fn()=>$reader->freezeTime($type,$block));
 $c=Contract::profile($unified,'Delegation20261030');$raw=$audit->check($name.'.raw_frozen_zero',fn()=>$c->call($client,'getFrozenStake(uint256)',['0'],$block));
 if($raw!==null){$r=$raw[0];if(in_array($r[0][5],['2','3'],true)||$r[0][3]==='0')$audit->check($name.'.typed_frozen_zero',fn()=>$reader->frozen('0',$block)->stake);else $audit->note($name.'.frozen_zero_type','observed','Index0 contains fungible stake, so NFT reader is not used for this position');}
 if($legacy!==$unified){
  $l=new NftLegacyStaking($client,$legacy);$audit->check($name.'.legacy_code',fn()=>$client->rpc->call('eth_getCode',[$legacy,$block]));
  $audit->check($name.'.legacy_empty_stake',fn()=>$l->stake($zero,$zero,$zero,'0',$block)->fields);
  foreach(['1','2']as $type)$audit->check($name.'.legacy_freeze.'.$type,fn()=>$l->freezeTime($type,$block));
  $audit->note($name.'.legacy_retirement','limitation','Retired legacy new-deposit policy is source/node-hook evidence; eth_call without an eligible NFT owner/approval cannot prove admission or funded exit behavior');
 }
 $max=bcsub(bcpow('2','256',0),'1',0);$draft=$reader->complete($dead,[$max]);
 try{$value=$client->call($draft->rpc(),$block);$audit->note($name.'.invalid_completion','observed',['result'=>$value,'meaning'=>'Nonpersistent invalid-index call returned; not evidence of successful funded completion']);}catch(Decimal\RpcException $e){$audit->note($name.'.invalid_completion',$e->getCode()===3?'expected-revert':'failed',['code'=>$e->getCode(),'message'=>$e->getMessage(),'data'=>$e->rpcData]);}
}
$audit->save(dirname(__DIR__).'/docs/live/nft-positions.json');echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;exit($audit->exitCode());
