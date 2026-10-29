<?php
declare(strict_types=1);
use Decimal\{Protobuf,Cosmos,CosmosFees,CosmosGrpc,Wallet,Address};
$codec=new Protobuf();$service=new Cosmos();
$reference=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/reference-tx.json'),true);
foreach(['body'=>'cosmos.tx.v1beta1.TxBody','auth'=>'cosmos.tx.v1beta1.AuthInfo','sign_doc'=>'cosmos.tx.v1beta1.SignDoc','tx_raw'=>'cosmos.tx.v1beta1.TxRaw'] as $key=>$type){$raw=hex2bin($reference[$key]);same($codec->encode($type,$codec->decode($type,$raw)),$raw);}
foreach(["\x08\x80\x00","\x08\x80","\x08".str_repeat("\xff",10),"\xa0\x06\x01"] as $bad)rejects(fn()=>$codec->decode('cosmos.tx.v1beta1.TxBody',$bad));
$coin=$codec->encode('cosmos.base.v1beta1.Coin',['denom'=>'del']);rejects(fn()=>$codec->decode('cosmos.base.v1beta1.Coin',$coin.$coin));
foreach(['-9223372036854775808','-1','0','9223372036854775807'] as $n){$raw=$codec->encode('google.protobuf.Timestamp',['seconds'=>$n]);same($codec->decode('google.protobuf.Timestamp',$raw)['seconds']??'0',$n);}
$key1=new Wallet(str_pad('1',64,'0',STR_PAD_LEFT));$key2=new Wallet(str_pad('2',64,'0',STR_PAD_LEFT));
$send=fn($w)=>$codec->any('/decimal.coin.v1.MsgSendCoin',['sender'=>Address::bech32($w->address),'recipient'=>Address::bech32($key2->address),'coin'=>['denom'=>'del','amount'=>'1']]);
$body=$service->body([$send($key2),$send($key1),$send($key2)]);same($service->requiredSigners($body),[$key2->address,$key1->address]);
$signers=[['wallet'=>$key2,'sequence'=>'0','accountNumber'=>'1'],['wallet'=>$key1,'sequence'=>'1','accountNumber'=>'2']];
$tx=$service->signChecked($body,'decimal_test-1',$signers,[['denom'=>'del','amount'=>'1']],'100');same(count($codec->decode('cosmos.tx.v1beta1.TxRaw',hex2bin(substr($tx['raw'],2)))['signatures']),2);
rejects(fn()=>$service->signChecked($body,'decimal_test-1',array_reverse($signers),[['denom'=>'del','amount'=>'1']],'100'));
rejects(fn()=>$service->signChecked($body,'decimal_test-1',[$signers[0]],[['denom'=>'del','amount'=>'1']],'100'));
$fee=CosmosFees::converge(fn($f)=>'signed:'.$f,fn($r)=>['fee'=>'5','snapshot'=>$r['snapshot']],'10','pinned');same($fee,['fee'=>'5','signedBytes'=>'signed:5','iterations'=>2,'snapshot'=>'pinned']);
rejects(fn()=>CosmosFees::converge(fn($f)=>$f,fn($r)=>['fee'=>$r['fee']==='1'?'2':'1','snapshot'=>'pinned'],'10','pinned'));
rejects(fn()=>CosmosFees::converge(fn($f)=>$f,fn($r)=>['fee'=>'1','snapshot'=>'stale'],'10','pinned'));
rejects(fn()=>CosmosFees::converge(fn($f)=>$f,fn($r)=>['fee'=>'11','snapshot'=>'pinned'],'10','pinned'));
$calls=[];$grpc=new CosmosGrpc('https://unused.test',function($route,$frame)use(&$calls,$codec){
 $calls[]=$route;same(ord($frame[0]),0);same(unpack('N',substr($frame,1,4))[1],strlen($frame)-5);
 if(str_ends_with($route,'/Balance')){same($codec->decode('cosmos.bank.v1beta1.QueryBalanceRequest',substr($frame,5))['denom'],'del');$out=$codec->encode('cosmos.bank.v1beta1.QueryBalanceResponse',['balance'=>['denom'=>'del','amount'=>'123']]);}
 else{$request=$codec->decode('cosmos.tx.v1beta1.SimulateRequest',substr($frame,5));same($request['tx_bytes'],'0x010203');$out=$codec->encode('cosmos.tx.v1beta1.SimulateResponse',['gas_info'=>['gas_wanted'=>'100','gas_used'=>'80']]);}
 return ['status'=>0,'body'=>"\0".pack('N',strlen($out)).$out];
});
same($grpc->query('cosmos.bank.v1beta1.Query','Balance',['address'=>Address::bech32($key1->address),'denom'=>'del'])['balance']['amount'],'123');
same($grpc->simulate("\x01\x02\x03")['gas_info']['gas_used'],'80');rejects(fn()=>$grpc->query('cosmos.tx.v1beta1.Service','BroadcastTx',[]));same(count($calls),2);
$failed=new CosmosGrpc('https://unused.test',fn()=>['status'=>7,'body'=>'']);rejects(fn()=>$failed->simulate('x'));
$truncated=new CosmosGrpc('https://unused.test',fn()=>['status'=>0,'body'=>"\0\0\0\0\x08x"]);rejects(fn()=>$truncated->simulate('x'));
echo "Cosmos strict decoding, signer binding, fee convergence and gRPC tests passed\n";
foreach([1,20,32,255] as $length){$bytes=str_repeat("\xab",$length);same(Address::fromBech32Bytes(Address::bech32Bytes($bytes,'d0valoper'),'d0valoper'),$bytes);}
rejects(fn()=>Address::bech32Bytes(''));rejects(fn()=>Address::bech32Bytes(str_repeat('x',256)));
$validationCases=json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/validation-subset.json'),true);
foreach($validationCases as $case){$actual=true;try{$message=$codec->decode($case['type_url'],hex2bin($case['protobuf_hex']));Decimal\CosmosValidation::validate($case['type_url'],$message);}catch(Throwable){$actual=false;}same($actual,$case['valid']);}
rejects(fn()=>Decimal\CosmosValidation::validate('/unknown.Msg',[]));
foreach(json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/validation-boundaries.json'),true) as $case){$actual=true;try{Decimal\CosmosValidation::validate($case['type_url'],$codec->decode($case['type_url'],hex2bin($case['protobuf_hex'])));}catch(Throwable){$actual=false;}same($actual,$case['valid']);}
$validSend=$codec->decode('/decimal.coin.v1.MsgSendCoin',hex2bin(array_values(array_filter($validationCases,fn($r)=>$r['type_url']==='/decimal.coin.v1.MsgSendCoin'&&$r['case']==='valid'))[0]['protobuf_hex']));
foreach(['0','-1',gmp_strval(gmp_pow(2,256))] as $amount){$bad=$validSend;$bad['coin']['amount']=$amount;rejects(fn()=>Decimal\CosmosValidation::validate('/decimal.coin.v1.MsgSendCoin',$bad));}
foreach(['DEL','ab','has space'] as $denom){$bad=$validSend;$bad['coin']['denom']=$denom;rejects(fn()=>Decimal\CosmosValidation::validate('/decimal.coin.v1.MsgSendCoin',$bad));}
$bad=$validSend;$bad['recipient']=$bad['sender'];rejects(fn()=>Decimal\CosmosValidation::validate('/decimal.coin.v1.MsgSendCoin',$bad));
same(Address::fromBech32Bytes(strtoupper(Address::bech32Bytes("\xab"))),"\xab");
$double=$codec->encode('google.protobuf.UninterpretedOption',['double_value'=>1.25]);same($codec->decode('google.protobuf.UninterpretedOption',$double)['double_value'],1.25);
rejects(fn()=>$codec->encode('google.protobuf.UninterpretedOption',['double_value'=>INF]));
// Independent Python protobuf fixtures cover every repeated packable field,
// including proto3 default packing and explicit/unset proto2 nonpacking.
foreach(json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/packed-vectors.json'),true) as $packedCase){
 $values=[$packedCase['field']=>$packedCase['values']];
 $wire=$codec->encode($packedCase['type'],$values);
 same(bin2hex($wire),$packedCase['protobuf_hex']);
 same($codec->decode($packedCase['type'],$wire),$values);
}
same(bin2hex($codec->encode('decimal.multisig.v1.MsgCreateWallet',['weights'=>['1','2']])),'1a020102');
// Low-level envelope APIs must reject malformed wire bytes before signing.
$envelopeBody=hex2bin($reference['body']);$envelopeAuth=hex2bin($reference['auth']);$envelopeSig='0x'.$reference['signature'];
rejects(fn()=>$service->signDoc("\xff",$envelopeAuth,'chain','1'));
rejects(fn()=>$service->signDoc($envelopeBody,"\xff",'chain','1'));
rejects(fn()=>$service->signDoc("\xff","\xff",'chain','1'));
rejects(fn()=>$service->sign("\xff",'chain',[['wallet'=>$key1,'sequence'=>'0','accountNumber'=>'1']],[['denom'=>'del','amount'=>'1']],'100'));
same(bin2hex($service->txRaw($envelopeBody,$envelopeAuth,[$envelopeSig])),$reference['tx_raw']);
$service->validateTxRaw(hex2bin($reference['tx_raw']));
rejects(fn()=>$service->txRaw($envelopeBody,$envelopeAuth,[]));
rejects(fn()=>$service->txRaw($envelopeBody,$envelopeAuth,[$envelopeSig,$envelopeSig]));
rejects(fn()=>$service->txRaw($envelopeBody,$envelopeAuth,['0x'.str_repeat('00',64)]));
rejects(fn()=>$service->txRaw($envelopeBody,$envelopeAuth,[substr($envelopeSig,0,-2).'02']));
rejects(fn()=>$service->txRaw("\xff",$envelopeAuth,[$envelopeSig]));
rejects(fn()=>$service->txRaw($envelopeBody,"\xff",[$envelopeSig]));
rejects(fn()=>$service->validateTxRaw("\xff"));
$missingSignatures=$codec->encode('cosmos.tx.v1beta1.TxRaw',['body_bytes'=>'0x'.$reference['body'],'auth_info_bytes'=>'0x'.$reference['auth']]);rejects(fn()=>$service->validateTxRaw($missingSignatures));
$emptyBody=$service->txRaw('',$envelopeAuth,[$envelopeSig]);rejects(fn()=>$service->validateTxRaw($emptyBody));
same(CosmosGrpc::parseStatusHeader("grpc-status: 0\r\n"),0);
same(CosmosGrpc::parseStatusHeader("Grpc-Status:\t16 \r\n"),16);
same(CosmosGrpc::parseStatusHeader("content-type: application/grpc\r\n"),null);
foreach(["grpc-status: 0garbage\r\n","grpc-status: 0 1\r\n","grpc-status: -1\r\n","grpc-status: 17\r\n","grpc-status: 0\r\nInjected: yes\r\n"] as $header)rejects(fn()=>CosmosGrpc::parseStatusHeader($header));
CosmosGrpc::requireHttp2(CURL_HTTP_VERSION_2_0);
rejects(fn()=>CosmosGrpc::requireHttp2(CURL_HTTP_VERSION_1_1));
rejects(fn()=>CosmosGrpc::requireHttp2(CURL_HTTP_VERSION_NONE));
