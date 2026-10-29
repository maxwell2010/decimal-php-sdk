<?php
declare(strict_types=1);
use Decimal\{CallbackSigner,Client,NonceManager,PreparedTransaction,TransactionDraft,SignedTransaction,Wallet};
$coreWallet=new Wallet(str_pad('1',64,'0',STR_PAD_LEFT));
foreach(json_decode(file_get_contents(__DIR__.'/transaction-vectors.json'),true,512,JSON_THROW_ON_ERROR) as $v){
    $coreDraft=new TransactionDraft($coreWallet->address,$v['creation']?null:'0x'.str_repeat('12',20),$v['value'],$v['data']);
    $corePrepared=new PreparedTransaction($coreDraft,202020,$v['nonce'],'100000',$v['type']===2?'100':'7',$v['type'],$v['type']===0?[]:[['address'=>'0x'.str_repeat('34',20),'storageKeys'=>['0x'.str_repeat('00',32),'0x'.str_repeat('00',31).'01']]],$v['type']===2?'3':'0');
    $coreSigned=$coreWallet->sign($corePrepared);
    same($coreSigned->raw,$v['raw']);same($coreSigned->hash,$v['hash']);same($coreSigned->sender,$v['sender']);same($coreSigned->unsignedHash,$corePrepared->signingHash());
    same($coreSigned->createdContract!==null,$v['creation']);
    same(array_key_exists('to',$coreDraft->rpc()),!$v['creation']);
    $callback=new CallbackSigner($coreWallet->address,fn($p)=>$coreWallet->sign($p)->raw);
    same($callback->sign($corePrepared)->raw,$v['raw']);
}
$corePrepared=new PreparedTransaction(TransactionDraft::del($coreWallet->address,'0x'.str_repeat('12',20),'0.01'),202020,'0','23100','100',2,[],'3','21000','10');
same($corePrepared->maximumFee(),'2310000');same($corePrepared->estimatedFee(),'273000');same(isset($corePrepared->fields()['gasPrice']),false);
same((new PreparedTransaction($corePrepared->draft,202020,'0','23100','100',2,[],'3'))->estimatedFee(),null);
rejects(fn()=>(new CallbackSigner($coreWallet->address,fn($p)=>$coreWallet->sign(new PreparedTransaction($p->draft,202020,'1','23100','100',2,[],'3'))))->sign($corePrepared));
rejects(fn()=>new PreparedTransaction($corePrepared->draft,202020,'18446744073709551616','1','1'));
rejects(fn()=>new PreparedTransaction($corePrepared->draft,202020,'0','1','1',1,[],'1'));
rejects(fn()=>new PreparedTransaction($corePrepared->draft,202020,'0','1','1',2,[],'1',null,'2'));
$noncePath=tempnam(sys_get_temp_dir(),'decimal-nonce-test-'); unlink($noncePath);
try{
    $n1=new NonceManager($noncePath);$n2=new NonceManager($noncePath);
    same($n1->reserve(202020,$coreWallet->address,'5'),'5');same($n2->reserve(202020,$coreWallet->address,'5'),'6');same($n1->reserve(202020,$coreWallet->address,'10'),'10');same($n1->reserve(75,$coreWallet->address,'0'),'0');
    file_put_contents($noncePath,'');rejects(fn()=>$n1->reserve(202020,$coreWallet->address,'0'));
    file_put_contents($noncePath,'corrupt');rejects(fn()=>$n1->reserve(202020,$coreWallet->address,'0'));
}finally{unlink($noncePath);unlink($noncePath.'.lock');}
echo "Core transaction, external signer and nonce checks passed\n";
rejects(fn()=>Decimal\TypedData::hash(['T'=>[['name'=>'a','type'=>'uint256'],['name'=>'a','type'=>'uint256']]],'T',[],['a'=>'1','extra'=>'2']));
rejects(fn()=>Decimal\TypedData::hash(['T'=>[['name'=>'a','type'=>'uint']]],'T',[],['a'=>'1']));
rejects(fn()=>Decimal\TypedData::hash(['T'=>[['name'=>'a','type'=>'Unknown']]],'T',[],['a'=>'1']));
rejects(fn()=>new PreparedTransaction($corePrepared->draft,202020,'0','18446744073709551616','1'));
foreach([['nonce','10000000000000000'],['gas','10000000000000000'],['tip','ff']] as [$field,$hex]){
    $decoded=(new Decimal\Rlp())->decode('0x'.substr($coreWallet->sign($corePrepared)->raw,4));
    $i=match($field){'nonce'=>1,'gas'=>4,'tip'=>2};$decoded[$i]=$hex;
    $convert=function($v)use(&$convert){return is_array($v)?array_map($convert,$v):($v===''?'':'0x'.$v);};
    $raw='0x02'.(new Decimal\Rlp())->encode(array_map($convert,$decoded));
    rejects(fn()=>new SignedTransaction($raw,202020));
}
$boundBlocks=[];$runtime='0x6000';$runtimeHash='0x'.kornrunner\Keccak::hash(hex2bin(substr($runtime,2)),256);
$boundRpc=new Decimal\Rpc('http://unused',function($r)use(&$boundBlocks,$runtime){
    $value=match($r['method']){'eth_chainId'=>'0x31524','eth_blockNumber'=>'0x10','eth_getCode'=>$runtime,'eth_getStorageAt'=>'0x'.str_repeat('0',64),'eth_call'=>(new Decimal\AbiCodec())->encodeParameters(['uint256'],['42']),default=>throw new RuntimeException('Unexpected deployment RPC')};
    if($r['method']==='eth_call')$boundBlocks[]=$r['params'][1];
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$value];
});
$bound=new Decimal\VerifiedContract(new Client(Decimal\Network::testnet(),$boundRpc),'0x'.str_repeat('12',20),'Token',$runtimeHash);
same($bound->call('totalSupply()'),['42']);same($boundBlocks,['0x10']);
$cancel=new Decimal\CancellationToken();$cancel->cancel();$rpcCalls=0;
$cancelClient=new Client(Decimal\Network::testnet(),new Decimal\Rpc('http://unused',function($r)use(&$rpcCalls){++$rpcCalls;throw new RuntimeException('Unexpected request');}));
rejects(fn()=>$cancelClient->prepare($corePrepared->draft,cancellation:$cancel));
rejects(fn()=>$cancelClient->waitReceipt('0x'.str_repeat('ab',32),cancellation:$cancel));same($rpcCalls,0);
$receiptHash='0x'.str_repeat('ab',32);
$receiptRpc=new Decimal\Rpc('http://unused',fn($r)=>['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':['transactionHash'=>$receiptHash,'status'=>'0x1']]);
same((new Client(Decimal\Network::testnet(),$receiptRpc))->waitReceipt($receiptHash)['status'],'0x1');
$receiptRpc=new Decimal\Rpc('http://unused',fn($r)=>['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':['transactionHash'=>'0x'.str_repeat('cd',32),'status'=>'0x1']]);
rejects(fn()=>(new Client(Decimal\Network::testnet(),$receiptRpc))->waitReceipt($receiptHash));
