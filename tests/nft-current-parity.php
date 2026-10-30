<?php
declare(strict_types=1);
$ncA='0x'.str_repeat('11',20);$ncB='0x'.str_repeat('22',20);$ncC='0x'.str_repeat('33',20);$ncOwner=$ncA;$ncStandard='0';$ncActive=true;$ncReserved=true;$ncReserveType='0';$ncReserveToken='0x'.str_repeat('00',20);$ncRate='10';$ncMinimum='5';$ncPositionAmount='3';$ncChecks=0;$ncTokenAllowed=true;
$ncRpc=new Decimal\Rpc('http://unused',function($r)use(&$ncOwner,&$ncStandard,&$ncActive,&$ncReserved,&$ncReserveType,&$ncReserveToken,&$ncRate,&$ncMinimum,&$ncPositionAmount,&$ncChecks,&$ncTokenAllowed,$ncA,$ncB,$ncC){
 if($r['method']==='eth_chainId')return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>'0x31524'];++$ncChecks;$c=new Decimal\AbiCodec();$sel=substr($r['params'][0]['data'],0,10);$sig=fn($s)=>$c->encodeFunctionSignature($s);
 if($sel===$sig('getNFTState(address)'))$v=$c->encodeParameters(['(bool,uint8,bool)'],[[$ncActive,$ncStandard,$ncReserved]]);
 elseif($sel===$sig('getReserve(uint256)'))$v=$c->encodeParameters(['(address,uint256,uint8)'],[[$ncReserveToken,'20',$ncReserveType]]);
 elseif($sel===$sig('MIN_RESERVE()'))$v=$c->encodeParameters(['uint256'],[$ncMinimum]);
 elseif($sel===$sig('rate(uint256)'))$v=$c->encodeParameters(['uint256'],[$ncRate]);
 elseif($sel===$sig('ownerOf(uint256)'))$v=$c->encodeParameters(['address'],[$ncOwner]);
 elseif($sel===$sig('balanceOf(address,uint256)'))$v=$c->encodeParameters(['uint256'],['10']);
 elseif($sel===$sig('checkToken(address)')){if(!$ncTokenAllowed)throw new RuntimeException('Token forbidden');$v='0x';}
 elseif($sel===$sig('getNFTStake(address,uint256,address,address,uint256)'))$v=$c->encodeParameters(['(address,uint256,uint256,uint8,address,address,address,uint256,uint256,bool)'],[[$ncB,'7',$ncPositionAmount,'2',$ncA,$ncA,$ncA,'20','0',true]]);
 else throw new RuntimeException('Unexpected current NFT read '.$sel);
 return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$v];
});$ncClient=new Decimal\Client(Decimal\Network::testnet(),$ncRpc);$nc721=new Decimal\NftCurrentOperations($ncClient,$ncB,721,$ncC);$nc1155=new Decimal\NftCurrentOperations($ncClient,$ncB,1155,$ncC);
$ncPermit=['deadline'=>'4000000000','v'=>'27','r'=>'0x'.str_repeat('00',31).'01','s'=>'0x'.str_repeat('00',31).'01'];
$ncBeforeTransfer=$ncChecks;
foreach(['0','2','01','-1',bcpow('2','256',0)]as $badAmount)rejects(fn()=>$nc721->transfer($ncA,$ncC,'7',$badAmount));
same($ncChecks,$ncBeforeTransfer); // Invalid ERC721 amounts must reject before ownership RPC, not silently transfer one token.
foreach(['0','01','-1',bcpow('2','256',0)]as $badAmount)rejects(fn()=>$nc1155->transfer($ncA,$ncC,'7',$badAmount));
rejects(fn()=>$nc721->transfer($ncA,$ncC,'7','1','0xabcd'));
same($ncChecks,$ncBeforeTransfer);
$ncTransfer=$nc721->transfer($ncA,$ncC,'7');same($ncTransfer->to,$ncB);
$ncCodec=new Decimal\AbiCodec();
same($ncTransfer->data,$ncCodec->encodeFunctionSignature('safeTransferFrom(address,address,uint256)').substr($ncCodec->encodeParameters(['address','address','uint256'],[$ncA,$ncC,'7']),2));
same($nc1155->transfer($ncA,$ncC,'7','2','0xabcd')->data,$ncCodec->encodeFunctionSignature('safeTransferFrom(address,address,uint256,uint256,bytes)').substr($ncCodec->encodeParameters(['address','address','uint256','uint256','bytes'],[$ncA,$ncC,'7','2','0xabcd']),2));
same($nc721->transferFrom($ncA,$ncA,$ncC,'7','1','0xabcd')->data,$ncCodec->encodeFunctionSignature('safeTransferFrom(address,address,uint256,bytes)').substr($ncCodec->encodeParameters(['address','address','uint256','bytes'],[$ncA,$ncC,'7','0xabcd']),2));
foreach(json_decode(file_get_contents(__DIR__.'/nft-current-permit-vectors.json'),true) as $row){
 if($row['standard']==='delegate'){$ncStandard='0';$d=$nc721->delegatePermit($ncA,$ncC,$ncA,'7',$ncPermit);}
 else{$ncStandard=$row['standard']===721?'0':'1';$svc=$row['standard']===721?$nc721:$nc1155;$d=$svc->mint($ncA,$ncC,'uri',$row['standard']===721?null:'7',$row['standard']===721?'1':'2','0','20',$ncA,$ncPermit);}
 same($d->data,$row['data']);same($d->value,'0');
}
$ncStandard='0';rejects(fn()=>$nc721->mint($ncA,$ncC,'uri',null,'1','1','20',$ncA,$ncPermit));
rejects(fn()=>$nc721->mint($ncA,$ncC,'uri',null,'1','0','0',$ncA,$ncPermit));
rejects(fn()=>$nc721->mint($ncA,$ncC,'uri',null,'1','0','20',$ncA,[...$ncPermit,'extra'=>'x']));
$ncActive=false;rejects(fn()=>$nc721->mint($ncA,$ncC,'uri',null,'1','0','20',$ncA,$ncPermit));$ncActive=true;
$ncReserved=false;rejects(fn()=>$nc721->mint($ncA,$ncC,'uri',null,'1','0','20',$ncA,$ncPermit));$ncReserved=true;
$ncMinimum='21';rejects(fn()=>$nc721->mint($ncA,$ncC,'uri',null,'1','0','20',$ncA,$ncPermit));$ncMinimum='5';
$ncTokenAllowed=false;rejects(fn()=>$nc721->mint($ncA,$ncC,'uri',null,'1','0','20',$ncA,$ncPermit));$ncTokenAllowed=true;
$ncStandard='1';$ncReserveType='2';$ncReserveToken=$ncA;$ncRate='11';rejects(fn()=>$nc1155->mint($ncA,$ncC,'uri','7','2','0','20',$ncA,$ncPermit));$ncRate='10';
$ncReserveToken=$ncC;rejects(fn()=>$nc1155->mint($ncA,$ncC,'uri','7','2','0','20',$ncA,$ncPermit));$ncReserveToken=$ncA;
rejects(fn()=>$nc1155->delegatePermit($ncA,$ncC,$ncA,'7',$ncPermit));rejects(fn()=>$nc721->delegatePermit($ncA,$ncC,$ncA,'7',$ncPermit,'1',true));rejects(fn()=>$nc721->delegatePermit($ncA,$ncC,$ncA,'7',$ncPermit,'1',false,'PythonDelegationNft'));
$ncOwner=$ncC;rejects(fn()=>$nc721->delegatePermit($ncA,$ncC,$ncA,'7',$ncPermit));$ncOwner=$ncA;
rejects(fn()=>$nc721->setTokenUri($ncA,'7','uri'));same($nc721->setTokenUri($ncA,'7','uri',true)->value,'0');
rejects(fn()=>$nc1155->withdraw($ncA,$ncC,$ncA,'7','2',null,'Delegation20261030'));same($nc1155->withdraw($ncA,$ncC,$ncA,'7','3',null,'Delegation20261030')->to,$ncC);
$ncBefore=$ncChecks;same($nc1155->withdraw($ncA,$ncC,$ncA,'7','2',null,'PythonDelegationNft')->to,$ncC);same($ncChecks,$ncBefore);
$ncLegacy=new Decimal\NftLegacyStaking($ncClient,$ncC);$ncReq=['validator'=>$ncA,'delegator'=>$ncA,'nft'=>$ncB,'token_id'=>'7','amount'=>'2','old_hold_timestamp'=>'1','new_hold_timestamp'=>'2','hold_timestamp'=>'1','hold_timestamps'=>['1','2'],'hold_timestamps_to_reset'=>['1','2'],'indexes'=>['0','1'],'new_validator'=>$ncC];
foreach(['stake_to_hold','reset_stake_hold','reset_stake_holds','withdraw_with_reset','transfer_with_reset','hold_with_reset','complete_stake'] as $operation)same($ncLegacy->plan($operation,$ncA,$ncReq)->to,$ncC);
rejects(fn()=>$ncLegacy->plan('reset_stake_holds',$ncA,[...$ncReq,'hold_timestamps'=>['1','1'],'hold_timestamps_to_reset'=>['1','1']]));
rejects(fn()=>$ncLegacy->plan('stake_to_hold',$ncA,[...$ncReq,'new_hold_timestamp'=>'1']));
echo "Current NFT permit, reserve economics and legacy reset requests passed\n";
foreach(json_decode(file_get_contents(__DIR__.'/nft-current-legacy-vectors.json'),true) as $v)same($ncLegacy->plan($v['operation'],$ncA,$ncReq)->data,$v['data']);
$ncFrozenRows=[];$ncFrozenRpc=new Decimal\Rpc('http://unused',function($r)use(&$ncFrozenRows){return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':(new Decimal\AbiCodec())->encodeParameters(['((address,address,address,uint256,uint256,uint8,uint256,uint256),uint8,uint8,uint256)[]'],[$ncFrozenRows])];});
$ncFrozenReader=new Decimal\NftStakingReader(new Decimal\Client(Decimal\Network::testnet(),$ncFrozenRpc),$ncC);
$ncFrozenRows=[[[$ncA,$ncA,$ncB,'2','7','2','0','123'],'1','1','4000000000']];same($ncFrozenReader->frozenMany(['0'])[0]->stake['holdStartTime'],'123');
rejects(fn()=>$ncFrozenReader->frozenMany(['0','1']));$ncFrozenRows[0][0][5]='1';rejects(fn()=>$ncFrozenReader->frozenMany(['0']));
rejects(fn()=>$ncFrozenReader->freezeTime('0'));rejects(fn()=>$ncFrozenReader->complete($ncA,['0','0']));
$ncBlankUnicode="\u{00A0}\u{0085}\u{2003}\u{001C}";
rejects(fn()=>$nc721->mint($ncA,$ncC,$ncBlankUnicode));rejects(fn()=>$nc721->mint($ncA,$ncC,"\xFF"));
rejects(fn()=>$nc721->createCollection($ncA,$ncA,$ncBlankUnicode,'Name','uri'));
rejects(fn()=>$nc721->createCollection($ncA,$ncA,'SYM',$ncBlankUnicode,'uri'));
rejects(fn()=>$nc721->createCollection($ncA,$ncA,'SYM','Name',$ncBlankUnicode));
rejects(fn()=>$nc721->createReservelessCollection($ncA,$ncA,'SYM','Name',"\xFF"));
$ncStandard='0';$ncReserved=false;$ncFormatted="\u{00A0}ipfs://метаданные\u{2003}";
$ncFormattedDraft=$nc721->mint($ncA,$ncC,$ncFormatted);same($ncFormattedDraft->data,Decimal\Contract::profile($ncB,'PythonErc721')->encode('mint(address,string,uint256,address)',[$ncC,$ncFormatted,'0','0x'.str_repeat('00',20)]));
$ncFormattedCreate=$nc721->createCollection($ncA,$ncA,"\u{00A0}SYM\u{2003}",'Название',$ncFormatted);same($ncFormattedCreate->data,Decimal\Contract::profile($ncC,'TestnetNFTCenter20261029')->encode('createDRC721((address,string,string,string,bool,bool))',[[$ncA,"\u{00A0}SYM\u{2003}",'Название',$ncFormatted,false,true]]));
