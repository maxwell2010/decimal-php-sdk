<?php
declare(strict_types=1);
$npZero='0x'.str_repeat('0',40);
foreach(json_decode(file_get_contents(__DIR__.'/nft-position-live-vectors.json'),true,512,JSON_THROW_ON_ERROR)as $np){
 $npRpc=new Decimal\Rpc('http://unused',function($r)use($np){
  if($r['method']==='eth_chainId')return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>Decimal\Units::hex((string)$np['chain_id'])];
  foreach($np['requests']as $record)if($r['method']==='eth_call'&&strtolower($r['params'][0]['to'])===strtolower($record['params'][0]['to'])&&$r['params'][0]['data']===$record['params'][0]['data']){
   if(isset($record['error']))return ['jsonrpc'=>'2.0','id'=>$r['id'],'error'=>['code'=>$record['error']['code'],'message'=>$record['error']['message'],'data'=>$record['error']['data']]];
   return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$record['result']];
  }throw new RuntimeException('Unexpected recorded NFT position request');
 });
 $npClient=new Decimal\Client(new Decimal\Network($np['chain_id'],'http://unused',$npZero),$npRpc);$npReader=new Decimal\NftStakingReader($npClient,$np['unified']);
 $npPosition=$npReader->stake($npZero,'0',$npZero,$npZero,'0',$np['block']);same(count($npPosition),10);same($npPosition['isActive'],false);same($npPosition['amount'],'0');
 same($npReader->freezeTime('1',$np['block']),$np['name']==='mainnet'?'1296000':'600');same($npReader->freezeTime('2',$np['block']),$np['name']==='mainnet'?'604800':'300');
 rejects(fn()=>$npReader->frozen('0',$np['block'])); // Actual index zero is a fungible type4 record, never an NFT.
 if($np['legacy']!==$np['unified']){$npLegacy=new Decimal\NftLegacyStaking($npClient,$np['legacy']);$npFields=$npLegacy->stake($npZero,$npZero,$npZero,'0',$np['block'])->fields;same(count($npFields),7);same(array_key_exists('holdStartTime',$npFields),false);same($npLegacy->freezeTime('1',$np['block']),'2592000');$npFrozen=$npLegacy->frozen('0',$np['block']);same($npFrozen->stake['tokenType'],'2');same(count($npFrozen->stake),7);same($npFrozen->matured('1793344032'),true);}
 $npMax=bcsub(bcpow('2','256',0),'1',0);$npDraft=$npReader->complete('0x000000000000000000000000000000000000dead',[$npMax]);
 try{$npClient->call($npDraft->rpc(),$np['block']);throw new RuntimeException('Expected actual invalid-index revert');}catch(Decimal\RpcException $e){same($e->getCode(),3);same(substr($e->rpcData,0,10),'0x4e487b71');}
}
echo "Actual legacy7/unified10 position reads, fungible rejection and completion reverts passed offline\n";
