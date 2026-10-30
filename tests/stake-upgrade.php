<?php
declare(strict_types=1);
// Decimal node a46cb48d7965c1a64a495cebb22e735dfe4b9da3: Stake.holdStartTime is field 7.
$stakeCodec=new Decimal\AbiCodec();$stakeAddress='0x'.str_repeat('12',20);
$stake7=[$stakeAddress,$stakeAddress,$stakeAddress,'1234567890123456789','42','2','1800000000'];$stake8=[...$stake7,'1790000000'];
$stakeTypes=['address','address','address','uint256','uint256','uint8','uint256'];
foreach(['Delegation','DelegationNFT','Delegation20261030'] as $profile){
    $contract=Decimal\Contract::profile($stakeAddress,$profile);
    $abi=json_decode(file_get_contents(dirname(__DIR__).'/abi/'.$profile.'.json'),true,512,JSON_THROW_ON_ERROR);
    $singleSignature=Decimal\Contract::signature(array_values(array_filter($abi,fn($x)=>($x['name']??'')==='getStake'))[0]);
    foreach([7,8] as $width){
        $row=$width===7?$stake7:$stake8;$tuple='('.implode(',',array_slice([...$stakeTypes,'uint256'],0,$width)).')';
        $encoded=$stakeCodec->encodeParameters([$tuple.'[]'],[[$row,$row]]);
        same($contract->decode('getStakes(bytes32[])',$encoded),[[$row,$row]]);
        same($contract->decode('getStakes(bytes32[])',$stakeCodec->encodeParameters([$tuple.'[]'],[[]])),[[]]);
        same($contract->decode($singleSignature,$stakeCodec->encodeParameters([$tuple],[$row])),[$row]);
        same($contract->decode('getFrozenStake(uint256)',$stakeCodec->encodeParameters(['('.$tuple.',uint8,uint8,uint256)'],[[$row,'1','2','1900000000']])),[[$row,'1','2','1900000000']]);
        same($contract->decode('getFrozenStakes(uint256[])',$stakeCodec->encodeParameters(['('.$tuple.',uint8,uint8,uint256)[]'],[[[$row,'1','2','1900000000'],[$row,'1','2','1900000000']]])),[[[$row,'1','2','1900000000'],[$row,'1','2','1900000000']]]);
        foreach(['0x'.substr($encoded,2,-2),$encoded.str_repeat('00',32),'0x'.str_repeat('0',63).'0'.substr($encoded,66),'0x'.substr($encoded,2,64).str_repeat('f',64).substr($encoded,130)] as $bad)rejects(fn()=>$contract->decode('getStakes(bytes32[])',$bad));
        $padding=$encoded;$padding[130]='1';rejects(fn()=>$contract->decode('getStakes(bytes32[])',$padding));
        $stakeRpc=new Decimal\Rpc('http://unused',function($r)use($encoded){return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':$encoded];});
        $stakeClient=new Decimal\Client(Decimal\Network::testnet(),$stakeRpc);
        $bindingClass='Decimal\\Contracts\\'.$profile;$binding=new $bindingClass($stakeAddress);
        same($binding->getStakes_232623f3($stakeClient,['0x'.str_repeat('ab',32)]),[[$row,$row]]);
        same((new Decimal\StakingReader($stakeClient,$contract))->stakes(['0x'.str_repeat('ab',32)],'0x20'),[$row,$row]);
        $frozenData=$stakeCodec->encodeParameters(['('.$tuple.',uint8,uint8,uint256)'],[[$row,'1','2','1900000000']]);
        $frozenRpc=new Decimal\Rpc('http://unused',fn($r)=>['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':$frozenData]);
        $frozen=(new Decimal\StakingReader(new Decimal\Client(Decimal\Network::testnet(),$frozenRpc),$contract))->frozen('1',$stakeAddress,'0x20');
        same($frozen->stake['amount'],$row[3]);same($frozen->stake['holdTimestamp'],$row[6]);same(array_key_exists('holdStartTime',$frozen->stake),$width===8);
        if($width===8)same($frozen->stake['holdStartTime'],$row[7]);
    }
}
$oldComponents=[];foreach(['validator','delegator','token','amount','tokenId','tokenType','holdTimestamp'] as $i=>$name)$oldComponents[]=['name'=>$name,'type'=>$stakeTypes[$i]];
$oldContract=new Decimal\Contract($stakeAddress,[['name'=>'getStakes','type'=>'function','inputs'=>[['name'=>'ids','type'=>'bytes32[]']],'outputs'=>[['type'=>'tuple[]','components'=>$oldComponents]],'stateMutability'=>'view']]);
same($oldContract->decode('getStakes(bytes32[])',$stakeCodec->encodeParameters(['('.implode(',',[...$stakeTypes,'uint256']).')[]'],[[$stake8]])),[[$stake8]]);
$customFrozenAbi=[['type'=>'function','name'=>'getFrozenStake','inputs'=>[['name'=>'index','type'=>'uint256']],'outputs'=>[['type'=>'tuple','components'=>[['name'=>'stake','type'=>'tuple','components'=>$oldComponents],['name'=>'freezeStatus','type'=>'uint8'],['name'=>'freezeType','type'=>'uint8'],['name'=>'unfreezeTimestamp','type'=>'uint256']]]],'stateMutability'=>'view']];
$customFrozenContract=new Decimal\Contract($stakeAddress,$customFrozenAbi);
$customFrozen=(new Decimal\StakingReader(new Decimal\Client(Decimal\Network::testnet(),$frozenRpc),$customFrozenContract))->frozen('1',$stakeAddress,'0x20');
same($customFrozen->stake['holdStartTime'],$stake8[7]);
// The general ABI codec remains schema-strict outside the named contract compatibility layer.
rejects(fn()=>$stakeCodec->decodeParameters(['('.implode(',',$stakeTypes).')[]'],$stakeCodec->encodeParameters(['('.implode(',',[...$stakeTypes,'uint256']).')[]'],[[$stake8]])));
echo "Stake tuple7/tuple8 upgrade compatibility passed\n";
