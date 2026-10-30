<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
use Decimal\{Network,Contract,NftReader,NftCurrentOperations};
$audit=new LiveRpcAudit('Extended NFT nonpersistent action simulations',220);
$zero='0x'.str_repeat('0',40);$operator='0x000000000000000000000000000000000000dead';
foreach(['mainnet'=>Network::mainnet(),'testnet'=>Network::testnet()] as $name=>$network){
 $client=$audit->client($network);$client->verifyChain();$block=$client->rpc->call('eth_blockNumber');$center=$client->resolveSystemContract('nft-center',$block);
 $targets=$name==='mainnet'?[['0x9b1129ec66afd4b7d20f95de078126853695ccfe',721,null]]:[['0xfdA7f75A7eE1dB6d7Eba2E924B74D727E8532890',721,'2'],['0x918e742d8dd0de7e7930fe0489cdbce1701fb29d',1155,'987654']];
 foreach($targets as [$address,$standard,$id]){
  $prefix=$name.'.'.$address;$reader=new NftReader($client,$address,$standard);$ops=new NftCurrentOperations($client,$address,$standard,$center);
  $creator=$audit->check($prefix.'.creator',fn()=>$reader->creator($block));if(!$creator)continue;
  $state=$audit->check($prefix.'.state',fn()=>$ops->collectionState($block));if(!$state)continue;
  $audit->note($prefix.'.snapshot','observed',compact('block','address','id','creator','state'));
  $owner=$standard===721&&$id!==null?$audit->check($prefix.'.owner',fn()=>$reader->ownerOf($id,$block)):$creator;
  if($standard===1155){$owner='0xb92bd490c8e9e5d9c46d71666418d4e7ae4534e9';$balance=$audit->check($prefix.'.balance',fn()=>$reader->balanceOf($owner,$id,$block));if($balance==='0'){$audit->note($prefix.'.owned-actions','limited','Known historical owner no longer holds this ID');$id=null;}}
  $drafts=['operator-approval-clear'=>$ops->setApprovalForAll($owner,$operator,false)];
  if($id!==null){
   if($standard===721)$drafts['token-approval-clear']=$ops->approve($owner,$zero,$id,$block);
   $drafts['self-transfer']=$ops->transfer($owner,$owner,$id,'1','0x',$block);
   if($standard===1155)$drafts['batch-self-transfer']=$ops->transferBatch1155($owner,$owner,[$id],['1'],'0x',$block);
  }
  foreach($drafts as $case=>$draft)$audit->check($prefix.'.eth_call.'.$case,function()use($client,$draft,$block){$raw=$client->call($draft->rpc(),$block);if($raw!=='0x')throw new RuntimeException('Unexpected void-action response');return ['result'=>$raw,'draft'=>$draft->rpc()];});
  if($id!==null){$burn=$ops->burn($owner,$id,'1',$block);try{$raw=$client->call($burn->rpc(),$block);$audit->note($prefix.'.eth_call.burn','passed',['result'=>$raw,'draft'=>$burn->rpc()]);}catch(Decimal\RpcException $e){$audit->note($prefix.'.eth_call.burn','failed',['code'=>$e->getCode(),'data'=>$e->rpcData,'message'=>$e->getMessage()]);}}
  try{
   $minimum='0';if($state['withReserve'])$minimum=Contract::profile($address,'ERC721')->call($client,'MIN_RESERVE()',[],$block)[0];
   $mint=$ops->mint($creator,$creator,'ipfs://decimal-sdk-read-only-simulation',$standard===1155?'987655':null,'1',$minimum,'0',$zero,null,$block);
   $raw=$client->call($mint->rpc(),$block);$audit->note($prefix.'.eth_call.mint','passed',['result'=>$raw,'draft'=>$mint->rpc(),'scope'=>'ephemeral EVM execution only; no token was created on chain']);
  }catch(Throwable $e){$audit->note($prefix.'.eth_call.mint','failed',['type'=>get_class($e),'message'=>$e->getMessage(),'code'=>$e->getCode(),'data'=>$e instanceof Decimal\RpcException?$e->rpcData:null]);}
 }
}
$audit->save(dirname(__DIR__).'/docs/live/nft-extended.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
exit($audit->exitCode());
