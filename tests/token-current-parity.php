<?php
declare(strict_types=1);
$current=new Decimal\CurrentTokenEconomy($tpClient,$tpCenter);
same($current->creationReserveWei('abc'),'2501000000000000000000');same($current->creationReserveWei('abcdefgh'),'1250000000000000000');
rejects(fn()=>$current->creationReserveWei('ABC'));rejects(fn()=>$current->create($tpA,'name','abc','1','0','3',50,'id','1'));
same($current->create($tpA,'name','abc','1','0','3',50,'id')->value,'2501000000000000000000');
same($current->buy($tpA,$tpB,'0.00000000000000001','20',$tpC)->data,$tpVectors['buyTokenForExactDEL']);
$tpBalance='100000000';same($current->sell($tpA,$tpB,'10','20',0,$tpC)->data,$tpVectors['sellExactTokensForDEL']);
$tpAllowance='0';rejects(fn()=>$current->convert($tpA,$tpB,$tpC,'1','1',['inDecimals'=>0,'outDecimals'=>0]));
rejects(fn()=>$current->updateMinSupply($tpA,$tpB,'0'));same($current->updateMinSupply($tpA,$tpB,'0',true)->to,$tpB);
$cv=json_decode(file_get_contents(__DIR__.'/token-current-vectors.json'),true);$cp=['deadline'=>'345','v'=>'27','r'=>'0x'.str_repeat('12',32),'s'=>'0x'.str_repeat('34',32)];
same($current->convertToDel($tpA,$tpCenter,$tpA,$tpB,'123','234',$cp)->data,$cv['gasCenter']);rejects(fn()=>$current->convertToDel($tpA,$tpCenter,$tpA,$tpB,'0','234',$cp));
$crpc=new Decimal\Rpc('http://unused',fn($r)=>['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':$tpCodec->encodeParameters(['uint256'],['1'])]);
$cc=new Decimal\CurrentTokenEconomy(new Decimal\Client(Decimal\Network::testnet(),$crpc),$tpCenter);$updates=$cc->updateDetails($tpA,$tpB,'id','123');same(count($updates),2);same($updates[0]->data,$tpEconomy->maintain($tpA,$tpB,'identity','id')->data);same($updates[1]->data,$tpEconomy->maintain($tpA,$tpB,'maxTotalSupply','123')->data);
echo "Recovery-014 current token profile checks passed\n";
$propsRpc=new Decimal\Rpc('http://unused',function($r)use($tpCodec,$tpA){if($r['method']==='eth_chainId')$v='0x31524';elseif($r['method']==='eth_blockNumber')$v='0x123';else{$s=substr($r['params'][0]['data'],0,10);$v=match($s){'0x06fdde03'=>$tpCodec->encodeParameters(['string'],['Token']),'0x95d89b41'=>$tpCodec->encodeParameters(['string'],['tok']),'0x313ce567'=>$tpCodec->encodeParameters(['uint8'],['6']),'0x18160ddd'=>$tpCodec->encodeParameters(['uint256'],['1234567']),'0x355274ea'=>$tpCodec->encodeParameters(['uint256'],['115792089237316195423570985008687907853269984665640564039457584007913129639935']),'0x7a9e5e4b'=>$tpCodec->encodeParameters(['bool'],[false]),default=>null};if($v===null){$fields=['cap'=>'uint256','mintable'=>'bool','burnable'=>'bool','owner'=>'address','identity'=>'string'];foreach($fields as $f=>$t)if($s===$tpCodec->encodeFunctionSignature($f.'()'))$v=$tpCodec->encodeParameters([$t],[$t==='bool'?false:($t==='address'?$tpA:($t==='string'?'identity':'115792089237316195423570985008687907853269984665640564039457584007913129639935'))]);if($v===null)throw new RuntimeException('Unknown property selector');}}return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$v];});
$props=new Decimal\CurrentTokenEconomy(new Decimal\Client(Decimal\Network::testnet(),$propsRpc),$tpCenter);$report=$props->properties($tpB,true);same($report['block'],'0x123');same($report['unlimited_cap'],true);same($report['total_supply']['formatted'],'1.234567');same($report['mintable'],false);rejects(fn()=>$props->mint($tpA,$tpB,$tpA,'1',6));
