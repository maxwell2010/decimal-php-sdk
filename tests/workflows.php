<?php
declare(strict_types=1);
use Decimal\{Address,WebSocket,Workflows,TransactionDraft};
same(Address::checksum('0x52908400098527886e0f7030069857d2e4169ee7'),'0x52908400098527886E0F7030069857D2E4169EE7');
foreach(['d0','d0valoper','d0valcons'] as $hrp){$bech=Address::bech32($address,$hrp);same(Address::fromBech32($bech,$hrp),$address);rejects(fn()=>Address::fromBech32(substr($bech,0,-1).'!', $hrp));}
foreach([0,1,125,126,65535,65536] as $n){$frame=WebSocket::frame(str_repeat('x',$n));$head=ord($frame[1]);same(($head&128)!==0,true);$offset=$n<126?2:($n<=65535?4:10);$mask=substr($frame,$offset,4);$decoded='';for($i=0;$i<$n;++$i)$decoded.=$frame[$offset+4+$i]^$mask[$i%4];same($decoded,str_repeat('x',$n));}
$multisend=Workflows::multisendDel($wallet->address,$address,[['address'=>$address,'amount'=>'1'],['address'=>$wallet->address,'amount'=>'2']]);same($multisend->value,'3');same(substr($multisend->data,0,10),'0x'.substr(kornrunner\Keccak::hash('aggregate((address,uint256,bytes)[])',256),0,8));
rejects(fn()=>Workflows::multisendDel($wallet->address,$address,[]));
rejects(fn()=>Workflows::multisendDel($wallet->address,$address,[['address'=>$address,'amount'=>'0']]));
$planRpc=new Decimal\Rpc('http://unused',fn($r)=>['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':(new Decimal\AbiCodec())->encodeParameters(['uint256'],['1'])]);
$action=new TransactionDraft($wallet->address,$address,'0','0xab');$plan=Workflows::allowance(new Decimal\Client(Decimal\Network::testnet(),$planRpc),$address,$address,'5',$action,true);same(count($plan['approvals']),2);same($plan['action'],$action);
$prepared=(new Decimal\Client(Decimal\Network::testnet(),$rpc))->prepare($draft,2,[],'7','2');same($prepared->type,2);same($prepared->gasPrice,'7');
rejects(fn()=>(new Decimal\Client(Decimal\Network::testnet(),$rpc))->prepare($draft,2,[],'7','2','1'));
echo "Transport, address and workflow checks passed\n";
$permitDomain='0x9d3162b76d84aef56dfadc8e1ee0d020c5e49c18ba6041530a5a9bf78036a7ee';
$permitRpc=new Decimal\Rpc('http://unused',function($r)use($permitDomain){
 $result=$r['method']==='eth_chainId'?'0x31524':(str_starts_with($r['params'][0]['data'],'0x7ecebe00')?(new Decimal\AbiCodec())->encodeParameters(['uint256'],['3']):$permitDomain);
 return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$permit=Decimal\TypedData::checkedPermit(new Decimal\Client(Decimal\Network::testnet(),$permitRpc),$wallet,$address,'Test','1',$address,'5','2000000000');same($permit['nonce'],'3');
rejects(fn()=>Decimal\TypedData::checkedPermit(new Decimal\Client(Decimal\Network::testnet(),$permitRpc),$wallet,$address,'Wrong','1',$address,'5','2000000000'));
$scale=bcscale(7);same(Decimal\Units::parse('1.25'),'1250000000000000000');same(Decimal\Units::hex('255'),'0xff');same(Decimal\Units::decimal('0xff'),'255');bcscale($scale);
