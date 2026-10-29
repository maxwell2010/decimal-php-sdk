<?php
declare(strict_types=1);
use Decimal\{AbiCodec,Contract,Events,Mnemonic,TypedData,SafeTransaction,Api,PreparedTransaction,TransactionDraft};
$v=json_decode(file_get_contents(__DIR__.'/crypto-vectors.json'),true,512,JSON_THROW_ON_ERROR);
same(Mnemonic::addresses($v['mnemonic']['phrase'],0,3),$v['mnemonic']['addresses']);
foreach([12,15,18,21,24] as $n) { $phrase=Mnemonic::generate($n);same(count(explode(' ',$phrase)),$n);same(Mnemonic::validate($phrase),true); }
same(Mnemonic::validate(str_repeat('abandon ',11).'abandon'),false);
rejects(fn()=>Mnemonic::wallet($v['mnemonic']['phrase'],-1));
foreach($v['transactions'] as $row) {
 $p=new PreparedTransaction(TransactionDraft::del($wallet->address,$address,'0.01','hello'),202020,'0','30000','7',$row['type'],[['address'=>'0x'.str_repeat('34',20),'storageKeys'=>['0x'.str_repeat('00',32)]]],$row['type']===2?'2':'0');
 $s=$wallet->sign($p);same($s->raw,$row['raw']);same($s->hash,$row['hash']);rejects(fn()=>new Decimal\SignedTransaction($s->raw,75));
}
$t=$v['typed'];same(TypedData::hash($t['types'],'Mail',$t['domain'],$t['message']),$t['hash']);same($wallet->signTypedData($t['types'],'Mail',$t['domain'],$t['message']),$t['signature']);same(Decimal\Wallet::recover($t['hash'],$t['signature']),$wallet->address);
$codec=new AbiCodec();
foreach(json_decode(file_get_contents(__DIR__.'/vectors.json'),true) as $row) {
 $signature=$row['signature'];$types=AbiCodec::split(substr($signature,strpos($signature,'(')+1,-1));
 same($codec->decodeParameters($types,'0x'.substr($row['data'],10)),$row['args']);
}
foreach(['0x00','0x'.str_repeat('f',64)] as $bad) rejects(fn()=>$codec->decodeParameters(['string'],$bad));
$safe=SafeTransaction::create($address,202020,new TransactionDraft($address,$address),'0');
$sig=$safe->sign($wallet);same($safe->packSignatures([$sig],[$wallet->address]),$sig);rejects(fn()=>$safe->packSignatures([$sig,$sig]));rejects(fn()=>$safe->packSignatures([$sig],[$address]));
same($safe->draft($wallet->address,$sig)->to,$address);
$api=new Api('https://example.com/api',fn($url)=>$url);same($api->get('validators',Api::page(2)),'https://example.com/api/validators?limit=2&offset=0');foreach(['../secret','%2e%2e/secret','/secret','https://bad.test','a?key=x'] as $bad) rejects(fn()=>$api->get($bad));
$abi=json_decode(file_get_contents(dirname(__DIR__).'/abi/Token.json'),true);
$log=['address'=>$address,'topics'=>['0x'.kornrunner\Keccak::hash('Transfer(address,address,uint256)',256),$codec->encodeParameters(['address'],[$wallet->address]),$codec->encodeParameters(['address'],[$address])],'data'=>$codec->encodeParameters(['uint256'],['42'])];
$event=Events::decode($abi,$log,$address);same($event['event'],'Transfer');same(array_values($event['args']),[$wallet->address,$address,'42']);rejects(fn()=>Events::decode($abi,$log,$wallet->address));
echo "Extended parity checks passed\n";
