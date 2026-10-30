<?php
declare(strict_types=1);
// Every RPC below is injected. eth_sendRawTransaction is handled locally and never reaches a node.
$exWallet=new Decimal\Wallet(str_repeat('0',63).'1');$exTo='0x'.str_repeat('22',20);$exToken='0x'.str_repeat('33',20);$exCalls=[];$exNonce=0;$exReceipts=[];
$exRpc=new Decimal\Rpc('http://unused',function($r)use(&$exCalls,&$exNonce,&$exReceipts,$exTo){
    $exCalls[]=$r;
    $result=match($r['method']){
        'eth_chainId'=>'0x31524','eth_gasPrice'=>'0x2','eth_estimateGas'=>'0x5208','eth_getBalance'=>'0x10000000000000000000',
        'eth_getTransactionCount'=>Decimal\Units::hex((string)$exNonce),'eth_call'=>'0x',
        'eth_blockNumber'=>'0x20',
        'eth_sendRawTransaction'=>(function()use($r,&$exNonce,&$exReceipts){$signed=new Decimal\SignedTransaction($r['params'][0],202020);++$exNonce;$exReceipts[$signed->hash]=['transactionHash'=>$signed->hash,'status'=>'0x1','blockNumber'=>'0x20','transactionIndex'=>'0x0','gasUsed'=>'0x5208','effectiveGasPrice'=>'0x2','logs'=>[]];return $signed->hash;})(),
        'eth_getTransactionReceipt'=>$exReceipts[$r['params'][0]]??null,
        default=>throw new RuntimeException('Unexpected injected RPC method'),
    };
    return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$result];
});
$exClient=new Decimal\Client(Decimal\Network::testnet(),$exRpc);$ex=new Decimal\TransactionExecutor($exClient);$exDraft=new Decimal\TransactionDraft($exWallet->address,$exTo,'100');
$exResult=$ex->execute($exDraft,$exWallet);$exFields=$exResult->toArray();same($exFields['status'],'dry_run');same($exFields['broadcasted'],false);same($exFields['gas'],'23100');same($exFields['fee_wei'],'46200');same($exFields['estimated_fee_wei'],'42000');same($exFields['required_wei'],'46300');same($exResult->isConfirmed(),false);same($exNonce,0);
rejects(fn()=>$ex->execute($exDraft,$exWallet,false,true));
rejects(fn()=>$ex->prepare($exDraft,['unknown'=>true]));
same($exClient->estimateFee($exDraft)->jsonSerialize()['maximum_fee_wei'],'46200');same($exClient->estimateFee($exDraft,true)->gasLimit,'21000');
$poor=new Decimal\FeeQuote($exDraft,'21000','23100','2','1');same($poor->canAfford(),false);same($poor->missing(),'46299');
$exTokenContract=Decimal\Contract::profile($exToken,'Token');$exApproval=$exTokenContract->draft($exWallet->address,'approve(address,uint256)',[$exTo,'100']);$exPlan=new Decimal\TransactionPlan($exDraft,[$exApproval],'test');
$before=count($exCalls);$exOffline=$ex->executePlan($exPlan,$exWallet);same($exOffline['status'],'requires_approvals');same(count($exCalls),$before);same($exNonce,0);
rejects(fn()=>$ex->executePlan($exPlan,$exWallet,broadcast:true));same($exNonce,0);
$exRefreshCount=0;$exSent=$ex->executePlan($exPlan,$exWallet,function()use(&$exRefreshCount,$exDraft,&$exNonce){++$exRefreshCount;same($exNonce,1);return $exDraft;},true,true);
same($exNonce,2);same($exRefreshCount,1);same($exSent['action']->isSuccessful(),true);same($exSent['action']->isConfirmed(),true);same($exSent['action']->toArray()['effective_fee_wei'],'42000');same($exSent['action']->toArray()['transaction_index'],'0');
$exHash=$exSent['action']->toArray()['tx_hash'];same($exClient->transactionResult($exHash)->toArray()['effective_fee_del'],'0.000000000000042');
same(Decimal\TransactionResult::fromReceipt('0x'.str_repeat('ab',32),null)->isPending(),true);
rejects(fn()=>Decimal\TransactionResult::fromReceipt('0x'.str_repeat('ab',32),$exReceipts[$exHash]));
$exZero=$exReceipts[$exHash];$exZero['effectiveGasPrice']='0x0';same(Decimal\TransactionResult::fromReceipt($exHash,$exZero)->toArray()['effective_fee_wei'],'0');
$exFail=$exZero;$exFail['status']='0x0';same(Decimal\TransactionResult::fromReceipt($exHash,$exFail)->isSuccessful(),false);
$exBefore=$exNonce;rejects(fn()=>$ex->executePlan($exPlan,$exWallet,fn()=>new Decimal\TransactionDraft($exWallet->address,$exTo,'101'),true));same($exNonce,$exBefore+1);
$exCancellation=new Decimal\CancellationToken();$exCancelSigner=new Decimal\CallbackSigner($exWallet->address,function($p)use($exWallet,$exCancellation){$signed=$exWallet->sign($p);$exCancellation->cancel();return $signed;});
$exBefore=$exNonce;rejects(fn()=>$ex->execute($exDraft,$exCancelSigner,true,options:['cancellation'=>$exCancellation]));same($exNonce,$exBefore);
rejects(fn()=>new Decimal\TransactionPlan($exDraft,[$exTo]));
same(Decimal\TransactionDraft::burnDel($exWallet->address,'1')->value,'1000000000000000000');
$exFailSends=0;$exFailRefreshes=0;$exFailHash='';
$exFailRpc=new Decimal\Rpc('http://unused',function($r)use(&$exFailSends,&$exFailHash){
    $value=match($r['method']){
        'eth_chainId'=>'0x31524','eth_gasPrice'=>'0x2','eth_estimateGas'=>'0x5208','eth_getBalance'=>'0x10000000000000000000','eth_getTransactionCount'=>'0x0','eth_call'=>'0x',
        'eth_sendRawTransaction'=>(function()use($r,&$exFailSends,&$exFailHash){++$exFailSends;return $exFailHash=(new Decimal\SignedTransaction($r['params'][0],202020))->hash;})(),
        'eth_getTransactionReceipt'=>['transactionHash'=>$exFailHash,'status'=>'0x0'],
        default=>throw new RuntimeException('Unexpected failing-approval method'),
    };return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$value];
});
$exFailRunner=new Decimal\TransactionExecutor(new Decimal\Client(Decimal\Network::testnet(),$exFailRpc));
rejects(fn()=>$exFailRunner->executePlan(new Decimal\TransactionPlan($exDraft,[$exApproval,$exApproval]),$exWallet,function()use(&$exFailRefreshes,$exDraft){++$exFailRefreshes;return $exDraft;},true,true));
same($exFailSends,1);same($exFailRefreshes,0);
$exRegistryCalls=[];$exRegistryCodec=new Decimal\AbiCodec();
$exRegistryRpc=new Decimal\Rpc('http://unused',function($r)use(&$exRegistryCalls,$exRegistryCodec,$exTo){$exRegistryCalls[]=$r;return ['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>match($r['method']){'eth_chainId'=>'0x31524','eth_blockNumber'=>'0x20','eth_call'=>$exRegistryCodec->encodeParameters(['address'],[$exTo]),default=>throw new RuntimeException('Unexpected registry method')}];});
$exRegistryClient=new Decimal\Client(Decimal\Network::testnet(),$exRegistryRpc);$exRegistry=$exRegistryClient->resolveSystemContracts();same($exRegistry['block'],'0x20');same($exRegistry['delegation'],$exTo);same(count($exRegistry),5);
foreach($exRegistryCalls as $r)if($r['method']==='eth_call')same($r['params'][1],'0x20');
$exZeroRegistry=new Decimal\Rpc('http://unused',fn($r)=>['jsonrpc'=>'2.0','id'=>$r['id'],'result'=>$r['method']==='eth_chainId'?'0x31524':$exRegistryCodec->encodeParameters(['address'],['0x'.str_repeat('0',40)])]);
rejects(fn()=>(new Decimal\Client(Decimal\Network::testnet(),$exZeroRegistry))->resolveSystemContract('delegation'));
rejects(fn()=>$exRegistryClient->resolveSystemContract(' '));
rejects(fn()=>$ex->execute($exDraft,$exWallet,true,eventProfile:'MissingProfile'));same($exNonce,$exBefore);
rejects(fn()=>$ex->executePlan($exPlan,$exWallet,fn()=>$exDraft,true,eventProfile:'MissingProfile'));same($exNonce,$exBefore);
echo "Common orchestration, fee/report DTO and approval sequencing checks passed (injected RPC only)\n";
