<?php
declare(strict_types=1);
use Decimal\{Protobuf,Cosmos,Wallet,Address};
$auditCodec=new Protobuf();$auditCosmos=new Cosmos();
$auditShallow=['name'=>'parent','nested_type'=>[['name'=>'child']]];
$auditBytes=$auditCodec->encode('google.protobuf.DescriptorProto',$auditShallow);
same($auditCodec->decode('google.protobuf.DescriptorProto',$auditBytes),$auditShallow);
$auditDeep=['name'=>'leaf'];for($i=0;$i<66;++$i)$auditDeep=['nested_type'=>[$auditDeep]];
rejects(fn()=>$auditCodec->encode('google.protobuf.DescriptorProto',$auditDeep));
$auditCycle=[];$auditCycle['nested_type']=[&$auditCycle];
rejects(fn()=>$auditCodec->encode('google.protobuf.DescriptorProto',$auditCycle));
rejects(fn()=>$auditCodec->encode('google.protobuf.FileDescriptorProto',['name'=>str_repeat('a',8*1024*1024)]));
$auditLongChain=str_repeat('a',129);
same(bin2hex($auditCosmos->signDoc('','',$auditLongChain,'0')),'1a8101'.bin2hex($auditLongChain));
$auditWallet=new Wallet(str_pad('1',64,'0',STR_PAD_LEFT));
rejects(fn()=>$auditCosmos->auth([1=>['publicKey'=>$auditWallet->publicKey(),'sequence'=>'0']],[],'1'));
$auditMessage=$auditCodec->any('/decimal.coin.v1.MsgSendCoin',['sender'=>Address::bech32($auditWallet->address),'recipient'=>Address::bech32('0x'.str_repeat('12',20)),'coin'=>['denom'=>'del','amount'=>'1']]);
$auditBody=$auditCosmos->body([$auditMessage]);
rejects(fn()=>$auditCosmos->signChecked($auditBody,'chain',[1=>['wallet'=>$auditWallet,'sequence'=>'0','accountNumber'=>'0']],[],'1'));
// Existing independent actual-node boundaries remain valid under the tightened codec.
foreach(json_decode(file_get_contents(dirname(__DIR__).'/resources/cosmos/validation-boundaries.json'),true) as $fixture){
    $valid=true;try{Decimal\CosmosValidation::validate($fixture['type_url'],$auditCodec->decode($fixture['type_url'],hex2bin($fixture['protobuf_hex'])));}catch(Throwable){$valid=false;}
    same($valid,$fixture['valid']);
}
echo "Final Cosmos resource, signer-list and chain-domain regressions passed\n";
rejects(fn()=>$auditCodec->encode('google.protobuf.DescriptorProto',[],-1));
rejects(fn()=>$auditCodec->decode('google.protobuf.DescriptorProto','',-1));
