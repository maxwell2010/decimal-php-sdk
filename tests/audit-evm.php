<?php
declare(strict_types=1);
use Decimal\{AbiCodec,Client,Network,Rpc,VerifiedContract,SafePolicy,SafeSignatures,TransactionDraft};
$auditSafe='0x'.str_repeat('12',20);$auditImpl='0x'.str_repeat('34',20);$auditNext='0x'.str_repeat('56',20);$auditOwner='0x'.str_repeat('78',20);$auditCodec=new AbiCodec();
$auditRuntimeHash='0x'.kornrunner\Keccak::hash(hex2bin('6000'),256);$auditImplHash='0x'.kornrunner\Keccak::hash(hex2bin('6001'),256);
$auditActiveImpl=$auditImpl;$auditImplCode='0x6001';$auditStorageBlocks=[];
$auditRpc=new Rpc('http://unused',function($r)use($auditSafe,$auditCodec,&$auditActiveImpl,&$auditImplCode,&$auditStorageBlocks){
    $result=match($r['method']){'eth_chainId'=>'0x31524','eth_blockNumber'=>'0x20','eth_getCode'=>$r['params'][0]===$auditSafe?'0x6000':$auditImplCode,default=>null};
    if($r['method']==='eth_getStorageAt'){$auditStorageBlocks[]=$r['params'][2];$result=$auditCodec->encodeParameters(['address'],[$auditActiveImpl]);}
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$auditClient=new Client(Network::testnet(),$auditRpc);
// A stable proxy runtime hash alone cannot prove which implementation executes.
rejects(fn()=>new VerifiedContract($auditClient,$auditSafe,'Token',$auditRuntimeHash));
$auditBound=new VerifiedContract($auditClient,$auditSafe,'Token',$auditRuntimeHash,$auditImpl,$auditImplHash);
$auditActiveImpl=$auditNext;rejects(fn()=>$auditBound->draft($auditOwner,'transfer(address,uint256)',[$auditNext,'1']));
$auditActiveImpl=$auditImpl;$auditImplCode='0x6002';rejects(fn()=>$auditBound->draft($auditOwner,'transfer(address,uint256)',[$auditNext,'1']));
same(array_unique($auditStorageBlocks),['0x20']);
// Signature validation must reproduce the caller seen by the owner contract.
$auditPolicy=new SafePolicy($auditSafe,202020,'0x20','0',[$auditOwner=>'1'],'1');$auditTx=$auditPolicy->transaction(new TransactionDraft($auditSafe,$auditNext));
foreach(['bytes32'=>'0x1626ba7e','bytes'=>'0x20c13b0b'] as $variant=>$magic){
    $auditSeen=[];$auditSignatureRpc=new Rpc('http://unused',function($r)use($auditCodec,$auditSafe,$magic,&$auditSeen){
        $result='0x31524';if($r['method']==='eth_call'){$auditSeen[]=$r['params'];if(($r['params'][0]['from']??null)!==$auditSafe)throw new RuntimeException('Owner signature requires Safe caller');$result=$auditCodec->encodeParameters(['bytes4'],[$magic]);}
        return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
    });
    $packed=SafeSignatures::pack(new Client(Network::testnet(),$auditSignatureRpc),$auditPolicy,$auditTx,[['kind'=>'contract','signer'=>$auditOwner,'signature'=>'0xaabb','interface'=>$variant]],$auditNext);
    same(strlen($packed),2+(65+32+2)*2);same($auditSeen[0][1],'0x20');same($auditSeen[0][0]['to'],$auditOwner);
}
echo "Final EVM proxy trust and contract-signature caller regressions passed\n";
$auditActiveImpl=$auditSafe;rejects(fn()=>Decimal\Deployment::implementation($auditClient,$auditSafe,'0x20'));
$auditBeacon='0x'.str_repeat('9a',20);$auditBeaconFrom=null;
$auditBeaconRpc=new Rpc('http://unused',function($r)use($auditCodec,$auditSafe,$auditBeacon,$auditImpl,&$auditBeaconFrom){
    $result='0x31524';
    if($r['method']==='eth_getStorageAt')$result=$auditCodec->encodeParameters(['address'],[str_starts_with($r['params'][1],'0x3608')?'0x'.str_repeat('0',40):$auditBeacon]);
    if($r['method']==='eth_call'){$auditBeaconFrom=$r['params'][0]['from']??null;if($auditBeaconFrom!==$auditSafe)throw new RuntimeException('Beacon lookup must reproduce proxy caller');$result=$auditCodec->encodeParameters(['address'],[$auditImpl]);}
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
same(Decimal\Deployment::implementation(new Client(Network::testnet(),$auditBeaconRpc),$auditSafe,'0x20'),$auditImpl);same($auditBeaconFrom,$auditSafe);
$auditMultiVector=json_decode(file_get_contents(__DIR__.'/restricted-multisend-vectors.json'),true,512,JSON_THROW_ON_ERROR);$auditMultiCode='0x6000';$auditMultiBalance='100';$auditMultiDomain=$auditMultiVector['domain'];$auditMultiCalls=[];
$auditMultiRpc=new Rpc('http://unused',function($r)use($auditCodec,&$auditMultiCode,&$auditMultiBalance,&$auditMultiDomain,&$auditMultiCalls){
    $auditMultiCalls[]=$r['method'];$result=match($r['method']){'eth_chainId'=>'0x31524','eth_blockNumber'=>'0x20','eth_getCode'=>$auditMultiCode,'eth_getStorageAt'=>$auditCodec->encodeParameters(['address'],['0x'.str_repeat('0',40)]),default=>null};
    if($r['method']==='eth_call')$result=match(substr($r['params'][0]['data'],0,10)){
        $auditCodec->encodeFunctionSignature('balanceOf(address)')=>$auditCodec->encodeParameters(['uint256'],[$auditMultiBalance]),
        $auditCodec->encodeFunctionSignature('allowance(address,address)')=>$auditCodec->encodeParameters(['uint256'],['2']),
        $auditCodec->encodeFunctionSignature('nonces(address)')=>$auditCodec->encodeParameters(['uint256'],['3']),
        $auditCodec->encodeFunctionSignature('DOMAIN_SEPARATOR()')=>$auditMultiDomain,
        default=>throw new RuntimeException('Unexpected multisend query')};
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$auditMultiClient=new Client(Network::testnet(),$auditMultiRpc);$auditWallet=new Decimal\Wallet(str_pad('1',64,'0',STR_PAD_LEFT));$auditRecipients=[['address'=>$auditNext,'amount'=>'3'],['address'=>$auditBeacon,'amount'=>'4']];
$auditMulti=new Decimal\RestrictedMultisend($auditMultiClient,$auditSafe,202020,$auditRuntimeHash);
$auditPlan=$auditMulti->plan($auditWallet->address,$auditOwner,$auditRecipients,'memo',true);same(count($auditPlan['approvals']),2);same($auditPlan['action']->data,$auditMultiVector['plain']);
$auditPermitPlan=$auditMulti->withPermit($auditWallet,$auditOwner,$auditRecipients,'Test','1','2000000000','memo');same($auditPermitPlan['approvals'],[]);same($auditPermitPlan['action']->data,$auditMultiVector['permit']);
rejects(fn()=>new Decimal\RestrictedMultisend($auditMultiClient,$auditSafe,75,$auditRuntimeHash));
rejects(fn()=>$auditMulti->plan($auditWallet->address,$auditOwner,[['address'=>$auditSafe,'amount'=>'1']]));
rejects(fn()=>$auditMulti->plan($auditWallet->address,$auditOwner,[['address'=>$auditWallet->address,'amount'=>'1']]));
rejects(fn()=>$auditMulti->plan($auditWallet->address,$auditOwner,[['address'=>$auditNext,'amount'=>'0']]));
rejects(fn()=>$auditMulti->plan($auditWallet->address,$auditOwner,$auditRecipients,str_repeat('a',257)));
$auditMultiBalance='6';rejects(fn()=>$auditMulti->withPermit($auditWallet,$auditOwner,$auditRecipients,'Test','1','2000000000'));
$auditMultiBalance='100';$auditMultiDomain='0x'.str_repeat('0',64);rejects(fn()=>$auditMulti->withPermit($auditWallet,$auditOwner,$auditRecipients,'Test','1','2000000000'));
$auditMultiCode='0x6001';rejects(fn()=>$auditMulti->plan($auditWallet->address,$auditOwner,$auditRecipients));same(in_array('eth_sendRawTransaction',$auditMultiCalls,true),false);
$auditUriVector=json_decode(file_get_contents(__DIR__.'/nft-uri-vectors.json'),true);$auditUriCalls=[];$auditUriError=false;
$auditUriClient=new Client(Network::testnet(),new Rpc('http://unused',function($r)use(&$auditUriCalls,&$auditUriError){$auditUriCalls[]=$r['method'];if($r['method']==='eth_call'&&$auditUriError)throw new Decimal\RpcException('URI permission denied');return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':'0x'];}));
$auditUriService=new Decimal\NftService($auditUriClient,$auditSafe);
foreach(['PythonErc721','PythonErc1155'] as $profile)same($auditUriService->setTokenUri($auditWallet->address,'7','ipfs://updated',$profile)->data,$auditUriVector['data']);
rejects(fn()=>$auditUriService->setTokenUri($auditWallet->address,'7','ipfs://updated','ERC721'));
$auditUriError=true;rejects(fn()=>$auditUriService->setTokenUri($auditWallet->address,'7','ipfs://updated'));same(in_array('eth_sendRawTransaction',$auditUriCalls,true),false);
