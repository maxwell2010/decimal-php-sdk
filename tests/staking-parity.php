<?php
declare(strict_types=1);
$spA='0x'.str_repeat('11',20);$spT='0x'.str_repeat('22',20);$spD='0x'.str_repeat('44',20);$spM='0x'.str_repeat('55',20);
$spAllowance='999999999999999999999';$spBalance=$spAllowance;$spRevert=false;$spCalls=[];$spCodec=new Decimal\AbiCodec();
$spRpc=new Decimal\Rpc('http://unused',function($r)use(&$spAllowance,&$spBalance,&$spRevert,&$spCalls,$spCodec){
    $spCalls[]=$r;
    if($r['method']==='eth_chainId')$result='0x31524';
    elseif($r['method']==='eth_call'){
        $selector=substr($r['params'][0]['data'],0,10);
        if($selector==='0x70a08231')$result=$spCodec->encodeParameters(['uint256'],[$spBalance]);
        elseif($selector==='0xdd62ed3e')$result=$spCodec->encodeParameters(['uint256'],[$spAllowance]);
        elseif($selector==='0x313ce567')$result=$spCodec->encodeParameters(['uint8'],['6']);
        elseif($spRevert)return ['jsonrpc'=>'2.0','id'=>$r['id'],'error'=>['code'=>-32000,'message'=>'role or stake rejected']];
        else $result='0x';
    }else throw new RuntimeException('Unexpected RPC including broadcast');
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$spService=new Decimal\StakingService(new Decimal\Client(Decimal\Network::testnet(),$spRpc),$spD,$spT,$spM);
$spRows=json_decode(file_get_contents(__DIR__.'/staking-parity-vectors.json'),true,512,JSON_THROW_ON_ERROR);
foreach($spRows as $row){$plan=$spService->plan($row['operation'],$spA,$row['request'],'0x10');same($plan['action']->data,$row['data']);same($plan['action']->value,$row['value']);same($plan['approvals'],[]);same($plan['action']->to,str_contains($row['operation'],'validator')?$spM:$spD);}
$spRequest=$spRows[1]['request'];$spAllowance='1';$plan=$spService->plan('delegate_erc20',$spA,$spRequest+['reset_existing'=>true,'auto_approve'=>true]);same(count($plan['approvals']),2);same($plan['requires_refresh'],true);same($plan['approvals'][0]->data,Decimal\Contract::profile($spT,'Token')->encode('approve(address,uint256)',[$spD,'0']));
rejects(fn()=>$spService->plan('delegate_erc20',$spA,$spRequest+['auto_approve'=>false]));
$spBalance='0';rejects(fn()=>$spService->plan('delegate_erc20',$spA,$spRequest));$spBalance='999999999999999999999';$spAllowance=$spBalance;
$spRevert=true;rejects(fn()=>$spService->plan('pause_validator',$spA,$spRequest));rejects(fn()=>$spService->plan('unbond_erc20',$spA,$spRequest));$spRevert=false;
$spNoDecimals=$spRequest;unset($spNoDecimals['decimals']);same($spService->plan('delegate_erc20',$spA,$spNoDecimals)['action']->data,$spRows[7]['data']);
$spBad=$spRequest;$spBad['amount']='0';rejects(fn()=>$spService->plan('delegate_erc20',$spA,$spBad));$spBad['amount']='0.0000001';rejects(fn()=>$spService->plan('delegate_erc20',$spA,$spBad));
rejects(fn()=>$spService->plan('multisend_erc20',$spA,[]));
$spBatch=['multicall'=>$spD,'memo'=>'hello','recipients'=>[['to'=>$spT,'amount_del'=>'1'],['to'=>$spM,'amount_del'=>'2']]];
$spPlan=$spService->plan('multisend_del',$spA,$spBatch);same($spPlan['action']->value,'3000000000000000000');$spDecoded=(new Decimal\AbiCodec())->decodeParameters(['(address,uint256,bytes)[]'],'0x'.substr($spPlan['action']->data,10))[0];same($spDecoded[2],['0x'.str_repeat('0',40),'0','0x68656c6c6f']);
$spBatch['recipients']=[$spBatch['recipients'][0]];same($spService->plan('multisend_del',$spA,$spBatch)['action']->data,'0x68656c6c6f');
echo "Staking/validator source parity and approval/memo regressions passed\n";

$spSeparate=$spService->separateResetTransfer($spA,$spA,$spT,'1.25',6,$spM,['12','34']);same(count($spSeparate['steps']),2);same($spSeparate['atomic'],false);same($spSeparate['requires_refresh'],true);
rejects(fn()=>$spService->separateResetTransfer($spA,$spA,$spT,'1',6,$spM,['12','12']));

$spNativeReset=$spRequest;unset($spNativeReset['token']);$spNativePlan=$spService->plan('reset_stake_hold',$spA,$spNativeReset);same(substr($spNativePlan['action']->data,0,10),'0x'.substr(kornrunner\Keccak::hash('resetHoldDEL(address,address,uint256)',256),0,8));
