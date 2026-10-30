<?php
declare(strict_types=1);
// Replay exact recorded sender, address, calldata and pinned block. No HTTP.
$lc=json_decode(file_get_contents(dirname(__DIR__).'/docs/live/nft-legacy-exit-custody.json'),true,512,JSON_THROW_ON_ERROR);
$lcSnapshot=$lc['checks'][0]['value'];
$lcRequests=array_values(array_filter($lc['requests'],fn($r)=>$r['method']==='eth_call'));
$lcRpc=new Decimal\Rpc('http://unused',function(array $r)use($lcRequests){
 if($r['method']==='eth_chainId')return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>'0x4b'];
 foreach($lcRequests as $row)if($r['method']===$row['method']&&$r['params']===$row['params'])return ['jsonrpc'=>'2.0','id'=>$r['id']]+(isset($row['error'])?['error'=>['code'=>$row['error']['code'],'message'=>$row['error']['message'],'data'=>$row['error']['data']]]:['result'=>$row['result']]);
 throw new RuntimeException('Unexpected custody diagnostic replay request');
});
$lcClient=new Decimal\Client(Decimal\Network::mainnet(),$lcRpc);
$lcPause=new Decimal\Contract($lcSnapshot['legacy'],[['type'=>'function','name'=>'paused','inputs'=>[],'outputs'=>[['name'=>'','type'=>'bool']]]]);
same($lcPause->call($lcClient,'paused()',[],$lcSnapshot['block'])[0],true);
same((new Decimal\AbiCodec())->encodeFunctionSignature('EnforcedPause()'),'0xd93c0665');
same((new Decimal\AbiCodec())->encodeFunctionSignature('ERC721NonexistentToken(uint256)'),'0x7e273289');
$lcReader=new Decimal\NftReader($lcClient,$lcSnapshot['asset'],721);
$lcOperations=new Decimal\NftOperations($lcClient,$lcSnapshot['asset'],721);
$lcLegacy=new Decimal\NftLegacyStaking($lcClient,$lcSnapshot['legacy']);
$lcDraft=$lcLegacy->plan('complete_stake',$lcSnapshot['delegator'],['indexes'=>['0']]);
$lcTransfer=$lcOperations->transferFrom($lcSnapshot['legacy'],$lcSnapshot['legacy'],$lcSnapshot['delegator'],'1');
$lcErrors=[['type'=>'error','name'=>'EnforcedPause','inputs'=>[]],['type'=>'error','name'=>'ERC721NonexistentToken','inputs'=>[['type'=>'uint256','name'=>'tokenId']]]];
foreach([fn()=>$lcReader->ownerOf('1',$lcSnapshot['block']),fn()=>$lcClient->simulate($lcTransfer,$lcSnapshot['block']),fn()=>$lcClient->simulate($lcDraft,$lcSnapshot['block'])] as $lcIndex=>$lcCall){
 try{$lcCall();throw new RuntimeException('Expected captured custody revert');}
 catch(Decimal\RpcException $e){same($e->getCode(),3);same($e->rpcData,$lcIndex===2?'0xd93c0665':'0x7e273289'.str_repeat('0',63).'1');same($e->getMessage(),'execution reverted');same($e->revertReason($lcErrors)['signature'],$lcIndex===2?'EnforcedPause()':'ERC721NonexistentToken(uint256)');same($e->revertReason($lcErrors)['arguments'],$lcIndex===2?[]:['1']);}
}
echo "Pinned legacy NFT custody/exit custom reverts preserved offline\n";


