<?php
declare(strict_types=1);
$bcA='0x'.str_repeat('11',20);$bcB='0x'.str_repeat('22',20);$bcC='0x'.str_repeat('33',20);$bcCodec=new Decimal\AbiCodec();$bcReads=[];
$bcRpc=new Decimal\Rpc('http://unused',function($r)use(&$bcReads,$bcCodec,$bcB){
    $bcReads[]=$r;
    $result=$r['method']==='eth_chainId'?'0x31524':$bcCodec->encodeParameters(['uint256'],['7']);
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$bcClient=new Decimal\Client(Decimal\Network::testnet(),$bcRpc);$bridge=new Decimal\BridgeService($bcClient,$bcC);$checksService=new Decimal\ChecksService($bcClient,$bcC);
$bcPermit=['deadline'=>'4000000000','v'=>'27','r'=>'0x'.str_repeat('0',63).'1','s'=>'0x'.str_repeat('0',63).'2'];
$bcSignature=$bcPermit['r'].substr($bcPermit['s'],2).'1b';
$bcDrafts=[
    'bridge_native'=>$bridge->transferNative($bcA,$bcB,'100','7','65535','42'),
    'bridge_token'=>$bridge->transferToken($bcA,$bcC,$bcB,'123456789012345678901','7','75','42')['action'],
    'bridge_complete'=>$bridge->completeTransfer($bcA,'deadbeef',true),
    'checks_del'=>$checksService->createDelForSigners($bcA,[$bcA,$bcB],'100','1000','42'),
    'checks_token'=>$checksService->createTokenForSigners($bcA,[$bcA,$bcB],'100','1000',$bcC,'42')['action'],
    'checks_permit'=>$checksService->createTokenForSigners($bcA,[$bcA,$bcB],'100','1000',$bcC,'42',$bcPermit)['action'],
    'checks_redeem'=>$checksService->redeemSignatures($bcA,[$bcSignature,$bcSignature],['0x'.str_repeat('ab',32),'0x'.str_repeat('cd',32)]),
];
same($bcReads,[]); // Explicit nonce/raw drafts do not contact a provider.
foreach(json_decode(file_get_contents(__DIR__.'/bridge-checks-parity-vectors.json'),true)['vectors'] as $row){same($bcDrafts[$row['name']]->data,$row['data']);same($bcDrafts[$row['name']]->value,$row['value']);}
same($bridge->quote('75','100','0x20'),['target_chain_id'=>'75','minimum_service_fee_wei'=>'7','call_value_wei'=>'107','block'=>'0x20']);
same($bcReads[1]['params'][1],'0x20');
rejects(fn()=>$bridge->transferNative($bcA,$bcB,'1','0','65536','0'));
rejects(fn()=>$bridge->transferNative($bcA,$bcB,bcsub(bcpow('2','256',0),'1',0),'1','75','0'));
rejects(fn()=>$bridge->completeTransfer($bcA,'0x'));
rejects(fn()=>$bridge->completeTransfer($bcA,'0xz1'));
rejects(fn()=>$checksService->createDelForSigners($bcA,[],'1','1','1'));
rejects(fn()=>$checksService->createDelForSigners($bcA,['owner'=>$bcA],'1','1','1'));
rejects(fn()=>$checksService->createDelForSigners($bcA,[$bcA,$bcB],bcsub(bcpow('2','256',0),'1',0),'1','1'));
rejects(fn()=>$checksService->createTokenForSigners($bcA,[$bcA],'1','1',$bcC,'1',array_replace($bcPermit,['v'=>'1'])));
rejects(fn()=>$checksService->createTokenForSigners($bcA,[$bcA],'1','1',$bcC,'1',array_replace($bcPermit,['s'=>'0x'.str_repeat('f',64)])));
rejects(fn()=>$checksService->redeemSignatures($bcA,[$bcSignature],[]));
rejects(fn()=>$checksService->redeemSignatures($bcA,['0x'.str_repeat('00',65)],['0x'.str_repeat('ab',32)]));
$bcSenderReads=[];
$bcNonceRpc=new Decimal\Rpc('http://unused',function($r)use(&$bcSenderReads,$bcCodec){$bcSenderReads[]=$r;return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':$bcCodec->encodeParameters(['uint256'],['42'])];});
same((new Decimal\ChecksService(new Decimal\Client(Decimal\Network::testnet(),$bcNonceRpc),$bcC))->createDelForSigners($bcA,[$bcA,$bcB],'100','1000')->data,$bcDrafts['checks_del']->data);
same($bcSenderReads[1]['params'][0]['from'],$bcA);
$bcAbi=json_decode(file_get_contents(dirname(__DIR__).'/abi/Checks.json'),true);$bcEvent=array_values(array_filter($bcAbi,fn($e)=>($e['type']??'')==='event'&&$e['name']==='CreatedCheck'))[0];
$bcLog=['address'=>$bcC,'topics'=>['0x'.kornrunner\Keccak::hash(Decimal\Contract::signature($bcEvent),256)],'data'=>$bcCodec->encodeParameters(array_map(Decimal\Contract::type(...),$bcEvent['inputs']),['0x'.str_repeat('ab',32),['0x'.str_repeat('cd',32),$bcA,'1']])];
same(count($checksService->createdChecks(['status'=>'0x1','logs'=>[$bcLog,$bcLog]])),2);
$bcWrong=$bcLog;$bcWrong['address']=$bcB;same($checksService->createdChecks(['status'=>'0x1','logs'=>[$bcWrong]]),[]);
$bcBad=$bcLog;$bcBad['data'].=str_repeat('00',32);rejects(fn()=>$checksService->createdChecks(['status'=>'0x1','logs'=>[$bcBad]]));
$bcRemoved=$bcLog;$bcRemoved['removed']=true;rejects(fn()=>$checksService->createdChecks(['status'=>'0x1','logs'=>[$bcRemoved]]));
$bcMismatch=$bcLog;$bcMismatch['transactionHash']='0x'.str_repeat('ab',32);rejects(fn()=>$checksService->createdChecks(['status'=>'0x1','transactionHash'=>'0x'.str_repeat('cd',32),'logs'=>[$bcMismatch]]));
echo "Bridge/checks source parity and independent calldata checks passed\n";
