<?php
declare(strict_types=1);
$tpA='0x'.str_repeat('11',20);$tpB='0x'.str_repeat('22',20);$tpC='0x'.str_repeat('33',20);$tpCenter='0x'.str_repeat('44',20);
$tpBalance='2000000';$tpAllowance='2000000';$tpCalls=[];$tpCodec=new Decimal\AbiCodec();
$tpRpc=new Decimal\Rpc('http://unused',function($r)use(&$tpBalance,&$tpAllowance,&$tpCalls,$tpCodec){
    $tpCalls[]=$r;
    if($r['method']==='eth_chainId')$value='0x31524';
    elseif($r['method']==='eth_call'){
        $selector=substr($r['params'][0]['data'],0,10);
        $value=match($selector){
            '0x70a08231'=>$tpCodec->encodeParameters(['uint256'],[$tpBalance]),
            '0xdd62ed3e'=>$tpCodec->encodeParameters(['uint256'],[$tpAllowance]),
            '0x313ce567'=>$tpCodec->encodeParameters(['uint8'],['6']),
            default=>'0x',
        };
    }else throw new RuntimeException('Forbidden RPC method');
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$value];
});
$tpClient=new Decimal\Client(Decimal\Network::testnet(),$tpRpc);$tpErc=new Decimal\Erc20Service($tpClient,$tpB);$tpEconomy=new Decimal\TokenEconomy($tpClient,$tpCenter);
$tpVectors=json_decode(file_get_contents(__DIR__.'/token-parity-vectors.json'),true,512,JSON_THROW_ON_ERROR);
same($tpErc->approve($tpA,$tpB,'1.25',6)->data,$tpVectors['approve']);
same($tpErc->transfer($tpA,$tpB,'1.25',null,'0x10')->data,$tpVectors['transfer']);
same($tpErc->transferFrom($tpC,$tpA,$tpB,'1.25',6)->data,$tpVectors['transferFrom']);
$tpBuy=$tpEconomy->buy($tpA,$tpB,'1.5','99');same($tpBuy->data,$tpVectors['buy']);same($tpBuy->value,'1500000000000000000');
same($tpEconomy->sell($tpA,$tpB,'1.25','4',6)->data,$tpVectors['sell']);
$tpConvert=$tpEconomy->convert($tpA,$tpB,$tpC,'1.25','2.5',6,18);same($tpConvert['action']->data,$tpVectors['convert']);same($tpConvert['approvals'],[]);
$tpAllowance='1';$tpConvert=$tpEconomy->convert($tpA,$tpB,$tpC,'1.25','2.5',6,18,true,true);same(count($tpConvert['approvals']),2);same($tpConvert['approvals'][0]->to,$tpB);same($tpConvert['approvals'][1]->data,Decimal\Contract::profile($tpB,'Token')->encode('approve(address,uint256)',[$tpCenter,'1250000']));
rejects(fn()=>$tpEconomy->convert($tpA,$tpB,$tpC,'1.25','2.5',6,18,false));
rejects(fn()=>$tpEconomy->convert($tpA,$tpB,$tpB,'1','1',6,6));
$tpBalance='1';rejects(fn()=>$tpErc->transfer($tpA,$tpB,'1.25',6));rejects(fn()=>$tpEconomy->sell($tpA,$tpB,'1.25','1',6));rejects(fn()=>$tpErc->transferFrom($tpC,$tpA,$tpB,'1.25',6));
rejects(fn()=>$tpErc->approve($tpA,$tpB,'0.0000001',6));
same($tpEconomy->create($tpA,'name','ABC','1','2','3',50,'identity')->data,$tpVectors['create']);same($tpEconomy->create($tpA,'name','ABC','1','2','3',50,'identity')->value,'2501000000000000000000000');
same($tpEconomy->updateDetails($tpA,$tpB,'new','100')->data,$tpVectors['update']);
foreach(['ABC'=>'2500000','ABCD'=>'250000','ABCDE'=>'25000','ABCDEF'=>'2500','ABCDEFG'=>'250','AB'=>'250']as $symbol=>$fee)same(Decimal\TokenEconomy::creationCommissionWei($symbol),Decimal\Units::parse($fee));
rejects(fn()=>Decimal\TokenEconomy::creationCommissionWei(' '));rejects(fn()=>$tpEconomy->create($tpA,'name','ABC','1','2','3',256,'identity'));
echo "ERC20 and token economics parity checks passed\n";
// Query overloads use exact source signatures and one caller-pinned block.
$tpSeen=[];$tpReadRpc=new Decimal\Rpc('http://unused',function($r)use(&$tpSeen,$tpCodec){$tpSeen[]=$r;return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':$tpCodec->encodeParameters(['uint256'],['123'])];});
$tpReader=new Decimal\TokenReader(new Decimal\Client(Decimal\Network::testnet(),$tpReadRpc),$tpB);
foreach(['calculateBuyInput','calculateBuyOutput','calculateSellInput','calculateSellOutput']as $kind){foreach([null,['10','20','30']]as $state){same($tpReader->quote($kind,'7',$state,'0x10'),'123');$last=$tpSeen[count($tpSeen)-1];same($last['params'][1],'0x10');$expected=Decimal\Contract::profile($tpB,'Token')->encode($kind.($state===null?'(uint256)':'(uint256,uint256,uint256,uint256)'),$state===null?['7']:[...$state,'7']);same($last['params'][0]['data'],$expected);}}
rejects(fn()=>$tpReader->quote('calculateBuyInput','0'));rejects(fn()=>$tpReader->quote('calculateBuyInput','1',['1']));
foreach(['buyTokenForExactDEL','buyExactTokenForDEL','sellExactTokensForDEL','sellTokensForExactDEL']as $kind){$draft=$tpEconomy->tradeRaw($kind,$tpA,$tpB,'10','20',$tpC);same($draft->from,$tpA);same($draft->value,str_starts_with($kind,'buy')?($kind==='buyTokenForExactDEL'?'10':'20'):'0');}
foreach(['identity'=>'x','maxTotalSupply'=>'100','minTotalSupply'=>'10']as $field=>$value)same($tpEconomy->maintain($tpA,$tpB,$field,$value)->to,$tpB);
rejects(fn()=>$tpEconomy->maintain($tpA,$tpB,'identity','x','TestnetReservelessToken20261029'));
$psWallet=new Decimal\Wallet(str_pad('1',64,'0',STR_PAD_LEFT));$psDeadline=(string)(time()+3600);$psMode='ok';
$psTypes=['Permit'=>array_map(fn($f)=>['name'=>$f[0],'type'=>$f[1]],[['owner','address'],['spender','address'],['value','uint256'],['nonce','uint256'],['deadline','uint256']])];
$psDomain=['name'=>'Token','version'=>'1','chainId'=>'202020','verifyingContract'=>$tpB];$psMsg=['owner'=>$psWallet->address,'spender'=>$tpCenter,'value'=>'12','nonce'=>'7','deadline'=>$psDeadline];$psSeparator='0x'.bin2hex(substr(Decimal\TypedData::preimage($psTypes,'Permit',$psDomain,$psMsg),2,32));
$psRpc=new Decimal\Rpc('http://unused',function($r)use(&$psMode,$tpCodec,$psSeparator){if($r['method']==='eth_chainId')return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>'0x31524'];$selector=substr($r['params'][0]['data'],0,10);if($selector==='0x54fd4d50'&&$psMode!=='ok')return ['jsonrpc'=>'2.0','id'=>$r['id'],'error'=>['code'=>-32000,'message'=>$psMode==='missing'?'execution reverted':'provider unavailable','data'=>'0x']];$result=match($selector){'0x06fdde03'=>$tpCodec->encodeParameters(['string'],['Token']),'0x7ecebe00'=>$tpCodec->encodeParameters(['uint256'],['7']),'0x3644e515'=>$tpCodec->encodeParameters(['bytes32'],[$psMode==='wrong'?'0x'.str_repeat('00',32):$psSeparator]),'0x54fd4d50'=>$tpCodec->encodeParameters(['string'],['1']),default=>throw new RuntimeException('Unexpected permit selector')};return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];});
$psService=new Decimal\PermitService(new Decimal\Client(Decimal\Network::testnet(),$psRpc),$tpB);
foreach(['ok','missing']as $psMode){$psData=$psService->prepare($psWallet->address,$tpCenter,'12',$psDeadline);same($psData['message']['nonce'],'7');$psSignature=$psWallet->signHash($psData['digest']);same(Decimal\PermitService::verify($psData,$psSignature)['deadline'],$psDeadline);$bad=$psData;$bad['message']['value']='13';rejects(fn()=>Decimal\PermitService::verify($bad,$psSignature));}
$psMode='provider';rejects(fn()=>$psService->prepare($psWallet->address,$tpCenter,'12',$psDeadline));$psMode='wrong';rejects(fn()=>$psService->prepare($psWallet->address,$tpCenter,'12',$psDeadline));rejects(fn()=>$psService->prepare($psWallet->address,$tpCenter,'0',$psDeadline));rejects(fn()=>$psService->prepare($psWallet->address,$tpCenter,'12','1'));
foreach(['buyTokenForExactDEL','buyExactTokenForDEL','sellExactTokensForDEL','sellTokensForExactDEL']as $kind)same($tpEconomy->tradeRaw($kind,$tpA,$tpB,'10','20',$tpC)->data,$tpVectors[$kind]);
same(Decimal\TokenEconomy::creationCommissionWei("\u{00A0}ABC\u{00A0}"),Decimal\TokenEconomy::creationCommissionWei('ABC'));
rejects(fn()=>Decimal\TokenEconomy::creationCommissionWei("\xff"));
$psMode='ok';$psData=$psService->prepare($psWallet->address,$tpCenter,'12',$psDeadline);$psSignature=$psWallet->signHash($psData['digest']);$psV=hexdec(substr($psSignature,-2));$psCompactV=substr($psSignature,0,-2).str_pad(dechex($psV-27),2,'0',STR_PAD_LEFT);same(Decimal\PermitService::verify($psData,$psCompactV)['v'],(string)$psV);
$psHigh=substr($psSignature,0,66).str_repeat('f',64).substr($psSignature,-2);rejects(fn()=>Decimal\PermitService::verify($psData,$psHigh));
