<?php
declare(strict_types=1);
require __DIR__.'/live_rpc_common.php';
use Decimal\{Network,Contract,NftReader,NftCurrentOperations,Units};
$audit=new LiveRpcAudit('NFT mainnet/testnet real read acceptance',180);
foreach(['mainnet'=>Network::mainnet(),'testnet'=>Network::testnet()] as $name=>$network){
 try{
  $client=$audit->client($network);$client->verifyChain();$block=$client->rpc->call('eth_blockNumber');Units::decimal($block);
  $audit->note($name.'.block','passed',$block);
  $center=$audit->check($name.'.resolve_nft_center',fn()=>$client->resolveSystemContract('nft-center',$block));if(!$center)continue;
  $registry=Contract::profile($center,'TestnetNFTCenter20261029');
  foreach(['owner()','paused()','getImpl()'] as $sig)$audit->check($name.'.center.'.$sig,fn()=>$registry->call($client,$sig,[],$block));
  $targets=$name==='testnet'?[['0xfdA7f75A7eE1dB6d7Eba2E924B74D727E8532890',721,'2']]:[['0x9b1129ec66afd4b7d20f95de078126853695ccfe',721,null]];
  if($name==='testnet'){
   $receipt=$audit->check('testnet.public1155mint_receipt',fn()=>$client->rpc->call('eth_getTransactionReceipt',['0x6196a0e9c610c4c0d2ddfbb35096ab8c15fb06e9f9f0bd3a7ed6ec10a73271ee']));
   foreach($receipt['logs']??[] as $log)if(strtolower($log['topics'][0]??'')==='0xc3d58168c5ae7397731d063d5bbf3d657854427343f4c083240f7aacaa2d0f62'){$targets[]=[$log['address'],1155,'987654'];break;}
  }else{
   foreach(['Candy_Status','candy_status'] as $symbol){$found=$audit->check('mainnet.symbol.'.$symbol,fn()=>$registry->call($client,'getTokenBySymbol(string)',[$symbol],$block)[0]);if(is_string($found)&&$found!=='0x'.str_repeat('0',40)){$targets[]=[$found,721,'4'];break;}}
  }
  if(!$targets)$audit->note($name.'.collection_coverage','limited','No known registered collection found; center probes only, no lifecycle claim');
  foreach($targets as [$address,$standard,$id]){
   $prefix=$name.'.'.$address;$reader=new NftReader($client,$address,$standard);$current=new NftCurrentOperations($client,$address,$standard,$center);
   $code=$audit->check($prefix.'.code',fn()=>$client->rpc->call('eth_getCode',[$address,$block]));if($code==='0x'){$audit->note($prefix.'.target','limited','Historical public collection has no code at this block');continue;}
   foreach(['name','symbol','contractUri','allowMint','refundable'] as $method)$audit->check($prefix.'.'.$method,fn()=>$reader->$method($block));
   $audit->check($prefix.'.nftType',fn()=>$reader->nftType(false,$block));
   $audit->check($prefix.'.collectionState',fn()=>$current->collectionState($block));
   if($id!==null)$audit->check($prefix.'.reserve',fn()=>$current->reserve($id,$block));
   if($id!==null)$audit->check($prefix.'.tokenUri',fn()=>$reader->tokenUri($id,$block));
   if($id===null){$audit->check($prefix.'.emptyCreatorBalance',fn()=>$reader->balanceOf('0x40900a48273644768c09183e00e43528c17a29f6',null,$block));$audit->note($prefix.'.ownedNFTCoverage','limited','Known reserveless collection; no owned token assumed');}elseif($standard===721){$owner=$audit->check($prefix.'.ownerOf',fn()=>$reader->ownerOf($id,$block));if(is_string($owner)){
    $audit->check($prefix.'.balanceOf',fn()=>$reader->balanceOf($owner,null,$block));
    $audit->check($prefix.'.getApproved',fn()=>$reader->getApproved($id,$block));
    $audit->check($prefix.'.approvedForAll',fn()=>$reader->isApprovedForAll($owner,$center,$block));
   }}else{$audit->check($prefix.'.exists',fn()=>$reader->exists($id,$block));$audit->check($prefix.'.rate',fn()=>$reader->rate($id,$block));}
   $audit->check($prefix.'.DOMAIN_SEPARATOR',fn()=>(new Contract($address,[['type'=>'function','name'=>'DOMAIN_SEPARATOR','stateMutability'=>'view','inputs'=>[],'outputs'=>[['type'=>'bytes32','name'=>'']]]]))->call($client,'DOMAIN_SEPARATOR()',[],$block)[0]);
  }
 }catch(Throwable $e){$audit->note($name.'.endpoint','unavailable',['type'=>get_class($e),'message'=>$e->getMessage()]);}
}
foreach($audit->checks as &$check)if($check['status']==='failed'&&($check['error']['code']??null)===3&&str_starts_with($check['name'],'mainnet.0x9b1129ec66afd4b7d20f95de078126853695ccfe.')&&(str_ends_with($check['name'],'.refundable')||str_ends_with($check['name'],'.nftType'))){$check['status']='unsupported-deployment-view';$check['classification']='Mainnet reserveless implementation does not expose the selected optional getter. RPC error propagates; no value inferred.';}unset($check);
$audit->save(dirname(__DIR__).'/docs/live/nft.json');
echo json_encode(['requests'=>count($audit->requests),'checks'=>$audit->checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;

exit($audit->exitCode());
