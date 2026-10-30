<?php
declare(strict_types=1);
// Standard Solidity wire vectors, plus actual node errors from the 0.1.6 audit.
$rrReason='0x08c379a0'.str_repeat('0',62).'20'.str_repeat('0',63).'4'.'6e6f7065'.str_repeat('0',56);
$rrDecoded=Decimal\RevertReason::decode($rrReason);
same($rrDecoded['kind'],'error');same($rrDecoded['message'],'nope');same($rrDecoded['arguments'],['nope']);
$rrPanic='0x4e487b71'.str_repeat('0',62).'32';
$rrDecoded=Decimal\RevertReason::decode($rrPanic);
same($rrDecoded['kind'],'panic');same($rrDecoded['panic_code'],'50');same($rrDecoded['message'],'Array index out of bounds');
same(Decimal\RevertReason::decode('0x')['kind'],'empty');
same(Decimal\RevertReason::decode('0x01')['kind'],'malformed');
same(Decimal\RevertReason::decode('0xdeadbeef')['kind'],'unknown');
foreach (['0x08c379a0','0x4e487b71',$rrPanic.'00',$rrReason.'00'] as $rrBad) same(Decimal\RevertReason::decode($rrBad)['kind'],'malformed');
foreach (['','0x0','0xgg','deadbeef'] as $rrBad) rejects(fn()=>Decimal\RevertReason::decode($rrBad));
$rrCodec=new Decimal\AbiCodec();
$rrAbi=[['type'=>'error','name'=>'Denied','inputs'=>[['type'=>'address','name'=>'owner'],['type'=>'uint256','name'=>'amount']]]];
$rrAddress='0x'.str_repeat('11',20);
$rrCustom=$rrCodec->encodeFunctionSignature('Denied(address,uint256)').substr($rrCodec->encodeParameters(['address','uint256'],[$rrAddress,'7']),2);
same(Decimal\RevertReason::decode($rrCustom)['kind'],'unknown');
$rrDecoded=Decimal\RevertReason::decode($rrCustom,$rrAbi);
same($rrDecoded['kind'],'custom');same($rrDecoded['signature'],'Denied(address,uint256)');same($rrDecoded['arguments'],[$rrAddress,'7']);
same(Decimal\RevertReason::decode($rrCustom.'00',$rrAbi)['kind'],'malformed');
// A known 4-byte collision must never be silently assigned to one ABI entry.
$rrCollision=[['type'=>'error','name'=>'burn','inputs'=>[['type'=>'uint256']]],['type'=>'error','name'=>'collate_propagate_storage','inputs'=>[['type'=>'bytes16']]]];
same($rrCodec->encodeFunctionSignature('burn(uint256)'),'0x42966c68');
same($rrCodec->encodeFunctionSignature('collate_propagate_storage(bytes16)'),'0x42966c68');
$rrDecoded=Decimal\RevertReason::decode('0x42966c68'.str_repeat('0',64),$rrCollision);
same($rrDecoded['kind'],'ambiguous');same(count($rrDecoded['candidates']),2);
same(Decimal\RevertReason::decode($rrCustom,[$rrAbi[0],$rrAbi[0]])['kind'],'custom');
foreach ([[null],[['type'=>'error','name'=>'Bad','inputs'=>'bad']],[['type'=>'error','inputs'=>[]]],[['type'=>'error','name'=>'Bad','inputs'=>[[]]]],[['type'=>'error','name'=>'Bad','inputs'=>[['type'=>'tuple']]]],['named'=>$rrAbi[0]]] as $rrBadAbi) {
    try {Decimal\RevertReason::decode($rrCustom,$rrBadAbi);throw new RuntimeException('Expected invalid ABI');}
    catch (InvalidArgumentException $e) {same(str_starts_with($e->getMessage(),'Invalid error ABI')||$e->getMessage()==='Error ABI must be a list',true);}
}
$rrError=new Decimal\RpcException('execution reverted',3,$rrReason);
same($rrError->revertReason()['message'],'nope');same($rrError->getMessage(),'execution reverted');same($rrError->rpcData,$rrReason);same($rrError->getCode(),3);
foreach ([null,['data'=>$rrPanic],['http_status'=>503],'0xodd','arbitrary'] as $rrData) same((new Decimal\RpcException('error',3,$rrData))->revertReason(),null);
same((new Decimal\RpcTransportException('transport',0,$rrPanic))->revertReason(),null);
$rrCapture=json_decode(file_get_contents(dirname(__DIR__).'/docs/live/nft-positions.json'),true,512,JSON_THROW_ON_ERROR);$rrActualPanics=0;
foreach ($rrCapture['requests'] as $rrRequest) {
    $rrData=$rrRequest['error']['data']??null;
    if (is_string($rrData)&&str_starts_with($rrData,'0x4e487b71')) {
        same((new Decimal\RpcException('execution reverted',3,$rrData))->revertReason()['panic_code'],'50');++$rrActualPanics;
    }
}
same($rrActualPanics>0,true);
$rrLegacyAbi=[['type'=>'error','name'=>'EnforcedPause','inputs'=>[]]];$rrActualPause=0;
foreach ($rrCapture['requests'] as $rrRequest) if (($rrRequest['error']['data']??null)==='0xd93c0665') {
    same((new Decimal\RpcException('execution reverted',3,$rrRequest['error']['data']))->revertReason($rrLegacyAbi)['signature'],'EnforcedPause()');++$rrActualPause;
}
same($rrActualPause>0,true);
same((new Decimal\RpcException('execution reverted',3,'0x'))->revertReason()['kind'],'empty');
// Chosen-block simulation must issue only chain verification and eth_call.
$rrCalls=[];$rrRpc=new Decimal\Rpc('http://unused',function($r)use(&$rrCalls){$rrCalls[]=$r;return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':'0x'];});
$rrClient=new Decimal\Client(Decimal\Network::testnet(),$rrRpc);$rrDraft=new Decimal\TransactionDraft($rrAddress,$rrAddress);
same($rrClient->simulate($rrDraft,'0x20'),'0x');same(array_column($rrCalls,'method'),['eth_chainId','eth_call']);same($rrCalls[1]['params'],[$rrDraft->rpc(),'0x20']);
$rrClient->simulate($rrDraft);same($rrCalls[3]['params'][1],'pending');
$rrBefore=count($rrCalls);foreach (['0x00','0x','-1','latest;send'] as $rrBlock) rejects(fn()=>$rrClient->simulate($rrDraft,$rrBlock));same(count($rrCalls),$rrBefore);
foreach (['earliest','safe','finalized','latest'] as $rrBlock) {$rrClient->simulate($rrDraft,$rrBlock);same($rrCalls[array_key_last($rrCalls)]['params'][1],$rrBlock);}
echo "Strict Solidity revert decoding, actual error replay and pinned simulation regressions passed\n";
