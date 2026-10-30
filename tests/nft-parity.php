<?php
declare(strict_types=1);
$nftA='0x'.str_repeat('11',20);$nftB='0x'.str_repeat('22',20);$nftC='0x'.str_repeat('33',20);$nftBalance='10';$nftOwner=$nftA;$nftApproved=false;$nftCalls=0;
$nftRpc=new Decimal\Rpc('http://unused',function($r)use(&$nftBalance,&$nftOwner,&$nftApproved,&$nftCalls){
 ++$nftCalls;$codec=new Decimal\AbiCodec();$data=$r['method']==='eth_chainId'?'0x31524':match(substr($r['params'][0]['data'],0,10)){
 '0x6352211e'=>$codec->encodeParameters(['address'],[$nftOwner]),'0x00fdd58e'=>$codec->encodeParameters(['uint256'],[$nftBalance]),'0xe985e9c5'=>$codec->encodeParameters(['bool'],[$nftApproved]),default=>throw new RuntimeException('Unexpected NFT RPC')};
 return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$data];
});$nftClient=new Decimal\Client(Decimal\Network::testnet(),$nftRpc);
foreach(json_decode(file_get_contents(__DIR__.'/nft-parity-vectors.json'),true) as $v){$service=new Decimal\NftOperations($nftClient,$nftB,$v['standard']);$draft=$service->{$v['method']}(...$v['args']);same($draft->data,$v['data']);same($draft->value,$v['value']);}
$n721=new Decimal\NftOperations($nftClient,$nftB,721);$n1155=new Decimal\NftOperations($nftClient,$nftB,1155);
rejects(fn()=>$n1155->mint($nftA,$nftC,'uri'));
rejects(fn()=>$n721->mint($nftA,$nftC,'uri',null,'1','1','1'));
rejects(fn()=>$n1155->transferBatch1155($nftA,$nftC,['7'],[]));
rejects(fn()=>$n1155->transferBatch1155($nftA,$nftC,['7','7'],['6','6']));
$nftOwner=$nftC;rejects(fn()=>$n721->approve($nftA,$nftC,'7'));$nftOwner=$nftA;
$nftBalance='0';rejects(fn()=>$n1155->burn($nftA,'7'));$nftBalance='10';
foreach([721,1155] as $std){$svc=$std===721?$n721:$n1155;
 foreach(['PythonDelegationNft','Delegation20261030'] as $profile){
  foreach([null,'123'] as $hold){
   $plan=$hold===null?$svc->delegate($nftA,$nftC,$nftA,'7','1',true,$profile):$svc->hold($nftA,$nftC,$nftA,'7',$hold,'1',true,$profile);
   same(count($plan['approvals']),1);same($plan['action']->to,$nftC);same($plan['approvals'][0]->to,$nftB);
   $exit=$svc->withdraw($nftA,$nftC,$nftA,'7','1',$hold,$profile);same($exit->to,$nftC);
   $transfer=$svc->transferStake($nftA,$nftC,$nftA,'7',$nftB,'1',$hold,$profile);same($transfer->to,$nftC);
  }
 }
}
rejects(fn()=>$n721->hold($nftA,$nftC,$nftA,'7','123','1',false));
$nftApproved=true;same(count($n721->hold($nftA,$nftC,$nftA,'7','123')['approvals']),0);
$exits=$n1155->withdrawMany($nftA,$nftC,$nftA,['7','7'],['2','3']);same(count($exits),2);same($exits[0]->data!==$exits[1]->data,true);
rejects(fn()=>$n1155->withdrawMany($nftA,$nftC,$nftA,['7'],['2','3']));
$nftBefore=$nftCalls;$mainNft=new Decimal\NftOperations(new Decimal\Client(Decimal\Network::mainnet(),$nftRpc),$nftB,721);rejects(fn()=>$mainNft->hold($nftA,$nftC,$nftA,'7','123','1',true,'PythonDelegationNft'));same($nftCalls,$nftBefore);
echo "NFT request parity and independent ABI vectors passed\n";

foreach(json_decode(file_get_contents(__DIR__.'/nft-stake-parity-vectors.json'),true) as $v){
 $svc=new Decimal\NftOperations($nftClient,$nftB,$v['standard']);
 $d=$v['transfer']?$svc->transferStake($nftA,$nftC,$nftA,'7',$nftB,'1',$v['hold'],$v['profile']):$svc->withdraw($nftA,$nftC,$nftA,'7','1',$v['hold'],$v['profile']);same($d->data,$v['data']);
}
$nftTuple=[$nftB,'7','3','2',$nftA,$nftC,$nftB,'999','123',true];$nftReadBlock=null;
$nftStakeRpc=new Decimal\Rpc('http://unused',function($r)use($nftTuple,&$nftReadBlock){$codec=new Decimal\AbiCodec();if($r['method']==='eth_chainId')$v='0x31524';else{$nftReadBlock=$r['params'][1];$v=$codec->encodeParameters(['(address,uint256,uint256,uint8,address,address,address,uint256,uint256,bool)'],[$nftTuple]);}return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$v];});
$nftPosition=(new Decimal\NftStakingReader(new Decimal\Client(Decimal\Network::testnet(),$nftStakeRpc),$nftC))->stake($nftB,'7',$nftA,$nftC,'123','0x20');
same(array_values($nftPosition),$nftTuple);same($nftPosition['reserveAmount'],'999');same($nftPosition['amount'],'3');same($nftPosition['isActive'],true);same($nftReadBlock,'0x20');
$nftReadRpc=new Decimal\Rpc('http://unused',function($r){return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':(new Decimal\AbiCodec())->encodeParameters(['string'],['Collection'])];});
same((new Decimal\NftReader(new Decimal\Client(Decimal\Network::testnet(),$nftReadRpc),$nftB,721))->name(),'Collection');
foreach([721,1155] as $std){$svc=new Decimal\NftOperations($nftClient,$nftB,$std);same($svc->addEthReserve($nftA,'7','11')->value,'11');same($svc->addReserve($nftA,'7','11')->value,'0');same(Decimal\NftOperations::createReservelessCollection($nftC,$std,$nftA,$nftA,'SYM','Name','uri',true,false)->value,'0');}
$nftEc=new Decimal\AbiCodec();$nftLog=['address'=>$nftB,'logIndex'=>'0x2','topics'=>['0x'.kornrunner\Keccak::hash('TransferBatch(address,address,address,uint256[],uint256[])',256),$nftEc->encodeParameters(['address'],[$nftC]),$nftEc->encodeParameters(['address'],[$nftA]),$nftEc->encodeParameters(['address'],[$nftC])],'data'=>$nftEc->encodeParameters(['uint256[]','uint256[]'],[['7','7'],['2','3']])];
$nftMoves=Decimal\NftReceipts::movements(['status'=>'0x1','logs'=>[$nftLog,$nftLog]],$nftB);same(count($nftMoves),2);same($nftMoves[0]['tokenIds'],['7','7']);same($nftMoves[0]['amounts'],['2','3']);
$nftLog['data']=$nftEc->encodeParameters(['uint256[]','uint256[]'],[['7','7'],['2']]);rejects(fn()=>Decimal\NftReceipts::movements(['status'=>'0x1','logs'=>[$nftLog]],$nftB));
$nftRaw=$n721->transferFrom($nftC,$nftA,$nftB,'7','1','0x1234');same($nftRaw->from,$nftC);same(substr($nftRaw->data,0,10),'0xb88d4fde');
same(substr($n721->transferFrom($nftC,$nftA,$nftB,'7','1',null,false)->data,0,10),'0x23b872dd');
foreach(json_decode(file_get_contents(__DIR__.'/nft-permit-vectors.json'),true) as $v){$typed=Decimal\NftPermitService::typedData($v['standard'],$nftB,'Collection','1',$nftA,$nftC,'2','4000000000','202020',$v['standard']===721?'7':null);same($typed['domainSeparator'],$v['domain']);same($typed['digest'],$v['digest']);}
$nftPermitOwner=$nftA;$nftPermitNonce='2';$nftPermitCode='0x';$nftPermitDomain=Decimal\NftPermitService::typedData(721,$nftB,'Collection','1',$nftA,$nftC,'2','4000000000','202020','7')['domainSeparator'];
$nftPermitRpc=new Decimal\Rpc('http://unused',function($r)use(&$nftPermitOwner,&$nftPermitNonce,&$nftPermitDomain,&$nftPermitCode){if($r['method']==='eth_getCode')return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$nftPermitCode];$c=new Decimal\AbiCodec();$result=$r['method']==='eth_chainId'?'0x31524':match(substr($r['params'][0]['data']??'',0,10)){'0x6352211e'=>$c->encodeParameters(['address'],[$nftPermitOwner]),'0x06fdde03'=>$c->encodeParameters(['string'],['Collection']),'0x7ecebe00','0x141a468c'=>$c->encodeParameters(['uint256'],[$nftPermitNonce]),'0x3644e515'=>$c->encodeParameters(['bytes32'],[$nftPermitDomain]),default=>throw new RuntimeException('Unexpected permit call')};return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];});
$nftPermitSvc=new Decimal\NftPermitService(new Decimal\Client(Decimal\Network::testnet(),$nftPermitRpc),$nftB,721,'1');same($nftPermitSvc->prepare($nftA,$nftC,'4000000000','7')['message']['nonce'],'2');
$nftPermitOwner=$nftC;rejects(fn()=>$nftPermitSvc->prepare($nftA,$nftC,'4000000000','7'));$nftPermitOwner=$nftA;$nftPermitDomain='0x'.str_repeat('00',32);rejects(fn()=>$nftPermitSvc->prepare($nftA,$nftC,'4000000000','7'));rejects(fn()=>$nftPermitSvc->prepare($nftA,$nftC,'1','7'));
rejects(fn()=>Decimal\NftPermitService::typedData(1155,$nftB,'Collection','1',$nftA,$nftC,'2','4000000000','202020','7'));
$nftPermitWallet=new Decimal\Wallet(str_repeat('0',63).'1');$nftPermitOwner=$nftPermitWallet->address;
$nftPermitDomain=Decimal\NftPermitService::typedData(721,$nftB,'Collection','1',$nftPermitOwner,$nftC,'2','4000000000','202020','7')['domainSeparator'];
$nftPermitPrepared=$nftPermitSvc->prepare($nftPermitOwner,$nftC,'4000000000','7');$nftPermitSig=$nftPermitWallet->signHash($nftPermitPrepared['digest']);
same($nftPermitSvc->verifyExternal($nftPermitOwner,$nftC,'4000000000',$nftPermitSig,'7')['signature'],$nftPermitSig);
$nftPermitNonce='3';rejects(fn()=>$nftPermitSvc->verifyExternal($nftPermitOwner,$nftC,'4000000000',$nftPermitSig,'7'));$nftPermitNonce='2';
$nftPermitParts=$nftPermitSvc->verifyExternal($nftPermitOwner,$nftC,'4000000000',$nftPermitSig,'7');
same($nftPermitSvc->draft($nftA,$nftPermitOwner,$nftC,'4000000000',$nftPermitParts['v'],$nftPermitParts['r'],$nftPermitParts['s'],'7')->value,'0');

$nftPermitCode='0x6000';rejects(fn()=>$nftPermitSvc->verifyExternal($nftPermitOwner,$nftC,'4000000000',$nftPermitSig,'7'));$nftPermitCode='0x';
foreach([['0x'.str_repeat('00',32),$nftPermitParts['s']],[$nftPermitParts['r'],'0x'.str_repeat('00',32)],['0xfffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141',$nftPermitParts['s']],[$nftPermitParts['r'],'0x7fffffffffffffffffffffffffffffff5d576e7357a4501ddfe92f46681b20a1']] as [$r,$s])rejects(fn()=>$nftPermitSvc->draft($nftA,$nftPermitOwner,$nftC,'4000000000','27',$r,$s,'7'));
$nftLog['data']=$nftEc->encodeParameters(['uint256[]','uint256[]'],[['7','7'],['2','3']]);$nftForeign=$nftLog;$nftForeign['address']=$nftC;$nftForeign['logIndex']='malformed';same(count(Decimal\NftReceipts::movements(['status'=>'0x1','logs'=>[$nftForeign,$nftLog]],$nftB)),1);
$nftRemoved=$nftLog;$nftRemoved['removed']=true;rejects(fn()=>Decimal\NftReceipts::movements(['status'=>'0x1','logs'=>[$nftRemoved]],$nftB));
