<?php
declare(strict_types=1);
$bpSender='0x'.str_repeat('11',20);$bpBridge='0x'.str_repeat('22',20);$bpToken='0x'.str_repeat('33',20);$bpCode='0x6000';$bpFeeRejected=false;$bpCalls=[];$bpCodec=new Decimal\AbiCodec();
$bpCaptured=json_decode(file_get_contents(dirname(__DIR__).'/docs/live/bridge-discovery.json'),true,512,JSON_THROW_ON_ERROR);$bpRealFeeError=null;
foreach($bpCaptured['requests'] as $row)if($row['method']==='eth_call'&&str_starts_with($row['params'][0]['data'],'0x5cd9d57e')&&isset($row['error'])){$bpRealFeeError=$row['error'];break;}
same(is_array($bpRealFeeError),true);
$bpRpc=new Decimal\Rpc('http://unused',function($r)use(&$bpCalls,&$bpCode,&$bpFeeRejected,$bpCodec,$bpRealFeeError){
    $bpCalls[]=$r;$selector=substr($r['params'][0]['data']??'',0,10);
    if($r['method']==='eth_call'&&$selector==='0x5cd9d57e'&&$bpFeeRejected)return ['jsonrpc'=>'2.0','id'=>$r['id'],'error'=>['code'=>$bpRealFeeError['code'],'message'=>$bpRealFeeError['message'],'data'=>$bpRealFeeError['data']]];
    $result=match($r['method']){
        'eth_chainId'=>'0x31524','eth_blockNumber'=>'0x20','eth_getCode'=>$bpCode,'eth_getStorageAt'=>'0x'.str_repeat('0',64),
        'eth_call'=>$bpCodec->encodeParameters(['uint256'],[$selector==='0x5cd9d57e'?'5':'0']),
        default=>throw new RuntimeException('Unexpected bridge verification RPC'),
    };return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$bpClient=new Decimal\Client(Decimal\Network::testnet(),$bpRpc);$bpRaw=new Decimal\BridgeService($bpClient,$bpBridge);
rejects(fn()=>$bpRaw->transferToken($bpSender,$bpToken,$bpSender,'1','0','75','0',true));same($bpCalls,[]);
rejects(fn()=>Decimal\BridgeService::verified($bpClient,$bpBridge,'0x'.str_repeat('0',64),'202020'));same($bpCalls,[]);
$bpHash='0x'.kornrunner\Keccak::hash(hex2bin(substr($bpCode,2)),256);
$bpVerified=Decimal\BridgeService::verified($bpClient,$bpBridge,$bpHash,'75');
$bpAllowanceBefore=count(array_filter($bpCalls,fn($r)=>$r['method']==='eth_call'&&str_starts_with($r['params'][0]['data'],'0xdd62ed3e')));
rejects(fn()=>$bpVerified->transferToken($bpSender,$bpToken,$bpSender,'1','0','75','0',true));
same(count(array_filter($bpCalls,fn($r)=>$r['method']==='eth_call'&&str_starts_with($r['params'][0]['data'],'0xdd62ed3e'))),$bpAllowanceBefore);
rejects(fn()=>$bpVerified->transferNative($bpSender,$bpSender,'1','0','75','0'));
$bpPlan=$bpVerified->transferToken($bpSender,$bpToken,$bpSender,'1','5','75','0',true);
same(count($bpPlan['approvals']),1);same($bpPlan['approvals'][0]->to,$bpToken);same($bpPlan['action']->to,$bpBridge);
same($bpPlan['approvals'][0]->data,Decimal\Contract::profile($bpToken,'Token')->encode('approve(address,uint256)',[$bpBridge,'1']));
$bpBefore=count($bpCalls);rejects(fn()=>$bpVerified->transferNative($bpSender,$bpSender,'1','5','56','0'));same(count($bpCalls),$bpBefore);
rejects(fn()=>$bpVerified->minimumServiceFee('75','0x10'));same(count($bpCalls),$bpBefore);
$bpQuote=$bpVerified->quote('75','100');same($bpQuote['minimum_service_fee_wei'],'5');same($bpQuote['call_value_wei'],'105');same($bpQuote['block'],'0x20');
$bpFeeRejected=true;$bpBefore=count(array_filter($bpCalls,fn($r)=>$r['method']==='eth_call'&&str_starts_with($r['params'][0]['data'],'0xdd62ed3e')));
try{Decimal\BridgeService::verified($bpClient,$bpBridge,$bpHash,'75');throw new RuntimeException('Expected actual captured uint16 profile rejection');}catch(Decimal\RpcException $e){same($e->getCode(),3);same($e->rpcData,'0x');}
same(count(array_filter($bpCalls,fn($r)=>$r['method']==='eth_call'&&str_starts_with($r['params'][0]['data'],'0xdd62ed3e'))),$bpBefore);
$bpFeeRejected=false;$bpCode='0x6001';rejects(fn()=>$bpVerified->transferNative($bpSender,$bpSender,'1','5','75','0'));
echo "Bridge approval binding, destination width, captured profile rejection and runtime-change regressions passed\n";
